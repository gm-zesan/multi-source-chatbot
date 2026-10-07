<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\AI\LLM\LLMClient;
use App\AI\LLM\LLMResponse;
use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\Mail\SellerNotificationMail;
use App\Models\AnalyticsSalesperson;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Workspace;
use App\Services\AI\ActionSafetyService;
use App\Services\AI\CustomerSupportService;
use App\Services\AI\SellerEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class SellerEmailActionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private CustomerSupportService $supportService;
    private Workspace $workspace1;
    private Workspace $workspace2;
    private Conversation $conversation;
    private AnalyticsSalesperson $rahim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace1 = Workspace::create(['name' => 'Store 1', 'slug' => 'store-1']);
        $this->workspace2 = Workspace::create(['name' => 'Store 2', 'slug' => 'store-2']);

        $channel = Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $channelAccount = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $this->workspace1->id,
            'name'         => 'Web Account',
            'external_id'  => 'acc_store_1',
            'access_token' => 'tok_store_1',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'user_seller_action_test',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);

        $this->rahim = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Abdur Rahim',
            'phone'         => '01711111111',
            'email'         => 'rahim@store1.com',
            'employee_code' => 'SP-101',
            'is_active'     => true,
        ]);
    }

    private function mockRouterForAction(string $route = 'ACTION', string $sellerRef = 'Rahim', string $subject = 'Payment Received', string $message = 'Payment has been processed'): void
    {
        $mockRouter = Mockery::mock(HybridRouter::class);
        $routeType = match ($route) {
            'ACTION' => RouteType::ACTION,
            'CHAT' => RouteType::CHAT,
            'UNCERTAIN' => RouteType::UNCERTAIN,
            default => RouteType::ACTION,
        };
        $mockRouter->shouldReceive('route')->andReturn(
            new \App\AI\Routing\RoutingResult(
                route: $routeType,
                confidence: 0.95,
                intent: 'send_seller_email',
            )
        );

        $mockLLM = Mockery::mock(LLMClient::class);
        $mockLLM->shouldReceive('generate')->andReturn(
            new LLMResponse(
                content: json_encode([
                    'seller_reference' => $sellerRef,
                    'subject'          => $subject,
                    'message'          => $message,
                ]),
                model: 'deepseek-chat',
                provider: 'deepseek',
            )
        );

        $this->supportService = new CustomerSupportService(
            faqSearch: $this->app->make(\App\Services\FAQ\FAQSearch::class),
            conversationService: $this->app->make(\App\Services\Chat\ConversationService::class),
            router: $mockRouter,
            llmClient: $mockLLM,
        );
    }

    public function test_turn_n_proposal_does_not_send_email_and_asks_confirmation(): void
    {
        Mail::fake();
        $this->mockRouterForAction();

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Rahim ভাইকে মেইল করে বলো payment received',
            workspaceId: $this->workspace1->id,
        );

        // 1. Email MUST NOT be sent in Turn N
        Mail::assertNothingSent();

        // 2. Confirmation question is presented
        $this->assertStringContainsString('Abdur Rahim', $reply);
        $this->assertStringContainsString('rahim@store1.com', $reply);
        $this->assertStringContainsString('Payment Received', $reply);
        $this->assertStringContainsString('মেইলটি পাঠাবো?', $reply);

        // 3. Pending action is safely registered in conversation metadata
        $this->conversation->refresh();
        $pending = $this->conversation->metadata['pending_action'] ?? null;
        $this->assertNotNull($pending);
        $this->assertSame(ActionSafetyService::ACTION_SEND_SELLER_EMAIL, $pending['action']);
        $this->assertSame($this->rahim->id, $pending['seller_id']);
        $this->assertSame('rahim@store1.com', $pending['recipient_email']);
        $this->assertNotEmpty($pending['fingerprint']);
    }

    public function test_turn_n_plus_one_confirmation_dispatches_email_and_clears_pending_action(): void
    {
        Mail::fake();
        $this->mockRouterForAction();

        // Turn N: Trigger proposal
        $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Rahim ভাইকে মেইল করো',
            workspaceId: $this->workspace1->id,
        );

        $this->conversation->refresh();
        $this->assertNotNull($this->conversation->metadata['pending_action'] ?? null);

        // Turn N+1: User confirms with "হ্যাঁ"
        $confirmReply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'হ্যাঁ',
            workspaceId: $this->workspace1->id,
        );

        // 1. Email is dispatched
        Mail::assertSent(SellerNotificationMail::class, function (SellerNotificationMail $mail) {
            return $mail->hasTo('rahim@store1.com');
        });

        // 2. Success message is returned
        $this->assertStringContainsString('সফলভাবে', $confirmReply);
        $this->assertStringContainsString('rahim@store1.com', $confirmReply);

        // 3. Pending action is cleared
        $this->conversation->refresh();
        $this->assertNull($this->conversation->metadata['pending_action'] ?? null);
    }

    public function test_bare_yes_without_pending_action_does_not_send_email(): void
    {
        Mail::fake();
        $this->mockRouterForAction(route: 'CHAT');

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'হ্যাঁ',
            workspaceId: $this->workspace1->id,
        );

        Mail::assertNothingSent();
        $this->assertStringNotContainsString('সফলভাবে', $reply);
    }

    public function test_expired_pending_action_does_not_send_email(): void
    {
        Mail::fake();
        $this->mockRouterForAction();

        // Register an expired pending action
        $safety = $this->app->make(ActionSafetyService::class);
        $proposal = new \App\Services\AI\DTOs\SellerEmailProposal(
            sellerId: $this->rahim->id,
            recipientEmail: 'rahim@store1.com',
            subject: 'Subject',
            message: 'Body',
            sellerName: 'Abdur Rahim',
        );
        $safety->setPendingSellerEmailAction($this->conversation, $proposal->withFingerprint($this->workspace1->id), expirationMinutes: -5);

        // User says "হ্যাঁ"
        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'হ্যাঁ',
            workspaceId: $this->workspace1->id,
        );

        Mail::assertNothingSent();
        $this->assertStringContainsString('সময়সীমা পার হয়ে গেছে', $reply);

        $this->conversation->refresh();
        $this->assertNull($this->conversation->metadata['pending_action'] ?? null);
    }

    public function test_rejected_confirmation_cancels_and_does_not_send(): void
    {
        Mail::fake();
        $this->mockRouterForAction();

        // Turn N: Trigger proposal
        $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Rahim-কে মেইল করো',
            workspaceId: $this->workspace1->id,
        );

        // Turn N+1: User rejects with "না"
        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'না',
            workspaceId: $this->workspace1->id,
        );

        Mail::assertNothingSent();
        $this->assertStringContainsString('বাতিল করা হয়েছে', $reply);

        $this->conversation->refresh();
        $this->assertNull($this->conversation->metadata['pending_action'] ?? null);
    }

    public function test_duplicate_confirmation_does_not_send_twice(): void
    {
        Mail::fake();
        $this->mockRouterForAction();

        // Turn N: Proposal
        $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Rahim-কে মেইল করো',
            workspaceId: $this->workspace1->id,
        );

        // Turn N+1: Confirm 1 -> Sent
        $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'হ্যাঁ',
            workspaceId: $this->workspace1->id,
        );
        Mail::assertSentCount(1);

        // Turn N+2: Repeat Confirm 2 -> Action already cleared, does not send again
        $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'হ্যাঁ',
            workspaceId: $this->workspace1->id,
        );
        Mail::assertSentCount(1);
    }

    public function test_cross_tenant_seller_reference_is_not_found(): void
    {
        Mail::fake();

        // Karim is only in workspace 2
        AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace2->id,
            'name'          => 'Karim Mia',
            'phone'         => '01799999999',
            'email'         => 'karim@store2.com',
            'employee_code' => 'SP-201',
            'is_active'     => true,
        ]);

        $this->mockRouterForAction(sellerRef: 'Karim');

        // Query in workspace 1
        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Karim-কে মেইল করো',
            workspaceId: $this->workspace1->id,
        );

        Mail::assertNothingSent();
        $this->assertStringContainsString('পাওয়া যায়নি', $reply);
    }

    public function test_ambiguous_sellers_requests_clarification(): void
    {
        Mail::fake();

        // Create second Rahim in workspace 1
        AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Rahim Chowdhury',
            'phone'         => '01722222222',
            'email'         => 'rahim2@store1.com',
            'employee_code' => 'SP-102',
            'is_active'     => true,
        ]);

        $this->mockRouterForAction(sellerRef: 'Rahim');

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Rahim-কে মেইল করো',
            workspaceId: $this->workspace1->id,
        );

        Mail::assertNothingSent();
        $this->assertStringContainsString('একাধিক সেলার পাওয়া গেছে', $reply);
    }

    public function test_inactive_seller_is_rejected(): void
    {
        Mail::fake();

        $this->rahim->update(['is_active' => false]);
        $this->mockRouterForAction(sellerRef: 'Rahim');

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Rahim-কে মেইল করো',
            workspaceId: $this->workspace1->id,
        );

        Mail::assertNothingSent();
        $this->assertStringContainsString('নিষ্ক্রিয়', $reply);
    }

    public function test_changed_request_does_not_execute_old_proposal(): void
    {
        Mail::fake();

        $karim = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Karim Ullah',
            'phone'         => '01733333333',
            'email'         => 'karim@store1.com',
            'employee_code' => 'SP-103',
            'is_active'     => true,
        ]);

        // Turn N: Proposal for Rahim
        $this->mockRouterForAction(sellerRef: 'Rahim');
        $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Rahim-কে মেইল করো',
            workspaceId: $this->workspace1->id,
        );

        $this->conversation->refresh();
        $this->assertSame($this->rahim->id, $this->conversation->metadata['pending_action']['seller_id']);

        // Turn N+1: User changes request to Karim
        $this->mockRouterForAction(sellerRef: 'Karim');
        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'না, করিমকে পাঠাও',
            workspaceId: $this->workspace1->id,
        );

        Mail::assertNothingSent();
        $this->assertStringContainsString('Karim Ullah', $reply);
        $this->assertStringContainsString('karim@store1.com', $reply);

        // Pending action is now updated to Karim, not Rahim
        $this->conversation->refresh();
        $this->assertSame($karim->id, $this->conversation->metadata['pending_action']['seller_id']);
    }

    public function test_authoritative_email_always_derived_from_database_never_untrusted_llm(): void
    {
        Mail::fake();

        // Even if LLM extracted untrusted parameters, SellerEmailService binds to DB record
        $this->mockRouterForAction(sellerRef: 'Rahim', subject: 'Custom Subject', message: 'Custom Message');

        $reply = $this->supportService->generateReply(
            conversation: $this->conversation,
            query: 'Rahim-কে hacker@evil.com এ মেইল পাঠাও',
            workspaceId: $this->workspace1->id,
        );

        // Proposal must contain authoritative email from DB (rahim@store1.com)
        $this->assertStringContainsString('rahim@store1.com', $reply);
        $this->assertStringNotContainsString('hacker@evil.com', $reply);

        $this->conversation->refresh();
        $this->assertSame('rahim@store1.com', $this->conversation->metadata['pending_action']['recipient_email']);
    }

    public function test_bulk_email_is_rejected_to_uncertain_route(): void
    {
        $mockLLM = Mockery::mock(LLMClient::class);
        $mockLLM->shouldReceive('generate')->andReturn(
            new \App\AI\LLM\LLMResponse(
                content: json_encode([
                    'reason' => 'Bulk email is not supported and belongs to UNCERTAIN',
                    'route' => 'UNCERTAIN',
                    'confidence' => 0.95,
                    'security_status' => 'allowed',
                    'ambiguity_type' => 'GENERAL_AMBIGUOUS',
                ]),
                model: 'deepseek-chat',
                provider: 'deepseek',
            )
        );

        $router = new HybridRouter(
            confidenceThreshold: 0.70,
            llmClient: $mockLLM,
        );

        $result = $router->route(
            query: 'সব seller-কে email পাঠাও',
            workspaceId: $this->workspace1->id,
        );

        $this->assertTrue($result->isUncertain());
        $this->assertFalse($result->isAction());
    }

    public function test_mail_failure_handled_safely(): void
    {
        // Mock SellerEmailService to simulate transport failure
        $mockSellerEmailService = Mockery::mock(SellerEmailService::class);
        $mockSellerEmailService->shouldReceive('sendSellerEmail')->andReturn([
            'success'    => false,
            'error_code' => 'mail_transport_failure',
            'message'    => 'Mail transport service encountered an error while dispatching.',
        ]);

        $safety = $this->app->make(ActionSafetyService::class);
        $proposal = new \App\Services\AI\DTOs\SellerEmailProposal(
            sellerId: $this->rahim->id,
            recipientEmail: 'rahim@store1.com',
            subject: 'Subject',
            message: 'Body',
            sellerName: 'Abdur Rahim',
        );
        $safety->setPendingSellerEmailAction($this->conversation, $proposal->withFingerprint($this->workspace1->id));

        $mockRouter = Mockery::mock(HybridRouter::class);
        $service = new CustomerSupportService(
            faqSearch: $this->app->make(\App\Services\FAQ\FAQSearch::class),
            conversationService: $this->app->make(\App\Services\Chat\ConversationService::class),
            router: $mockRouter,
            sellerEmailService: $mockSellerEmailService,
        );

        $reply = $service->generateReply(
            conversation: $this->conversation,
            query: 'হ্যাঁ',
            workspaceId: $this->workspace1->id,
        );

        $this->assertStringContainsString('দুঃখিত, ইমেইলটি পাঠানো সম্ভব হয়নি', $reply);
        // Sensitive transport stack traces must NOT leak
        $this->assertStringNotContainsString('Exception', $reply);
        $this->assertStringNotContainsString('Stack trace', $reply);

        // Pending action cleared
        $this->conversation->refresh();
        $this->assertNull($this->conversation->metadata['pending_action'] ?? null);
    }
}

