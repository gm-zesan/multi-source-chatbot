<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\Conversation;
use App\Services\AI\CustomerSupportService;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use Mockery;
use Tests\TestCase;

class CustomerSupportServiceAnalyticsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_query_dispatches_analytics_with_trusted_workspace(): void
    {
        $mockFaqSearch = Mockery::mock(FAQSearch::class);
        $mockFaqSearch->shouldReceive('getLastTelemetry')->andReturn([]);
        $mockConvService = Mockery::mock(ConversationService::class);

        $mockRouter = Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->with('Aj koto cashin hoise?', Mockery::any(), 5)
            ->andReturn(new RoutingResult(
                route: RouteType::ANALYTICS,
                confidence: 0.95,
                intent: 'cash_collection',
            ));

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        $mockAnalyticsClient->shouldReceive('query')
            ->once()
            ->with('Aj koto cashin hoise?', 5, Mockery::any())
            ->andReturn([
                'success'    => true,
                'intent'     => 'cash_collection',
                'report'     => "📊 **Business Analytics Summary**\n- **Total Cash Collected:** ৳25,000.00",
                'latency_ms' => 45.2,
            ]);

        $businessAnalyticsTool = new \App\AI\Tools\BusinessAnalyticsTool($mockAnalyticsClient);

        $service = new CustomerSupportService(
            faqSearch: $mockFaqSearch,
            conversationService: $mockConvService,
            router: $mockRouter,
            analyticsClient: $mockAnalyticsClient,
            businessAnalyticsTool: $businessAnalyticsTool,
        );

        $result = $service->handleQuery(
            query: 'Aj koto cashin hoise?',
            workspaceId: 5,
        );

        $this->assertSame('analytics', $result['route']);
        $this->assertEquals(0.95, $result['confidence']);
        $this->assertStringContainsString('৳25,000.00', $result['reply']);
    }
}
