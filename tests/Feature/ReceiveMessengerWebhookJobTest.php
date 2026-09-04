<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Jobs\ReceiveMessengerWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use App\Events\IncomingMessageReceived;
use App\Events\ConversationCreated;
use Tests\TestCase;

class ReceiveMessengerWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    private ChannelAccount $account;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Event::fake();

        $this->workspace = Workspace::create(['name' => 'Test', 'slug' => 'test']);

        $channel = Channel::create(['slug' => 'facebook', 'name' => 'Facebook', 'is_active' => true]);
        $this->account = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'         => 'Test Account',
            'external_id'  => 'test_ext_123',
            'access_token' => 'test_token',
        ]);
    }

    public function test_job_dispatches_to_messenger_queue(): void
    {
        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->account->id,
            channel: 'facebook',
            payload: [
                'external_user_id'    => 'fb_user_123',
                'external_message_id' => 'msg_001',
                'text'                => 'How do I reset my password?',
                'customer_name'       => 'John Doe',
            ],
        );

        $job->handle();

        // Assert conversation was created
        $this->assertDatabaseHas('conversations', [
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'fb_user_123',
            'status'             => 'open',
        ]);

        // Assert message was saved
        $this->assertDatabaseHas('messages', [
            'external_message_id' => 'msg_001',
            'body'                => 'How do I reset my password?',
            'direction'           => 'inbound',
        ]);
    }

    public function test_job_updates_existing_conversation(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'fb_user_123',
            'customer_name'      => 'Test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'unread_count'       => 0,
        ]);

        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->account->id,
            channel: 'facebook',
            payload: [
                'external_user_id'    => 'fb_user_123',
                'external_message_id' => 'msg_002',
                'text'                => 'Hello again!',
            ],
        );

        $job->handle();

        // Same conversation, new message
        $this->assertDatabaseHas('conversations', [
            'id'            => $conversation->id,
            'last_message'  => 'Hello again!',
            'unread_count'  => 1,
        ]);

        $this->assertDatabaseHas('messages', [
            'external_message_id' => 'msg_002',
            'conversation_id'     => $conversation->id,
        ]);
    }

    public function test_job_ignores_duplicate_messages(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'fb_user_123',
            'customer_name'      => 'Test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // First message
        Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => 'msg_001',
            'body'                => 'Original message',
            'direction'           => 'inbound',
            'type'                => 'text',
        ]);

        // Duplicate incoming
        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->account->id,
            channel: 'facebook',
            payload: [
                'external_user_id'    => 'fb_user_123',
                'external_message_id' => 'msg_001',
                'text'                => 'Original message',
            ],
        );

        $job->handle();

        // Still only 1 message with that external ID
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_job_fires_incoming_message_received_event(): void
    {
        Event::fake();

        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->account->id,
            channel: 'facebook',
            payload: [
                'external_user_id'    => 'fb_user_123',
                'external_message_id' => 'msg_001',
                'text'                => 'Test message',
            ],
        );

        $job->handle();

        Event::assertDispatched(IncomingMessageReceived::class);
    }

    public function test_job_fires_conversation_created_for_new_conversations(): void
    {
        Event::fake();

        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->account->id,
            channel: 'facebook',
            payload: [
                'external_user_id'    => 'new_user_999',
                'external_message_id' => 'msg_001',
                'text'                => 'First message ever',
            ],
        );

        $job->handle();

        Event::assertDispatched(ConversationCreated::class);
    }

    public function test_job_handles_missing_channel_account_gracefully(): void
    {
        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: 99999,
            channel: 'facebook',
            payload: [
                'external_user_id'    => 'fb_user_123',
                'external_message_id' => 'msg_001',
                'text'                => 'Test',
            ],
        );

        // Should not throw
        $job->handle();

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_job_saves_correct_message_metadata(): void
    {
        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->account->id,
            channel: 'facebook',
            payload: [
                'external_user_id'    => 'fb_user_123',
                'external_message_id' => 'msg_001',
                'text'                => 'How do I reset my password?',
                'type'                => 'text',
                'customer_name'       => 'John',
                'customer_avatar'     => 'http://example.com/avatar.jpg',
            ],
        );

        $job->handle();

        $this->assertDatabaseHas('messages', [
            'type'                => 'text',
            'body'                => 'How do I reset my password?',
            'direction'           => 'inbound',
            'external_message_id' => 'msg_001',
        ]);
    }
}
