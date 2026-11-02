<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Relationships;

use App\Models\SpreadsheetImportJob;
use App\Services\Spreadsheet\DTOs\SourceColumnDescriptor;
use App\Services\Spreadsheet\Relationships\DTOs\CandidateKeyDescriptor;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;
use App\Services\Spreadsheet\Relationships\DTOs\SpreadsheetRelationshipGraph;
use App\Services\Spreadsheet\Security\Exceptions\TenantScopeViolationException;
use App\Services\Spreadsheet\Security\Exceptions\UnauthorizedDataAccessException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Cross-Sheet Relationship Service.
 *
 * Orchestrates multi-sheet candidate key discovery, inclusion dependency analysis,
 * cardinality inference, junction table detection, and generation-fenced graph persistence.
 */
class CrossSheetRelationshipService
{
    public function __construct(
        protected readonly CandidateKeyProfiler $keyProfiler = new CandidateKeyProfiler(),
        protected readonly InclusionDependencyAnalyzer $indAnalyzer = new InclusionDependencyAnalyzer(),
        protected readonly CardinalityInferenceEngine $cardinalityEngine = new CardinalityInferenceEngine(),
    ) {}

    /**
     * Discover relationships by directly loading an uploaded multi-sheet Excel file from disk.
     *
     * @param SpreadsheetImportJob $job
     * @param string $absolutePath
     * @param string|null $fencingGenerationId
     * @param int $sampleRowLimit
     * @return SpreadsheetRelationshipGraph
     */
    public function discoverWorkbookFile(
        SpreadsheetImportJob $job,
        string $absolutePath,
        ?string $fencingGenerationId = null,
        int $sampleRowLimit = 1000
    ): SpreadsheetRelationshipGraph {
        if (! file_exists($absolutePath) || ! is_readable($absolutePath)) {
            throw new InvalidArgumentException("Spreadsheet file does not exist or is not readable: {$absolutePath}");
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $sheetNames = $reader->listWorksheetNames($absolutePath);

        if (count($sheetNames) < 2) {
            return new SpreadsheetRelationshipGraph(
                workspaceId: (int) $job->workspace_id,
                documentId: (string) $job->document_id,
                jobId: (string) $job->id,
                generationId: (string) $job->generation_id,
                nodes: [],
                edges: [],
                ambiguities: ["Workbook contains less than 2 sheets (" . count($sheetNames) . " found); cross-sheet discovery skipped."],
                graphVersion: SpreadsheetRelationshipGraph::GRAPH_VERSION,
                discoveredAt: now()->toISOString()
            );
        }

        $spreadsheet = $reader->load($absolutePath);
        $multiSheetData = [];

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (! $sheet) {
                continue;
            }

            $highestRow = min((int) $sheet->getHighestRow(), $sampleRowLimit + 1);
            $highestColumn = $sheet->getHighestColumn();
            $rangeData = $sheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, true, true, false);

            if (empty($rangeData)) {
                continue;
            }

            $rawRows = [];
            foreach ($rangeData as $r) {
                $rawRows[] = array_map(fn($c) => is_scalar($c) ? (string) $c : '', $r);
            }

            $inspector = new \App\Services\Spreadsheet\SpreadsheetInspectorService();
            $analysis = $inspector->analyzeSheetData($rawRows, $sheetName);
            $headerRowIndex = (int) ($analysis['header_row_index'] ?? 0);
            $descriptors = (array) ($analysis['source_descriptors'] ?? []);

            // Build tabular rows indexed by col_000, col_001...
            $rows = [];
            $headerCols = array_keys($descriptors);
            $dataSlice = array_slice($rawRows, $headerRowIndex + 1);

            foreach ($dataSlice as $rowArr) {
                $rowObj = [];
                foreach ($headerCols as $idx => $colId) {
                    $rowObj[$colId] = $rowArr[$idx] ?? null;
                }
                $rows[] = $rowObj;
            }

            $multiSheetData[$sheetName] = [
                'descriptors' => $descriptors,
                'rows'        => $rows,
                'entity_type' => $analysis['detected_purpose'] ?? 'unknown',
            ];
        }

        $graph = $this->discoverRelationships($job, $multiSheetData, $fencingGenerationId);

        if (! empty($graph->edges)) {
            $annotatedEdges = [];
            foreach ($graph->edges as $edgeId => $edge) {
                $evidence = $edge->evidence;
                $evidence[] = "Evaluated on initial sample of up to {$sampleRowLimit} rows per sheet; full dataset validation pending.";
                $annotatedEdges[$edgeId] = new DiscoveredRelationshipEdge(
                    id: $edge->id,
                    sourceSheet: $edge->sourceSheet,
                    sourceColumnIds: $edge->sourceColumnIds,
                    sourceHeaders: $edge->sourceHeaders,
                    targetSheet: $edge->targetSheet,
                    targetColumnIds: $edge->targetColumnIds,
                    targetHeaders: $edge->targetHeaders,
                    cardinality: $edge->cardinality,
                    isJunctionTable: $edge->isJunctionTable,
                    inclusionRatio: $edge->inclusionRatio,
                    headerSimilarity: $edge->headerSimilarity,
                    entityAlignment: $edge->entityAlignment,
                    typeCompatibility: $edge->typeCompatibility,
                    confidence: $edge->confidence,
                    state: $edge->state,
                    evidence: $evidence,
                    orphanCount: $edge->orphanCount,
                    confirmedBy: $edge->confirmedBy,
                    confirmedAt: $edge->confirmedAt
                );
            }

            $graph = new SpreadsheetRelationshipGraph(
                workspaceId: $graph->workspaceId,
                documentId: $graph->documentId,
                jobId: $graph->jobId,
                generationId: $graph->generationId,
                nodes: $graph->nodes,
                edges: $annotatedEdges,
                ambiguities: $graph->ambiguities,
                graphVersion: $graph->graphVersion,
                discoveredAt: $graph->discoveredAt
            );

            $this->persistGraph($job, $graph);
        }

        return $graph;
    }

    /**
     * Discover relationships across multiple sheets in a spreadsheet workbook.
     *
     * @param SpreadsheetImportJob $job
     * @param array<string, array{rows: array<int, array<string, mixed>>, descriptors: array<string, SourceColumnDescriptor|array<string, mixed>>, entity_type?: string}> $multiSheetData
     * @param string|null $fencingGenerationId Generation token for optimistic locking
     * @return SpreadsheetRelationshipGraph
     */
    public function discoverRelationships(
        SpreadsheetImportJob $job,
        array $multiSheetData,
        ?string $fencingGenerationId = null
    ): SpreadsheetRelationshipGraph {
        // 1. Generation Fencing Check
        if ($fencingGenerationId !== null && $job->generation_id !== $fencingGenerationId) {
            throw new InvalidArgumentException(
                "Stale generation fencing token: Job generation '{$job->generation_id}' does not match request '{$fencingGenerationId}'."
            );
        }

        $sheetNames = array_keys($multiSheetData);
        $sheetCount = count($sheetNames);
        if ($sheetCount < 2) {
            return new SpreadsheetRelationshipGraph(
                workspaceId: (int) $job->workspace_id,
                documentId: (string) $job->document_id,
                jobId: (string) $job->id,
                generationId: (string) $job->generation_id,
                nodes: [],
                edges: [],
                ambiguities: ["Workbook contains less than 2 sheets; cross-sheet discovery skipped."],
                graphVersion: SpreadsheetRelationshipGraph::GRAPH_VERSION,
                discoveredAt: now()->toISOString()
            );
        }

        // 2. Profile Candidate Keys for each sheet
        $sheetProfiles = [];
        $nodes = [];

        foreach ($multiSheetData as $sheetName => $sheetInfo) {
            $rows = (array) ($sheetInfo['rows'] ?? []);
            $descriptors = (array) ($sheetInfo['descriptors'] ?? []);
            $entityType = (string) ($sheetInfo['entity_type'] ?? 'unknown');

            $keyDescriptors = $this->keyProfiler->profileSheet($sheetName, $rows, $descriptors);

            // Find primary candidate key (highest confidence single-column key)
            $bestPk = null;
            foreach ($keyDescriptors as $kd) {
                if ($kd->isCandidateKey && ! $kd->isComposite) {
                    if ($bestPk === null || $kd->confidence > $bestPk->confidence) {
                        $bestPk = $kd;
                    }
                }
            }

            $sheetProfiles[$sheetName] = [
                'rows'           => $rows,
                'descriptors'    => $descriptors,
                'entity_type'    => $entityType,
                'key_descriptors'=> $keyDescriptors,
                'best_pk'        => $bestPk,
                'row_count'      => count($rows),
            ];

            $nodes[$sheetName] = [
                'sheet_name'     => $sheetName,
                'row_count'      => count($rows),
                'primary_key'    => $bestPk ? $bestPk->columnIds : null,
                'candidate_keys' => array_keys(array_filter($keyDescriptors, fn($k) => $k->isCandidateKey)),
                'entity_type'    => $entityType,
            ];
        }

        // 3. Discover Cross-Sheet Relationships (Pairs: Source Sheet -> Target Sheet PK)
        $edges = [];
        $candidateJunctionCounts = [];
        $ambiguities = [];

        foreach ($sheetProfiles as $sourceSheet => $sourceProfile) {
            $sourceRows = $sourceProfile['rows'];
            $sourceDescriptors = $sourceProfile['descriptors'];
            $sourceRowCount = count($sourceRows);

            if ($sourceRowCount === 0) {
                continue;
            }

            foreach ($sheetProfiles as $targetSheet => $targetProfile) {
                if ($sourceSheet === $targetSheet) {
                    continue; // Skip self-joins
                }

                $targetPk = $targetProfile['best_pk'];
                if ($targetPk === null) {
                    continue; // Target sheet has no viable primary key
                }

                // Extract target PK column values
                $targetPkValues = $this->extractColumnValues($targetProfile['rows'], $targetPk->columnIds[0]);

                // Compare each column in source sheet against target PK
                $sourceColIds = array_keys($sourceDescriptors ?: ($sourceRows[0] ?? []));

                foreach ($sourceColIds as $sourceColId) {
                    $sourceColValues = $this->extractColumnValues($sourceRows, $sourceColId);
                    if (empty($sourceColValues)) {
                        continue;
                    }

                    // Check inclusion dependency: Does sourceColValues ⊆ targetPkValues?
                    $indResult = $this->indAnalyzer->analyzeInclusion($sourceColValues, $targetPkValues);

                    // If IND is valid (≥ 0.85) or partial (≥ 0.60)
                    if ($indResult['inclusion_ratio'] >= 0.60) {
                        $sourceUniqueness = $this->computeUniquenessRatio($sourceColValues);
                        $sourceHeaders = [$this->extractHeaderLabel($sourceColId, $sourceDescriptors[$sourceColId] ?? null)];
                        $sourceTypes = [$this->extractPhysicalTypeLabel($sourceDescriptors[$sourceColId] ?? null)];

                        $edge = $this->cardinalityEngine->inferRelationship(
                            sourceSheet: $sourceSheet,
                            sourceColumnIds: [$sourceColId],
                            sourceHeaders: $sourceHeaders,
                            sourceTypes: $sourceTypes,
                            sourceUniquenessRatio: $sourceUniqueness,
                            targetSheet: $targetSheet,
                            targetPk: $targetPk,
                            indResult: $indResult,
                            sourceEntityType: $sourceProfile['entity_type'],
                            targetEntityType: $targetProfile['entity_type'],
                            isKnownJunction: false
                        );

                        $edges[$edge->id] = $edge;

                        // Only viable candidate relationships (IND >= 0.85 and non-rejected) qualify as junction targets
                        if ($edge->state !== DiscoveredRelationshipEdge::STATE_REJECTED && $edge->inclusionRatio >= 0.85) {
                            $candidateJunctionCounts[$sourceSheet][$targetSheet] = true;
                        }

                        if ($edge->state === DiscoveredRelationshipEdge::STATE_NEEDS_CONFIRMATION) {
                            $ambiguities[] = "Ambiguous candidate relationship detected between {$sourceSheet} ({$sourceHeaders[0]}) and {$targetSheet} ({$targetPk->headers[0]}). Confidence: {$edge->confidence}.";
                        }
                    }
                }
            }
        }

        // 4. Detect Junction Tables (Associative tables connecting ≥ 2 distinct target entities without an independent single PK)
        foreach ($candidateJunctionCounts as $sheetName => $targets) {
            if (count($targets) >= 2) {
                $sourceProfile = $sheetProfiles[$sheetName] ?? null;
                $sourcePk = $sourceProfile['best_pk'] ?? null;

                // Collect foreign key column IDs originating from this sheet
                $fkColIds = [];
                foreach ($edges as $edge) {
                    if ($edge->sourceSheet === $sheetName && $edge->state !== DiscoveredRelationshipEdge::STATE_REJECTED) {
                        $fkColIds = array_merge($fkColIds, $edge->sourceColumnIds);
                    }
                }
                $fkColIds = array_unique($fkColIds);

                // If sheet has an independent single-column PK that is NOT one of the foreign keys,
                // it is an independent entity table (e.g. Orders referencing Customers & Shippers), not an associative junction table.
                $hasIndependentEntityPk = ($sourcePk !== null 
                    && ! $sourcePk->isComposite 
                    && ! in_array($sourcePk->columnIds[0], $fkColIds, true));

                if (! $hasIndependentEntityPk) {
                    // Mark qualifying edges originating from this associative table as junction edges
                    foreach ($edges as $id => $edge) {
                        if ($edge->sourceSheet === $sheetName && isset($targets[$edge->targetSheet])) {
                            $edges[$id] = new DiscoveredRelationshipEdge(
                                id: $edge->id,
                                sourceSheet: $edge->sourceSheet,
                                sourceColumnIds: $edge->sourceColumnIds,
                                sourceHeaders: $edge->sourceHeaders,
                                targetSheet: $edge->targetSheet,
                                targetColumnIds: $edge->targetColumnIds,
                                targetHeaders: $edge->targetHeaders,
                                cardinality: DiscoveredRelationshipEdge::CARDINALITY_MANY_TO_MANY,
                                isJunctionTable: true,
                                inclusionRatio: $edge->inclusionRatio,
                                headerSimilarity: $edge->headerSimilarity,
                                entityAlignment: $edge->entityAlignment,
                                typeCompatibility: $edge->typeCompatibility,
                                confidence: $edge->confidence,
                                state: $edge->state,
                                evidence: array_merge($edge->evidence, ["Sheet '{$sheetName}' connects multiple entities (" . implode(', ', array_keys($targets)) . "), confirming N:M Junction Table behavior."]),
                                orphanCount: $edge->orphanCount,
                                confirmedBy: $edge->confirmedBy,
                                confirmedAt: $edge->confirmedAt
                            );
                        }
                    }
                }
            }
        }

        // 5. Build Graph DTO
        $graph = new SpreadsheetRelationshipGraph(
            workspaceId: (int) $job->workspace_id,
            documentId: (string) $job->document_id,
            jobId: (string) $job->id,
            generationId: (string) $job->generation_id,
            nodes: $nodes,
            edges: $edges,
            ambiguities: $ambiguities,
            graphVersion: SpreadsheetRelationshipGraph::GRAPH_VERSION,
            discoveredAt: now()->toISOString()
        );

        // 6. Persist to Job detected_schema (Safe Non-Destructive JSON Merge)
        $this->persistGraph($job, $graph);

        return $graph;
    }

    /**
     * Confirm a candidate relationship edge by authorized workspace owner.
     */
    public function confirmRelationship(
        SpreadsheetImportJob $job,
        string $edgeId,
        int $userId,
        int $workspaceId
    ): DiscoveredRelationshipEdge {
        $this->authorizeWorkspace($job, $workspaceId);

        $graph = $this->loadGraph($job);
        if (! isset($graph->edges[$edgeId])) {
            throw new InvalidArgumentException("Relationship edge '{$edgeId}' not found in job '{$job->id}'.");
        }

        $edge = $graph->edges[$edgeId];
        $confirmedEdge = $edge->withState(DiscoveredRelationshipEdge::STATE_CONFIRMED, $userId);

        $updatedEdges = $graph->edges;
        $updatedEdges[$edgeId] = $confirmedEdge;

        $updatedGraph = new SpreadsheetRelationshipGraph(
            workspaceId: $graph->workspaceId,
            documentId: $graph->documentId,
            jobId: $graph->jobId,
            generationId: $graph->generationId,
            nodes: $graph->nodes,
            edges: $updatedEdges,
            ambiguities: array_values(array_filter($graph->ambiguities, fn($a) => ! str_contains($a, $edge->sourceSheet))),
            graphVersion: $graph->graphVersion,
            discoveredAt: $graph->discoveredAt
        );

        $this->persistGraph($job, $updatedGraph);

        Log::info('[CrossSheetRelationshipService] Relationship edge confirmed by owner', [
            'job_id'       => $job->id,
            'workspace_id' => $workspaceId,
            'edge_id'      => $edgeId,
            'user_id'      => $userId,
        ]);

        return $confirmedEdge;
    }

    /**
     * Reject a candidate relationship edge.
     */
    public function rejectRelationship(
        SpreadsheetImportJob $job,
        string $edgeId,
        int $userId,
        int $workspaceId,
        ?string $reason = null
    ): DiscoveredRelationshipEdge {
        $this->authorizeWorkspace($job, $workspaceId);

        $graph = $this->loadGraph($job);
        if (! isset($graph->edges[$edgeId])) {
            throw new InvalidArgumentException("Relationship edge '{$edgeId}' not found in job '{$job->id}'.");
        }

        $edge = $graph->edges[$edgeId];
        $rejectedEdge = $edge->withState(DiscoveredRelationshipEdge::STATE_REJECTED, $userId, $reason);

        $updatedEdges = $graph->edges;
        $updatedEdges[$edgeId] = $rejectedEdge;

        $updatedGraph = new SpreadsheetRelationshipGraph(
            workspaceId: $graph->workspaceId,
            documentId: $graph->documentId,
            jobId: $graph->jobId,
            generationId: $graph->generationId,
            nodes: $graph->nodes,
            edges: $updatedEdges,
            ambiguities: $graph->ambiguities,
            graphVersion: $graph->graphVersion,
            discoveredAt: $graph->discoveredAt
        );

        $this->persistGraph($job, $updatedGraph);

        Log::info('[CrossSheetRelationshipService] Relationship edge rejected by owner', [
            'job_id'       => $job->id,
            'workspace_id' => $workspaceId,
            'edge_id'      => $edgeId,
            'user_id'      => $userId,
            'reason'       => $reason,
        ]);

        return $rejectedEdge;
    }

    /**
     * Load existing relationship graph from job.
     */
    public function loadGraph(SpreadsheetImportJob $job): SpreadsheetRelationshipGraph
    {
        $detected = (array) ($job->detected_schema ?? []);
        $relData = (array) ($detected['relationships'] ?? []);

        if (empty($relData)) {
            return new SpreadsheetRelationshipGraph(
                workspaceId: (int) $job->workspace_id,
                documentId: (string) $job->document_id,
                jobId: (string) $job->id,
                generationId: (string) $job->generation_id,
                nodes: [],
                edges: [],
                ambiguities: [],
                graphVersion: SpreadsheetRelationshipGraph::GRAPH_VERSION,
                discoveredAt: now()->toISOString()
            );
        }

        return SpreadsheetRelationshipGraph::fromArray($relData);
    }

    /**
     * Persist graph safely into detected_schema->relationships.
     */
    public function persistGraph(SpreadsheetImportJob $job, SpreadsheetRelationshipGraph $graph): void
    {
        $currentSchema = (array) ($job->detected_schema ?? []);
        $currentSchema['relationships'] = $graph->toArray();
        $currentSchema['relationships_updated_at'] = now()->toISOString();
        $currentSchema['relationships_generation_id'] = $graph->generationId;

        $job->update([
            'detected_schema' => $currentSchema,
        ]);
    }

    /**
     * Re-import drift evaluation: invalidate edges whose underlying columns or IND broke.
     */
    public function evaluateDrift(
        SpreadsheetImportJob $job,
        array $newMultiSheetData
    ): SpreadsheetRelationshipGraph {
        $oldGraph = $this->loadGraph($job);
        if (empty($oldGraph->edges)) {
            return $this->discoverRelationships($job, $newMultiSheetData);
        }

        $newGraph = $this->discoverRelationships($job, $newMultiSheetData);
        $reconciledEdges = [];

        foreach ($oldGraph->edges as $id => $oldEdge) {
            if ($oldEdge->state === DiscoveredRelationshipEdge::STATE_REJECTED) {
                // Previously rejected edge remains rejected across rediscovery
                $reconciledEdges[$id] = $oldEdge;
                continue;
            }

            if (isset($newGraph->edges[$id])) {
                $newEdge = $newGraph->edges[$id];
                if ($oldEdge->state === DiscoveredRelationshipEdge::STATE_CONFIRMED) {
                    // If previously confirmed and still valid IND, preserve CONFIRMED
                    if ($newEdge->inclusionRatio >= 0.85) {
                        $reconciledEdges[$id] = $newEdge->withState(DiscoveredRelationshipEdge::STATE_CONFIRMED, $oldEdge->confirmedBy);
                    } else {
                        // Drift occurred: mark STALE
                        $reconciledEdges[$id] = $newEdge->withState(DiscoveredRelationshipEdge::STATE_STALE, null, 'Inclusion dependency dropped below 0.85 after re-import.');
                    }
                } else {
                    $reconciledEdges[$id] = $newEdge;
                }
            } else {
                // If the sheets and columns still exist in the new dataset, but IND dropped below threshold: mark STALE
                $fromSheet = $oldEdge->sourceSheet;
                $toSheet = $oldEdge->targetSheet;
                $fromCol = $oldEdge->sourceColumnIds[0] ?? null;
                $toCol = $oldEdge->targetColumnIds[0] ?? null;

                $fromExists = $fromCol && isset($newMultiSheetData[$fromSheet]['descriptors'][$fromCol]);
                $toExists = $toCol && isset($newMultiSheetData[$toSheet]['descriptors'][$toCol]);

                if ($fromExists && $toExists) {
                    $reconciledEdges[$id] = $oldEdge->withState(
                        DiscoveredRelationshipEdge::STATE_STALE,
                        null,
                        'Data drift: Inclusion dependency dropped below 0.85 after re-import.'
                    );
                } else {
                    // Target or source column/sheet was removed or renamed: mark INVALIDATED
                    $reconciledEdges[$id] = $oldEdge->withState(
                        DiscoveredRelationshipEdge::STATE_INVALIDATED,
                        null,
                        'Target or source column was removed or renamed in new file version.'
                    );
                }
            }
        }

        // Add any newly discovered edges
        foreach ($newGraph->edges as $id => $newEdge) {
            if (! isset($reconciledEdges[$id])) {
                $reconciledEdges[$id] = $newEdge;
            }
        }

        $finalGraph = new SpreadsheetRelationshipGraph(
            workspaceId: $newGraph->workspaceId,
            documentId: $newGraph->documentId,
            jobId: $newGraph->jobId,
            generationId: $newGraph->generationId,
            nodes: $newGraph->nodes,
            edges: $reconciledEdges,
            ambiguities: $newGraph->ambiguities,
            graphVersion: $newGraph->graphVersion,
            discoveredAt: now()->toISOString()
        );

        $this->persistGraph($job, $finalGraph);
        return $finalGraph;
    }

    /**
     * Authorize workspace ownership.
     */
    private function authorizeWorkspace(SpreadsheetImportJob $job, int $workspaceId): void
    {
        if ((int) $job->workspace_id !== $workspaceId) {
            throw new TenantScopeViolationException(
                message: "Cross-tenant relationship modification denied: Job belongs to workspace {$job->workspace_id}, context is {$workspaceId}.",
                requestWorkspaceId: $workspaceId,
                targetWorkspaceId: (int) $job->workspace_id
            );
        }
    }

    private function extractColumnValues(array $rows, string $colId): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (isset($row[$colId])) {
                $values[] = $row[$colId];
            }
        }
        return $values;
    }

    private function computeUniquenessRatio(array $values): float
    {
        $nonNull = array_filter($values, fn($v) => $v !== null && trim((string) $v) !== '');
        $count = count($nonNull);
        if ($count === 0) {
            return 0.0;
        }
        $distinct = count(array_unique(array_map('strval', $nonNull)));
        return (float) ($distinct / $count);
    }

    private function extractHeaderLabel(string $colId, SourceColumnDescriptor|array|null $desc): string
    {
        if ($desc instanceof SourceColumnDescriptor) {
            return $desc->originalHeader;
        }
        if (is_array($desc)) {
            return (string) ($desc['original_header'] ?? $desc['header'] ?? $desc['name'] ?? $colId);
        }
        return $colId;
    }

    private function extractPhysicalTypeLabel(SourceColumnDescriptor|array|null $desc): string
    {
        if ($desc instanceof SourceColumnDescriptor) {
            return $desc->physicalType;
        }
        if (is_array($desc)) {
            return (string) ($desc['physical_type'] ?? 'string');
        }
        return 'string';
    }
}
