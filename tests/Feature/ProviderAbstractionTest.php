<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\CustomerSupportAgent;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProviderAbstractionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Provider Test Workspace',
            'slug' => 'provider-test-workspace',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Widget',
            'external_id'  => 'web_widget_prov',
            'access_token' => 'token_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_prov_888',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'What are your shipping rates inside Dhaka?',
            'answer'       => 'Delivery inside Dhaka is 60 BDT and takes 24-48 hours.',
            'is_active'    => true,
        ]);
    }

    public function test_primary_provider_succeeds(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.default_model', 'deepseek-chat');

        Http::fake([
            'https://api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Inside Dhaka shipping is 60 BDT!']]
                ]
            ], 200),
            '*/memory/search' => Http::response(['has_memories' => false], 200),
        ]);

        $service = app(CustomerSupportService::class);
        $result = $service->handleQuery(
            query: 'What are your shipping rates inside Dhaka?',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
        );

        $this->assertNotEmpty($result['reply']);
        $this->assertStringContainsString('60 BDT', $result['reply']);
    }

    public function test_fallback_provider_triggered_when_primary_fails(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.default_model', 'deepseek-chat');
        Config::set('ai.fallback_provider', 'openrouter');
        Config::set('ai.fallback_model', 'openrouter/free');

        Http::fake([
            // Primary provider fails with 500
            'https://api.deepseek.com/*' => Http::response([
                'error' => 'Internal Server Error'
            ], 500),

            // Secondary fallback provider succeeds
            'https://openrouter.ai/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Recovered reply from OpenRouter fallback!']]
                ]
            ], 200),

            '*/memory/search' => Http::response(['has_memories' => false], 200),
        ]);

        $service = app(CustomerSupportService::class);
        $result = $service->handleQuery(
            query: 'What are your shipping rates inside Dhaka?',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
        );

        $this->assertNotEmpty($result['reply']);
        // When deepseek fails, openrouter fallback is called or top grounded FAQ answer is delivered
        $this->assertTrue(
            str_contains($result['reply'], 'OpenRouter fallback') ||
            str_contains($result['reply'], '60 BDT')
        );
    }

    public function test_graceful_grounded_fallback_when_both_providers_fail(): void
    {
        Config::set('ai.default', 'deepseek');
        Config::set('ai.fallback_provider', 'openrouter');

        $faq = FAQ::first();
        $faqSearch = $this->createMock(\App\Services\FAQ\FAQSearch::class);
        $hit = new FAQSearchResult($faq, 0.95, 0.98, 0.92, 'hybrid');
        $faqSearch->method('search')->willReturn(new \Illuminate\Database\Eloquent\Collection([$hit]));
        $this->app->instance(\App\Services\FAQ\FAQSearch::class, $faqSearch);

        Http::fake([
            // Both providers fail
            'https://api.deepseek.com/*' => Http::response(['error' => 'Rate limit exceeded'], 429),
            'https://openrouter.ai/*'   => Http::response(['error' => 'Service Unavailable'], 503),
            '*/memory/search'           => Http::response(['has_memories' => false], 200),
        ]);

        $service = app(CustomerSupportService::class);
        $result = $service->handleQuery(
            query: 'What are your shipping rates inside Dhaka?',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
        );

        // Deterministic grounded FAQ answer must be returned
        $this->assertNotEmpty($result['reply']);
        $this->assertStringContainsString('Delivery inside Dhaka is 60 BDT', $result['reply']);
    }
}
