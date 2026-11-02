<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Relationships;

/**
 * Inclusion Dependency (IND) Analyzer.
 *
 * Computes exact mathematical value containment ratios between referencing columns (FK)
 * and candidate primary key columns (PK) across sheets using safe comparison normalization.
 */
class InclusionDependencyAnalyzer
{
    /**
     * Mathematical threshold for a viable Foreign Key candidate.
     */
    public const IND_THRESHOLD = 0.85;

    /**
     * Bengali to Western numeric character translation map.
     */
    private const BANGLA_NUMERAL_MAP = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    /**
     * Analyze inclusion dependency: Does C_FK ⊆ C_PK?
     *
     * @param array<int, mixed> $fkValues Raw values from the referencing foreign column
     * @param array<int, mixed> $pkValues Raw values from the candidate primary key column
     * @return array{
     *     inclusion_ratio: float,
     *     distinct_fk_count: int,
     *     matched_count: int,
     *     orphan_count: int,
     *     is_valid_ind: bool,
     *     sample_orphans: array<int, string>
     * }
     */
    public function analyzeInclusion(array $fkValues, array $pkValues): array
    {
        // 1. Build distinct normalized lookup set for PK
        $pkSet = [];
        foreach ($pkValues as $val) {
            $normalized = $this->normalizeForComparison($val);
            if ($normalized !== null) {
                $pkSet[$normalized] = true;
            }
        }

        // 2. Build distinct normalized set for FK (ignoring nulls and empty strings)
        $fkSet = [];
        $rawFkMap = [];
        foreach ($fkValues as $val) {
            $normalized = $this->normalizeForComparison($val);
            if ($normalized !== null) {
                $fkSet[$normalized] = true;
                if (! isset($rawFkMap[$normalized])) {
                    $rawFkMap[$normalized] = is_scalar($val) ? (string) $val : json_encode($val);
                }
            }
        }

        $distinctFkCount = count($fkSet);
        if ($distinctFkCount === 0 || empty($pkSet)) {
            return [
                'inclusion_ratio'   => 0.0,
                'distinct_fk_count' => $distinctFkCount,
                'matched_count'     => 0,
                'orphan_count'      => $distinctFkCount,
                'is_valid_ind'      => false,
                'sample_orphans'    => [],
            ];
        }

        // 3. Calculate intersection and identify orphans
        $matchedCount = 0;
        $orphans = [];

        foreach (array_keys($fkSet) as $fkItem) {
            if (isset($pkSet[$fkItem])) {
                $matchedCount++;
            } else {
                $orphans[] = $rawFkMap[$fkItem] ?? $fkItem;
            }
        }

        $orphanCount = count($orphans);
        $inclusionRatio = (float) ($matchedCount / $distinctFkCount);
        $isValidInd = ($inclusionRatio >= self::IND_THRESHOLD);

        return [
            'inclusion_ratio'   => round($inclusionRatio, 4),
            'distinct_fk_count' => $distinctFkCount,
            'matched_count'     => $matchedCount,
            'orphan_count'      => $orphanCount,
            'is_valid_ind'      => $isValidInd,
            'sample_orphans'    => array_slice($orphans, 0, 5),
        ];
    }

    /**
     * Analyze inclusion dependency for composite keys.
     *
     * @param array<int, array<int, mixed>> $fkCompositeRows Array of rows containing FK column values
     * @param array<int, array<int, mixed>> $pkCompositeRows Array of rows containing PK column values
     * @return array{
     *     inclusion_ratio: float,
     *     distinct_fk_count: int,
     *     matched_count: int,
     *     orphan_count: int,
     *     is_valid_ind: bool,
     *     sample_orphans: array<int, string>
     * }
     */
    public function analyzeCompositeInclusion(array $fkCompositeRows, array $pkCompositeRows): array
    {
        $pkSet = [];
        foreach ($pkCompositeRows as $row) {
            $normRow = array_map([$this, 'normalizeForComparison'], $row);
            if (! in_array(null, $normRow, true)) {
                $pkSet[(string) json_encode($normRow)] = true;
            }
        }

        $fkSet = [];
        foreach ($fkCompositeRows as $row) {
            $normRow = array_map([$this, 'normalizeForComparison'], $row);
            if (! in_array(null, $normRow, true)) {
                $fkSet[(string) json_encode($normRow)] = true;
            }
        }

        $distinctFkCount = count($fkSet);
        if ($distinctFkCount === 0 || empty($pkSet)) {
            return [
                'inclusion_ratio'   => 0.0,
                'distinct_fk_count' => $distinctFkCount,
                'matched_count'     => 0,
                'orphan_count'      => $distinctFkCount,
                'is_valid_ind'      => false,
                'sample_orphans'    => [],
            ];
        }

        $matchedCount = 0;
        $orphans = [];

        foreach (array_keys($fkSet) as $fkSig) {
            if (isset($pkSet[$fkSig])) {
                $matchedCount++;
            } else {
                $orphans[] = $fkSig;
            }
        }

        $orphanCount = count($orphans);
        $inclusionRatio = (float) ($matchedCount / $distinctFkCount);

        return [
            'inclusion_ratio'   => round($inclusionRatio, 4),
            'distinct_fk_count' => $distinctFkCount,
            'matched_count'     => $matchedCount,
            'orphan_count'      => $orphanCount,
            'is_valid_ind'      => ($inclusionRatio >= self::IND_THRESHOLD),
            'sample_orphans'    => array_slice($orphans, 0, 5),
        ];
    }

    /**
     * Safely normalize value strictly for set-comparison without altering source storage.
     *
     * @param mixed $val
     * @return string|null Null if value is empty/null
     */
    public function normalizeForComparison(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }

        if (is_bool($val)) {
            return $val ? '1' : '0';
        }

        $str = is_scalar($val) ? (string) $val : json_encode($val);
        $trimmed = trim($str);

        if ($trimmed === '') {
            return null;
        }

        // 1. Translate Bengali numerals to Western numerals
        $translated = strtr($trimmed, self::BANGLA_NUMERAL_MAP);

        // 2. Normalize numeric strings (e.g. "120.00" -> "120", "005" -> "5" only if pure integer)
        if (is_numeric($translated)) {
            $floatVal = (float) $translated;
            if ($floatVal == (int) $floatVal && ! str_contains($translated, 'e') && ! str_contains($translated, 'E')) {
                return (string) (int) $floatVal;
            }
            return (string) $floatVal;
        }

        // 3. Case and unicode normalization
        return mb_strtolower($translated, 'UTF-8');
    }
}

