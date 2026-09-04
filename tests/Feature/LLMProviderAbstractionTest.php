<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\LLM\LLMClient;
use App\AI\LLM\LLMRequest;
use App\AI\LLM\LLMResponse;
use App\AI\LLM\Providers\DeepSeekProvider;
use App\AI\LLM\Providers\OpenAIProvider;
use App\AI\LLM\Providers\OpenRouterProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LLMProviderAbstractionTest extends TestCase
{
    protected function tearDown(): void
    {
        LLMClient::resetFake();
        parent::tearDown();
    }

    public function test_deepseek_provider_sends_standard_request(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'DeepSeek standard response'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $provider = new DeepSeekProvider(apiKey: 'sk-test', baseUrl: 'https://api.deepseek.com', defaultModel: 'deepseek-chat');
        $request = LLMRequest::fromPrompt('Test prompt', 'System instructions');

        $response = $provider->send($request);

        $this->assertInstanceOf(LLMResponse::class, $response);
        $this->assertEquals('deepseek', $response->provider);
        $this->assertEquals('deepseek-chat', $response->model);
        $this->assertEquals('DeepSeek standard response', $response->content);
        $this->assertEquals(15, $response->usage['total_tokens']);
    }

    public function test_openrouter_provider_sends_standard_request(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'OpenRouter standard response'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 6, 'total_tokens' => 18],
            ], 200),
        ]);

        $provider = new OpenRouterProvider(apiKey: 'sk-or-test', baseUrl: 'https://openrouter.ai/api/v1', defaultModel: 'openrouter/free');
        $request = LLMRequest::fromPrompt('Test prompt');

        $response = $provider->send($request);

        $this->assertInstanceOf(LLMResponse::class, $response);
        $this->assertEquals('openrouter', $response->provider);
        $this->assertEquals('OpenRouter standard response', $response->content);
        $this->assertEquals(18, $response->usage['total_tokens']);
    }

    public function test_openai_provider_sends_standard_request(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'OpenAI standard response'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4, 'total_tokens' => 12],
            ], 200),
        ]);

        $provider = new OpenAIProvider(apiKey: 'sk-oa-test', baseUrl: 'https://api.openai.com/v1', defaultModel: 'gpt-4o-mini');
        $request = LLMRequest::fromPrompt('Test prompt');

        $response = $provider->send($request);

        $this->assertInstanceOf(LLMResponse::class, $response);
        $this->assertEquals('openai', $response->provider);
        $this->assertEquals('gpt-4o-mini', $response->model);
        $this->assertEquals('OpenAI standard response', $response->content);
        $this->assertEquals(12, $response->usage['total_tokens']);
    }

    public function test_llm_client_generic_fallback_when_primary_provider_fails(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.fallback_provider', 'openrouter');

        // Primary fails with 500, fallback succeeds with 200
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response('Internal Server Error', 500),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Fallback recovered response'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $client = new LLMClient([
            'deepseek'   => new DeepSeekProvider('sk-test', 'https://api.deepseek.com'),
            'openrouter' => new OpenRouterProvider('sk-test', 'https://openrouter.ai/api/v1'),
        ]);

        $request = LLMRequest::fromPrompt('What is the return window?');
        $response = $client->generate($request);

        $this->assertInstanceOf(LLMResponse::class, $response);
        $this->assertEquals('openrouter', $response->provider);
        $this->assertEquals('Fallback recovered response', $response->content);
    }

    public function test_llm_client_fake_for_testing(): void
    {
        LLMClient::fake([
            'Faked response 1',
            'Faked response 2',
        ]);

        $client = new LLMClient();

        $resp1 = $client->generate(LLMRequest::fromPrompt('Q1'));
        $this->assertEquals('Faked response 1', $resp1->content);

        $resp2 = $client->generate(LLMRequest::fromPrompt('Q2'));
        $this->assertEquals('Faked response 2', $resp2->content);
    }

    public function test_auth_error_does_not_trigger_blind_fallback(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.fallback_provider', 'openrouter');

        // Primary fails with 401 Invalid Key
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response('Unauthorized - Invalid API Key', 401),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response('Should not be called', 200),
        ]);

        $client = new LLMClient([
            'deepseek'   => new DeepSeekProvider('sk-bad-key', 'https://api.deepseek.com'),
            'openrouter' => new OpenRouterProvider('sk-good-key', 'https://openrouter.ai/api/v1'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $client->generate(LLMRequest::fromPrompt('Test query'));
    }

    public function test_telemetry_is_populated_on_llm_response(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Telemetry test response'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10, 'total_tokens' => 30],
            ], 200),
        ]);

        $client = new LLMClient([
            'deepseek' => new DeepSeekProvider('sk-test', 'https://api.deepseek.com'),
        ]);

        $response = $client->generate(LLMRequest::fromPrompt('Test'));

        $this->assertNotEmpty($response->telemetry);
        $this->assertArrayHasKey('latency_ms', $response->telemetry);
        $this->assertFalse($response->telemetry['fallback_used']);
        $this->assertEquals('deepseek', $response->telemetry['active_provider']);
        $this->assertEquals(30, $response->telemetry['total_tokens']);
    }
}
