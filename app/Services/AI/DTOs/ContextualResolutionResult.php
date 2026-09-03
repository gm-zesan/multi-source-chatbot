<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

/**
 * Value Object representing the output of the KGM-Aware Contextual Anaphora & Topic Resolution (Phase M2).
 *
 * Status Contract:
 * - 'self_contained' : Query is complete and standalone; resolvedQuery === rawQuery.
 * - 'resolved'       : Unambiguous antecedent identified; resolvedQuery contains contextualized query.
 * - 'ambiguous'      : Multiple plausible competing candidates; resolvedQuery === null (no blind guessing).
 * - 'unresolved'     : Zero antecedent or sub-threshold confidence; resolvedQuery === null.
 *
 * Source Contract:
 * - 'self_contained', 'local_turns', 'kgm', 'topic_continuation', 'unresolved'.
 */
class ContextualResolutionResult
{
    public function __construct(
        public readonly string $rawQuery,
        public readonly ?string $resolvedQuery = null,
        public readonly ?string $activeTopic = null,
        public readonly ?array $resolvedEntity = null,       // ['type' => 'Product|Order|Preference', 'id' => '...', 'name' => '...']
        public readonly array $candidates = [],              // [['type' => '...', 'id' => '...', 'name' => '...', 'score' => 0.94]]
        public readonly float $confidence = 1.0,
        public readonly string $status = 'resolved',         // 'self_contained', 'resolved', 'ambiguous', 'unresolved'
        public readonly string $source = 'local_turns',      // 'self_contained', 'local_turns', 'kgm', 'topic_continuation', 'unresolved'
        public readonly array $diagnostics = [],
    ) {}

    /**
     * Whether the query was self-contained and required zero contextual expansion.
     */
    public function isSelfContained(): bool
    {
        return $this->status === 'self_contained';
    }

    /**
     * Whether the resolution has sufficient confidence and an unambiguous winner.
     */
    public function isResolved(): bool
    {
        return $this->status === 'resolved' && $this->resolvedQuery !== null;
    }

    /**
     * Whether multiple plausible candidate entities exist, necessitating clarification.
     */
    public function isAmbiguous(): bool
    {
        return $this->status === 'ambiguous';
    }

    /**
     * Whether resolution failed to identify any antecedent.
     */
    public function isUnresolved(): bool
    {
        return $this->status === 'unresolved';
    }

    /**
     * Whether the query has multiple competing entities or insufficient confidence,
     * requiring interactive clarification (Phase M4) instead of blind guessing.
     */
    public function needsClarification(): bool
    {
        return in_array($this->status, ['ambiguous', 'unresolved'], true);
    }

    /**
     * Convenience getter for primary entity name string (backward compatibility).
     */
    public function getResolvedEntityName(): ?string
    {
        return $this->resolvedEntity['name'] ?? null;
    }

    /**
     * Serialized representation for telemetry & diagnostics.
     */
    public function toArray(): array
    {
        return [
            'raw_query'           => $this->rawQuery,
            'resolved_query'      => $this->resolvedQuery,
            'active_topic'        => $this->activeTopic,
            'resolved_entity'     => $this->resolvedEntity,
            'candidates'          => $this->candidates,
            'confidence'          => $this->confidence,
            'status'              => $this->status,
            'source'              => $this->source,
            'needs_clarification' => $this->needsClarification(),
            'diagnostics'         => $this->diagnostics,
        ];
    }
}
