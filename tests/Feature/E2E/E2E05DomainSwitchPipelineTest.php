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
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2E-05: Domain Switch (Analytics -> Knowledge)
 *
 * Pathway: Turn 1 (Hasan sales -> ANALYTICS) -> Turn 2 (Return policy -> KNOWLEDGE) -> Stale analytics context cleared -> Clean FAQ Answer
 */
class E2E05DomainSwitchPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;
    private FAQ $faq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'E2E Domain Switch Store', 'slug' => 'e2e-domain-switch-store']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Domain Switch Widget',
            'external_id'  => 'e2e_domain_switch_01',
            'access_token' => 'token_domain_switch_01',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'e2e_domain_user',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $category = FAQCategory::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Refunds',
            'slug'         => 'refunds-' . uniqid(),
            'is_active'    => true,
        ]);

        $this->faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'category_id'  => $category->id,
            'question'     => 'Return policy কী?',
            'answer'       => 'পণ্য হাতে পাওয়ার ৭ দিনের মধ্যে অক্ষত অবস্থায় রিটার্ন করতে পারবেন।',
            'is_active'    => true,
        ]);
    }

    public function test_e2e_05_domain_switch_clears_stale_analytics_context_for_knowledge(): void
    {
        // Turn 1 was analytics query about Hasan
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'আজকে Hasan কত বিক্রি করেছে?',
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Hasan এর আজকের মোট সেলস ৳৪৫,০০০.০০।',
        ]);

        // Turn 2 switches domain to Knowledge FAQ
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(
                route: RouteType::KNOWLEDGE,
                confidence: 0.97,
                intent: 'return_policy_query',
                signals: [],
                entities: [],
                routerLatencyMs: 16.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $mockSearch = \Mockery::mock(FAQSearch::class);
        $mockSearch->shouldReceive('search')
            ->once()
            ->andReturn(new Collection([
                new FAQSearchResult(
                    faq: $this->faq,
                    keywordScore: 0.92,
                    semanticScore: 0.95,
                    finalScore: 0.95,
                    matchType: 'hybrid'
                ),
            ]));

        $this->app->instance(HybridRouter::class, $mockRouter);
        $this->app->instance(FAQSearch::class, $mockSearch);

        $service = $this->app->make(CustomerSupportService::class);
        $reply = $service->generateReply(
            conversation: $this->conversation,
            query: 'Return policy কী?',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('৭ দিনের মধ্যে অক্ষত অবস্থায়', $reply);
        // Ensure no stale Hasan analytics context leaked into knowledge FAQ answer
        $this->assertStringNotContainsString('Hasan', $reply);
        $this->assertStringNotContainsString('৪৫,০০০', $reply);
    }
}
