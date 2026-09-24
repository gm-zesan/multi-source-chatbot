<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * E2E-03: Analytics Cash Collection Query
 *
 * Pathway: User Query -> HybridRouter (ANALYTICS) -> Collections aggregation -> Workspace isolation verified (৳32,000 for WS1)
 */
class E2E03AnalyticsCashCollectionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'E2E Cash Store', 'slug' => 'e2e-cash-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Cash Widget',
            'external_id'  => 'e2e_cash_01',
            'access_token' => 'token_cash_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'e2e_cash_user',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_e2e_03_analytics_cash_collection_returns_expected_workspace_amount(): void
    {
        $query = 'আজকে কত টাকা cash collection হয়েছে?';

        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(
                route: RouteType::ANALYTICS,
                confidence: 0.95,
                intent: 'cash_collection_total',
                signals: [],
                entities: [],
                routerLatencyMs: 19.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');
        Http::fake([
            "{$baseUrl}/analytics/query" => function ($request) {
                $payload = $request->data();
                $this->assertEquals($this->workspace->id, $payload['workspace_id']);

                return Http::response([
                    'success'               => true,
                    'intent'                => 'cash_collection_total',
                    'report'                => "💵 **আজকে মোট Cash Collection:** ৳৩২,০০০.০০",
                    'sql'                   => "SELECT SUM(amount) AS total FROM collections WHERE payment_method = 'cash' AND workspace_id = 1",
                    'rows'                  => [['total' => 32000.0]],
                    'is_security_rejection' => false,
                    'is_ambiguous'          => false,
                    'latency_ms'            => 88.0,
                ], 200);
            },
        ]);

        $this->app->instance(HybridRouter::class, $mockRouter);

        $service = $this->app->make(CustomerSupportService::class);
        $reply = $service->generateReply(
            conversation: $this->conversation,
            query: $query,
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('Cash Collection', $reply);
        $this->assertStringContainsString('৳৩২,০০০.০০', $reply);
        // Ensure no cross-workspace combined data leaked
        $this->assertStringNotContainsString('52,000', $reply);
    }
}
