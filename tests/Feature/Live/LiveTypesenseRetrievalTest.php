<?php

declare(strict_types=1);

namespace Tests\Feature\Live;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Retrieval\RetrievalClient;
use Tests\Feature\Live\Support\BaseLiveTestCase;

/**
 * 5C: Live Knowledge & Typesense Vector Retrieval Integration Tests
 *
 * Verifies real TCP socket hybrid retrieval: Laravel -> FastAPI (:8001/api/v1/search) -> Typesense (:8108).
 */
class LiveTypesenseRetrievalTest extends BaseLiveTestCase
{
    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Live Knowledge Store', 'slug' => 'live-knowledge-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Live Knowledge Widget',
            'external_id'  => 'live_knw_01',
            'access_token' => 'token_knw_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'live_knw_user_01',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_live_ret_01_retrieval_client_queries_fastapi_and_typesense(): void
    {
        $client = app(RetrievalClient::class);

        $results = $client->search(
            query: 'ডেলিভারি চার্জ কত?',
            workspaceId: null,
            topK: 5
        );

        $this->assertNotEmpty($results, 'Typesense live search should return indexed delivery charge FAQs.');
        
        $topHit = $results->first();
        $this->assertNotNull($topHit);
        $this->assertGreaterThan(0.0, $topHit->finalScore);
        
        $telemetry = $client->getLastTelemetry();
        $this->assertNotEmpty($telemetry);
        $this->assertArrayHasKey('total_retrieval_latency_ms', $telemetry);
        $this->assertGreaterThan(0.0, (float) $telemetry['total_retrieval_latency_ms']);
    }

    public function test_live_ret_02_knowledge_retrieval_tool_live_search(): void
    {
        $tool = app(KnowledgeRetrievalTool::class);

        $results = $tool->execute(
            query: 'ডেলিভারি চার্জ কত?',
            workspaceId: null,
            perPage: 5
        );

        $this->assertNotEmpty($results);
        $this->assertGreaterThan(0, $results->count());
    }

    public function test_live_ret_03_zero_match_out_of_domain_query_handled_by_answerability_gate(): void
    {
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(
                route: RouteType::KNOWLEDGE,
                confidence: 0.88,
                intent: 'unsupported_quantum_query',
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
            query: 'How to build a time machine using quantum particles?',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        // Ensure low-confidence / ungrounded result is rejected safely
        $this->assertStringContainsString('আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়', $reply);
        $this->assertStringNotContainsString('time machine', $reply);
    }
}
