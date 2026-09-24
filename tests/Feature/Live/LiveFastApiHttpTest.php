<?php

declare(strict_types=1);

namespace Tests\Feature\Live;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\AI\Tools\BusinessAnalyticsTool;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Analytics\AnalyticsClient;
use Tests\Feature\Live\Support\BaseLiveTestCase;

/**
 * 5B: Live Laravel -> FastAPI HTTP Socket Integration Tests
 *
 * Verifies real TCP socket communication between Laravel and FastAPI on port 8001 without Http::fake() or mocks.
 */
class LiveFastApiHttpTest extends BaseLiveTestCase
{
    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Live Store', 'slug' => 'live-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Live Widget',
            'external_id'  => 'live_widget_01',
            'access_token' => 'token_live_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'live_user_01',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_live_http_01_analytics_client_socket_query(): void
    {
        $client = app(AnalyticsClient::class);

        $t_start = microtime(true);
        $response = $client->query(
            query: 'আজকের মোট বিক্রি কত?',
            workspaceId: $this->workspace->id
        );
        $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

        $this->assertTrue($response['success'], 'Real FastAPI analytics query should succeed over HTTP socket.');
        $this->assertSame('semantic', $response['engine']);
        $this->assertNotEmpty($response['report']);
        $this->assertNotNull($response['sql']);
        $this->assertStringContainsString('workspace_id', (string) $response['sql']);
        $this->assertGreaterThan(0.0, $response['client_latency_ms']);
    }

    public function test_live_http_02_business_analytics_tool_live_execution(): void
    {
        $tool = app(BusinessAnalyticsTool::class);

        $result = $tool->execute(
            query: 'আজকে মোট কত টাকা ক্যাশ কালেকশন হয়েছে?',
            workspaceId: $this->workspace->id
        );

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['report']);
        $this->assertStringContainsString('Business Analytics', $result['report']);
    }

    public function test_live_http_03_customer_support_service_live_analytics_dispatch(): void
    {
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(
                route: RouteType::ANALYTICS,
                confidence: 0.95,
                intent: 'sales_total_amount',
                signals: [],
                entities: [],
                routerLatencyMs: 15.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $this->app->instance(HybridRouter::class, $mockRouter);

        $service = $this->app->make(CustomerSupportService::class);
        $reply = $service->generateReply(
            conversation: $this->conversation,
            query: 'আজকের বিক্রি কত?',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('Business Analytics', $reply);
    }
}
