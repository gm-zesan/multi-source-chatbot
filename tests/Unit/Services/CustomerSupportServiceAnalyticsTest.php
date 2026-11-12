<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\AI\Orchestration\CustomerSupportService;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerSupportServiceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_query_dispatches_analytics_with_trusted_workspace(): void
    {
        $ws = Workspace::create(['name' => 'WS 5', 'slug' => 'ws-5']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $user = User::factory()->create(['workspace_id' => $ws->id]);
        $user->assignRole('owner');
        $this->actingAs($user);

        $mockFaqSearch = Mockery::mock(FAQSearch::class);
        $mockConvService = Mockery::mock(ConversationService::class);

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        $mockAnalyticsClient->shouldReceive('query')
            ->once()
            ->with('Aj koto cashin hoise?', $ws->id, Mockery::any())
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
            analyticsClient: $mockAnalyticsClient,
            businessAnalyticsTool: $businessAnalyticsTool,
        );

        $conversation = new Conversation([
            'workspace_id' => $ws->id,
            'metadata' => [
                'role' => 'owner',
                'is_simulator' => true,
            ],
        ]);

        $result = $service->handleQuery(
            query: 'Aj koto cashin hoise?',
            workspaceId: $ws->id,
            conversation: $conversation,
        );

        $this->assertSame('analytics', $result['route']);
        $this->assertEquals(0.95, $result['confidence']);
        $this->assertStringContainsString('৳25,000.00', $result['reply']);
    }
}
