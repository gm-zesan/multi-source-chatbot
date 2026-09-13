<?php

declare(strict_types=1);

namespace App\AI\LLM\Providers;

use App\AI\LLM\LLMRequest;
use App\AI\LLM\LLMResponse;
use App\AI\LLM\ProviderCapabilities;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenericProvider implements LLMProviderInterface
{
    private string $name;
    private string $apiKey;
    private string $baseUrl;
    private string $defaultModel;

    public function __construct(string $name, string $apiKey, string $baseUrl, string $defaultModel)
    {
        $this->name = $name;
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultModel = $defaultModel;
    }

    public function send(LLMRequest $request): LLMResponse
    {
        $model = $request->model ?? $this->defaultModel;
        
        // Some providers expect /v1/chat/completions, some expect /chat/completions. 
        // We assume the baseUrl provided in env points to the directory containing /chat/completions or /v1.
        // It's safest to assume the user provides the base URL correctly. 
        // If baseUrl ends in /v1, we append /chat/completions. If not, we still append /chat/completions.
        // E.g., OpenRouter: https://openrouter.ai/api/v1 -> https://openrouter.ai/api/v1/chat/completions
        // DeepSeek: https://api.deepseek.com -> https://api.deepseek.com/chat/completions
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
            'HTTP-Referer'  => config('app.url', 'http://localhost'), // Often required by OpenRouter
            'X-Title'       => config('app.name', 'Chatbot Orchestrator'),
        ];

        // NO ->withoutVerifying() to strictly enforce TLS security
        $response = Http::timeout(30)->withHeaders($headers)->post($url, $payload);

        if (!$response->successful()) {
            throw new RuntimeException("{$this->name} API error [HTTP {$response->status()}]: {$response->body()}");
        }

        $data = $response->json();
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        return new LLMResponse(
            content: $message['content'] ?? null,
            provider: $this->name,
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
            maxContextWindow: 128000,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }
}
