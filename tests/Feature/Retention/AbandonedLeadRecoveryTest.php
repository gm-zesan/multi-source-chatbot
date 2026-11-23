<?php

declare(strict_types=1);

namespace Tests\Feature\Retention;

use App\Channels\Contracts\ChannelDriver;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Models\WorkspaceWallet;
use App\Services\Retention\AbandonedLeadRecoveryService;
use App\Support\ChannelManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AbandonedLeadRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private AbandonedLeadRecoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Test Boutique',
            'slug' => 'test-boutique',
        ]);

        WorkspaceWallet::create([
            'workspace_id'    => $this->workspace->id,
            'balance'         => 100.0,
            'currency'        => 'BDT',
            'is_auto_recharge'=> false,
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'website'],
            ['name' => 'Website Live Chat']
        );

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'external_id'  => 'acc_test_123',
            'name'         => 'Storefront Web Chat',
            'access_token' => 'dummy_access_token',
        ]);

        $this->service = app(AbandonedLeadRecoveryService::class);
    }

    public function test_eligible_abandoned_lead_receives_polite_follow_up(): void
    {
        // 3 hours ago: customer asked about price but didn't buy
        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_001',
            'customer_name'      => 'Tanvir Ahmed',
            'status'             => 'open',
            'last_direction'     => 'outbound',
            'last_message_at'    => Carbon::now()->subHours(3),
            'ai_tags'            => ['price_inquiry', 'needs_phone'],
            'metadata'           => ['discussed_product' => 'Panjabi Classic'],
        ]);

        $eligible = $this->service->findEligibleConversations($this->workspace->id);
        $this->assertTrue($eligible->contains('id', $conversation->id));

        $result = $this->service->sendFollowUp($conversation);
        $this->assertTrue($result['success']);

        $conversation->refresh();
        $this->assertTrue($conversation->metadata['abandoned_lead_followed_up']);
        $this->assertNotNull($conversation->metadata['abandoned_lead_followed_up_at']);
        $this->assertStringContainsString('Panjabi Classic', $conversation->last_message);
        $this->assertStringContainsString('Tanvir Ahmed', $conversation->last_message);

        // Assert message recorded in database
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
        ]);
    }

    public function test_conversation_outside_meta_24h_window_is_safely_skipped(): void
    {
        // 25 hours ago: outside standard 24h window
        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_002',
            'customer_name'      => 'Rahim',
            'status'             => 'open',
            'last_direction'     => 'outbound',
            'last_message_at'    => Carbon::now()->subHours(25),
            'ai_tags'            => ['hot_lead'],
        ]);

        $eligible = $this->service->findEligibleConversations($this->workspace->id);
        $this->assertFalse($eligible->contains('id', $conversation->id));

        $result = $this->service->sendFollowUp($conversation);
        $this->assertFalse($result['success']);
        $this->assertSame('meta_24h_window_expired', $result['reason']);
    }

    public function test_conversation_less_than_2_hours_ago_is_not_yet_abandoned(): void
    {
        // 30 minutes ago: ongoing / recent
        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_003',
            'customer_name'      => 'Karim',
            'status'             => 'open',
            'last_direction'     => 'outbound',
            'last_message_at'    => Carbon::now()->subMinutes(30),
            'ai_tags'            => ['needs_phone'],
        ]);

        $eligible = $this->service->findEligibleConversations($this->workspace->id);
        $this->assertFalse($eligible->contains('id', $conversation->id));
    }

    public function test_conversation_with_existing_order_is_suppressed(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_004',
            'customer_name'      => 'Salam',
            'status'             => 'open',
            'last_direction'     => 'outbound',
            'last_message_at'    => Carbon::now()->subHours(4),
            'ai_tags'            => ['hot_lead', 'order_placed'],
        ]);

        // Order already placed
        WorkspaceOrder::create([
            'workspace_id'    => $this->workspace->id,
            'conversation_id' => $conversation->id,
            'order_number'    => 'ORD-TEST-1234',
            'channel'         => 'web',
            'customer_name'   => 'Salam',
            'customer_phone'  => '01711223344',
            'status'          => 'pending',
            'captured_at'     => Carbon::now()->subHours(4),
        ]);

        $eligible = $this->service->findEligibleConversations($this->workspace->id);
        $this->assertFalse($eligible->contains('id', $conversation->id));

        $result = $this->service->sendFollowUp($conversation);
        $this->assertFalse($result['success']);
        $this->assertSame('order_already_placed', $result['reason']);
    }

    public function test_already_followed_up_conversation_is_never_repeated(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_005',
            'customer_name'      => 'Sakib',
            'status'             => 'open',
            'last_direction'     => 'outbound',
            'last_message_at'    => Carbon::now()->subHours(4),
            'ai_tags'            => ['needs_phone'],
            'metadata'           => [
                'abandoned_lead_followed_up'    => true,
                'abandoned_lead_followed_up_at' => Carbon::now()->subHours(1)->toIso8601String(),
            ],
        ]);

        $eligible = $this->service->findEligibleConversations($this->workspace->id);
        $this->assertFalse($eligible->contains('id', $conversation->id));

        $result = $this->service->sendFollowUp($conversation);
        $this->assertFalse($result['success']);
        $this->assertSame('already_followed_up', $result['reason']);
    }

    public function test_paused_ai_conversation_is_skipped(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_006',
            'customer_name'      => 'Nayeem',
            'status'             => 'open',
            'last_direction'     => 'outbound',
            'last_message_at'    => Carbon::now()->subHours(3),
            'ai_tags'            => ['needs_phone'],
            'metadata'           => ['ai_paused' => true],
        ]);

        $eligible = $this->service->findEligibleConversations($this->workspace->id);
        $this->assertFalse($eligible->contains('id', $conversation->id));
    }

    public function test_artisan_command_sync_execution(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_cmd_1',
            'customer_name'      => 'Cmd User',
            'status'             => 'open',
            'last_direction'     => 'outbound',
            'last_message_at'    => Carbon::now()->subHours(5),
            'ai_tags'            => ['needs_phone'],
        ]);

        $this->artisan('retention:process-abandoned-leads --sync --workspace=' . $this->workspace->id)
            ->expectsOutputToContain('Found 1 conversation(s) ready for follow-up')
            ->expectsOutputToContain('Sent follow-up message')
            ->assertExitCode(0);

        $conversation->refresh();
        $this->assertTrue($conversation->metadata['abandoned_lead_followed_up']);
    }
}
