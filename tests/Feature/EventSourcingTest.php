<?php

namespace Tests\Feature;

use App\Events\ConversationCreated;
use App\Events\IncomingMessageReceived;
use App\Listeners\ExtractCRMEntitiesListener;
use App\Listeners\RunFAQEngineListener;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventSourcingTest extends TestCase
{
    use RefreshDatabase;

    private ChannelAccount $account;
    private Conversation $conversation;
    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::create(['name' => 'Test', 'slug' => 'test']);
        $channel = Channel::create(['slug' => 'facebook', 'name' => 'Facebook', 'is_active' => true]);
        $this->account = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id'  => $workspace->id,
            'name'         => 'Test Account',
            'external_id'  => 'test_ext_123',
            'access_token' => 'test_token',
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'fb_user_123',
            'customer_name'      => 'Test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->message = Message::create([
            'conversation_id' => $this->conversation->id,
            'body'            => 'How do I reset my password?',
            'direction'       => 'inbound',
            'type'            => 'text',
        ]);
    }

    public function test_incoming_message_received_has_correct_listeners_registered(): void
    {
        $listeners = Event::getListeners(IncomingMessageReceived::class);

        $this->assertNotEmpty($listeners);
    }

    public function test_incoming_message_received_has_two_listeners(): void
    {
        $listeners = Event::getListeners(IncomingMessageReceived::class);

        // Listeners are wrapped in closures by Laravel; count should be >= 2
        $this->assertGreaterThanOrEqual(2, count($listeners));
    }

    public function test_conversation_created_event_can_be_dispatched(): void
    {
        $dispatched = false;

        Event::listen(ConversationCreated::class, function () use (&$dispatched) {
            $dispatched = true;
        });

        event(new ConversationCreated(
            conversation: $this->conversation,
            rawPayload: [],
        ));

        $this->assertTrue($dispatched);
    }

    public function test_incoming_message_received_carries_correct_data(): void
    {
        /** @var IncomingMessageReceived|null $capturedEvent */
        $capturedEvent = null;

        Event::listen(IncomingMessageReceived::class, function ($event) use (&$capturedEvent) {
            $capturedEvent = $event;
        });

        event(new IncomingMessageReceived(
            conversation: $this->conversation,
            message: $this->message,
            account: $this->account,
            rawPayload: ['some' => 'data'],
        ));

        $this->assertNotNull($capturedEvent);
        $this->assertSame($this->conversation->id, $capturedEvent->conversation->id);
        $this->assertSame($this->message->id, $capturedEvent->message->id);
        $this->assertSame($this->account->id, $capturedEvent->account->id);
        $this->assertSame(['some' => 'data'], $capturedEvent->rawPayload);
    }

    public function test_crm_and_faq_listeners_are_independent(): void
    {
        // This test verifies the listeners are not chained.
        // Each listener handles IncomingMessageReceived independently.

        $crmListener = app(ExtractCRMEntitiesListener::class);
        $faqListener = app(RunFAQEngineListener::class);

        $this->assertNotNull($crmListener);
        $this->assertNotNull($faqListener);

        // They must be separate classes (not chained)
        $this->assertNotSame($crmListener, $faqListener);

        // They should be on different queues
        $crmRef = new \ReflectionClass($crmListener);
        $faqRef = new \ReflectionClass($faqListener);

        $this->assertSame('crm', $crmRef->getProperty('queue')->getValue($crmListener));
        $this->assertSame('faq', $faqRef->getProperty('queue')->getValue($faqListener));
    }

    public function test_run_faq_engine_listener_executes_ai_agent_and_saves_outbound_reply(): void
    {
        \App\AI\Agents\CustomerSupportAgent::fake([
            'Click forgot password on the login screen to reset your password.',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response(['message_id' => 'fb_mid_123'], 200),
        ]);

        $listener = app(RunFAQEngineListener::class);
        $listener->handle(new IncomingMessageReceived(
            conversation: $this->conversation,
            message: $this->message,
            account: $this->account,
            rawPayload: [],
        ));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'body' => 'Click forgot password on the login screen to reset your password.',
        ]);
    }

    public function test_run_faq_engine_listener_delivers_to_channel_driver(): void
    {
        \App\AI\Agents\CustomerSupportAgent::fake([
            'Our standard shipping takes 2-3 business days.',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response([
                'recipient_id' => 'user_123',
                'message_id' => 'm_mid_facebook_reply_999',
            ], 200),
        ]);

        $listener = app(RunFAQEngineListener::class);
        $listener->handle(new IncomingMessageReceived(
            conversation: $this->conversation,
            message: $this->message,
            account: $this->account,
            rawPayload: [],
        ));

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com')
                && str_contains($request['message']['text'], 'Our standard shipping takes 2-3 business days.');
        });
    }
}
