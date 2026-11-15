<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Channels\Instagram\InstagramDriver;
use App\Channels\Website\WebsiteDriver;
use App\Channels\WhatsApp\WhatsAppDriver;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiChannelDriversIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Channel $whatsappChannel;
    private Channel $instagramChannel;
    private Channel $websiteChannel;
    private ChannelAccount $whatsappAccount;
    private ChannelAccount $instagramAccount;
    private ChannelAccount $websiteAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'OmniChannel WS', 'slug' => 'omni-ws']);

        $this->whatsappChannel = Channel::create(['name' => 'WhatsApp', 'slug' => 'whatsapp', 'driver' => 'whatsapp']);
        $this->instagramChannel = Channel::create(['name' => 'Instagram', 'slug' => 'instagram', 'driver' => 'instagram']);
        $this->websiteChannel = Channel::create(['name' => 'Website', 'slug' => 'website', 'driver' => 'website']);

        $this->whatsappAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->whatsappChannel->id,
            'name'         => 'WA Business Account',
            'external_id'  => 'wa_phone_id_999',
            'access_token' => 'wa_test_token',
            'is_active'    => true,
        ]);

        $this->instagramAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->instagramChannel->id,
            'name'         => 'IG Page Account',
            'external_id'  => 'ig_page_id_888',
            'access_token' => 'ig_test_token',
            'is_active'    => true,
        ]);

        $this->websiteAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->websiteChannel->id,
            'name'         => 'Storefront Web Widget',
            'external_id'  => 'widget_key_777',
            'access_token' => 'widget_token',
            'is_active'    => true,
        ]);
    }

    public function test_whatsapp_driver_webhook_verification_and_message_flow(): void
    {
        config(['services.facebook.verify_token' => 'secure_wa_token']);

        $driver = new WhatsAppDriver();

        // 1. Verify Webhook handshake
        $validReq = Request::create('/webhook', 'GET', [
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'secure_wa_token',
            'hub.challenge'    => '1234567890',
        ]);
        $response = $driver->verifyWebhook($validReq);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1234567890', $response->getContent());

        // Invalid token returns 403
        $invalidReq = Request::create('/webhook', 'GET', [
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'wrong_token',
            'hub.challenge'    => '999',
        ]);
        $this->assertSame(403, $driver->verifyWebhook($invalidReq)->getStatusCode());

        // 2. Parse Inbound Webhook Payload
        $waPayload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata'          => ['phone_number_id' => 'wa_phone_id_999'],
                                'contacts'          => [['profile' => ['name' => 'Tariq Customer']]],
                                'messages'          => [
                                    [
                                        'from' => '8801711000000',
                                        'id'   => 'wamid.HBgLODgwMTcxM',
                                        'text' => ['body' => 'What are your store hours?'],
                                    ]
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame('wa_phone_id_999', $driver->extractAccountId($waPayload));

        $parsed = $driver->parseWebhook($waPayload);
        $this->assertNotNull($parsed);
        $this->assertSame('8801711000000', $parsed['external_user_id']);
        $this->assertSame('wamid.HBgLODgwMTcxM', $parsed['external_message_id']);
        $this->assertSame('What are your store hours?', $parsed['text']);
        $this->assertSame('Tariq Customer', $parsed['customer_name']);

        // 3. Send Outbound Message
        Http::fake([
            'https://graph.facebook.com/v20.0/wa_phone_id_999/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts'          => [['wa_id' => '8801711000000']],
                'messages'          => [['id' => 'wamid.OUTBOUND_123']],
            ], 200),
        ]);

        $conversation = new Conversation([
            'channel_account_id' => $this->whatsappAccount->id,
            'external_user_id'   => '8801711000000',
        ]);

        $sendResult = $driver->send($this->whatsappAccount, $conversation, 'We are open 9am to 9pm daily.');
        $this->assertArrayHasKey('messages', $sendResult);
        $this->assertSame('wamid.OUTBOUND_123', $sendResult['messages'][0]['id']);
    }

    public function test_instagram_driver_webhook_and_messaging_flow(): void
    {
        config(['services.facebook.verify_token' => 'secure_ig_token']);

        $driver = new InstagramDriver();

        // 1. Verify Webhook handshake
        $req = Request::create('/webhook', 'GET', [
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'secure_ig_token',
            'hub.challenge'    => 'ig_challenge_abc',
        ]);
        $this->assertSame(200, $driver->verifyWebhook($req)->getStatusCode());

        // 2. Parse Inbound Message
        $igPayload = [
            'entry' => [
                [
                    'id'        => 'ig_page_id_888',
                    'messaging' => [
                        [
                            'sender'    => ['id' => 'igsid_555444'],
                            'recipient' => ['id' => 'ig_page_id_888'],
                            'message'   => [
                                'mid'  => 'm_ig_mid_111',
                                'text' => 'Do you have red dresses in size M?',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame('ig_page_id_888', $driver->extractAccountId($igPayload));

        $parsed = $driver->parseWebhook($igPayload);
        $this->assertNotNull($parsed);
        $this->assertSame('igsid_555444', $parsed['external_user_id']);
        $this->assertSame('m_ig_mid_111', $parsed['external_message_id']);
        $this->assertSame('Do you have red dresses in size M?', $parsed['text']);

        // 3. Send Outbound Message
        Http::fake([
            'https://graph.facebook.com/v20.0/me/messages' => Http::response([
                'recipient_id' => 'igsid_555444',
                'message_id'   => 'mid.IG_OUT_999',
            ], 200),
        ]);

        $conversation = new Conversation([
            'channel_account_id' => $this->instagramAccount->id,
            'external_user_id'   => 'igsid_555444',
        ]);

        $sendResult = $driver->send($this->instagramAccount, $conversation, 'Yes, size M is currently in stock!');
        $this->assertSame('mid.IG_OUT_999', $sendResult['message_id']);
    }

    public function test_website_driver_and_widget_messaging_flow(): void
    {
        $driver = new WebsiteDriver();

        // 1. Verify Webhook
        $req = Request::create('/webhook', 'GET');
        $this->assertSame(200, $driver->verifyWebhook($req)->getStatusCode());

        // 2. Parse Inbound Webhook
        $webPayload = [
            'channel_account_id' => 'widget_key_777',
            'visitor_id'         => 'vis_browser_session_99',
            'visitor_name'       => 'Online Shopper',
            'text'               => 'Is delivery free in Dhaka?',
        ];

        $this->assertSame('widget_key_777', $driver->extractAccountId($webPayload));

        $parsed = $driver->parseWebhook($webPayload);
        $this->assertNotNull($parsed);
        $this->assertSame('vis_browser_session_99', $parsed['external_user_id']);
        $this->assertSame('Is delivery free in Dhaka?', $parsed['text']);
        $this->assertSame('Online Shopper', $parsed['customer_name']);

        // 3. Send Outbound Message
        $conversation = new Conversation([
            'channel_account_id' => $this->websiteAccount->id,
            'external_user_id'   => 'vis_browser_session_99',
        ]);

        $sendResult = $driver->send($this->websiteAccount, $conversation, 'Free delivery for orders over ৳1,000!');
        $this->assertTrue($sendResult['success']);
        $this->assertTrue($sendResult['delivered']);
        $this->assertSame('vis_browser_session_99', $sendResult['recipient_id']);
    }
}
