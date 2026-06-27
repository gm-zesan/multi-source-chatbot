<?php

namespace App\Services;

/**
 * Data Transfer Object for Context Router results.
 */
class RoutingResult
{
    /**
     * @param string|null   $sourceId        Resolved database connection (db_01, db_02, etc.)
     * @param string|null   $table           Resolved table name (customers, products, etc.)
     * @param string|null   $queryType       Detected query intent type (select, count, aggregate)
     * @param float         $confidence      Overall confidence score (0.0 – 1.0)
     * @param array         $metadata        Extra routing context (routing path, timing, etc.)
     * @param bool          $usedFallback    Whether the router fell back to keyword matching
     */
    public function __construct(
        public readonly ?string $sourceId = null,
        public readonly ?string $table = null,
        public readonly ?string $queryType = null,
        public readonly float   $confidence = 0.0,
        public readonly array   $metadata = [],
        public readonly bool    $usedFallback = false,
    ) {}

    /**
     * Convert to an array for logging / intent merging.
     */
    public function toArray(): array
    {
        return [
            'source_id'     => $this->sourceId,
            'table'         => $this->table,
            'query_type'    => $this->queryType,
            'confidence'    => $this->confidence,
            'metadata'      => $this->metadata,
            'used_fallback' => $this->usedFallback,
        ];
    }

    /**
     * Whether the routing result is usable (meets minimum confidence).
     */
    public function isActionable(float $threshold = 0.6): bool
    {
        return $this->confidence >= $threshold
            && ($this->sourceId !== null || $this->table !== null);
    }
}
