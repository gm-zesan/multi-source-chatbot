<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\AI\Agents\KnowledgeSupportAgent;
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
use App\Services\AI\SemanticAnswerabilityGate;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouterToKnowledgeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $channelAccount;
    private Conversation $conversation;
    private CustomerSupportService $supportService;
    private FAQCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Knowledge Test Store',
            'slug' => 'knowledge-store',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        $this->channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Widget Knowledge',
            'external_id'  => 'web_int_knowledge_001',
            'access_token' => 'token_int_knw_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_int_knowledge',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->category = FAQCategory::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Policies',
            'slug'         => 'policies',
            'is_active'    => true,
        ]);

        $this->supportService = app(CustomerSupportService::class);
    }

    private function mockRouterRoute(RouteType $route, string $intent = 'knowledge_faq', float $confidence = 0.95): void
    {
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->andReturn(new RoutingResult(
                route: $route,
                confidence: $confidence,
                intent: $intent,
                signals: [],
                entities: [],
                routerLatencyMs: 20.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $this->app->instance(HybridRouter::class, $mockRouter);
    }

    public function test_valid_faq_query_routes_to_knowledge_and_passes_answerability_gate(): void
    {
        $this->mockRouterRoute(RouteType::KNOWLEDGE, 'return_policy_inquiry');

        $faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'category_id'  => $this->category->id,
            'question'     => 'আপনাদের রিটার্ন পলিসি কি?',
            'answer'       => 'পণ্য হাতে পাওয়ার ৭ দিনের মধ্যে অক্ষত অবস্থায় রিটার্ন করতে পারবেন।',
            'is_active'    => true,
        ]);

        $mockSearch = \Mockery::mock(FAQSearch::class);
        $mockSearch->shouldReceive('search')
            ->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([
                new FAQSearchResult(
                    faq: $faq,
                    keywordScore: 0.90,
                    semanticScore: 0.92,
                    finalScore: 0.92,
                    matchType: 'semantic'
                ),
            ]));

        $this->app->instance(FAQSearch::class, $mockSearch);
        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'আপনাদের রিটার্ন পলিসি কি?',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('৭ দিনের মধ্যে', $reply);
    }

    public function test_out_of_domain_query_routes_to_ood_with_deterministic_rejection(): void
    {
        $this->mockRouterRoute(RouteType::OOD, 'out_of_domain');
        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'How to repair a Boeing 747 aircraft engine?',
            workspaceId: $this->workspace->id
        );

        $this->assertStringContainsString('আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়', $reply);
    }

    public function test_zero_match_knowledge_query_triggers_unanswerable_fallback_without_hallucination(): void
    {
        $this->mockRouterRoute(RouteType::KNOWLEDGE, 'unknown_policy');

        $mockSearch = \Mockery::mock(FAQSearch::class);
        $mockSearch->shouldReceive('search')
            ->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([])); // Empty retrieval result

        $this->app->instance(FAQSearch::class, $mockSearch);
        $this->supportService = $this->app->make(CustomerSupportService::class);

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'আপনাদের কি মঙ্গল গ্রহে ডেলিভারি সুবিধা আছে?',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        // Ensure no fabricated affirmative answer
        $this->assertStringNotContainsString('হ্যাঁ, মঙ্গল গ্রহে ডেলিভারি করা হয়', $reply);
    }
}
