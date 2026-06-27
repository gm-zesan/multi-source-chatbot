<?php

namespace App\DTO;

/**
 * RoutingResult
 *
 * Immutable Data Transfer Object representing the result of context routing.
 * Carries all routing-level decisions (source, table, query type) along with
 * per-level confidence scores and fallback metadata.
 *
 * @property-read string|null $sourceId         Resolved database connection (db_01, db_02, etc.)
 * @property-read string|null $table            Resolved table name (customers, products, etc.)
 * @property-read string|null $queryType        Detected query intent type (select, count, aggregate, filtered_search)
 * @property-read float       $confidence       Composite confidence score (0.0 – 1.0)
 * @property-read float       $matchedDbScore   Cosine similarity score for database match (0.0 – 1.0)
 * @property-read float       $matchedTableScore Cosine similarity score for table match (0.0 – 1.0)
 * @property-read array       $metadata         Extra routing context (routing path, timing, level scores)
 * @property-read bool        $usedFallback     Whether the router fell back to keyword matching
 */
class RoutingResult
{
    /**
     * @param string|null $sourceId          Resolved database connection
     * @param string|null $table             Resolved table name
     * @param string|null $queryType         Detected query intent type
     * @param float       $confidence        Composite confidence score (0.0 – 1.0)
     * @param float       $matchedDbScore    Cosine similarity for database match (0.0 – 1.0)
     * @param float       $matchedTableScore Cosine similarity for table match (0.0 – 1.0)
     * @param array       $metadata          Extra context for auditing/debugging
     * @param bool        $usedFallback      Whether keyword fallback was used
     */
    public function __construct(
        public readonly ?string $sourceId = null,
        public readonly ?string $table = null,
        public readonly ?string $queryType = null,
        public readonly float   $confidence = 0.0,
        public readonly float   $matchedDbScore = 0.0,
        public readonly float   $matchedTableScore = 0.0,
        public readonly array   $metadata = [],
        public readonly bool    $usedFallback = false,
    ) {}

    /**
     * Convert to a plain array for logging, serialization, or intent merging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_id'          => $this->sourceId,
            'table'              => $this->table,
            'query_type'         => $this->queryType,
            'confidence'         => $this->confidence,
            'matched_db_score'   => $this->matchedDbScore,
            'matched_table_score' => $this->matchedTableScore,
            'metadata'           => $this->metadata,
            'used_fallback'      => $this->usedFallback,
        ];
    }

    /**
     * Whether this routing result meets the minimum confidence threshold
     * and has at least a source or table resolved.
     *
     * @param  float $threshold Minimum confidence (default 0.6)
     * @return bool
     */
    public function isActionable(float $threshold = 0.6): bool
    {
        return $this->confidence >= $threshold
            && ($this->sourceId !== null || $this->table !== null);
    }

    /**
     * Get a human-readable description of the routing path taken.
     *
     * @return string
     */
    public function routingDescription(): string
    {
        $parts = [];

        if ($this->sourceId) {
            $parts[] = "source:{$this->sourceId}";
        }
        if ($this->table) {
            $parts[] = "table:{$this->table}";
        }
        if ($this->queryType) {
            $parts[] = "type:{$this->queryType}";
        }

        $path = !empty($parts) ? implode(' → ', $parts) : 'no route';
        $method = $this->usedFallback ? 'keyword_fallback' : 'semantic';
        $score = number_format($this->confidence * 100, 1);

        return "[{$method}] {$path} ({$score}%)";
    }
}
