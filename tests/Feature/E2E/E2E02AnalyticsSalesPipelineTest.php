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
 * E2E-02: Analytics Sales Query
 *
 * Pathway: User Query -> HybridRouter (ANALYTICS) -> BusinessAnalyticsTool -> AnalyticsClient -> Python Semantic Engine -> SQL Execution -> Formatted Report
 */
class E2E02AnalyticsSalesPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'E2E Sales Store', 'slug' => 'e2e-sales-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Sales Widget',
            'external_id'  => 'e2e_sales_01',
            'access_token' => 'token_sales_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'e2e_sales_user',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_e2e_02_analytics_sales_query_executes_and_formats_report(): void
    {
        $query = 'আজকে মোট কত টাকার বিক্রি হয়েছে?';

        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(
                route: RouteType::ANALYTICS,
                confidence: 0.96,
                intent: 'sales_total_amount',
                signals: [],
                entities: [],
                routerLatencyMs: 20.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');
        Http::fake([
            "{$baseUrl}/analytics/query" => function ($request) {
                $payload = $request->data();
                $this->assertEquals($this->workspace->id, $payload['workspace_id']);
                $this->assertEquals('semantic', $payload['engine']);

                return Http::response([
                    'success'               => true,
                    'intent'                => 'sales_total_amount',
                    'report'                => "📊 **আজকের মোট বিক্রি:** ৳৪৫,০০০.০০ (মোট ১২টি অর্ডার সম্পন্ন)",
                    'sql'                   => "SELECT SUM(total_amount) AS total, COUNT(*) AS count FROM sales WHERE workspace_id = 1 AND order_date = CURRENT_DATE",
                    'rows'                  => [['total' => 45000.0, 'count' => 12]],
                    'is_security_rejection' => false,
                    'is_ambiguous'          => false,
                    'latency_ms'            => 112.4,
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
        $this->assertStringContainsString('আজকের মোট বিক্রি', $reply);
        $this->assertStringContainsString('৳৪৫,০০০.০০', $reply);
    }
}
