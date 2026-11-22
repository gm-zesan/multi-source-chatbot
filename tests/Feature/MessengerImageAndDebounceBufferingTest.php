<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Channels\Facebook\FacebookDriver;
use App\Events\IncomingMessageReceived;
use App\Jobs\ReceiveMessengerWebhookJob;
use App\Listeners\RunFAQEngineListener;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessengerImageAndDebounceBufferingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Channel $facebookChannel;
    private ChannelAccount $facebookAccount;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake();

        $this->workspace = Workspace::forceCreate([
            'id'            => 777001,
            'name'          => 'Smart Gadgets Store',
            'slug'          => 'smart-gadgets-store',
            'support_phone' => '01711223344',
            'is_active'     => true,
        ]);

        $this->facebookChannel = Channel::firstOrCreate(
            ['slug' => 'facebook'],
            ['name' => 'Facebook Messenger', 'type' => 'social', 'is_active' => true]
        );

        $this->facebookAccount = ChannelAccount::forceCreate([
            'id'           => 777101,
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->facebookChannel->id,
            'name'         => 'Smart Gadgets FB Page',
            'external_id'  => 'fb_page_777001',
            'access_token' => 'tok_fb_page_secret',
            'is_active'    => true,
        ]);
    }

    public function test_facebook_driver_parses_image_attachment_without_text(): void
    {
        $driver = new FacebookDriver();

        $payload = [
            'entry' => [
                [
                    'id' => 'fb_page_777001',
                    'messaging' => [
                        [
                            'sender'    => ['id' => 'psid_customer_999'],
                            'recipient' => ['id' => 'fb_page_777001'],
                            'timestamp' => 1700000000000,
                            'message'   => [
                                'mid'         => 'mid_attachment_slip_001',
                                'attachments' => [
                                    [
                                        'type'    => 'image',
                                        'payload' => [
                                            'url' => 'https://cdn.facebook.com/bkash_receipt_slip.png',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $parsed = $driver->parseWebhook($payload);

        $this->assertNotNull($parsed, 'FacebookDriver must not drop attachment payloads when text is missing.');
        $this->assertSame('psid_customer_999', $parsed['external_user_id']);
        $this->assertSame('mid_attachment_slip_001', $parsed['external_message_id']);
        $this->assertSame('image', $parsed['type']);
        $this->assertSame('[Attachment: Image]', $parsed['text']);
        $this->assertCount(1, $parsed['attachments']);
    }

    public function test_receive_messenger_job_persists_image_and_flags_priority(): void
    {
        $payload = [
            'external_user_id'    => 'psid_customer_receipt_user',
            'external_message_id' => 'mid_img_receipt_123',
            'type'                => 'image',
            'text'                => '[Attachment: Image]',
            'attachments'         => [
                ['type' => 'image', 'payload' => ['url' => 'https://cdn.facebook.com/bkash_receipt.png']],
            ],
        ];

        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->facebookAccount->id,
            channel: 'facebook',
            payload: $payload,
        );

        $job->handle();

        $conversation = Conversation::where('channel_account_id', $this->facebookAccount->id)
            ->where('external_user_id', 'psid_customer_receipt_user')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame('high', $conversation->priority);
        $this->assertContains('media_received', (array) $conversation->ai_tags);

        $message = Message::where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('image', $message->type);
        $this->assertSame('[Attachment: Image]', $message->body);
    }

    public function test_run_faq_listener_acknowledges_image_promptly(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->facebookAccount->id,
            'external_user_id'   => 'psid_media_sender_444',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $message = Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => 'mid_media_msg_555',
            'direction'           => 'inbound',
            'type'                => 'image',
            'body'                => '[Attachment: Image]',
        ]);

        $event = new IncomingMessageReceived(
            conversation: $conversation,
            message: $message,
            account: $this->facebookAccount,
            rawPayload: [],
        );

        $listener = app(RunFAQEngineListener::class);
        $listener->handle($event);

        $outboundReply = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->first();

        $this->assertNotNull($outboundReply, 'Outbound media acknowledgment message must be saved.');
        $this->assertStringContainsString('ছবিটি/পেমেন্ট স্লিপটি গ্রহণ করা হয়েছে', $outboundReply->body);
    }

    public function test_rapid_burst_messages_are_buffered(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->facebookAccount->id,
            'external_user_id'   => 'psid_burst_customer_777',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $msg1 = Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => 'mid_burst_1',
            'direction'           => 'inbound',
            'type'                => 'text',
            'body'                => 'Hello, apnader hotline number koto?',
        ]);

        $msg2 = Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => 'mid_burst_2',
            'direction'           => 'inbound',
            'type'                => 'text',
            'body'                => 'R delivery charge koto pore?',
        ]);

        // Simulate buffer already updated with msg2 as latest before msg1 processes
        $convId = $conversation->id;
        Cache::put("conv_debounce_latest_{$convId}", $msg2->id, 30);
        Cache::put("conv_debounce_msgs_{$convId}", [
            ['id' => $msg1->id, 'text' => $msg1->body],
            ['id' => $msg2->id, 'text' => $msg2->body],
        ], 30);

        $listener = app(RunFAQEngineListener::class);

        // Run msg1 event - it should detect it is superseded and not fire duplicate response
        $event1 = new IncomingMessageReceived($conversation, $msg1, $this->facebookAccount, []);
        $listener->handle($event1);

        $this->assertSame(0, Message::where('conversation_id', $conversation->id)->where('direction', 'outbound')->count());

        // Now run msg2 event (the tail of the burst)
        $event2 = new IncomingMessageReceived($conversation, $msg2, $this->facebookAccount, []);
        $listener->handle($event2);

        // Exactly ONE outbound response was created for the burst!
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('direction', 'outbound')->count());
    }
}
