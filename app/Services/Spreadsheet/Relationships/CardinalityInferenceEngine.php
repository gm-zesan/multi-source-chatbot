<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Relationships;

use App\Services\Spreadsheet\Relationships\DTOs\CandidateKeyDescriptor;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;

/**
 * Cardinality & Confidence Inference Engine.
 *
 * Infers relationship cardinality (1:1, 1:N, N:M junction) and calculates multi-factor
 * confidence scores based on value containment, header token distance, entity semantics,
 * and physical type compatibility.
 */
class CardinalityInferenceEngine
{
    public const CONFIDENCE_THRESHOLD_HIGH = 0.85;
    public const CONFIDENCE_THRESHOLD_MEDIUM = 0.60;

    /**
     * Infer cardinality, calculate composite confidence, and build DiscoveredRelationshipEdge.
     *
     * @param string $sourceSheet Referencing sheet (e.g. Orders)
     * @param array<int, string> $sourceColumnIds Foreign key column IDs
     * @param array<int, string> $sourceHeaders Foreign key headers
     * @param array<int, string> $sourceTypes Physical types of foreign columns
     * @param float $sourceUniquenessRatio Uniqueness ratio of the foreign key in source sheet
     * @param string $targetSheet Referenced sheet (e.g. Customers)
     * @param CandidateKeyDescriptor $targetPk Referenced candidate primary key
     * @param array<string, mixed> $indResult Analysis result from InclusionDependencyAnalyzer
     * @param string $sourceEntityType Semantic entity type of source column/sheet
     * @param string $targetEntityType Semantic entity type of target sheet
     * @param bool $isKnownJunction Candidate junction flag
     */
    public function inferRelationship(
        string $sourceSheet,
        array $sourceColumnIds,
        array $sourceHeaders,
        array $sourceTypes,
        float $sourceUniquenessRatio,
        string $targetSheet,
        CandidateKeyDescriptor $targetPk,
        array $indResult,
        string $sourceEntityType = 'unknown',
        string $targetEntityType = 'unknown',
        bool $isKnownJunction = false
    ): DiscoveredRelationshipEdge {
        $ind = (float) ($indResult['inclusion_ratio'] ?? 0.0);
        $orphanCount = (int) ($indResult['orphan_count'] ?? 0);
        $isValidInd = (bool) ($indResult['is_valid_ind'] ?? false);

        // 1. Cardinality Inference
        $cardinality = $this->determineCardinality(
            $sourceUniquenessRatio,
            $targetPk->uniquenessRatio,
            $targetPk->totalRowCount,
            $isKnownJunction
        );

        // 2. Header Similarity Score (0.0 to 1.0)
        $headerSim = $this->computeHeaderSimilarity($sourceHeaders, $targetPk->headers, $targetSheet);

        // 3. Physical Type Compatibility (0.0 to 1.0)
        $typeCompat = $this->computeTypeCompatibility($sourceTypes, $targetPk->physicalTypes);

        // 4. Entity Alignment Score (0.0 to 1.0)
        $entityAlign = $this->computeEntityAlignment($sourceHeaders, $sourceEntityType, $targetEntityType, $targetSheet);

        // 5. Multi-factor Confidence Formulation
        // Formula: (IND * 0.50) + (HeaderSim * 0.25) + (EntityAlign * 0.15) + (TypeCompat * 0.10)
        $confidence = ($ind * 0.50) + ($headerSim * 0.25) + ($entityAlign * 0.15) + ($typeCompat * 0.10);
        $confidence = round(min(1.0, max(0.0, $confidence)), 4);

        // 6. Lifecycle State Determination
        $evidence = [];
        $state = DiscoveredRelationshipEdge::STATE_DISCOVERED;

        $evidence[] = "Inclusion Dependency (IND): " . round($ind, 4) . " (" . ($isValidInd ? 'VALID ≥ 0.85' : 'INVALID < 0.85') . ").";
        $evidence[] = "Cardinality resolved as {$cardinality} (Source uniqueness: " . round($sourceUniquenessRatio, 4) . ", Target uniqueness: " . round($targetPk->uniquenessRatio, 4) . ").";
        $evidence[] = "Header token similarity: " . round($headerSim, 4) . ".";
        $evidence[] = "Physical type compatibility: " . round($typeCompat, 4) . ".";
        $evidence[] = "Entity alignment score: " . round($entityAlign, 4) . ".";

        if (! $isValidInd) {
            $state = DiscoveredRelationshipEdge::STATE_REJECTED;
            $evidence[] = "Fails minimum inclusion dependency threshold (0.85). Cannot be activated.";
        } elseif ($confidence >= self::CONFIDENCE_THRESHOLD_HIGH) {
            $state = DiscoveredRelationshipEdge::STATE_DISCOVERED;
            $evidence[] = "High confidence candidate eligible for confirmation/activation.";
        } elseif ($confidence >= self::CONFIDENCE_THRESHOLD_MEDIUM) {
            $state = DiscoveredRelationshipEdge::STATE_NEEDS_CONFIRMATION;
            $evidence[] = "Ambiguous confidence score. Requires mandatory Owner confirmation before activation.";
        } else {
            $state = DiscoveredRelationshipEdge::STATE_REJECTED;
            $evidence[] = "Low confidence score below viability threshold (0.60).";
        }

        $edgeId = DiscoveredRelationshipEdge::generateId(
            $sourceSheet,
            $sourceColumnIds,
            $targetSheet,
            $targetPk->columnIds
        );

        return new DiscoveredRelationshipEdge(
            id: $edgeId,
            sourceSheet: $sourceSheet,
            sourceColumnIds: $sourceColumnIds,
            sourceHeaders: $sourceHeaders,
            targetSheet: $targetSheet,
            targetColumnIds: $targetPk->columnIds,
            targetHeaders: $targetPk->headers,
            cardinality: $cardinality,
            isJunctionTable: $isKnownJunction,
            inclusionRatio: $ind,
            headerSimilarity: $headerSim,
            entityAlignment: $entityAlign,
            typeCompatibility: $typeCompat,
            confidence: $confidence,
            state: $state,
            evidence: $evidence,
            orphanCount: $orphanCount
        );
    }

    /**
     * Determine cardinality based on uniqueness distribution.
     */
    private function determineCardinality(
        float $sourceUniqueness,
        float $targetUniqueness,
        int $targetRowCount,
        bool $isJunction
    ): string {
        if ($isJunction) {
            return DiscoveredRelationshipEdge::CARDINALITY_MANY_TO_MANY;
        }

        if ($targetUniqueness >= 0.99 && $sourceUniqueness >= 0.99) {
            // If sample size is very small (< 5 rows), mark 1:1 as ambiguous to avoid false certainty
            if ($targetRowCount < 5) {
                return DiscoveredRelationshipEdge::CARDINALITY_AMBIGUOUS;
            }
            return DiscoveredRelationshipEdge::CARDINALITY_ONE_TO_ONE;
        }

        if ($targetUniqueness >= 0.99 && $sourceUniqueness < 0.90) {
            return DiscoveredRelationshipEdge::CARDINALITY_ONE_TO_MANY;
        }

        if ($sourceUniqueness >= 0.90 && $sourceUniqueness < 0.99) {
            return DiscoveredRelationshipEdge::CARDINALITY_AMBIGUOUS;
        }

        return DiscoveredRelationshipEdge::CARDINALITY_ONE_TO_MANY;
    }

    /**
     * Compute header token similarity (0.0 to 1.0).
     *
     * @param array<int, string> $sourceHeaders
     * @param array<int, string> $targetHeaders
     * @param string $targetSheet
     * @return float
     */
    public function computeHeaderSimilarity(array $sourceHeaders, array $targetHeaders, string $targetSheet): float
    {
        $srcHeader = strtolower(trim(implode('_', $sourceHeaders)));
        $tgtHeader = strtolower(trim(implode('_', $targetHeaders)));
        $sheetName = strtolower(trim($targetSheet));

        if ($srcHeader === '' || $tgtHeader === '') {
            return 0.0;
        }

        // Exact match
        if ($srcHeader === $tgtHeader) {
            return 1.0;
        }

        // Clean tokens
        $srcClean = preg_replace('/[^a-z0-9]+/i', ' ', $srcHeader) ?? $srcHeader;
        $tgtClean = preg_replace('/[^a-z0-9]+/i', ' ', $tgtHeader) ?? $tgtHeader;
        $sheetClean = preg_replace('/[^a-z0-9]+/i', ' ', $sheetName) ?? $sheetName;

        // Check if source header contains target sheet name or target header
        // E.g. "customer_id" contains "customer", target sheet is "Customers"
        if (str_contains($srcClean, rtrim($sheetClean, 's')) || str_contains($sheetClean, rtrim($srcClean, 's'))) {
            return 0.90;
        }

        if (str_contains($srcClean, $tgtClean) || str_contains($tgtClean, $srcClean)) {
            return 0.85;
        }

        // Compute Levenshtein distance similarity
        $maxLen = max(strlen($srcHeader), strlen($tgtHeader));
        if ($maxLen > 0) {
            $lev = levenshtein($srcHeader, $tgtHeader);
            $levSim = max(0.0, 1.0 - ($lev / $maxLen));
            if ($levSim > 0.6) {
                return round($levSim, 4);
            }
        }

        return 0.30;
    }

    /**
     * Compute physical type compatibility (0.0 to 1.0).
     *
     * @param array<int, string> $sourceTypes
     * @param array<int, string> $targetTypes
     * @return float
     */
    public function computeTypeCompatibility(array $sourceTypes, array $targetTypes): float
    {
        $src = strtolower($sourceTypes[0] ?? 'string');
        $tgt = strtolower($targetTypes[0] ?? 'string');

        if ($src === $tgt) {
            return 1.0;
        }

        $numericTypes = ['integer', 'decimal', 'currency', 'percentage'];
        if (in_array($src, $numericTypes, true) && in_array($tgt, $numericTypes, true)) {
            return 0.85;
        }

        if (($src === 'string' || $tgt === 'string') && in_array($src . ':' . $tgt, ['string:identifier', 'identifier:string'], true)) {
            return 0.95;
        }

        if ($src === 'string' || $tgt === 'string') {
            return 0.50; // String can coerce other types
        }

        return 0.20;
    }

    /**
     * Compute entity alignment score (0.0 to 1.0).
     */
    public function computeEntityAlignment(
        array $sourceHeaders,
        string $sourceEntity,
        string $targetEntity,
        string $targetSheet
    ): float {
        $srcHeader = strtolower(trim(implode('_', $sourceHeaders)));
        $srcEntity = strtolower(trim($sourceEntity));
        $tgtEntity = strtolower(trim($targetEntity));
        $sheet = strtolower(trim($targetSheet));

        if ($srcEntity !== 'unknown' && $srcEntity === $tgtEntity) {
            return 1.0;
        }

        if ($tgtEntity !== 'unknown' && str_contains($srcHeader, $tgtEntity)) {
            return 0.90;
        }

        if (str_contains($srcHeader, rtrim($sheet, 's'))) {
            return 0.85;
        }

        return 0.40;
    }
}
