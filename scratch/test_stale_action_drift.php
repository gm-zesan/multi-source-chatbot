<?php

declare(strict_types=1);

namespace Tests\Scratch;

use App\AI\Routing\HybridRouter;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\ActionSafetyService;
use App\Services\AI\CustomerSupportService;
use App\Services\AI\SellerEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class test_stale_action_drift extends TestCase
{
    use RefreshDatabase;

    public function test_stale_action_is_executed_after_topic_drift_when_user_says_ok(): void
    {
        $workspace = Workspace::create([
            'name' => 'Safety Workspace',
            'slug' => 'safety-ws',
        ]);

        $channel = \App\Models\Channel::create([
            'name' => 'Web',
            'slug' => 'web-safety',
            'driver' => 'web',
            'is_active' => true,
        ]);

        $account = \App\Models\ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id' => $channel->id,
            'name' => 'Web Account',
            'external_id' => 'web-acc-1',
            'access_token' => 'token123',
            'is_active' => true,
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id' => 'user_1',
            'customer_name' => 'Customer 1',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);

        $actionSafety = app(ActionSafetyService::class);

        // Turn 1: Propose an action
        $actionSafety->setPendingAction(
            conversation: $conversation,
            action: ActionSafetyService::ACTION_SEND_SELLER_EMAIL,
            parameters: [
                'seller_id' => 10,
                'subject' => 'Product Defect Report',
                'message' => 'The customer reported a defect.',
                'seller_name' => 'Seller Karim',
                'recipient_email' => 'karim@example.com',
            ],
            promptMessage: 'Do you want me to send this email to Seller Karim?'
        );

        $this->assertNotNull($actionSafety->getPendingAction($conversation));

        // Mock SellerEmailService to track execution without sending real emails
        $mockSellerEmail = $this->createMock(SellerEmailService::class);
        $mockSellerEmail->expects($this->once())
            ->method('sendSellerEmail')
            ->willReturn(['success' => true, 'message' => 'Email sent']);
        $this->app->instance(SellerEmailService::class, $mockSellerEmail);

        $service = app(CustomerSupportService::class);

        // Turn 2: Topic drift query (e.g. user asks about delivery)
        // Since delivery query is neither confirmation nor rejection, pending action remains
        $this->assertFalse($actionSafety->isConfirmationIntent('delivery charge koto?'));
        $this->assertFalse($actionSafety->isRejectionIntent('delivery charge koto?'));

        // Turn 3: User says "ok" (acknowledging delivery info)
        $reply = $service->generateReply(
            conversation: $conversation,
            query: 'ok',
            workspaceId: $workspace->id
        );

        // Verify that the stale action was actually triggered because user said "ok" after drift!
        $this->assertStringContainsString('সফলভাবে', $reply);
        $this->assertNull($actionSafety->getPendingAction($conversation));
    }
}
