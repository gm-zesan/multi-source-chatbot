<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Routing\RouteType;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\DTOs\ContextualResolutionResult;
use App\Services\AI\DTOs\FollowUpDecision;
use App\Services\AI\FollowUpPolicyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpPolicyManagerTest extends TestCase
{
    use RefreshDatabase;

    private FollowUpPolicyManager $policyManager;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policyManager = new FollowUpPolicyManager();

        $workspace = Workspace::create(['name' => 'FollowUp Test Workspace', 'slug' => 'followup-test']);
        $channel = \App\Models\Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $channelAccount = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test Web Channel',
            'external_id'  => 'acc_test_web_channel',
            'access_token' => 'tok_test_web_channel',
            'is_active'    => true,
        ]);
        $this->conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'test_followup_user_1',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);
    }

    /**
     * 1. Allows purchase_interest for valid KNOWLEDGE when confident.
     */
    public function test_allows_purchase_interest_for_valid_grounded_knowledge(): void
    {
        $mockAnswerability = new class {
            public function isConfident(): bool { return true; }
        };

        $decision = $this->policyManager->evaluate(
            rawAnswer: 'আমাদের Royal Silk Panjabi ৩,৫০০ টাকা।',
            proposedFollowUp: 'আপনি কি এটি নিতে চাচ্ছেন?',
            followUpType: 'purchase_interest',
            route: RouteType::KNOWLEDGE,
            userQuery: 'Royal Silk Panjabi দাম কত?',
            conversation: $this->conversation,
            answerabilityDecision: $mockAnswerability,
        );

        $this->assertTrue($decision->isAllowed());
        $this->assertSame('purchase_interest', $decision->followUpType);
        $this->assertSame('আপনি কি এটি নিতে চাচ্ছেন?', $decision->followUpText);
        $this->assertSame("আমাদের Royal Silk Panjabi ৩,৫০০ টাকা।\n\nআপনি কি এটি নিতে চাচ্ছেন?", $decision->finalReply());
    }

    /**
     * 2. Allows valid CHAT follow-up when appropriate.
     */
    public function test_allows_valid_chat_follow_up(): void
    {
        $decision = $this->policyManager->evaluate(
            rawAnswer: 'আমরা ঢাকা ও ঢাকার বাইরে হোম ডেলিভারি দিয়ে থাকি।',
            proposedFollowUp: 'আপনার ডেলিভারি লোকেশনটি জানাবেন কি?',
            followUpType: 'delivery',
            route: RouteType::CHAT,
            userQuery: 'আপনাদের ডেলিভারি সুবিধা আছে?',
            conversation: $this->conversation,
        );

        $this->assertTrue($decision->isAllowed());
        $this->assertSame('delivery', $decision->followUpType);
        $this->assertSame('আপনার ডেলিভারি লোকেশনটি জানাবেন কি?', $decision->followUpText);
    }

    /**
     * 3. Suppresses ANALYTICS route.
     */
    public function test_suppresses_analytics_route(): void
    {
        $decision = $this->policyManager->evaluate(
            rawAnswer: 'গত মাসে মোট ৫০টি শার্ট বিক্রি হয়েছে।',
            proposedFollowUp: 'আপনি কি আরও দেখতে চান?',
            followUpType: 'purchase_interest',
            route: RouteType::ANALYTICS,
            userQuery: 'গত মাসের সেলস রিপোর্ট কত?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('unauthorized_route_analytics', $decision->suppressionReason);
        $this->assertSame('গত মাসে মোট ৫০টি শার্ট বিক্রি হয়েছে।', $decision->finalReply());
    }

    /**
     * 4. Suppresses OOD route.
     */
    public function test_suppresses_ood_route(): void
    {
        $decision = $this->policyManager->evaluate(
            rawAnswer: 'আজকের তাপমাত্রা ৩২ ডিগ্রি সেলসিয়াস।',
            proposedFollowUp: 'আপনি কি কিছু কিনতে চান?',
            followUpType: 'purchase_interest',
            route: RouteType::OOD,
            userQuery: 'আজকের আবহাওয়া কেমন?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('unauthorized_route_ood', $decision->suppressionReason);
        $this->assertSame('আজকের তাপমাত্রা ৩২ ডিগ্রি সেলসিয়াস।', $decision->finalReply());
    }

    /**
     * 5. Suppresses UNCERTAIN route.
     */
    public function test_suppresses_uncertain_route(): void
    {
        $decision = $this->policyManager->evaluate(
            rawAnswer: 'আপনি কি ব্ল্যাক নাকি হোয়াইট পাঞ্জাবি চাচ্ছেন?',
            proposedFollowUp: 'এখনই অর্ডার করবেন?',
            followUpType: 'purchase_interest',
            route: RouteType::UNCERTAIN,
            userQuery: 'পাঞ্জাবি কেমন?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('unauthorized_route_uncertain', $decision->suppressionReason);
    }

    /**
     * 6. Suppresses when pending clarification is active in conversation.
     */
    public function test_suppresses_when_pending_clarification_active(): void
    {
        $this->conversation->metadata = [
            'pending_clarification' => ['active' => true],
        ];
        $this->conversation->save();

        $decision = $this->policyManager->evaluate(
            rawAnswer: 'পাঞ্জাবির সাইজগুলো হলো M, L, XL।',
            proposedFollowUp: 'কোন সাইজটি নিবেন?',
            followUpType: 'product_variant',
            route: RouteType::KNOWLEDGE,
            userQuery: 'সাইজ কি কি আছে?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('pending_clarification_active', $decision->suppressionReason);
    }

    /**
     * 7. Suppresses when pending action is active in conversation.
     */
    public function test_suppresses_when_pending_action_active(): void
    {
        $this->conversation->metadata = [
            'pending_action' => ['action' => 'cancel_order'],
        ];
        $this->conversation->save();

        $decision = $this->policyManager->evaluate(
            rawAnswer: 'আপনার অনুরোধটি প্রক্রিয়াধীন রয়েছে।',
            proposedFollowUp: 'নতুন কিছু অর্ডার করবেন?',
            followUpType: 'purchase_interest',
            route: RouteType::CHAT,
            userQuery: 'অর্ডার ক্যানসেল করতে চাই',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('pending_action_active', $decision->suppressionReason);
    }

    /**
     * 8. Suppresses when conversation is handed off to human.
     */
    public function test_suppresses_when_handed_off_to_human(): void
    {
        $this->conversation->metadata = [
            'handoff_to_human' => true,
        ];
        $this->conversation->save();

        $decision = $this->policyManager->evaluate(
            rawAnswer: 'আমাদের একজন প্রতিনিধি শীঘ্রই যোগাযোগ করবেন।',
            proposedFollowUp: 'আপনি কি অন্য কোনো প্রোডাক্ট দেখতে চান?',
            followUpType: 'purchase_interest',
            route: RouteType::CHAT,
            userQuery: 'হেল্প দরকার',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('handoff_active', $decision->suppressionReason);
    }

    /**
     * 9. Suppresses complaint, damage, late delivery, or refund queries.
     */
    public function test_suppresses_complaint_or_refund_queries(): void
    {
        // Refund query
        $decision1 = $this->policyManager->evaluate(
            rawAnswer: 'রিফান্ডের জন্য ৭ কার্যদিবস সময় প্রয়োজন।',
            proposedFollowUp: 'আপনি কি অন্য কোনো পাঞ্জাবি নিতে চান?',
            followUpType: 'purchase_interest',
            route: RouteType::KNOWLEDGE,
            userQuery: 'আমার রিফান্ডের টাকা এখনো পাইনি কেন?',
            conversation: $this->conversation,
        );
        $this->assertFalse($decision1->isAllowed());
        $this->assertSame('complaint_or_dispute_guard', $decision1->suppressionReason);

        // Damaged item complaint
        $decision2 = $this->policyManager->evaluate(
            rawAnswer: 'নষ্ট পণ্যের ছবি ইনবক্সে দিলে আমরা রিপ্লেস করে দিবো।',
            proposedFollowUp: 'আপনি কি নতুন অর্ডার করবেন?',
            followUpType: 'purchase_interest',
            route: RouteType::CHAT,
            userQuery: 'প্রোডাক্টটি ছেঁড়া ও নষ্ট পেয়েছি!',
            conversation: $this->conversation,
        );
        $this->assertFalse($decision2->isAllowed());
        $this->assertSame('complaint_or_dispute_guard', $decision2->suppressionReason);
    }

    /**
     * 10. Suppresses unsupported follow_up_type.
     */
    public function test_suppresses_unsupported_follow_up_type(): void
    {
        $decision = $this->policyManager->evaluate(
            rawAnswer: 'আমাদের শোরুম ধানমন্ডিতে।',
            proposedFollowUp: 'আপনি কি আমাদের ফেসবুক পেজে লাইক দিয়েছেন?',
            followUpType: 'social_media_follow',
            route: RouteType::CHAT,
            userQuery: 'শোরুম কোথায়?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('unsupported_type', $decision->suppressionReason);
    }

    /**
     * 11. Suppresses empty proposal or null follow-up.
     */
    public function test_suppresses_empty_or_null_proposal(): void
    {
        $decision = $this->policyManager->evaluate(
            rawAnswer: 'আমাদের শোরুম রাত ৮টায় বন্ধ হয়।',
            proposedFollowUp: null,
            followUpType: null,
            route: RouteType::KNOWLEDGE,
            userQuery: 'দোকান কয়টায় বন্ধ হয়?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('no_proposal', $decision->suppressionReason);
        $this->assertSame('আমাদের শোরুম রাত ৮টায় বন্ধ হয়।', $decision->finalReply());
    }

    /**
     * 12. Suppresses repeated CTA of the same type without turn cooldown.
     */
    public function test_suppresses_repeated_cta_of_same_type(): void
    {
        $this->conversation->metadata = [
            'follow_up_state' => [
                'last_type' => 'purchase_interest',
                'last_text' => 'আপনি কি এটি নিতে চাচ্ছেন?',
                'status'    => 'asked',
            ],
        ];
        $this->conversation->save();

        $decision = $this->policyManager->evaluate(
            rawAnswer: 'আমাদের ব্লু শার্টের দাম ১,২০০ টাকা।',
            proposedFollowUp: 'আপনি কি এটি কিনতে আগ্রহী?',
            followUpType: 'purchase_interest',
            route: RouteType::KNOWLEDGE,
            userQuery: 'ব্লু শার্টের দাম কত?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('repetition_cooldown_active', $decision->suppressionReason);
    }

    /**
     * 13. Suppresses after explicit denial (e.g., "না", "লাগবে না", "no").
     */
    public function test_suppresses_after_explicit_denial(): void
    {
        $denials = ['না', 'না, লাগবে না', 'দরকার নেই', 'no', 'not now', 'na', 'lagbe na'];

        foreach ($denials as $denial) {
            $decision = $this->policyManager->evaluate(
                rawAnswer: 'ঠিক আছে, কোনো সমস্যা নেই। অন্য কোনো তথ্য লাগলে জানাবেন।',
                proposedFollowUp: 'আপনি কি অন্য কোনো পাঞ্জাবি দেখতে চান?',
                followUpType: 'purchase_interest',
                route: RouteType::CHAT,
                userQuery: $denial,
                conversation: $this->conversation,
            );

            $this->assertFalse($decision->isAllowed(), "Failed to suppress denial: {$denial}");
            $this->assertSame('user_denial_guard', $decision->suppressionReason);
        }
    }

    /**
     * 14. Handles malformed JSON and plain text gracefully.
     */
    public function test_handles_malformed_json_and_plain_text_safely(): void
    {
        $rawPlainText = "আমাদের সব পণ্য প্রিমিয়াম কোয়ালিটি সুতি কাপড়ে তৈরি।";
        $parsed = app(\App\Services\AI\CustomerSupportService::class)->parseAgentOutput($rawPlainText);

        $this->assertSame($rawPlainText, $parsed['answer']);
        $this->assertNull($parsed['proposed_follow_up']);
        $this->assertNull($parsed['follow_up_type']);

        $decision = $this->policyManager->evaluate(
            rawAnswer: $parsed['answer'],
            proposedFollowUp: $parsed['proposed_follow_up'],
            followUpType: $parsed['follow_up_type'],
            route: RouteType::KNOWLEDGE,
            userQuery: 'কাপড়ের কোয়ালিটি কেমন?',
            conversation: $this->conversation,
        );

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('no_proposal', $decision->suppressionReason);
        $this->assertSame($rawPlainText, $decision->finalReply());
    }

    /**
     * 15. Never permits ACTION / sensitive credential collection proposal.
     */
    public function test_never_permits_action_or_sensitive_credential_proposals(): void
    {
        $sensitiveProposals = [
            'দয়া করে আপনার বিকাশ পিন কোডটি দিন।',
            'Please provide your credit card number and CVV.',
            'আপনার অ্যাকাউন্টের পাসওয়ার্ড লিখুন।',
        ];

        foreach ($sensitiveProposals as $proposal) {
            $decision = $this->policyManager->evaluate(
                rawAnswer: 'পেমেন্ট গেটওয়েতে সমস্যা হয়েছিল।',
                proposedFollowUp: $proposal,
                followUpType: 'purchase_interest',
                route: RouteType::CHAT,
                userQuery: 'পেমেন্ট কিভাবে করবো?',
                conversation: $this->conversation,
            );

            $this->assertFalse($decision->isAllowed(), "Failed to suppress sensitive proposal: {$proposal}");
            $this->assertSame('forbidden_action_guard', $decision->suppressionReason);
        }
    }
}
