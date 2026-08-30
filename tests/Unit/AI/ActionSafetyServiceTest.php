<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\ActionSafetyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionSafetyServiceTest extends TestCase
{
    use RefreshDatabase;

    private ActionSafetyService $service;
    private Workspace $workspace1;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ActionSafetyService();

        $this->workspace1 = Workspace::create(['name' => 'WS1', 'slug' => 'ws1']);
        $channel = Channel::create(['slug' => 'test_chan', 'name' => 'Test Channel', 'is_active' => true]);
        $account = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id'  => $this->workspace1->id,
            'name'         => 'Account 1',
            'external_id'  => 'acc_1',
            'access_token' => 'token_1',
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_1',
            'customer_name'      => 'Customer 1',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_pending_action_lifecycle(): void
    {
        $this->assertNull($this->service->getPendingAction($this->conversation));

        $this->service->setPendingAction(
            conversation: $this->conversation,
            action: 'cancel_order',
            parameters: ['order_id' => 1024],
            promptMessage: 'Confirm cancellation for order 1024',
        );

        $pending = $this->service->getPendingAction($this->conversation->fresh());
        $this->assertNotNull($pending);
        $this->assertSame('cancel_order', $pending['action']);
        $this->assertSame(1024, $pending['parameters']['order_id']);
        $this->assertSame('awaiting_confirmation', $pending['status']);

        $this->service->clearPendingAction($this->conversation->fresh());
        $this->assertNull($this->service->getPendingAction($this->conversation->fresh()));
    }

    public function test_format_confirmation_payload(): void
    {
        $payload = $this->service->formatConfirmationPayload(
            action: 'cancel_order',
            message: 'আপনি কি আপনার অর্ডার #1024 বাতিল করতে চান?',
            entityId: 1024,
            language: 'bn',
        );

        $this->assertSame('confirmation', $payload['type']);
        $this->assertSame('cancel_order', $payload['action']);
        $this->assertSame(1024, $payload['entity_id']);
        $this->assertCount(2, $payload['options']);
        $this->assertSame('confirm', $payload['options'][0]['value']);
        $this->assertSame('reject', $payload['options'][1]['value']);
    }

    public function test_validate_tenant_authorization(): void
    {
        // Valid matching workspace
        $valid = $this->service->validateTenantAuthorization(
            conversation: $this->conversation,
            expectedWorkspaceId: $this->workspace1->id,
            action: 'cancel_order',
            parameters: ['order_id' => 1024],
        );
        $this->assertTrue($valid);

        // Mismatched workspace (e.g. Workspace 2)
        $invalid = $this->service->validateTenantAuthorization(
            conversation: $this->conversation,
            expectedWorkspaceId: 9999,
            action: 'cancel_order',
            parameters: ['order_id' => 1024],
        );
        $this->assertFalse($invalid);
    }
}
