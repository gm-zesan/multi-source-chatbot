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
     * Create from a FastAPI batch JSON response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn (array $item) => EmbeddingResponse::fromArray($item),
            $data['vectors'] ?? [],
        );

        return new self(
            items: $items,
            dimensions: (int) ($data['dimensions'] ?? 0),
            model: $data['model'] ?? 'unknown',
            timeMs: isset($data['time_ms']) ? (float) $data['time_ms'] : null,
        );
    }
}
