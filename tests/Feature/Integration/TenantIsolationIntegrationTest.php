<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

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

class TenantIsolationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace1;
    private Workspace $workspace2;
    private ChannelAccount $account1;
    private ChannelAccount $account2;
    private Conversation $conv1;
    private Conversation $conv2;
    private CustomerSupportService $supportService;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        // Workspace 1 (e.g. Retailer Alpha)
        $this->workspace1 = Workspace::create([
            'name' => 'Retailer Alpha',
            'slug' => 'retailer-alpha',
        ]);

        $this->account1 = ChannelAccount::create([
            'workspace_id' => $this->workspace1->id,
            'channel_id'   => $channel->id,
            'name'         => 'Alpha Chat Widget',
            'external_id'  => 'web_alpha_01',
            'access_token' => 'token_alpha',
            'is_active'    => true,
        ]);

        $this->conv1 = Conversation::create([
            'channel_account_id' => $this->account1->id,
            'external_user_id'   => 'user_alpha_01',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // Workspace 2 (e.g. Retailer Beta)
        $this->workspace2 = Workspace::create([
            'name' => 'Retailer Beta',
            'slug' => 'retailer-beta',
        ]);

        $this->account2 = ChannelAccount::create([
            'workspace_id' => $this->workspace2->id,
            'channel_id'   => $channel->id,
            'name'         => 'Beta Chat Widget',
            'external_id'  => 'web_beta_01',
            'access_token' => 'token_beta',
            'is_active'    => true,
        ]);

        $this->conv2 = Conversation::create([
            'channel_account_id' => $this->account2->id,
            'external_user_id'   => 'user_beta_01',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->supportService = app(CustomerSupportService::class);
    }

    private function mockRouterRoute(RouteType $route, string $intent = 'cash_collection'): void
    {
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->andReturn(new RoutingResult(
                route: $route,
                confidence: 0.95,
                intent: $intent,
                signals: [],
                entities: [],
                routerLatencyMs: 15.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $this->app->instance(HybridRouter::class, $mockRouter);
    }

    public function test_workspace_1_today_cash_collection_returns_32000_never_combined_52000(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'cash_collection');
        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');

        // Verify Workspace 1 sends workspace_id = 1 strictly to python analytics service
        Http::fake([
            "{$baseUrl}/analytics/query" => function ($request) {
                $payload = $request->data();
                $wsId = $payload['workspace_id'];

                if ($wsId === $this->workspace1->id) {
                    return Http::response([
                        'success'               => true,
                        'intent'                => 'cash_collection',
                        'report'                => "💵 **Workspace 1 Cash Collection:** ৳32,000.00",
                        'sql'                   => "SELECT SUM(amount) FROM collections WHERE payment_method = 'cash' AND workspace_id = 1",
                        'rows'                  => [['total' => 32000.0]],
                        'is_security_rejection' => false,
                        'is_ambiguous'          => false,
                        'latency_ms'            => 80.0,
                    ], 200);
                }

                return Http::response(['success' => false], 400);
            },
        ]);

        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conv1,
            query: 'আজকে cash collection কত?',
            workspaceId: $this->workspace1->id
        );

        $this->assertStringContainsString('৳32,000.00', $reply);
        // Explicitly assert combined total ৳52,000 is NEVER leaked
        $this->assertStringNotContainsString('52,000', $reply);
        $this->assertStringNotContainsString('52000', $reply);
    }

    public function test_workspace_2_today_cash_collection_returns_20000_isolated(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'cash_collection');
        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');

        Http::fake([
            "{$baseUrl}/analytics/query" => function ($request) {
                $payload = $request->data();
                $wsId = $payload['workspace_id'];

                if ($wsId === $this->workspace2->id) {
                    return Http::response([
                        'success'               => true,
                        'intent'                => 'cash_collection',
                        'report'                => "💵 **Workspace 2 Cash Collection:** ৳20,000.00",
                        'sql'                   => "SELECT SUM(amount) FROM collections WHERE payment_method = 'cash' AND workspace_id = 2",
                        'rows'                  => [['total' => 20000.0]],
                        'is_security_rejection' => false,
                        'is_ambiguous'          => false,
                        'latency_ms'            => 80.0,
                    ], 200);
                }

                return Http::response(['success' => false], 400);
            },
        ]);

        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conv2,
            query: 'আজকে cash collection কত?',
            workspaceId: $this->workspace2->id
        );

        $this->assertStringContainsString('৳20,000.00', $reply);
        $this->assertStringNotContainsString('52,000', $reply);
        $this->assertStringNotContainsString('32,000', $reply);
    }

    public function test_workspace_1_cannot_query_or_expose_workspace_2_salesperson_nasir(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'salesperson_query');
        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');

        Http::fake([
            "{$baseUrl}/analytics/query" => function ($request) {
                $payload = $request->data();
                $wsId = $payload['workspace_id'];

                // When Workspace 1 queries for Nasir (who only exists in WS 2), SQL returns 0 rows
                if ($wsId === $this->workspace1->id) {
                    return Http::response([
                        'success'               => true,
                        'intent'                => 'salesperson_query',
                        'report'                => "ℹ️ **তথ্য পাওয়া যায়নি:** আপনার ওয়ার্কস্পেসে 'Nasir' নামের কোনো সেলসপারসনের রেকর্ড পাওয়া যায়নি।",
                        'sql'                   => "SELECT * FROM salespersons WHERE name = 'Nasir' AND workspace_id = 1",
                        'rows'                  => [],
                        'is_security_rejection' => false,
                        'is_ambiguous'          => false,
                        'latency_ms'            => 60.0,
                    ], 200);
                }

                return Http::response(['success' => false], 400);
            },
        ]);

        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conv1,
            query: 'Nasir er sales koto?',
            workspaceId: $this->workspace1->id
        );

        $this->assertStringContainsString('রেকর্ড পাওয়া যায়নি', $reply);
    }
}
