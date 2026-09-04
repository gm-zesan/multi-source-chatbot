<?php

declare(strict_types=1);

namespace App\AI\LLM\Providers;

use App\AI\LLM\LLMRequest;
use App\AI\LLM\LLMResponse;
use App\AI\LLM\ProviderCapabilities;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekProvider implements LLMProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $defaultModel;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null, ?string $defaultModel = null)
    {
        $this->apiKey = $apiKey ?? (string) config('ai.providers.deepseek.key', env('DEEPSEEK_API_KEY', ''));
        $this->baseUrl = rtrim($baseUrl ?? (string) config('ai.providers.deepseek.url', 'https://api.deepseek.com'), '/');
        $this->defaultModel = $defaultModel ?? (string) config('ai.default_model', 'deepseek-chat');
    }

    public function send(LLMRequest $request): LLMResponse
    {
        $model = $request->model ?? $this->defaultModel;
        $url = "{$this->baseUrl}/chat/completions";

        $payload = [
            'model'       => $model,
            'messages'    => $request->messages,
            'temperature' => $request->temperature,
        ];

        if ($request->maxTokens !== null) {
            $payload['max_tokens'] = $request->maxTokens;
        }
        if ($request->tools !== null) {
            $payload['tools'] = $request->tools;
        }
        if ($request->responseFormat !== null) {
            $payload['response_format'] = $request->responseFormat;
        }

        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
        ];

        $response = Http::timeout(30)->withHeaders($headers)->post($url, $payload);

        if (!$response->successful()) {
            throw new RuntimeException("DeepSeek API error [HTTP {$response->status()}]: {$response->body()}");
        }

        $data = $response->json();
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        return new LLMResponse(
            content: $message['content'] ?? null,
            provider: 'deepseek',
            model: $model,
            toolCalls: $message['tool_calls'] ?? null,
            usage: [
                'prompt_tokens'     => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens'      => $data['usage']['total_tokens'] ?? 0,
            ],
            finishReason: $choice['finish_reason'] ?? null,
            rawResponse: $data,
        );
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            supportsToolCalling: true,
            supportsJsonMode: true,
            supportsSystemPrompt: true,
            maxContextWindow: 64000,
        );
    }

    public function getName(): string
    {
        return 'deepseek';
    }
}
