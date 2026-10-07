<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\AI\DTOs\FollowUpDecision;
use App\Services\AI\FollowUpPolicyManager;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use App\Services\Memory\ConversationMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SelectiveFollowUpIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $channelAccount;
    private Conversation $conversation;
    private FAQCategory $category;
    private $routerMock;
    private $faqSearchMock;
    private CustomerSupportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Selective FollowUp Store', 'slug' => 'selective-store']);
        $channel = \App\Models\Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $this->channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Channel',
            'external_id'  => 'acc_selective_store',
            'access_token' => 'tok_selective_store',
            'is_active'    => true,
        ]);
        $this->conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_followup_integration',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);
        $this->category = FAQCategory::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Products',
            'slug'         => 'products',
        ]);

        $this->routerMock = Mockery::mock(HybridRouter::class);
        $this->faqSearchMock = Mockery::mock(FAQSearch::class);
        $this->faqSearchMock->shouldReceive('getLastTelemetry')->andReturn([]);

        $this->app->instance(HybridRouter::class, $this->routerMock);
        $this->app->instance(FAQSearch::class, $this->faqSearchMock);

        $this->service = new CustomerSupportService(
            faqSearch: $this->faqSearchMock,
            conversationService: app(ConversationService::class),
            router: $this->routerMock,
            memoryService: app(ConversationMemoryService::class),
        );
    }

    private function createHit(FAQ $faq, float $score = 0.92): \Illuminate\Database\Eloquent\Collection
    {
        $hit = new FAQSearchResult(
            faq: $faq,
            keywordScore: $score,
            semanticScore: $score,
            finalScore: $score,
            matchType: 'hybrid',
        );
        return new \Illuminate\Database\Eloquent\Collection([$hit]);
    }

    /**
     * 1. Product price query -> Answer + selective purchase_interest CTA.
     */
    public function test_product_price_query_generates_answer_and_selective_cta(): void
    {
        $faq = FAQ::create([
            'workspace_id'    => $this->workspace->id,
            'faq_category_id' => $this->category->id,
            'question'        => 'Royal Silk Panjabi দাম কত?',
            'answer'          => 'Royal Silk Panjabi ৩,৫০০ টাকা।',
            'is_published'    => true,
        ]);

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'product_price'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHit($faq));

        // When LLM agent is called via DeepSeek/OpenRouter in testing fallback, it returns the structured response
        $result = $this->service->handleQuery('Royal Silk Panjabi দাম কত?', $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertStringContainsString('Royal Silk Panjabi', $result['reply']);
        // Assert that policy manager evaluated and state is updated cleanly
        $this->assertArrayHasKey('follow_up_decision', $result);
    }

    /**
     * 2. Availability query -> Answer + purchase_interest CTA.
     */
    public function test_product_availability_query_allows_purchase_cta(): void
    {
        $policyManager = new FollowUpPolicyManager();
        $mockAnswerability = new class { public function isConfident(): bool { return true; } };

        $decision = $policyManager->evaluate(
            rawAnswer: 'জি, প্রিমিয়াম ব্লু শার্ট বর্তমানে M এবং L সাইজে স্টকে এভেইলেবল আছে।',
            proposedFollowUp: 'আপনি কি এটি নিতে চাচ্ছেন?',
            followUpType: 'purchase_interest',
            route: RouteType::KNOWLEDGE,
            userQuery: 'প্রিমিয়াম ব্লু শার্ট কি এভেইলেবল আছে?',
            conversation: $this->conversation,
            answerabilityDecision: $mockAnswerability,
        );

        $this->assertTrue($decision->isAllowed());
        $this->assertSame("জি, প্রিমিয়াম ব্লু শার্ট বর্তমানে M এবং L সাইজে স্টকে এভেইলেবল আছে।\n\nআপনি কি এটি নিতে চাচ্ছেন?", $decision->finalReply());
    }

    /**
     * 3. Delivery query -> Answer + delivery follow-up.
     */
    public function test_delivery_query_allows_delivery_follow_up(): void
    {
        $policyManager = new FollowUpPolicyManager();

        $decision = $policyManager->evaluate(
            rawAnswer: 'ঢাকার ভেতরে ডেলিভারি চার্জ ৬০ টাকা এবং ঢাকার বাইরে ১২০ টাকা। সাধারণত ২-৩ কার্যদিবসে ডেলিভারি সম্পন্ন হয়।',
            proposedFollowUp: 'আপনার ডেলিভারি ঠিকানা কোথায়?',
            followUpType: 'delivery',
            route: RouteType::CHAT,
            userQuery: 'ডেলিভারি চার্জ কত?',
            conversation: $this->conversation,
        );

        $this->assertTrue($decision->isAllowed());
        $this->assertSame('delivery', $decision->followUpType);
        $this->assertSame('আপনার ডেলিভারি ঠিকানা কোথায়?', $decision->followUpText);
    }

    /**
     * 4. Store hours query -> Answer only (sales CTA suppressed).
     */
    public function test_store_hours_query_suppresses_sales_cta(): void
    {
        $policyManager = new FollowUpPolicyManager();

        $decision = $policyManager->evaluate(
            rawAnswer: 'আমাদের ধানমন্ডি আউটলেট প্রতিদিন সকাল ১০টা থেকে রাত ৮টা পর্যন্ত খোলা থাকে।',
            proposedFollowUp: 'আপনি কি কোনো প্রোডাক্ট কিনতে চাচ্ছেন?',
            followUpType: 'purchase_interest',
            route: RouteType::KNOWLEDGE,
            userQuery: 'দোকান কয়টায় বন্ধ হয়?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('general_info_non_cta', $decision->suppressionReason);
        $this->assertSame('আমাদের ধানমন্ডি আউটলেট প্রতিদিন সকাল ১০টা থেকে রাত ৮টা পর্যন্ত খোলা থাকে।', $decision->finalReply());
    }

    /**
     * 5. Complaint query -> Support answer only (CTA suppressed).
     */
    public function test_complaint_query_suppresses_cta(): void
    {
        $policyManager = new FollowUpPolicyManager();

        $decision = $policyManager->evaluate(
            rawAnswer: 'আমরা অত্যন্ত দুঃখিত। অনুগ্রহ করে আপনার ত্রুটিপূর্ণ পণ্যের ছবি পাঠান, আমরা দ্রুত এক্সচেঞ্জ করে দেব।',
            proposedFollowUp: 'আপনি কি অন্য কোনো পণ্য কিনতে চান?',
            followUpType: 'purchase_interest',
            route: RouteType::CHAT,
            userQuery: 'আপনারা আমাকে ভাঙা ও নষ্ট প্রোডাক্ট পাঠিয়েছেন!',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('complaint_or_dispute_guard', $decision->suppressionReason);
        $this->assertStringNotContainsString('কিনতে চান', $decision->finalReply());
    }

    /**
     * 6. Refund query -> Support answer only (CTA suppressed).
     */
    public function test_refund_query_suppresses_cta(): void
    {
        $policyManager = new FollowUpPolicyManager();

        $decision = $policyManager->evaluate(
            rawAnswer: 'রিফান্ড রিকোয়েস্ট অনুমোদনের পর ৩-৫ কার্যদিবসের মধ্যে টাকা ফেরত দেয়া হয়।',
            proposedFollowUp: 'আপনি কি নতুন কোনো শার্ট অর্ডার করতে চান?',
            followUpType: 'purchase_interest',
            route: RouteType::KNOWLEDGE,
            userQuery: 'আমার রিফান্ডের টাকা ফেরত পাইনি',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('complaint_or_dispute_guard', $decision->suppressionReason);
        $this->assertSame('রিফান্ড রিকোয়েস্ট অনুমোদনের পর ৩-৫ কার্যদিবসের মধ্যে টাকা ফেরত দেয়া হয়।', $decision->finalReply());
    }

    /**
     * 7. OOD query -> Out-of-domain message only, no CTA.
     */
    public function test_ood_query_has_no_cta(): void
    {
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::OOD, 0.98, 'general_politics'));

        $this->faqSearchMock->shouldNotReceive('search');

        $result = $this->service->handleQuery('আজকে কি নির্বাচন?', $this->workspace->id, $this->conversation);

        $this->assertSame('ood', $result['route']);
        $this->assertStringContainsString('কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়', $result['reply']);
        $this->assertNull($result['follow_up_decision'] ?? null);
    }

    /**
     * 8. UNCERTAIN query -> Clarification message only.
     */
    public function test_uncertain_query_generates_clarification_only(): void
    {
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::UNCERTAIN, 0.50, 'ambiguous_product'));

        $this->faqSearchMock->shouldReceive('search')
            ->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $result = $this->service->handleQuery('পাঞ্জাবিটা কেমন?', $this->workspace->id, $this->conversation);

        $this->assertSame('uncertain', $result['route']);
        $this->assertNull($result['follow_up_decision'] ?? null);
    }

    /**
     * 9. Repeated CTA -> Suppressed on immediately subsequent turn.
     */
    public function test_repeated_cta_is_suppressed(): void
    {
        $policyManager = new FollowUpPolicyManager();

        // Simulate Turn 1 where CTA was allowed and recorded
        $decision1 = new FollowUpDecision(
            allowed: true,
            answer: 'রয়েল পাঞ্জাবির দাম ৩,৫০০ টাকা।',
            followUpText: 'আপনি কি এটি নিতে চাচ্ছেন?',
            followUpType: 'purchase_interest',
        );
        $policyManager->recordFollowUpState($this->conversation, $decision1);

        // Turn 2: LLM proposes the exact same CTA type immediately
        $decision2 = $policyManager->evaluate(
            rawAnswer: 'আমাদের কাছে লাল এবং নীল দুটি রঙ আছে।',
            proposedFollowUp: 'আপনি কি এটি নিতে চাচ্ছেন?',
            followUpType: 'purchase_interest',
            route: RouteType::KNOWLEDGE,
            userQuery: 'কি কি রঙ আছে?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision2->isAllowed());
        $this->assertSame('repetition_cooldown_active', $decision2->suppressionReason);
        $this->assertSame('আমাদের কাছে লাল এবং নীল দুটি রঙ আছে।', $decision2->finalReply());
    }

    /**
     * 10. User changes topic -> Stale CTA does not contaminate new response.
     */
    public function test_user_changes_topic_allows_different_context_follow_up(): void
    {
        $policyManager = new FollowUpPolicyManager();

        // Expire previous state when topic changes
        $this->conversation->metadata = [
            'follow_up_state' => [
                'last_type' => 'purchase_interest',
                'last_text' => 'আপনি কি এটি নিতে চাচ্ছেন?',
                'status'    => 'expired',
            ],
        ];
        $this->conversation->save();

        // User asks about delivery (different topic and type)
        $decision = $policyManager->evaluate(
            rawAnswer: 'আমরা সুন্দরবন কুরিয়ারের মাধ্যমে সারাদেশে ডেলিভারি করি।',
            proposedFollowUp: 'আপনার জেলা বা এলাকাটি জানাবেন?',
            followUpType: 'delivery',
            route: RouteType::CHAT,
            userQuery: 'ডেলিভারি কিভাবে করেন?',
            conversation: $this->conversation,
        );

        $this->assertTrue($decision->isAllowed());
        $this->assertSame('delivery', $decision->followUpType);
    }

    /**
     * 11. User says "না" -> No immediate repeated CTA.
     */
    public function test_user_says_no_suppresses_further_sales_cta(): void
    {
        $policyManager = new FollowUpPolicyManager();

        $decision = $policyManager->evaluate(
            rawAnswer: 'ঠিক আছে, অন্য কোনো তথ্য জানতে চাইলে বলুন।',
            proposedFollowUp: 'আপনি কি কোনো ডিসকাউন্ট কুপন চান?',
            followUpType: 'purchase_interest',
            route: RouteType::CHAT,
            userQuery: 'না, লাগবে না',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('user_denial_guard', $decision->suppressionReason);
        $this->assertSame('ঠিক আছে, অন্য কোনো তথ্য জানতে চাইলে বলুন।', $decision->finalReply());
    }

    /**
     * 12. User says "হ্যাঁ" / affirmation -> NO order mutation (ACTION remains strictly deferred).
     */
    public function test_user_affirmation_does_not_mutate_database_or_create_order(): void
    {
        $policyManager = new FollowUpPolicyManager();

        // Inbound affirmation: "হ্যাঁ, আমি নিতে চাই"
        $decision = $policyManager->evaluate(
            rawAnswer: 'ধন্যবাদ আপনার আগ্রহের জন্য! আমাদের শপ পেইজ বা হটলাইনে যোগাযোগ করে অর্ডার কনফার্ম করতে পারেন।',
            proposedFollowUp: null,
            followUpType: null,
            route: RouteType::CHAT,
            userQuery: 'হ্যাঁ, আমি নিতে চাই',
            conversation: $this->conversation,
        );

        // Verify zero action mutations occurred
        $this->assertFalse($decision->isAllowed());
        $this->assertSame('no_proposal', $decision->suppressionReason);
        $this->assertArrayNotHasKey('pending_action', $this->conversation->fresh()->metadata ?? []);
    }

    /**
     * 13. Analytics query -> No follow-up CTA.
     */
    public function test_analytics_query_suppresses_follow_up(): void
    {
        $policyManager = new FollowUpPolicyManager();

        $decision = $policyManager->evaluate(
            rawAnswer: 'আজকে মোট ১,২০,০০০ টাকার সেল হয়েছে।',
            proposedFollowUp: 'আপনি কি প্রোডাক্ট দেখতে চান?',
            followUpType: 'purchase_interest',
            route: RouteType::ANALYTICS,
            userQuery: 'আজকের টোটাল সেলস কত?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('unauthorized_route_analytics', $decision->suppressionReason);
        $this->assertSame('আজকে মোট ১,২০,০০০ টাকার সেল হয়েছে।', $decision->finalReply());
    }

    /**
     * 14. Uploaded-file analytics query -> No follow-up CTA.
     */
    public function test_uploaded_file_analytics_suppresses_follow_up(): void
    {
        $this->conversation->metadata = [
            'active_file_id' => 'file_sales_2026.xlsx',
        ];
        $this->conversation->save();

        $policyManager = new FollowUpPolicyManager();

        $decision = $policyManager->evaluate(
            rawAnswer: 'ফাইল অনুযায়ী জুন মাসে শীর্ষ বিক্রিত পণ্য ছিল কটন টি-শার্ট।',
            proposedFollowUp: 'অন্য কোনো ফাইল চেক করবেন?',
            followUpType: 'purchase_interest',
            route: RouteType::ANALYTICS,
            userQuery: 'এই ফাইলে জুন মাসের টপ সেলিং প্রোডাক্ট কোনটি?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('unauthorized_route_analytics', $decision->suppressionReason);
        $this->assertSame('ফাইল অনুযায়ী জুন মাসে শীর্ষ বিক্রিত পণ্য ছিল কটন টি-শার্ট।', $decision->finalReply());
    }
}
