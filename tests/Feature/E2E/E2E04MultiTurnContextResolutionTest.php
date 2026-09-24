<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * E2E-04: Multi-Turn Context Resolution
 *
 * Pathway: Turn 1 (Rahim-এর due কত?) -> Contextual State Saved -> Turn 2 (ওটা কবে থেকে?) -> Pronoun/Entity Resolved -> Analytics
 */
class E2E04MultiTurnContextResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'E2E MultiTurn Store', 'slug' => 'e2e-multiturn-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront MultiTurn Widget',
            'external_id'  => 'e2e_multiturn_01',
            'access_token' => 'token_multiturn_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'e2e_multiturn_user',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_e2e_04_multi_turn_pronoun_resolves_correct_entity_and_scope(): void
    {
        // Turn 1: User asks for Rahim's due
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Rahim-এর due কত?',
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Customer Rahim-এর বকেয়া পরিমাণ ৳১৫,৪০০.০০।',
        ]);

        // Turn 2: Follow-up question with pronoun "ওটা কবে থেকে?"
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(
                route: RouteType::ANALYTICS,
                confidence: 0.94,
                intent: 'customer_due_history',
                signals: [],
                entities: ['customer' => 'Rahim'],
                routerLatencyMs: 24.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');
        Http::fake([
            "{$baseUrl}/analytics/query" => function ($request) {
                $payload = $request->data();
                $this->assertNotEmpty($payload['history']);
                $this->assertEquals('user', $payload['history'][0]['role']);
                $this->assertEquals('Rahim-এর due কত?', $payload['history'][0]['content']);

                return Http::response([
                    'success'               => true,
                    'intent'                => 'customer_due_history',
                    'report'                => "📅 **Customer Rahim:** বকেয়া ৳১৫,৪০০.০০ (সর্বশেষ লেনদেন: ১২ আগস্ট, ২০২৬)",
                    'sql'                   => "SELECT due_amount, last_order_date FROM customers WHERE name = 'Rahim' AND workspace_id = 1",
                    'rows'                  => [['due_amount' => 15400.0, 'last_order_date' => '2026-08-12']],
                    'is_security_rejection' => false,
                    'is_ambiguous'          => false,
                    'latency_ms'            => 105.0,
                ], 200);
            },
        ]);

        $this->app->instance(HybridRouter::class, $mockRouter);

        $service = $this->app->make(CustomerSupportService::class);
        $reply = $service->generateReply(
            conversation: $this->conversation,
            query: 'ওটা কবে থেকে?',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('Rahim', $reply);
        $this->assertStringContainsString('৳১৫,৪০০.০০', $reply);
    }
}
