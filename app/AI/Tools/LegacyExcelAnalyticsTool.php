<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Formatters\LegacyExcelAnalyticsReportFormatter;
use App\Enums\DocumentLifecycleStatus;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
use App\Services\Spreadsheet\Security\AnalyticsAstAuthorizer;
use App\Services\Spreadsheet\Security\Exceptions\UnauthorizedDataAccessException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stringable;
use Throwable;

/**
 * LegacyExcelAnalyticsTool (legacy_excel_analytics).
 *
 * Governed spreadsheet query tool executing exclusively through Contract 3.0.
 * Enforces:
 *   planCandidateQuery -> prepareVirtualQuery -> authorizePreparedQuery (HMAC Grant) -> executeVirtualQuery.
 * Decommissions and prohibits direct unauthenticated execution routes.
 *
 * Zero Legacy Fallback Guarantee:
 *   Failures strictly fail closed or return structured deterministic responses.
 *   Never delegates to CustomerSupportService or legacy routers.
 */
class LegacyExcelAnalyticsTool extends AbstractGovernedTool
{
    public function __construct(
        private readonly ?AnalyticsClient $analyticsClient = null,
        private readonly ?AnalyticsAstAuthorizer $astAuthorizer = null,
        private readonly ?CrossSheetRelationshipService $relationshipService = null,
        private readonly ?LegacyExcelAnalyticsReportFormatter $formatter = null,
    ) {}

    public function name(): string
    {
        return 'legacy_excel_analytics';
    }

    public function description(): Stringable|string
    {
        return 'Executes governed analytical questions over uploaded enterprise spreadsheet datasets via the Contract 3.0 SQLite virtual execution gateway.';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRequiredPermissions(): array
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()
                ->description('Natural language question to query against the uploaded spreadsheet records.')
                ->required(),
            'dataset_id' => $schema->string()
                ->description('Optional UUID of the specific spreadsheet import job. If omitted, uses the latest active dataset.'),
        ];
    }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        // 1. SLA Deadline Guard
        $remaining = $context->getRemainingSeconds();
        if ($remaining < 0.5) {
            throw new AgentExecutionTimeoutException(
                message: "Tool [{$this->name()}] execution aborted: remaining SLA budget ({$remaining}s) is below 0.5s threshold.",
                timeoutSeconds: $context->timeoutSeconds,
                elapsedSeconds: $context->timeoutSeconds - $remaining,
            );
        }

        $workspaceId = $context->getWorkspaceId();

        // 2. Strict Server-Side Customer Boundaries (Fail-Closed)
        if ($context->isCustomer()) {
            Log::warning("[{$this->name()}] Customer channel attempted spreadsheet analytics execution", [
                'workspace_id' => $workspaceId,
                'channel'      => $context->getChannel(),
            ]);

            throw new UnauthorizedToolException(
                message: 'Customer channels are strictly forbidden from executing spreadsheet analytics.',
                toolName: $this->name(),
                workspaceId: $workspaceId,
            );
        }

        $question = trim((string) ($arguments['question'] ?? $arguments['query'] ?? ''));
        if ($question === '') {
            return ToolResult::failure('Question parameter cannot be empty.');
        }

        // 3. Resolve Services & Formatter
        $client = $this->analyticsClient ?? app(AnalyticsClient::class);
        $authorizer = $this->astAuthorizer ?? app(AnalyticsAstAuthorizer::class);
        $relService = $this->relationshipService ?? app(CrossSheetRelationshipService::class);
        $reportFormatter = $this->formatter ?? app(LegacyExcelAnalyticsReportFormatter::class);

        // 4. Resolve Authoritative Target Spreadsheet Dataset (Server-Controlled Workspace Scoping + Lifecycle & Ambiguity Fencing)
        $explicitDatasetId = !empty($arguments['dataset_id']) ? trim((string) $arguments['dataset_id']) : null;

        $resolution = $this->resolveAuthoritativeJob($workspaceId, $explicitDatasetId);
        if ($resolution['ambiguous'] === true) {
            Log::warning("[{$this->name()}] Ambiguous active spreadsheet import jobs detected; failing closed", [
                'workspace_id'        => $workspaceId,
                'explicit_dataset_id' => $explicitDatasetId,
                'candidate_job_ids'   => $resolution['candidate_ids'],
            ]);

            return ToolResult::failure(
                error: 'সক্রিয় স্প্রেডশিট ডেটাসেটের একাধিক সমসাময়িক জেনারেশন পাওয়া যাওয়ায় নিরাপদ ফলাফল নির্ধারণ করা সম্ভব হয়নি।',
                metadata: [
                    'tool_name'        => $this->name(),
                    'status'           => 'ambiguous_active_jobs',
                    'contract_version' => '3.0.0',
                ]
            );
        }

        $job = $resolution['job'];

        // 5. Fail-Closed: Missing or Ineligible Spreadsheet Handling
        if ($job === null) {
            $noSpreadsheetReport = $reportFormatter->formatNoSpreadsheet();

            return ToolResult::success([
                'status'  => 'no_spreadsheet_found',
                'message' => 'No active spreadsheet datasets found for this workspace. Upload a spreadsheet to enable Excel analytics.',
                'report'  => $noSpreadsheetReport,
                'rows'    => [],
            ], [
                'execution_time_ms' => $context->getElapsedMs(),
                'tool_name'         => $this->name(),
                'sources'           => [],
            ]);
        }

        $datasetId = (string) ($job->document_id ?? $job->id);
        $generationId = (string) ($job->generation_id ?: Str::uuid());
        $descriptors = is_array($job->source_descriptors) ? $job->source_descriptors : [];
        $activeGraph = $relService->loadGraph($job);
        $activeEdges = $activeGraph ? $activeGraph->getActiveEdges() : [];
        $filename = $job->document?->original_filename ?? 'Spreadsheet Dataset';

        $edgeDescriptions = [];
        foreach ($activeEdges as $edge) {
            $sourceCol = !empty($edge->sourceHeaders[0]) ? $edge->sourceHeaders[0] : 'col';
            $targetCol = !empty($edge->targetHeaders[0]) ? $edge->targetHeaders[0] : 'col';
            $cardinality = !empty($edge->cardinality) ? " ({$edge->cardinality})" : '';
            $edgeDescriptions[] = "{$edge->sourceSheet}.{$sourceCol} -> {$edge->targetSheet}.{$targetCol}{$cardinality}";
        }

        $recordCount = $this->countAuthoritativeRecords($workspaceId, $job);

        // 6. Contract 3.0 Execution Sequence
        try {
            // Stage A: Plan Candidate Query
            $plan = $client->planCandidateQuery(
                question: $question,
                workspaceId: $workspaceId,
                datasetId: $datasetId
            );

            // Stage B: Prepare Virtual Query
            $artifact = $client->prepareVirtualQuery(
                plan: $plan,
                workspaceId: $workspaceId,
                datasetId: $datasetId,
                generationId: $generationId,
                graph: $activeGraph,
                sourceDescriptors: $descriptors
            );

            // Stage C: Authorize Query & Issue Single-Use Cryptographic HMAC Grant
            $grant = $authorizer->authorizePreparedQuery(
                artifact: $artifact,
                sourceDescriptors: $descriptors,
                graph: $activeGraph,
                context: $context->serverControlledContext
            );

            // Stage D: Execute Against Virtual SQLite Gateway
            $executionResponse = $client->executeVirtualQuery(
                artifact: $artifact,
                grant: $grant
            );

            // Pre-response lifecycle & concurrent generation revalidation
            $liveRevalidation = $this->revalidateJobAndLifecycle($job, $workspaceId, $explicitDatasetId);
            if (! $liveRevalidation['valid']) {
                Log::warning("[{$this->name()}] Concurrent generation or lifecycle change detected after live execution", [
                    'workspace_id' => $workspaceId,
                    'job_id'       => $job->id,
                    'reason'       => $liveRevalidation['reason'],
                ]);

                return ToolResult::failure(
                    error: 'স্প্রেডশিট ডেটাসেটটি হালনাগাদ হচ্ছে অথবা পরিবর্তন হয়েছে। অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন।',
                    metadata: [
                        'tool_name'        => $this->name(),
                        'status'           => $liveRevalidation['reason'],
                        'sources'          => [$filename],
                        'contract_version' => '3.0.0',
                    ]
                );
            }

            /** @var SpreadsheetImportJob $liveFreshJob */
            $liveFreshJob = $liveRevalidation['job'];
            $livePartial = $this->resolvePartialImportProvenance($liveFreshJob, $recordCount);

            // 7. Format Deterministic Bengali Report
            $formattedReport = $reportFormatter->formatExecution(
                query: $question,
                filename: $filename,
                recordCount: $recordCount > 0 ? $recordCount : null,
                activeEdges: $activeEdges,
                executionResponse: $executionResponse,
                isOfflineFallback: false,
                isPartialDataset: $livePartial['is_partial_dataset'],
                importedRows: $livePartial['imported_rows'],
                failedRows: $livePartial['failed_rows'],
                totalRows: $livePartial['total_rows'],
            );

            return ToolResult::success([
                'status'             => 'executed',
                'dataset_id'         => $datasetId,
                'report'             => $formattedReport,
                'row_count'          => $executionResponse->rowCount,
                'columns'            => $executionResponse->columns,
                'rows'               => $executionResponse->rows,
                'latency_ms'         => $executionResponse->latencyMs,
                'is_partial_dataset' => $livePartial['is_partial_dataset'],
                'imported_rows'      => $livePartial['imported_rows'],
                'failed_rows'        => $livePartial['failed_rows'],
            ], [
                'execution_time_ms'       => $context->getElapsedMs(),
                'tool_name'               => $this->name(),
                'query_fingerprint'       => $artifact->queryFingerprint,
                'contract_version'        => '3.0.0',
                'sources'                 => array_merge([$filename], $edgeDescriptions),
                'relationships_active'    => count($activeEdges),
                'dataset_status'          => $livePartial['dataset_status'],
                'is_partial_dataset'      => $livePartial['is_partial_dataset'],
                'imported_rows'           => $livePartial['imported_rows'],
                'failed_rows'             => $livePartial['failed_rows'],
                'total_rows'              => $livePartial['total_rows'],
                'partial_dataset_warning' => $livePartial['partial_dataset_warning'],
            ]);
        } catch (UnauthorizedDataAccessException $e) {
            Log::warning("[{$this->name()}] Security rejection during AST authorization", [
                'workspace_id' => $workspaceId,
                'error'        => $e->getMessage(),
            ]);

            throw new UnauthorizedToolException(
                message: "Access denied to spreadsheet column or relationship: {$e->getMessage()}",
                toolName: $this->name(),
                workspaceId: $workspaceId
            );
        } catch (AgentExecutionTimeoutException $timeoutEx) {
            throw $timeoutEx;
        } catch (Throwable $e) {
            Log::error("[{$this->name()}] Contract 3.0 query execution failed: " . $e->getMessage(), [
                'workspace_id' => $workspaceId,
                'query'        => $question,
            ]);

            // Resilient Structured Fallback: Revalidate authoritative job, generation & document lifecycle before fallback
            $revalidation = $this->revalidateJobAndLifecycle($job, $workspaceId, $explicitDatasetId);
            if (! $revalidation['valid']) {
                Log::warning("[{$this->name()}] Offline fallback rejected by lifecycle/generation revalidation", [
                    'workspace_id' => $workspaceId,
                    'job_id'       => $job->id,
                    'reason'       => $revalidation['reason'],
                ]);

                return ToolResult::failure(
                    error: 'স্প্রেডশিট ডেটাসেটের বর্তমান জেনারেশন যাচাই করা সম্ভব হয়নি অথবা হালনাগাদ চলছে। অনুগ্রহ করে পরে আবার চেষ্টা করুন।',
                    metadata: [
                        'tool_name'        => $this->name(),
                        'status'           => $revalidation['reason'],
                        'sources'          => [$filename],
                        'contract_version' => '3.0.0',
                    ]
                );
            }

            /** @var SpreadsheetImportJob $freshJob */
            $freshJob = $revalidation['job'];
            $verifiedRecordCount = $this->countAuthoritativeRecords($workspaceId, $freshJob);

            if ($verifiedRecordCount > 0) {
                $datasetFreshness = $freshJob->completed_at?->toIso8601String();
                $freshnessKnown = $datasetFreshness !== null;
                $provenanceWarning = $freshnessKnown ? null : 'dataset_freshness_unknown';
                $offlinePartial = $this->resolvePartialImportProvenance($freshJob, $verifiedRecordCount);

                $fallbackReport = $reportFormatter->formatExecution(
                    query: $question,
                    filename: $filename,
                    recordCount: $verifiedRecordCount,
                    activeEdges: $activeEdges,
                    executionResponse: null,
                    isOfflineFallback: true,
                    isPartialDataset: $offlinePartial['is_partial_dataset'],
                    importedRows: $offlinePartial['imported_rows'],
                    failedRows: $offlinePartial['failed_rows'],
                    totalRows: $offlinePartial['total_rows'],
                );

                return ToolResult::success([
                    'status'             => 'synthesized_fallback',
                    'dataset_id'         => $datasetId,
                    'report'             => $fallbackReport,
                    'row_count'          => $verifiedRecordCount,
                    'columns'            => [],
                    'rows'               => [],
                    'is_partial_dataset' => $offlinePartial['is_partial_dataset'],
                    'imported_rows'      => $offlinePartial['imported_rows'],
                    'failed_rows'        => $offlinePartial['failed_rows'],
                ], [
                    'execution_time_ms'       => $context->getElapsedMs(),
                    'tool_name'               => $this->name(),
                    'source_status'           => 'persisted_structured_dataset',
                    'is_cached_summary'       => true,
                    'dataset_generation'      => (string) $freshJob->generation_id,
                    'dataset_freshness'       => $datasetFreshness,
                    'freshness_known'         => $freshnessKnown,
                    'provenance_warning'      => $provenanceWarning,
                    'import_job_id'           => (string) $freshJob->id,
                    'provenance_fenced'       => true,
                    'lifecycle_revalidated'   => true,
                    'offline_fallback_reason' => 'downstream_gateway_unavailable',
                    'sources'                 => array_merge([$filename], $edgeDescriptions),
                    'relationships_active'    => count($activeEdges),
                    'contract_version'        => '3.0.0',
                    'dataset_status'          => $offlinePartial['dataset_status'],
                    'is_partial_dataset'      => $offlinePartial['is_partial_dataset'],
                    'imported_rows'           => $offlinePartial['imported_rows'],
                    'failed_rows'             => $offlinePartial['failed_rows'],
                    'total_rows'              => $offlinePartial['total_rows'],
                    'partial_dataset_warning' => $offlinePartial['partial_dataset_warning'],
                ]);
            }

            $safeError = $reportFormatter->formatError($e->getMessage());

            return ToolResult::failure(
                error: $safeError,
                metadata: [
                    'tool_name'        => $this->name(),
                    'exception_class'  => class_basename($e),
                    'sources'          => [$filename],
                    'contract_version' => '3.0.0',
                ]
            );
        }
    }

    /**
     * Resolve the single authoritative completed job matching workspace, document lifecycle, and active generation.
     *
     * @return array{job: ?SpreadsheetImportJob, ambiguous: bool, candidate_ids: array<int, string>}
     */
    private function resolveAuthoritativeJob(int $workspaceId, ?string $explicitDatasetId): array
    {
        $jobQuery = SpreadsheetImportJob::where('workspace_id', $workspaceId)
            ->whereIn('status', ['completed', 'completed_with_errors']);

        if ($explicitDatasetId !== null) {
            $jobQuery->where(function ($q) use ($explicitDatasetId) {
                $q->where('id', $explicitDatasetId)
                  ->orWhere('document_id', $explicitDatasetId);
            });
        }

        $candidates = $jobQuery
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->filter(function (SpreadsheetImportJob $candidate) use ($workspaceId): bool {
                if (! $candidate->isCompleted()) {
                    return false;
                }

                if ($candidate->document_id !== null) {
                    $doc = KnowledgeDocument::where('workspace_id', $workspaceId)
                        ->where('id', $candidate->document_id)
                        ->first();

                    if ($doc === null || $doc->lifecycle_status !== DocumentLifecycleStatus::ACTIVE) {
                        return false;
                    }

                    if (!empty($doc->active_generation_id) && (string) $candidate->generation_id !== (string) $doc->active_generation_id) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        if ($candidates->isEmpty()) {
            return ['job' => null, 'ambiguous' => false, 'candidate_ids' => []];
        }

        if ($candidates->count() >= 2) {
            /** @var SpreadsheetImportJob $first */
            $first = $candidates[0];
            /** @var SpreadsheetImportJob $second */
            $second = $candidates[1];

            $firstTimestamp = $first->completed_at?->toIso8601String() ?? $first->created_at?->toIso8601String();
            $secondTimestamp = $second->completed_at?->toIso8601String() ?? $second->created_at?->toIso8601String();

            $tiedTimestamps = $firstTimestamp !== null && $firstTimestamp === $secondTimestamp;
            $conflictingTargets = (string) $first->generation_id !== (string) $second->generation_id
                || (string) $first->document_id !== (string) $second->document_id;

            if ($tiedTimestamps && $conflictingTargets) {
                return [
                    'job'           => null,
                    'ambiguous'     => true,
                    'candidate_ids' => $candidates->pluck('id')->map(fn ($id) => (string) $id)->all(),
                ];
            }
        }

        return [
            'job'           => $candidates->first(),
            'ambiguous'     => false,
            'candidate_ids' => [(string) $candidates->first()->id],
        ];
    }

    /**
     * Count only structured records strictly fenced to workspace + document + authoritative import_job_id.
     */
    private function countAuthoritativeRecords(int $workspaceId, SpreadsheetImportJob $job): int
    {
        return StructuredRecord::where('workspace_id', $workspaceId)
            ->where('document_id', $job->document_id)
            ->whereNotNull('import_job_id')
            ->where('import_job_id', $job->id)
            ->count();
    }

    /**
     * Revalidate before emitting any result that the job, document lifecycle, and generation have not shifted concurrently.
     *
     * @return array{valid: bool, job: ?SpreadsheetImportJob, reason: ?string}
     */
    private function revalidateJobAndLifecycle(
        SpreadsheetImportJob $initialJob,
        int $workspaceId,
        ?string $explicitDatasetId
    ): array {
        $freshJob = SpreadsheetImportJob::where('workspace_id', $workspaceId)
            ->where('id', $initialJob->id)
            ->first();

        if ($freshJob === null || ! $freshJob->isCompleted()) {
            return ['valid' => false, 'job' => null, 'reason' => 'job_no_longer_eligible'];
        }

        if ((string) $freshJob->generation_id !== (string) $initialJob->generation_id) {
            return ['valid' => false, 'job' => null, 'reason' => 'concurrent_generation_mutation'];
        }

        if ($freshJob->document_id !== null) {
            $freshDoc = KnowledgeDocument::where('workspace_id', $workspaceId)
                ->where('id', $freshJob->document_id)
                ->first();

            if ($freshDoc === null || $freshDoc->lifecycle_status !== DocumentLifecycleStatus::ACTIVE) {
                return ['valid' => false, 'job' => null, 'reason' => 'document_lifecycle_inactive'];
            }

            if (!empty($freshDoc->active_generation_id) && (string) $freshDoc->active_generation_id !== (string) $freshJob->generation_id) {
                return ['valid' => false, 'job' => null, 'reason' => 'concurrent_active_generation_superseded'];
            }
        }

        // Reject if a newer authoritative completed job for the same scope was published during execution
        $resolution = $this->resolveAuthoritativeJob($workspaceId, $explicitDatasetId);
        if ($resolution['ambiguous'] === true) {
            return ['valid' => false, 'job' => null, 'reason' => 'ambiguous_active_jobs_during_execution'];
        }

        $latestAuthoritative = $resolution['job'];
        if ($latestAuthoritative === null || (string) $latestAuthoritative->id !== (string) $freshJob->id) {
            return ['valid' => false, 'job' => null, 'reason' => 'concurrent_newer_generation_published'];
        }

        return ['valid' => true, 'job' => $freshJob, 'reason' => null];
    }
}
