<?php

declare(strict_types=1);

namespace App\AI\LLM;

class LLMResponse
{
    /**
     * @param array<int, array<string, mixed>>|null $toolCalls
     * @param array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int} $usage
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public ?string $content,
        public string $provider,
        public string $model,
        public ?array $toolCalls = null,
        public array $usage = [],
        public ?string $finishReason = null,
        public array $rawResponse = [],
        public array $telemetry = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }
}
