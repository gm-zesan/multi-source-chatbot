<?php

declare(strict_types=1);

namespace App\AI\LLM;

use App\AI\LLM\Providers\DeepSeekProvider;
use App\AI\LLM\Providers\LLMProviderInterface;
use App\AI\LLM\Providers\OpenAIProvider;
use App\AI\LLM\Providers\OpenRouterProvider;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class LLMClient
{
    /** @var array<string, LLMProviderInterface> */
    private array $providers = [];

    /** @var array<int, LLMResponse>|null */
    private static ?array $fakedResponses = null;

    public function __construct(array $customProviders = [])
    {
        $this->providers = $customProviders;
    }

    /**
     * Fake responses for unit/feature testing.
     *
     * @param array<int, LLMResponse|string> $responses
     */
    public static function fake(array $responses = []): void
    {
        self::$fakedResponses = [];
        foreach ($responses as $resp) {
            if (is_string($resp)) {
                self::$fakedResponses[] = new LLMResponse(
                    content: $resp,
                    provider: 'fake',
                    model: 'fake-model',
                );
            } elseif ($resp instanceof LLMResponse) {
                self::$fakedResponses[] = $resp;
            }
        }
    }

    /**
     * Clear test fakes.
     */
    public static function resetFake(): void
    {
        self::$fakedResponses = null;
    }

    /**
     * Generate completion using the configured provider with automatic generic fallback.
     */
    public function generate(LLMRequest $request, ?string $providerName = null): LLMResponse
    {
        // If faked for testing, pop next faked response
        if (self::$fakedResponses !== null) {
            if (!empty(self::$fakedResponses)) {
                return array_shift(self::$fakedResponses);
            }
            return new LLMResponse(
                content: 'Default faked AI response.',
                provider: 'fake',
                model: 'fake-model',
            );
        }

        $t_start = microtime(true);
        $primaryName = $providerName ?? (string) config('ai.default', 'deepseek');
        $fallbackName = (string) config('ai.fallback_provider', 'openrouter');
        $fallbackUsed = false;
        $errorCategory = null;

        // Tier 1: Try Primary Provider
        try {
            $provider = $this->resolveProvider($primaryName);
            $response = $provider->send($request);
        } catch (Throwable $primaryError) {
            $errorCategory = LLMErrorType::classify($primaryError);

            Log::warning("[LLMClient] Primary provider '{$primaryName}' failed [Category: {$errorCategory->value}]: " . $primaryError->getMessage(), [
                'primary_provider'  => $primaryName,
                'fallback_provider' => $fallbackName,
                'error_category'    => $errorCategory->value,
                'fallback_eligible' => $errorCategory->isFallbackEligible(),
                'query_snippet'     => mb_substr($request->messages[count($request->messages) - 1]['content'] ?? '', 0, 80),
            ]);

            // If error is configuration bug (401 Auth) or request bug (400 Bad Request), fail fast without blind fallback!
            if (!$errorCategory->isFallbackEligible()) {
                throw $primaryError;
            }

            // Tier 2: Try Generic Configured Fallback if different from primary
            if ($fallbackName !== '' && $fallbackName !== $primaryName) {
                try {
                    $fallbackProvider = $this->resolveProvider($fallbackName);
                    $response = $fallbackProvider->send($request);
                    $fallbackUsed = true;
                } catch (Throwable $fallbackError) {
                    $fallbackCategory = LLMErrorType::classify($fallbackError);
                    Log::error("[LLMClient] Fallback provider '{$fallbackName}' also failed [Category: {$fallbackCategory->value}]: " . $fallbackError->getMessage(), [
                        'primary_error'     => $primaryError->getMessage(),
                        'fallback_error'    => $fallbackError->getMessage(),
                        'primary_category'  => $errorCategory->value,
                        'fallback_category' => $fallbackCategory->value,
                    ]);
                    throw new RuntimeException("All LLM providers ({$primaryName}, {$fallbackName}) failed. Primary: {$primaryError->getMessage()} | Fallback: {$fallbackError->getMessage()}", 0, $fallbackError);
                }
            } else {
                throw $primaryError;
            }
        }

        // Observability & Telemetry Enrichment
        $latencyMs = round((microtime(true) - $t_start) * 1000, 2);
        $response->telemetry = [
            'latency_ms'        => $latencyMs,
            'fallback_used'     => $fallbackUsed,
            'primary_provider'  => $primaryName,
            'active_provider'   => $response->provider,
            'model'             => $response->model,
            'prompt_tokens'     => $response->usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $response->usage['completion_tokens'] ?? 0,
            'total_tokens'      => $response->usage['total_tokens'] ?? 0,
            'primary_error'     => $errorCategory?->value,
        ];

        Log::info('[LLMClient] Execution completed', [
            'provider'      => $response->provider,
            'model'         => $response->model,
            'fallback_used' => $fallbackUsed,
            'latency_ms'    => $latencyMs,
            'tokens'        => $response->telemetry['total_tokens'],
        ]);

        return $response;
    }

    /**
     * Resolve provider instance by name.
     */
    public function resolveProvider(string $name): LLMProviderInterface
    {
        $cleanName = strtolower(trim($name));

        if (isset($this->providers[$cleanName])) {
            return $this->providers[$cleanName];
        }

        $provider = match ($cleanName) {
            'deepseek'   => new DeepSeekProvider(),
            'openrouter' => new OpenRouterProvider(),
            'openai'     => new OpenAIProvider(),
            default      => throw new InvalidArgumentException("Unsupported LLM provider: '{$name}'. Supported: deepseek, openrouter, openai."),
        };

        $this->providers[$cleanName] = $provider;
        return $provider;
    }

    /**
     * Register a custom provider instance.
     */
    public function registerProvider(string $name, LLMProviderInterface $provider): void
    {
        $this->providers[strtolower(trim($name))] = $provider;
    }
}
