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
 * E2E-06: Strict Multi-Tenant Isolation
 *
 * Pathway: Workspace 1 (cash ৳32k) vs Workspace 2 (cash ৳20k) vs Combined DB (৳52k). WS1 never sees ৳52k or WS2's salesperson Nasir.
 */
class E2E06TenantIsolationE2ETest extends TestCase
{
    use RefreshDatabase;

    private Workspace $ws1;
    private Workspace $ws2;
    private Conversation $conv1;
    private Conversation $conv2;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->ws1 = Workspace::create(['name' => 'Tenant 1 Mart', 'slug' => 'tenant-1-mart']);
        $acc1 = ChannelAccount::create([
            'workspace_id' => $this->ws1->id,
            'channel_id'   => $channel->id,
            'name'         => 'T1 Web Widget',
            'external_id'  => 't1_widget',
            'access_token' => 't1_tok',
            'is_active'    => true,
        ]);
        $this->conv1 = Conversation::create([
            'channel_account_id' => $acc1->id,
            'external_user_id'   => 'user_t1',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->ws2 = Workspace::create(['name' => 'Tenant 2 Mart', 'slug' => 'tenant-2-mart']);
        $acc2 = ChannelAccount::create([
            'workspace_id' => $this->ws2->id,
            'channel_id'   => $channel->id,
            'name'         => 'T2 Web Widget',
            'external_id'  => 't2_widget',
            'access_token' => 't2_tok',
            'is_active'    => true,
        ]);
        $this->conv2 = Conversation::create([
            'channel_account_id' => $acc2->id,
            'external_user_id'   => 'user_t2',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_e2e_06_tenant_isolation_prevents_cross_workspace_aggregation_and_records(): void
    {
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->andReturn(new RoutingResult(
                route: RouteType::ANALYTICS,
                confidence: 0.96,
                intent: 'tenant_analytics',
                signals: [],
                entities: [],
                routerLatencyMs: 14.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');
        Http::fake([
            "{$baseUrl}/analytics/query" => function ($request) {
                $payload = $request->data();
                $wsId = $payload['workspace_id'];

                if ($wsId === $this->ws1->id) {
                    return Http::response([
                        'success'               => true,
                        'intent'                => 'cash_collection_total',
                        'report'                => "💵 **Workspace 1 Cash Total:** ৳32,000.00",
                        'sql'                   => "SELECT SUM(amount) FROM collections WHERE workspace_id = 1",
                        'rows'                  => [['total' => 32000.0]],
                        'is_security_rejection' => false,
                        'is_ambiguous'          => false,
                        'latency_ms'            => 75.0,
                    ], 200);
                }

                if ($wsId === $this->ws2->id) {
                    return Http::response([
                        'success'               => true,
                        'intent'                => 'cash_collection_total',
                        'report'                => "💵 **Workspace 2 Cash Total:** ৳20,000.00",
                        'sql'                   => "SELECT SUM(amount) FROM collections WHERE workspace_id = 2",
                        'rows'                  => [['total' => 20000.0]],
                        'is_security_rejection' => false,
                        'is_ambiguous'          => false,
                        'latency_ms'            => 75.0,
                    ], 200);
                }

                return Http::response(['success' => false], 400);
            },
        ]);

        $this->app->instance(HybridRouter::class, $mockRouter);
        $service = $this->app->make(CustomerSupportService::class);

        // Turn 1: Workspace 1 Query
        $reply1 = $service->generateReply(
            conversation: $this->conv1,
            query: 'Show me today\'s cash collection',
            workspaceId: $this->ws1->id
        );

        $this->assertStringContainsString('৳32,000.00', $reply1);
        $this->assertStringNotContainsString('52,000', $reply1);
        $this->assertStringNotContainsString('20,000', $reply1);

        // Turn 2: Workspace 2 Query
        $reply2 = $service->generateReply(
            conversation: $this->conv2,
            query: 'Show me today\'s cash collection',
            workspaceId: $this->ws2->id
        );

        $this->assertStringContainsString('৳20,000.00', $reply2);
        $this->assertStringNotContainsString('52,000', $reply2);
        $this->assertStringNotContainsString('32,000', $reply2);
    }
}
