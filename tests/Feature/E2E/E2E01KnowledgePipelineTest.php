<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2E-01: Simple Knowledge Query
 *
 * Pathway: User Question -> HybridRouter (KNOWLEDGE) -> KnowledgeRetrievalTool -> FAQSearch -> SemanticAnswerabilityGate -> Final Grounded Response
 */
class E2E01KnowledgePipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;
    private FAQ $faq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'E2E Knowledge Store', 'slug' => 'e2e-knowledge-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);
        
        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Widget',
            'external_id'  => 'e2e_widget_01',
            'access_token' => 'token_e2e_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'e2e_user_01',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $category = FAQCategory::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Delivery',
            'slug'         => 'delivery-' . uniqid(),
            'is_active'    => true,
        ]);

        $this->faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'category_id'  => $category->id,
            'question'     => 'ডেলিভারি চার্জ কত?',
            'answer'       => 'ঢাকার ভেতরে ডেলিভারি চার্জ ৬০ টাকা এবং ঢাকার বাইরে ১২০ টাকা।',
            'is_active'    => true,
        ]);
    }

    public function test_e2e_01_knowledge_query_executes_grounded_answer(): void
    {
        $query = 'ডেলিভারি চার্জ কত?';

        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(
                route: RouteType::KNOWLEDGE,
                confidence: 0.98,
                intent: 'delivery_charge',
                signals: [],
                entities: [],
                routerLatencyMs: 18.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $mockSearch = \Mockery::mock(FAQSearch::class);
        $mockSearch->shouldReceive('search')
            ->once()
            ->andReturn(new Collection([
                new FAQSearchResult(
                    faq: $this->faq,
                    keywordScore: 0.95,
                    semanticScore: 0.98,
                    finalScore: 0.98,
                    matchType: 'hybrid'
                ),
            ]));

        $this->app->instance(HybridRouter::class, $mockRouter);
        $this->app->instance(FAQSearch::class, $mockSearch);

        $service = $this->app->make(CustomerSupportService::class);
        $reply = $service->generateReply(
            conversation: $this->conversation,
            query: $query,
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('ঢাকার ভেতরে ডেলিভারি চার্জ ৬০ টাকা', $reply);
        $this->assertStringNotContainsString('Analytics Service', $reply);
    }
}
