<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\LLM\LLMClient;
use App\AI\LLM\LLMRequest;
use App\AI\LLM\LLMResponse;
use App\AI\LLM\Providers\GenericProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Provider Failure Modes, Timeouts, Retries, and Resilience Test Suite.
 *
 * Validates system resilience against external AI provider failures, timeouts,
 * HTTP 429 rate limits, malformed JSON responses, gateway 502/503 errors, and total failure fail-closed semantics.
 */
class ProviderFailureAndResilienceTest extends TestCase
{
    protected function tearDown(): void
    {
        LLMClient::resetFake();
        parent::tearDown();
    }

    /**
     * Test 1: Primary provider timeout recovers through secondary fallback provider.
     */
    public function test_primary_provider_timeout_triggers_fallback_recovery(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.fallback_provider', 'openrouter');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => function () {
                throw new ConnectionException('Connection timed out after 30000ms');
            },
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Recovered via OpenRouter after DeepSeek timeout'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 8, 'total_tokens' => 23],
            ], 200),
        ]);

        $client = new LLMClient([
            'deepseek'   => new GenericProvider('deepseek', 'sk-test', 'https://api.deepseek.com', 'deepseek-chat'),
            'openrouter' => new GenericProvider('openrouter', 'sk-test', 'https://openrouter.ai/api/v1', 'openrouter/free'),
        ]);

        $request = LLMRequest::fromPrompt('Customer query during high latency');
        $response = $client->generate($request);

        $this->assertInstanceOf(LLMResponse::class, $response);
        $this->assertEquals('openrouter', $response->provider);
        $this->assertEquals('Recovered via OpenRouter after DeepSeek timeout', $response->content);
        $this->assertTrue($response->telemetry['fallback_used']);
    }

    /**
     * Test 2: HTTP 429 Rate Limit (Too Many Requests) gracefully fails over to secondary provider.
     */
    public function test_http_429_rate_limit_triggers_fallback_recovery(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.fallback_provider', 'openrouter');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'error' => ['message' => 'Rate limit exceeded. Please wait.', 'type' => 'rate_limit_error']
            ], 429),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Rate limit failover response'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 6, 'total_tokens' => 18],
            ], 200),
        ]);

        $client = new LLMClient([
            'deepseek'   => new GenericProvider('deepseek', 'sk-test', 'https://api.deepseek.com', 'deepseek-chat'),
            'openrouter' => new GenericProvider('openrouter', 'sk-test', 'https://openrouter.ai/api/v1', 'openrouter/free'),
        ]);

        $request = LLMRequest::fromPrompt('What is your delivery policy?');
        $response = $client->generate($request);

        $this->assertEquals('openrouter', $response->provider);
        $this->assertEquals('Rate limit failover response', $response->content);
        $this->assertTrue($response->telemetry['fallback_used']);
    }

    /**
     * Test 3: Upstream Gateway Errors (502 Bad Gateway / 503 Service Unavailable / 504 Gateway Timeout).
     */
    public function test_upstream_gateway_503_triggers_fallback_recovery(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.fallback_provider', 'openrouter');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response('503 Service Temporarily Unavailable', 503),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Recovered from 503 upstream error'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $client = new LLMClient([
            'deepseek'   => new GenericProvider('deepseek', 'sk-test', 'https://api.deepseek.com', 'deepseek-chat'),
            'openrouter' => new GenericProvider('openrouter', 'sk-test', 'https://openrouter.ai/api/v1', 'openrouter/free'),
        ]);

        $response = $client->generate(LLMRequest::fromPrompt('Test 503 recovery'));
        $this->assertEquals('openrouter', $response->provider);
        $this->assertEquals('Recovered from 503 upstream error', $response->content);
    }

    /**
     * Test 4: Malformed HTTP 200 payload with empty choices or invalid format.
     */
    public function test_malformed_response_handled_without_crashing(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.fallback_provider', 'openrouter');

        // Primary returns 200 but malformed choices structure
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'id'      => 'malformed-1',
                'choices' => [], // Empty choices!
            ], 200),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Valid fallback after malformed primary'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 5, 'total_tokens' => 13],
            ], 200),
        ]);

        $client = new LLMClient([
            'deepseek'   => new GenericProvider('deepseek', 'sk-test', 'https://api.deepseek.com', 'deepseek-chat'),
            'openrouter' => new GenericProvider('openrouter', 'sk-test', 'https://openrouter.ai/api/v1', 'openrouter/free'),
        ]);

        $response = $client->generate(LLMRequest::fromPrompt('Query'));
        $this->assertInstanceOf(LLMResponse::class, $response);
        // GenericProvider handles empty choices gracefully by returning content = null
        $this->assertNull($response->content);
    }

    /**
     * Test 5: All providers down fails closed with clear diagnostic exception.
     */
    public function test_all_providers_down_fails_closed_without_hallucinating(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.fallback_provider', 'openrouter');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response('500 Internal Server Error', 500),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response('500 Internal Server Error', 500),
        ]);

        $client = new LLMClient([
            'deepseek'   => new GenericProvider('deepseek', 'sk-test', 'https://api.deepseek.com', 'deepseek-chat'),
            'openrouter' => new GenericProvider('openrouter', 'sk-test', 'https://openrouter.ai/api/v1', 'openrouter/free'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/All LLM providers .* failed/');

        $client->generate(LLMRequest::fromPrompt('Critical question'));
    }

    /**
     * Test 6: Auth failure (401) is not retried against fallback (fail-closed security).
     */
    public function test_auth_error_fails_immediately_without_fallback_loop(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.fallback_provider', 'openrouter');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response('Unauthorized - Bad Key', 401),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response('Should never be called', 200),
        ]);

        $client = new LLMClient([
            'deepseek'   => new GenericProvider('deepseek', 'sk-invalid', 'https://api.deepseek.com', 'deepseek-chat'),
            'openrouter' => new GenericProvider('openrouter', 'sk-valid', 'https://openrouter.ai/api/v1', 'openrouter/free'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $client->generate(LLMRequest::fromPrompt('Query'));

        // Verify OpenRouter was never called
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'openrouter.ai');
        });
    }
}
