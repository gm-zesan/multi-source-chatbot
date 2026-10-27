<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\AnalyticsSalesperson;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\ActionSafetyService;
use App\Services\AI\CustomerSupportService;
use App\Services\AI\DTOs\SellerEmailProposal;
use App\Services\AI\SellerEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ActionSafetyTopicDriftTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation1;
    private Conversation $conversation2;
    private ActionSafetyService $safetyService;
    private AnalyticsSalesperson $salesperson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->safetyService = new ActionSafetyService();
        $this->workspace = Workspace::create(['name' => 'Drift Store', 'slug' => 'drift-store']);

        $channel = Channel::create(['name' => 'Web', 'slug' => 'web-drift', 'driver' => 'web']);
        $this->account = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $this->workspace->id,
            'name'         => 'Account Drift',
            'external_id'  => 'acc_drift',
            'access_token' => 'tok_drift',
            'is_active'    => true,
        ]);

        $this->conversation1 = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_drift_1',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);

        $this->conversation2 = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_drift_2',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);

        $this->salesperson = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Rafiq Ahmed',
            'email'         => 'rafiq@driftstore.com',
            'phone'         => '01700000000',
            'employee_code' => 'SP-DR-01',
            'is_active'     => true,
        ]);
    }

    private function createSupportService(SellerEmailService $mockEmailService, ?HybridRouter $router = null): CustomerSupportService
    {
        $mockRouter = $router ?? Mockery::mock(HybridRouter::class);
        if ($router === null) {
            $mockRouter->shouldReceive('route')->andReturn(
                new RoutingResult(route: RouteType::CHAT, confidence: 1.0, intent: 'chat')
            );
        }

        return new CustomerSupportService(
            faqSearch: $this->app->make(\App\Services\FAQ\FAQSearch::class),
            conversationService: $this->app->make(\App\Services\Chat\ConversationService::class),
            router: $mockRouter,
            actionSafety: $this->safetyService,
            sellerEmailService: $mockEmailService,
        );
    }

    public function test_valid_confirmation_executes_only_intended_pending_action(): void
    {
        $mockEmail = Mockery::mock(SellerEmailService::class);
        $mockEmail->shouldReceive('sendSellerEmail')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Sent']);

        $service = $this->createSupportService($mockEmail);

        // Turn 1: Propose email action
        $proposal = new SellerEmailProposal(
            sellerId: $this->salesperson->id,
            recipientEmail: 'rafiq@driftstore.com',
            subject: 'Refund Inquiry',
            message: 'Customer asked for refund',
            sellerName: 'Rafiq Ahmed',
            fingerprint: 'fp_123',
        );
        $this->safetyService->setPendingSellerEmailAction($this->conversation1, $proposal);

        // Turn 2: User explicitly confirms
        $reply = $service->generateReply(
            conversation: $this->conversation1,
            query: 'হ্যাঁ, পাঠিয়ে দিন',
            workspaceId: $this->workspace->id,
        );

        $this->assertStringContainsString('সফলভাবে Rafiq Ahmed', $reply);
        $this->assertNull($this->safetyService->getPendingAction($this->conversation1->fresh()));
    }

    public function test_topic_change_invalidates_pending_action_and_subsequent_ok_does_not_execute(): void
    {
        $mockEmail = Mockery::mock(SellerEmailService::class);
        // Email must NEVER be called
        $mockEmail->shouldReceive('sendSellerEmail')->never();

        $service = $this->createSupportService($mockEmail);

        // Turn 1: Propose action
        $proposal = new SellerEmailProposal(
            sellerId: $this->salesperson->id,
            recipientEmail: 'rafiq@driftstore.com',
            subject: 'Defect Report',
            message: 'Broken zipper',
            sellerName: 'Rafiq Ahmed',
            fingerprint: 'fp_defect',
        );
        $this->safetyService->setPendingSellerEmailAction($this->conversation1, $proposal);

        // Turn 2: User drifts to another topic (neither confirmation nor rejection)
        $service->generateReply(
            conversation: $this->conversation1,
            query: 'delivery charge koto?',
            workspaceId: $this->workspace->id,
        );

        // Verify pending action was immediately cleared upon topic drift
        $this->assertNull($this->safetyService->getPendingAction($this->conversation1->fresh()));

        // Turn 3: User says "ok" (responding to the delivery answer)
        $reply = $service->generateReply(
            conversation: $this->conversation1,
            query: 'ok',
            workspaceId: $this->workspace->id,
        );

        // Verify email was NOT executed and reply is not an email confirmation
        $this->assertStringNotContainsString('সফলভাবে', $reply);
    }

    public function test_compound_query_with_discourse_marker_does_not_execute_action(): void
    {
        $mockEmail = Mockery::mock(SellerEmailService::class);
        $mockEmail->shouldReceive('sendSellerEmail')->never();

        $service = $this->createSupportService($mockEmail);

        // Turn 1: Propose action
        $proposal = new SellerEmailProposal(
            sellerId: $this->salesperson->id,
            recipientEmail: 'rafiq@driftstore.com',
            subject: 'Defect Report',
            message: 'Broken zipper',
            sellerName: 'Rafiq Ahmed',
            fingerprint: 'fp_defect_2',
        );
        $this->safetyService->setPendingSellerEmailAction($this->conversation1, $proposal);

        // Turn 2: User sends a compound question containing discourse marker "ok" or "send"
        $queries = [
            'ok, delivery charge koto?',
            'send me the product catalogue',
            'sure, what is the price of shoes?',
        ];

        foreach ($queries as $q) {
            $this->assertFalse(
                $this->safetyService->isConfirmationIntent($q),
                "Expected query '{$q}' NOT to be classified as confirmation intent."
            );
        }

        // When user asks "ok, delivery charge koto?", action must be cleared, not executed
        $service->generateReply(
            conversation: $this->conversation1,
            query: 'ok, delivery charge koto?',
            workspaceId: $this->workspace->id,
        );

        $this->assertNull($this->safetyService->getPendingAction($this->conversation1->fresh()));
    }

    public function test_confirmation_from_another_conversation_cannot_execute_action(): void
    {
        $mockEmail = Mockery::mock(SellerEmailService::class);
        $mockEmail->shouldReceive('sendSellerEmail')->never();

        $service = $this->createSupportService($mockEmail);

        // Turn 1: Propose action on Conversation 1
        $proposal = new SellerEmailProposal(
            sellerId: $this->salesperson->id,
            recipientEmail: 'rafiq@driftstore.com',
            subject: 'Secret Report',
            message: 'Private message',
            sellerName: 'Rafiq Ahmed',
            fingerprint: 'fp_secret',
        );
        $this->safetyService->setPendingSellerEmailAction($this->conversation1, $proposal);

        // Conversation 2 tries to confirm
        $replyConv2 = $service->generateReply(
            conversation: $this->conversation2,
            query: 'হ্যাঁ',
            workspaceId: $this->workspace->id,
        );

        $this->assertStringNotContainsString('সফলভাবে', $replyConv2);
        // Conversation 1 proposal remains untouched and isolated
        $this->assertNotNull($this->safetyService->getPendingAction($this->conversation1->fresh()));
    }

    public function test_rejection_and_expiration_continue_to_work_as_intended(): void
    {
        $mockEmail = Mockery::mock(SellerEmailService::class);
        $mockEmail->shouldReceive('sendSellerEmail')->never();

        $service = $this->createSupportService($mockEmail);

        // 1. Test Rejection
        $proposal = new SellerEmailProposal(
            sellerId: $this->salesperson->id,
            recipientEmail: 'rafiq@driftstore.com',
            subject: 'Test Subject',
            message: 'Test Message',
            sellerName: 'Rafiq Ahmed',
            fingerprint: 'fp_reject',
        );
        $this->safetyService->setPendingSellerEmailAction($this->conversation1, $proposal);

        $reply = $service->generateReply(
            conversation: $this->conversation1,
            query: 'না, পাঠানোর দরকার নেই',
            workspaceId: $this->workspace->id,
        );

        $this->assertStringContainsString('বাতিল করা হয়েছে', $reply);
        $this->assertNull($this->safetyService->getPendingAction($this->conversation1->fresh()));

        // 2. Test Expiration
        $this->safetyService->setPendingSellerEmailAction($this->conversation1, $proposal, expirationMinutes: -1);
        $expiredReply = $service->generateReply(
            conversation: $this->conversation1,
            query: 'হ্যাঁ',
            workspaceId: $this->workspace->id,
        );

        $this->assertStringContainsString('সময়সীমা পার হয়ে গেছে', $expiredReply);
        $this->assertNull($this->safetyService->getPendingAction($this->conversation1->fresh()));
    }
}
