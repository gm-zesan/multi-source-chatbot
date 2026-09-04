<?php

declare(strict_types=1);

namespace App\AI\LLM;

class LLMRequest
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<int, mixed>|null $tools
     * @param array<string, mixed>|null $responseFormat
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $messages,
        public ?string $model = null,
        public float $temperature = 0.7,
        public ?int $maxTokens = null,
        public ?array $tools = null,
        public ?array $responseFormat = null,
        public array $metadata = [],
    ) {}

    /**
     * Create an LLMRequest from a simple prompt string.
     */
    public static function fromPrompt(
        string $prompt,
        ?string $systemPrompt = null,
        ?string $model = null,
        float $temperature = 0.7,
        ?int $maxTokens = null,
    ): self {
        $messages = [];
        if ($systemPrompt !== null && trim($systemPrompt) !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        return new self(
            messages: $messages,
            model: $model,
            temperature: $temperature,
            maxTokens: $maxTokens,
        );
    }
}
