<?php

declare(strict_types=1);

namespace App\Services\Memory\DTOs;

/**
 * Value Object representing the decision of the Memory Relevance Gate (Phase M3).
 *
 * Guaranteed Architectural Boundary:
 * - action: 'RETRIEVE' | 'BYPASS'
 * - reason: Explicit audit reason (e.g. 'self_contained_policy', 'personal_recall_cue', 'anaphora_needing_memory', 'local_context_satisfied', 'ood_query')
 * - relevanceScore: Float score (0.0 to 1.0)
 * - diagnostics: Key-value diagnostics for telemetry and performance auditing
 */
class MemoryGateDecision
{
    public function __construct(
        public readonly string $action,
        public readonly string $reason,
        public readonly float $relevanceScore = 0.0,
        public readonly array $diagnostics = [],
    ) {}

    /**
     * Whether memory should be retrieved from the persistent memory service.
     */
    public function shouldRetrieve(): bool
    {
        return $this->action === 'RETRIEVE';
    }

    /**
     * Whether memory retrieval is bypassed (saving network latency and preventing context pollution).
     */
    public function isBypassed(): bool
    {
        return $this->action === 'BYPASS';
    }

    /**
     * Serialized representation for telemetry & diagnostics.
     */
    public function toArray(): array
    {
        return [
            'action'          => $this->action,
            'should_retrieve' => $this->shouldRetrieve(),
            'reason'          => $this->reason,
            'relevance_score' => $this->relevanceScore,
            'diagnostics'     => $this->diagnostics,
        ];
    }
}
