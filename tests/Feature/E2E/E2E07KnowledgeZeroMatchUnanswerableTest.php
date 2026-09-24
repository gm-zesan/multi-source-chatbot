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
use App\Services\FAQ\FAQSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2E-07: Knowledge Zero Match / Unanswerable
 *
 * Pathway: Valid In-Domain Query -> HybridRouter (KNOWLEDGE) -> Empty Retrieval -> SemanticAnswerabilityGate -> Deterministic Fallback (NO Hallucination)
 */
class E2E07KnowledgeZeroMatchUnanswerableTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'E2E Zero Match Store', 'slug' => 'e2e-zero-match-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Zero Match Widget',
            'external_id'  => 'e2e_zero_01',
            'access_token' => 'token_zero_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'e2e_zero_user',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_e2e_07_zero_match_knowledge_query_rejects_and_prevents_hallucination(): void
    {
        $query = 'আপনাদের কি মঙ্গল গ্রহে ফ্রি হোম ডেলিভারি সুবিধা আছে?';

        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(
                route: RouteType::KNOWLEDGE,
                confidence: 0.90,
                intent: 'unsupported_delivery_inquiry',
                signals: [],
                entities: [],
                routerLatencyMs: 20.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $mockSearch = \Mockery::mock(FAQSearch::class);
        $mockSearch->shouldReceive('search')
            ->once()
            ->andReturn(new Collection([])); // Zero matches found in vector DB

        $this->app->instance(HybridRouter::class, $mockRouter);
        $this->app->instance(FAQSearch::class, $mockSearch);

        $service = $this->app->make(CustomerSupportService::class);
        $reply = $service->generateReply(
            conversation: $this->conversation,
            query: $query,
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        // Ensure no fabricated positive response is generated
        $this->assertStringNotContainsString('হ্যাঁ, মঙ্গল গ্রহে ডেলিভারি করা হয়', $reply);
        $this->assertStringContainsString('আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়', $reply);
    }
}
