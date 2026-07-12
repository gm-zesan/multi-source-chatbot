<?php

declare(strict_types=1);

namespace App\Services\NLP\Embedding;

class EmbeddingResponse
{
    /**
     * @param array<float> $vector      The embedding vector (float array).
     * @param int          $dimensions  Vector dimensionality.
     * @param string       $model       Model name that generated the vector.
     * @param float|null   $timeMs      Processing time in milliseconds.
     * @param string|null  $text        Original input text (echoed back by the API).
     */
    public function __construct(
        public readonly array $vector,
        public readonly int $dimensions,
        public readonly string $model,
        public readonly ?float $timeMs = null,
        public readonly ?string $text = null,
    ) {}

    /**
     * Create from the Python FastAPI /embed JSON response.
     *
     * Python response shape (FastAPI Pydantic serialization):
     *   {"embedding": [0.1, 0.2, ...], "dimensions": 768}
     *
     * The caller supplies model name and timing separately from config / instrumentation.
     *
     * @param array<string, mixed> $data
     * @param string               $model  Model name (from config, not in API response).
     * @param float|null           $timeMs Latency in milliseconds (from caller instrumentation).
     */
    public static function fromArray(array $data, string $model = 'unknown', ?float $timeMs = null): self
    {
        return new self(
            vector: $data['embedding'] ?? [],
            dimensions: (int) ($data['dimensions'] ?? 0),
            model: $model,
            timeMs: $timeMs,
            text: $data['text'] ?? null,
        );
    }
}
