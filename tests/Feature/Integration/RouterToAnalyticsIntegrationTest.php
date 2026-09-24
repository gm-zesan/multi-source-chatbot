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
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouterToAnalyticsIntegrationTest extends TestCase
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
            'name' => 'Integration Retailers Ltd',
            'slug' => 'integration-retailers',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        $this->channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Widget',
            'external_id'  => 'web_int_analytics_001',
            'access_token' => 'token_int_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_int_analytics',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->supportService = app(CustomerSupportService::class);
    }

    /**
     * Helper to mock Python analytics service endpoint responses.
     */
    private function mockAnalyticsEndpoint(array $responseData, int $status = 200): void
    {
        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');
        Http::fake([
            "{$baseUrl}/analytics/query" => Http::response($responseData, $status),
        ]);
    }

    /**
     * Helper to mock the LLM router output.
     */
    private function mockRouterRoute(RouteType $route, string $intent = 'business_analytics', float $confidence = 0.95): void
    {
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->andReturn(new RoutingResult(
                route: $route,
                confidence: $confidence,
                intent: $intent,
                signals: [],
                entities: [],
                routerLatencyMs: 25.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $this->app->instance(HybridRouter::class, $mockRouter);
        $this->supportService = $this->app->make(CustomerSupportService::class);
    }

    public function test_simple_sales_query_routes_to_analytics_and_executes_business_analytics_tool(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'sales_query');
        $this->mockAnalyticsEndpoint([
            'success'               => true,
            'intent'                => 'sales_total',
            'report'                => "📊 **আজকের মোট বিক্রি:** ৳45,000.00 (মোট অর্ডার: 12 টি)",
            'sql'                   => "SELECT SUM(total_amount) AS total FROM sales WHERE workspace_id = 1 AND order_date = CURRENT_DATE",
            'rows'                  => [['total' => 45000.0]],
            'is_security_rejection' => false,
            'is_ambiguous'          => false,
            'latency_ms'            => 120.5,
        ]);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'আজকের মোট বিক্রি কত?',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('আজকের মোট বিক্রি', $reply);
        $this->assertStringContainsString('৳45,000.00', $reply);
    }

    public function test_cash_collection_query_routes_and_returns_collection_report(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'cash_collection');
        $this->mockAnalyticsEndpoint([
            'success'               => true,
            'intent'                => 'cash_collection',
            'report'                => "💵 **আজকে মোট Cash Collection:** ৳32,000.00",
            'sql'                   => "SELECT SUM(amount) AS total FROM collections WHERE payment_method = 'cash' AND workspace_id = 1",
            'rows'                  => [['total' => 32000.0]],
            'is_security_rejection' => false,
            'is_ambiguous'          => false,
            'latency_ms'            => 95.0,
        ]);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'আজকে cash collection কত?',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('Cash Collection', $reply);
        $this->assertStringContainsString('৳32,000.00', $reply);
    }

    public function test_customer_due_query_routes_to_analytics_with_customer_intent(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'customer_due');
        $this->mockAnalyticsEndpoint([
            'success'               => true,
            'intent'                => 'customer_due',
            'report'                => "👤 **Customer Due Report:**\n- Customer: Rahim\n- Outstanding Due: ৳15,400.00",
            'sql'                   => "SELECT due_amount FROM customers WHERE name LIKE '%Rahim%' AND workspace_id = 1",
            'rows'                  => [['customer' => 'Rahim', 'due' => 15400.0]],
            'is_security_rejection' => false,
            'is_ambiguous'          => false,
            'latency_ms'            => 110.0,
        ]);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Rahim er koto taka baki?',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('Rahim', $reply);
        $this->assertStringContainsString('৳15,400.00', $reply);
    }

    public function test_top_salesperson_query_routes_to_analytics_with_ranking_intent(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'top_salesperson');
        $this->mockAnalyticsEndpoint([
            'success'               => true,
            'intent'                => 'top_salesperson',
            'report'                => "🏆 **Top Salesperson:** Hasan (Total Sales: ৳125,000.00)",
            'sql'                   => "SELECT sp.name, SUM(s.total_amount) as total FROM sales s JOIN salespersons sp ON s.salesperson_id = sp.id WHERE s.workspace_id = 1 GROUP BY sp.name ORDER BY total DESC LIMIT 1",
            'rows'                  => [['name' => 'Hasan', 'total' => 125000.0]],
            'is_security_rejection' => false,
            'is_ambiguous'          => false,
            'latency_ms'            => 140.0,
        ]);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Top salesperson ke?',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('Top Salesperson', $reply);
        $this->assertStringContainsString('Hasan', $reply);
    }

    public function test_product_revenue_query_routes_to_analytics_with_product_filter(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'product_revenue');
        $this->mockAnalyticsEndpoint([
            'success'               => true,
            'intent'                => 'product_revenue',
            'report'                => "💻 **Product Revenue:** Laptop Pro 15 (Total: ৳450,000.00, Units: 5)",
            'sql'                   => "SELECT p.name, SUM(oi.subtotal) as revenue FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.name LIKE '%Laptop Pro 15%' AND oi.workspace_id = 1 GROUP BY p.name",
            'rows'                  => [['name' => 'Laptop Pro 15', 'revenue' => 450000.0, 'units' => 5]],
            'is_security_rejection' => false,
            'is_ambiguous'          => false,
            'latency_ms'            => 130.0,
        ]);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Laptop Pro 15 er revenue koto?',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('Laptop Pro 15', $reply);
        $this->assertStringContainsString('৳450,000.00', $reply);
    }

    public function test_date_interval_query_routes_to_analytics_with_temporal_filter(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'temporal_sales');
        $this->mockAnalyticsEndpoint([
            'success'               => true,
            'intent'                => 'temporal_sales',
            'report'                => "📅 **Last 7 Days Sales Summary:** ৳280,000.00 across 48 orders",
            'sql'                   => "SELECT SUM(total_amount) as total, COUNT(*) as count FROM sales WHERE order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY) AND workspace_id = 1",
            'rows'                  => [['total' => 280000.0, 'count' => 48]],
            'is_security_rejection' => false,
            'is_ambiguous'          => false,
            'latency_ms'            => 115.0,
        ]);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Last 7 days er total sales koto?',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('Last 7 Days Sales Summary', $reply);
        $this->assertStringContainsString('৳280,000.00', $reply);
    }

    public function test_grouped_analytics_summary_routes_to_analytics_and_formats_table(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'grouped_sales_summary');
        $this->mockAnalyticsEndpoint([
            'success'               => true,
            'intent'                => 'grouped_sales_summary',
            'report'                => "| Salesperson | Total Sales | Orders |\n| :--- | :--- | :--- |\n| Hasan | ৳125,000.00 | 25 |\n| Rakib | ৳98,000.00 | 18 |",
            'sql'                   => "SELECT sp.name, SUM(s.total_amount) as total, COUNT(s.id) as orders FROM sales s JOIN salespersons sp ON s.salesperson_id = sp.id WHERE s.workspace_id = 1 GROUP BY sp.name",
            'rows'                  => [
                ['salesperson' => 'Hasan', 'total' => 125000.0, 'orders' => 25],
                ['salesperson' => 'Rakib', 'total' => 98000.0, 'orders' => 18],
            ],
            'is_security_rejection' => false,
            'is_ambiguous'          => false,
            'latency_ms'            => 155.0,
        ]);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Salesperson wise sales summary dekhao',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('Salesperson', $reply);
        $this->assertStringContainsString('Hasan', $reply);
        $this->assertStringContainsString('Rakib', $reply);
    }

    public function test_analytics_query_does_not_accidentally_fallback_to_knowledge_or_chat(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'sales_query');
        $this->mockAnalyticsEndpoint([
            'success'               => true,
            'intent'                => 'sales_total',
            'report'                => "📊 **মোট বিক্রি:** ৳50,000.00",
            'sql'                   => "SELECT SUM(total_amount) FROM sales WHERE workspace_id = 1",
            'rows'                  => [['total' => 50000.0]],
            'is_security_rejection' => false,
            'is_ambiguous'          => false,
            'latency_ms'            => 100.0,
        ]);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'আজকের বিক্রি কত?',
            workspaceId: $this->workspace->id
        );

        // Ensure no chat pleasantries or knowledge FAQ fallback notices are present
        $this->assertStringNotContainsString('দুঃখিত, এই বিষয়টি আমাদের কাস্টমার সাপোর্ট নলেজ বেসের', $reply);
        $this->assertStringNotContainsString('নলেজ বেস থেকে কোনো তথ্য পাওয়া যায়নি', $reply);
        $this->assertStringContainsString('মোট বিক্রি', $reply);
    }
}
