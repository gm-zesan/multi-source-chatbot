<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Relationships;

use App\Services\Spreadsheet\DTOs\SourceColumnDescriptor;
use App\Services\Spreadsheet\Relationships\DTOs\CandidateKeyDescriptor;
use App\Services\Spreadsheet\SpreadsheetInspectorService;

/**
 * Candidate Key Profiler.
 *
 * Mathematically evaluates source columns and composite column pairs for Primary Key
 * viability based on exact null ratio, uniqueness ratio, physical type consistency,
 * and semantic role heuristics without loading entire large datasets into memory.
 */
class CandidateKeyProfiler
{
    /**
     * Thresholds per mathematical contract.
     */
    public const MIN_UNIQUENESS_RATIO_PK = 0.99;
    public const MAX_NULL_RATIO_PK = 0.00;
    public const COMPOSITE_PAIR_MAX_CANDIDATE_COLS = 10;

    /**
     * Profile all columns in a sheet to discover single-column and composite candidate keys.
     *
     * @param string $sheetName
     * @param array<int, array<string, mixed>> $rows Tabular rows indexed by columnId or numeric indices
     * @param array<string, SourceColumnDescriptor|array<string, mixed>> $columnDescriptors Metadata descriptors
     * @return array<string, CandidateKeyDescriptor> Keyed by descriptor identifier
     */
    public function profileSheet(
        string $sheetName,
        array $rows,
        array $columnDescriptors = []
    ): array {
        $totalRows = count($rows);
        if ($totalRows === 0) {
            return [];
        }

        // 1. Identify distinct column identifiers from descriptors or first row
        $columnIds = $this->resolveColumnIds($rows, $columnDescriptors);
        if (empty($columnIds)) {
            return [];
        }

        $singleKeyDescriptors = [];
        $viableCompositePool = [];

        // 2. Profile each single column
        foreach ($columnIds as $colId) {
            $descriptor = $this->profileSingleColumn($sheetName, $colId, $rows, $totalRows, $columnDescriptors[$colId] ?? null);
            $singleKeyDescriptors[$colId] = $descriptor;

            // Collect non-null columns with partial uniqueness as candidate composite components
            if ($descriptor->nullRatio === 0.0 && $descriptor->uniquenessRatio > 0.10 && ! $descriptor->isCandidateKey) {
                $viableCompositePool[$colId] = $descriptor;
            }
        }

        // 3. Composite Key Profiling (Width = 2) if needed
        $compositeKeyDescriptors = [];
        $hasSinglePk = false;
        foreach ($singleKeyDescriptors as $desc) {
            if ($desc->isCandidateKey) {
                $hasSinglePk = true;
                break;
            }
        }

        // We run composite profiling if no single PK exists OR if viable candidate pool is small and bounded
        if (! $hasSinglePk && count($viableCompositePool) >= 2) {
            $compositeKeyDescriptors = $this->profileCompositePairs(
                $sheetName,
                $viableCompositePool,
                $rows,
                $totalRows,
                $columnDescriptors
            );
        }

        return array_merge($singleKeyDescriptors, $compositeKeyDescriptors);
    }

    /**
     * Profile a single column for key metrics.
     */
    public function profileSingleColumn(
        string $sheetName,
        string $colId,
        array $rows,
        int $totalRows,
        SourceColumnDescriptor|array|null $meta = null
    ): CandidateKeyDescriptor {
        $header = $this->extractHeader($colId, $meta);
        $physicalType = $this->extractPhysicalType($colId, $meta);
        $semanticRole = $this->extractSemanticRole($meta);

        $nullCount = 0;
        $emptyStringCount = 0;
        $distinctValues = [];
        $nonNullCount = 0;

        foreach ($rows as $row) {
            $val = $row[$colId] ?? null;

            if ($val === null) {
                $nullCount++;
                continue;
            }

            if (is_string($val)) {
                $trimmed = trim($val);
                if ($trimmed === '') {
                    $emptyStringCount++;
                    $nullCount++; // Empty strings treated as null for PK viability
                    continue;
                }
                $normVal = $trimmed;
            } elseif (is_numeric($val) || is_bool($val)) {
                $normVal = (string) $val;
            } else {
                $normVal = json_encode($val);
            }

            $nonNullCount++;
            $distinctValues[$normVal] = true;
        }

        $distinctCount = count($distinctValues);
        $nullRatio = $totalRows > 0 ? (float) ($nullCount / $totalRows) : 0.0;
        $uniquenessRatio = $nonNullCount > 0 ? (float) ($distinctCount / $nonNullCount) : 0.0;
        $emptyStringRatio = $totalRows > 0 ? (float) ($emptyStringCount / $totalRows) : 0.0;

        $nonPkRoles = ['amount', 'quantity', 'measure', 'currency', 'percentage', 'date_time', 'description', 'free_text'];
        $isMeasureOrText = in_array(strtolower($semanticRole), $nonPkRoles, true);

        $isCandidateKey = (! $isMeasureOrText && $nullRatio <= self::MAX_NULL_RATIO_PK && $uniquenessRatio >= self::MIN_UNIQUENESS_RATIO_PK);

        // Calculate confidence score
        $evidence = [];
        $confidence = 0.0;

        if ($isCandidateKey) {
            $confidence = 0.85;
            $evidence[] = "Strict non-null constraint satisfied (null_ratio: " . round($nullRatio, 4) . ").";
            $evidence[] = "Uniqueness ratio threshold satisfied (" . round($uniquenessRatio, 4) . " >= " . self::MIN_UNIQUENESS_RATIO_PK . ").";

            if ($semanticRole === 'identifier') {
                $confidence = min(1.0, $confidence + 0.10);
                $evidence[] = "Semantic role matches IDENTIFIER prior.";
            }

            if (in_array($physicalType, ['integer', 'string', 'identifier'], true)) {
                $confidence = min(1.0, $confidence + 0.05);
                $evidence[] = "Physical type ({$physicalType}) is standard for primary keys.";
            }
        } else {
            $confidence = round($uniquenessRatio * (1.0 - $nullRatio) * 0.5, 4);
            if ($nullRatio > 0.0) {
                $evidence[] = "Violates primary key non-null constraint ({$nullCount} null/empty cells, ratio: " . round($nullRatio, 4) . ").";
            }
            if ($uniquenessRatio < self::MIN_UNIQUENESS_RATIO_PK) {
                $evidence[] = "Uniqueness ratio below primary key threshold (" . round($uniquenessRatio, 4) . " < " . self::MIN_UNIQUENESS_RATIO_PK . ").";
            }
        }

        return new CandidateKeyDescriptor(
            sheetName: $sheetName,
            columnIds: [$colId],
            headers: [$header],
            physicalTypes: [$physicalType],
            totalRowCount: $totalRows,
            nullCount: $nullCount,
            nullRatio: $nullRatio,
            distinctCount: $distinctCount,
            uniquenessRatio: $uniquenessRatio,
            emptyStringRatio: $emptyStringRatio,
            isComposite: false,
            isCandidateKey: $isCandidateKey,
            confidence: round($confidence, 4),
            evidence: $evidence,
        );
    }

    /**
     * Bounded composite key profiler (Width = 2).
     *
     * @param array<string, CandidateKeyDescriptor> $candidateCols
     * @return array<string, CandidateKeyDescriptor>
     */
    private function profileCompositePairs(
        string $sheetName,
        array $candidateCols,
        array $rows,
        int $totalRows,
        array $columnDescriptors
    ): array {
        $keys = array_keys(array_slice($candidateCols, 0, self::COMPOSITE_PAIR_MAX_CANDIDATE_COLS));
        $count = count($keys);
        $compositeResults = [];

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $colA = $keys[$i];
                $colB = $keys[$j];

                $compositeSignature = "composite_{$colA}_{$colB}";
                $distinctPairs = [];
                $nullCount = 0;
                $nonNullCount = 0;

                foreach ($rows as $row) {
                    $valA = $row[$colA] ?? null;
                    $valB = $row[$colB] ?? null;

                    if ($valA === null || $valB === null || trim((string) $valA) === '' || trim((string) $valB) === '') {
                        $nullCount++;
                        continue;
                    }

                    $nonNullCount++;
                    $pairKey = json_encode([trim((string) $valA), trim((string) $valB)]);
                    $distinctPairs[$pairKey] = true;
                }

                $distinctCount = count($distinctPairs);
                $nullRatio = $totalRows > 0 ? (float) ($nullCount / $totalRows) : 0.0;
                $uniquenessRatio = $nonNullCount > 0 ? (float) ($distinctCount / $nonNullCount) : 0.0;

                $isCandidateKey = ($nullRatio <= self::MAX_NULL_RATIO_PK && $uniquenessRatio >= self::MIN_UNIQUENESS_RATIO_PK);

                if ($isCandidateKey || $uniquenessRatio >= 0.95) {
                    $descA = $candidateCols[$colA];
                    $descB = $candidateCols[$colB];

                    $evidence = [
                        "Composite pair ({$colA}, {$colB}) evaluated.",
                        "Combined uniqueness ratio: " . round($uniquenessRatio, 4),
                        "Combined null count: {$nullCount}",
                    ];

                    $confidence = $isCandidateKey ? 0.88 : round($uniquenessRatio * 0.7, 4);

                    $compositeResults[$compositeSignature] = new CandidateKeyDescriptor(
                        sheetName: $sheetName,
                        columnIds: [$colA, $colB],
                        headers: array_merge($descA->headers, $descB->headers),
                        physicalTypes: array_merge($descA->physicalTypes, $descB->physicalTypes),
                        totalRowCount: $totalRows,
                        nullCount: $nullCount,
                        nullRatio: $nullRatio,
                        distinctCount: $distinctCount,
                        uniquenessRatio: $uniquenessRatio,
                        emptyStringRatio: 0.0,
                        isComposite: true,
                        isCandidateKey: $isCandidateKey,
                        confidence: $confidence,
                        evidence: $evidence,
                    );
                }
            }
        }

        return $compositeResults;
    }

    /**
     * Resolve list of column identifiers.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $descriptors
     * @return array<int, string>
     */
    private function resolveColumnIds(array $rows, array $descriptors): array
    {
        if (! empty($descriptors)) {
            return array_keys($descriptors);
        }

        $firstRow = reset($rows);
        if (! is_array($firstRow)) {
            return [];
        }

        return array_map('strval', array_keys($firstRow));
    }

    private function extractHeader(string $colId, SourceColumnDescriptor|array|null $meta): string
    {
        if ($meta instanceof SourceColumnDescriptor) {
            return $meta->originalHeader;
        }
        if (is_array($meta)) {
            return (string) ($meta['original_header'] ?? $meta['header'] ?? $meta['name'] ?? $colId);
        }
        return $colId;
    }

    private function extractPhysicalType(string $colId, SourceColumnDescriptor|array|null $meta): string
    {
        if ($meta instanceof SourceColumnDescriptor) {
            return $meta->physicalType;
        }
        if (is_array($meta)) {
            return (string) ($meta['physical_type'] ?? 'string');
        }
        return 'string';
    }

    private function extractSemanticRole(SourceColumnDescriptor|array|null $meta): string
    {
        if ($meta instanceof SourceColumnDescriptor) {
            return $meta->semanticRole;
        }
        if (is_array($meta)) {
            return (string) ($meta['semantic_role'] ?? 'custom_attribute');
        }
        return 'custom_attribute';
    }
}

