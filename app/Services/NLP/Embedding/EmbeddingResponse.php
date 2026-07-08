<?php

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
     * Create from a FastAPI JSON response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vector: $data['vector'] ?? [],
            dimensions: (int) ($data['dimensions'] ?? 0),
            model: $data['model'] ?? 'unknown',
            timeMs: isset($data['time_ms']) ? (float) $data['time_ms'] : null,
            text: $data['text'] ?? null,
        );
    }
}
