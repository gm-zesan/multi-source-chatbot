<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\AI\Tools\ExcelAnalyticsTool;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\FAQ\FAQSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DownstreamFailureSafetyIntegrationTest extends TestCase
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
            'name' => 'Resilience Store',
            'slug' => 'resilience-store',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        $this->channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Widget Safety',
            'external_id'  => 'web_int_safety_001',
            'access_token' => 'token_int_safety_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_int_safety',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->supportService = app(CustomerSupportService::class);
    }

    private function mockRouterRoute(RouteType $route, string $intent = 'failure_test'): void
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

    public function test_downstream_analytics_service_unavailable_handled_gracefully(): void
    {
        $this->mockRouterRoute(RouteType::ANALYTICS, 'sales_query');
        $baseUrl = rtrim(config('analytics.base_url', 'http://127.0.0.1:8001'), '/');

        // Simulate connection refused / 500 server error
        Http::fake([
            "{$baseUrl}/analytics/query" => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'আজকের বিক্রি কত?',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        // Ensure user-friendly service error notice is returned
        $this->assertStringContainsString('Analytics Service', $reply);
        // Ensure no raw exception stack trace is leaked
        $this->assertStringNotContainsString('Stack trace:', $reply);
        $this->assertStringNotContainsString('Illuminate\\Http\\Client', $reply);
    }

    public function test_downstream_faq_retrieval_failure_handled_gracefully(): void
    {
        $this->mockRouterRoute(RouteType::KNOWLEDGE, 'return_policy');

        $mockSearch = \Mockery::mock(FAQSearch::class);
        $mockSearch->shouldReceive('search')
            ->andThrow(new \RuntimeException('Typesense connection timeout on port 8108'));

        $this->app->instance(FAQSearch::class, $mockSearch);
        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'আপনাদের রিটার্ন পলিসি কি?',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        // Verify graceful fallback without crashing application
        $this->assertStringNotContainsString('Typesense connection timeout', $reply);
    }

    public function test_excel_analytics_tool_rejects_path_traversal_and_malformed_input(): void
    {
        $excelTool = app(ExcelAnalyticsTool::class);

        $result = $excelTool->execute(
            question: 'Show all users',
            workspaceId: $this->workspace->id,
            fileId: '../../../../etc/passwd'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Excel Query Error', $result['report']);
    }
}
