<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Relationships\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Discovered Relationship Edge DTO.
 *
 * Encapsulates a directed relationship between a referencing column (FK)
 * and a referenced column (PK) across sheets, along with mathematical containment,
 * cardinality, lifecycle state, and explainable multi-factor evidence.
 */
class DiscoveredRelationshipEdge implements Arrayable, JsonSerializable
{
    // Lifecycle states
    public const STATE_DISCOVERED = 'DISCOVERED';
    public const STATE_NEEDS_CONFIRMATION = 'NEEDS_CONFIRMATION';
    public const STATE_CONFIRMED = 'CONFIRMED';
    public const STATE_REJECTED = 'REJECTED';
    public const STATE_INVALIDATED = 'INVALIDATED';
    public const STATE_STALE = 'STALE';

    // Cardinality types
    public const CARDINALITY_ONE_TO_ONE = '1:1';
    public const CARDINALITY_ONE_TO_MANY = '1:N';
    public const CARDINALITY_MANY_TO_MANY = 'N:M';
    public const CARDINALITY_AMBIGUOUS = 'ambiguous';

    public function __construct(
        public readonly string $id,
        public readonly string $sourceSheet,
        public readonly array $sourceColumnIds,
        public readonly array $sourceHeaders,
        public readonly string $targetSheet,
        public readonly array $targetColumnIds,
        public readonly array $targetHeaders,
        public readonly string $cardinality,
        public readonly bool $isJunctionTable,
        public readonly float $inclusionRatio,
        public readonly float $headerSimilarity,
        public readonly float $entityAlignment,
        public readonly float $typeCompatibility,
        public readonly float $confidence,
        public readonly string $state,
        public readonly array $evidence = [],
        public readonly int $orphanCount = 0,
        public readonly ?int $confirmedBy = null,
        public readonly ?string $confirmedAt = null,
        public readonly ?string $rejectionReason = null,
    ) {}

    /**
     * Generate a deterministic, stable edge identifier.
     */
    public static function generateId(
        string $sourceSheet,
        array $sourceColumnIds,
        string $targetSheet,
        array $targetColumnIds
    ): string {
        $sourceSig = strtolower(trim($sourceSheet)) . ':' . implode(',', $sourceColumnIds);
        $targetSig = strtolower(trim($targetSheet)) . ':' . implode(',', $targetColumnIds);
        return 'edge_' . substr(hash('sha256', "{$sourceSig}->{$targetSig}"), 0, 16);
    }

    /**
     * Create edge with new state transition.
     */
    public function withState(string $newState, ?int $userId = null, ?string $reason = null): self
    {
        return new self(
            id: $this->id,
            sourceSheet: $this->sourceSheet,
            sourceColumnIds: $this->sourceColumnIds,
            sourceHeaders: $this->sourceHeaders,
            targetSheet: $this->targetSheet,
            targetColumnIds: $this->targetColumnIds,
            targetHeaders: $this->targetHeaders,
            cardinality: $this->cardinality,
            isJunctionTable: $this->isJunctionTable,
            inclusionRatio: $this->inclusionRatio,
            headerSimilarity: $this->headerSimilarity,
            entityAlignment: $this->entityAlignment,
            typeCompatibility: $this->typeCompatibility,
            confidence: $this->confidence,
            state: $newState,
            evidence: $this->evidence,
            orphanCount: $this->orphanCount,
            confirmedBy: $userId ?? $this->confirmedBy,
            confirmedAt: $newState === self::STATE_CONFIRMED ? now()->toISOString() : $this->confirmedAt,
            rejectionReason: $reason ?? $this->rejectionReason,
        );
    }

    /**
     * Instantiate from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            sourceSheet: (string) ($data['source_sheet'] ?? ''),
            sourceColumnIds: (array) ($data['source_column_ids'] ?? []),
            sourceHeaders: (array) ($data['source_headers'] ?? []),
            targetSheet: (string) ($data['target_sheet'] ?? ''),
            targetColumnIds: (array) ($data['target_column_ids'] ?? []),
            targetHeaders: (array) ($data['target_headers'] ?? []),
            cardinality: (string) ($data['cardinality'] ?? self::CARDINALITY_AMBIGUOUS),
            isJunctionTable: (bool) ($data['is_junction_table'] ?? false),
            inclusionRatio: (float) ($data['inclusion_ratio'] ?? 0.0),
            headerSimilarity: (float) ($data['header_similarity'] ?? 0.0),
            entityAlignment: (float) ($data['entity_alignment'] ?? 0.0),
            typeCompatibility: (float) ($data['type_compatibility'] ?? 0.0),
            confidence: (float) ($data['confidence'] ?? 0.0),
            state: (string) ($data['state'] ?? self::STATE_DISCOVERED),
            evidence: (array) ($data['evidence'] ?? []),
            orphanCount: (int) ($data['orphan_count'] ?? 0),
            confirmedBy: isset($data['confirmed_by']) ? (int) $data['confirmed_by'] : null,
            confirmedAt: isset($data['confirmed_at']) ? (string) $data['confirmed_at'] : null,
            rejectionReason: isset($data['rejection_reason']) ? (string) $data['rejection_reason'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'source_sheet'        => $this->sourceSheet,
            'source_column_ids'   => $this->sourceColumnIds,
            'source_headers'      => $this->sourceHeaders,
            'target_sheet'        => $this->targetSheet,
            'target_column_ids'   => $this->targetColumnIds,
            'target_headers'      => $this->targetHeaders,
            'cardinality'         => $this->cardinality,
            'is_junction_table'   => $this->isJunctionTable,
            'inclusion_ratio'     => round($this->inclusionRatio, 4),
            'header_similarity'   => round($this->headerSimilarity, 4),
            'entity_alignment'    => round($this->entityAlignment, 4),
            'type_compatibility'  => round($this->typeCompatibility, 4),
            'confidence'          => round($this->confidence, 4),
            'state'               => $this->state,
            'evidence'            => $this->evidence,
            'orphan_count'        => $this->orphanCount,
            'confirmed_by'        => $this->confirmedBy,
            'confirmed_at'        => $this->confirmedAt,
            'rejection_reason'    => $this->rejectionReason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

