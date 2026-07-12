<?php

declare(strict_types=1);

namespace App\Services\NLP\Embedding;

use App\Services\NLP\Embedding\EmbeddingResponse;

class BatchEmbeddingResponse
{
    /**
     * @param array<EmbeddingResponse> $items       Individual embedding results.
     * @param int                      $dimensions  Vector dimensionality.
     * @param string                   $model       Model name used.
     * @param float|null               $timeMs      Total processing time in milliseconds.
     */
    public function __construct(
        public readonly array $items,
        public readonly int $dimensions,
        public readonly string $model,
        public readonly ?float $timeMs = null,
    ) {}

    /**
     * Create from the Python FastAPI /embed-batch JSON response.
     *
     * Python response shape (FastAPI Pydantic serialization):
     *   {"embeddings": [[0.1, 0.2, ...], [0.3, 0.4, ...]], "dimensions": 768}
     *
     * Each inner array is a float vector — NOT a nested object.
     * The caller supplies model name and timing separately from config / instrumentation.
     *
     * @param array<string, mixed> $data
     * @param string               $model  Model name (from config).
     * @param float|null           $timeMs Latency in milliseconds.
     */
    public static function fromArray(array $data, string $model = 'unknown', ?float $timeMs = null): self
    {
        $dimensions = (int) ($data['dimensions'] ?? 0);
        $rawVectors = $data['embeddings'] ?? [];

        $items = array_map(
            fn (array $vector) => new EmbeddingResponse(
                vector: $vector,
                dimensions: count($vector),
                model: $model,
            ),
            $rawVectors,
        );

        return new self(
            items: $items,
            dimensions: $dimensions,
            model: $model,
            timeMs: $timeMs,
        );
    }
}
