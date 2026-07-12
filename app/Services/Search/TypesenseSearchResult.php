<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Contracts\Support\Arrayable;

class TypesenseSearchResult implements Arrayable
{
    /**
     * @param array<string, mixed> $document     The matched document.
     * @param float                $keywordScore Normalized keyword match score (0–1).
     * @param float                $vectorScore  Normalized vector similarity score (0–1).
     * @param float                $finalScore   Combined final score.
     * @param string               $matchType    'keyword', 'vector', or 'hybrid'.
     */
    public function __construct(
        public readonly array $document,
        public readonly float $keywordScore = 0.0,
        public readonly float $vectorScore = 0.0,
        public readonly float $finalScore = 0.0,
        public readonly string $matchType = 'keyword',
    ) {}

    /**
     * Convenience accessor for document fields.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->document[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'document'       => $this->document,
            'keyword_score'  => round($this->keywordScore, 4),
            'vector_score'   => round($this->vectorScore, 4),
            'final_score'    => round($this->finalScore, 4),
            'match_type'     => $this->matchType,
        ];
    }
}
