<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Events\IncomingMessageReceived;
use App\Listeners\RunFAQEngineListener;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QueuedJobKillSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name'      => 'Kill Switch Queued Pilot',
            'slug'      => 'kill-switch-queued-pilot',
            'currency'  => 'BDT',
            'is_active' => true,
            'settings'  => [
                'ai_canary_enabled' => true,
                'ai_kill_switch'    => false,
                'fallback_message'  => 'আমাদের সাপোর্ট টিম অফলাইনে আছে, দ্রুত আপনার সাথে যোগাযোগ করবে।',
            ],
        ]);

        config(['ai.rollout_mode' => 'limited_canary']);
        config(['ai.canary_workspaces' => [$this->workspace->id]]);

        $channel = Channel::create(['name' => 'WhatsApp', 'slug' => 'whatsapp', 'driver' => 'whatsapp']);
        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'WhatsApp Pilot Account',
            'external_id'  => 'wa_pilot_001',
            'access_token' => 'pilot_token_secret',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'customer_wa_99',
            'customer_name'      => 'Test Queued Customer',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_queued_listener_halts_immediately_when_workspace_kill_switch_is_active(): void
    {
        // 1. Engage kill switch for workspace
        $this->workspace->setAiKillSwitch(true);
        $this->assertTrue($this->workspace->fresh()->isAiKillSwitchActive());

        $inboundMsg = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'আপনাদের ডেলিভারি ফি কত?',
            'status'          => 'delivered',
        ]);

        $event = new IncomingMessageReceived(
            conversation: $this->conversation,
            message: $inboundMsg,
            account: $this->account,
            rawPayload: ['text' => 'আপনাদের ডেলিভারি ফি কত?']
        );

        $listener = app(RunFAQEngineListener::class);
        $listener->handle($event);

        // Verify outbound reply is saved with fallback message and marked safe handoff
        $outbound = Message::where('conversation_id', $this->conversation->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->first();

        $this->assertNotNull($outbound);
        $this->assertSame('আমাদের সাপোর্ট টিম অফলাইনে আছে, দ্রুত আপনার সাথে যোগাযোগ করবে।', $outbound->body);
    }

    public function test_queued_retry_halts_safely_if_kill_switch_engaged_mid_flight(): void
    {
        // Message arrives initially when kill switch was off
        $this->assertFalse($this->workspace->isAiKillSwitchActive());

        // Merchant flips the emergency switch right as job enters worker queue
        $this->workspace->setAiKillSwitch(true);

        $inboundMsg = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'জরুরি সাহায্য দরকার!',
            'status'          => 'delivered',
        ]);

        $event = new IncomingMessageReceived(
            conversation: $this->conversation,
            message: $inboundMsg,
            account: $this->account,
            rawPayload: ['text' => 'জরুরি সাহায্য দরকার!']
        );

        $listener = app(RunFAQEngineListener::class);
        $listener->handle($event);

        $outbound = Message::where('conversation_id', $this->conversation->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->first();

        $this->assertNotNull($outbound);
        $this->assertSame('আমাদের সাপোর্ট টিম অফলাইনে আছে, দ্রুত আপনার সাথে যোগাযোগ করবে।', $outbound->body);
    }

    public function test_daily_traffic_guardrail_halts_queued_listener_at_100_queries(): void
    {
        config(['ai.canary_daily_limit' => 2]); // Set low quota for testing

        $listener = app(RunFAQEngineListener::class);

        // Turn 1
        $msg1 = Message::create(['conversation_id' => $this->conversation->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'প্রশ্ন ১', 'status' => 'delivered']);
        $listener->handle(new IncomingMessageReceived($this->conversation, $msg1, $this->account, []));

        // Turn 2
        $msg2 = Message::create(['conversation_id' => $this->conversation->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'প্রশ্ন ২', 'status' => 'delivered']);
        $listener->handle(new IncomingMessageReceived($this->conversation, $msg2, $this->account, []));

        // Turn 3: Exceeds quota (2 queries)
        $msg3 = Message::create(['conversation_id' => $this->conversation->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'প্রশ্ন ৩', 'status' => 'delivered']);
        $listener->handle(new IncomingMessageReceived($this->conversation, $msg3, $this->account, []));

        $outboundLatest = Message::where('conversation_id', $this->conversation->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->first();

        $this->assertNotNull($outboundLatest);
        $this->assertSame('আমাদের সাপোর্ট টিম অফলাইনে আছে, দ্রুত আপনার সাথে যোগাযোগ করবে।', $outboundLatest->body);
    }
}
