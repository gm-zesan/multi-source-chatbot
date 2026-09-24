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
 * E2E-09: Downstream Failure Resilience
 *
 * Pathway: Downstream Python Service Outage -> Graceful Error Notification -> No Unhandled 500 Crash -> No Leaked Stack Trace
 */
class E2E09DownstreamFailureResilienceTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'E2E Resilience Store', 'slug' => 'e2e-resilience-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Resilience Widget',
            'external_id'  => 'e2e_resilience_01',
            'access_token' => 'token_resilience_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'e2e_resilience_user',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_e2e_09_analytics_service_downtime_handles_gracefully(): void
    {
        $query = 'আজকে মোট বিক্রি কত?';

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

        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');
        Http::fake([
            "{$baseUrl}/analytics/query" => Http::response(['error' => 'Database connection timeout'], 503),
        ]);

        $this->app->instance(HybridRouter::class, $mockRouter);

        $service = $this->app->make(CustomerSupportService::class);
        $reply = $service->generateReply(
            conversation: $this->conversation,
            query: $query,
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('Analytics Service', $reply);
        $this->assertStringNotContainsString('Database connection timeout', $reply);
        $this->assertStringNotContainsString('Stack trace:', $reply);
    }
}
