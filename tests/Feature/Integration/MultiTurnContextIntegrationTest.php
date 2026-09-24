<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiTurnContextIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $channelAccount;
    private Conversation $conversation;
    private CustomerSupportService $supportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Context Retailers Ltd',
            'slug' => 'context-retailers',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        $this->channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Widget Context',
            'external_id'  => 'web_int_context_001',
            'access_token' => 'token_int_ctx_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_int_context',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->supportService = app(CustomerSupportService::class);
    }

    private function mockRouterRoute(RouteType $route, string $intent = 'context_query', float $confidence = 0.95): void
    {
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->andReturn(new RoutingResult(
                route: $route,
                confidence: $confidence,
                intent: $intent,
                signals: [],
                entities: [],
                routerLatencyMs: 22.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $this->app->instance(HybridRouter::class, $mockRouter);
    }

    public function test_multi_turn_entity_and_date_continuity_across_conversation_turns(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'sales_query');
        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');

        // Turn 1: Establish context for Hasan
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Hasan er sales koto?',
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Hasan এর আজকের মোট সেলস ৳45,000.00।',
        ]);

        // Turn 2: Follow-up query "গতকাল কত ছিল?"
        Http::fake([
            '*analytics/query*' => function ($request) {
                $payload = $request->data();
                // Ensure conversation history was passed to Python analytics engine
                $this->assertNotEmpty($payload['history']);
                $this->assertEquals('user', $payload['history'][0]['role']);
                $this->assertEquals('Hasan er sales koto?', $payload['history'][0]['content']);

                return Http::response([
                    'success'               => true,
                    'intent'                => 'yesterday_sales_hasan',
                    'report'                => "📅 **Hasan এর গতকালের সেলস:** ৳38,000.00",
                    'sql'                   => "SELECT SUM(total_amount) FROM sales WHERE salesperson_id = (SELECT id FROM salespersons WHERE name = 'Hasan') AND order_date = DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)",
                    'rows'                  => [['total' => 38000.0]],
                    'is_security_rejection' => false,
                    'is_ambiguous'          => false,
                    'latency_ms'            => 110.0,
                ], 200);
            },
        ]);

        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'গতকাল কত ছিল?',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('Hasan', $reply);
        $this->assertStringContainsString('৳38,000.00', $reply);
    }

    public function test_domain_switch_from_analytics_to_knowledge_clears_stale_context(): void
    {
        // Prior turn was analytics
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Hasan er sales koto?',
        ]);

        // Turn 2 switches domain to Knowledge FAQ
        $this->mockRouterRoute(RouteType::KNOWLEDGE, 'office_location');

        $category = FAQCategory::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'General',
            'slug'         => 'general-' . uniqid(),
            'is_active'    => true,
        ]);

        $faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'category_id'  => $category->id,
            'question'     => 'আপনাদের অফিস কোথায়?',
            'answer'       => 'আমাদের প্রধান অফিস বনানী, ঢাকা-তে অবস্থিত।',
            'is_active'    => true,
        ]);

        $mockSearch = \Mockery::mock(FAQSearch::class);
        $mockSearch->shouldReceive('search')
            ->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([
                new FAQSearchResult(
                    faq: $faq,
                    keywordScore: 0.90,
                    semanticScore: 0.94,
                    finalScore: 0.94,
                    matchType: 'semantic'
                ),
            ]));

        $this->app->instance(FAQSearch::class, $mockSearch);
        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'আপনাদের অফিস কোথায়?',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('বনানী, ঢাকা', $reply);
        // Ensure no stale Hasan analytics leaks into knowledge FAQ answer
        $this->assertStringNotContainsString('Hasan', $reply);
    }
}
