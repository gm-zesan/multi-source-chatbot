<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Channels\WhatsApp\WhatsAppDriver;
use App\Enums\RoleEnum;
use App\Jobs\ReceiveMessengerWebhookJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppWebhookAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private Channel $whatsappChannel;
    private ChannelAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->workspace = Workspace::create([
            'name'      => 'WhatsApp Retail WS',
            'slug'      => 'wa-retail-ws',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'name'         => 'WhatsApp Store Owner',
            'email'        => 'wa-owner@example.com',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->owner->assignRole(RoleEnum::ADMIN->value);

        $this->whatsappChannel = Channel::firstOrCreate(
            ['slug' => 'whatsapp'],
            ['name' => 'WhatsApp Business', 'is_active' => true]
        );

        $this->whatsappAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->whatsappChannel->id,
            'name'         => 'Official Sales Desk',
            'display_name' => 'Official Sales Desk (+8801700000000)',
            'external_id'  => '109283746592817', // Phone Number ID
            'access_token' => 'EAAB_test_mock_whatsapp_token_99999',
            'settings'     => [
                'phone_number_id' => '109283746592817',
                'waba_id'         => '10009988776655',
            ],
            'is_active'    => true,
        ]);

        config(['services.whatsapp.verify_token' => 'entrepreneurs_automation_whatsapp_webhook']);
    }

    public function test_whatsapp_webhook_handshake_verification_succeeds_with_valid_token(): void
    {
        $response = $this->get('/webhook/whatsapp?' . http_build_query([
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'entrepreneurs_automation_whatsapp_webhook',
            'hub.challenge'    => '7788990011',
        ]));

        $response->assertStatus(200);
        $this->assertSame('7788990011', $response->getContent());
    }

    public function test_whatsapp_webhook_handshake_rejects_invalid_token(): void
    {
        $response = $this->get('/webhook/whatsapp?' . http_build_query([
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'invalid_random_token',
            'hub.challenge'    => '99999',
        ]));

        $response->assertStatus(403);
    }

    public function test_whatsapp_driver_parses_incoming_text_message(): void
    {
        $driver = new WhatsAppDriver();

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry'  => [
                [
                    'id'      => '10009988776655',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata'          => [
                                    'display_phone_number' => '+8801700000000',
                                    'phone_number_id'      => '109283746592817',
                                ],
                                'contacts'          => [
                                    [
                                        'profile' => ['name' => 'Tariq Hasan'],
                                        'wa_id'   => '8801811223344',
                                    ],
                                ],
                                'messages'          => [
                                    [
                                        'from'      => '8801811223344',
                                        'id'        => 'wamid.HBgLODgwMTgxMTIyMzM0FBR',
                                        'timestamp' => '1710000000',
                                        'text'      => ['body' => 'Apnader delivery charge koto?'],
                                        'type'      => 'text',
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $parsed = $driver->parseWebhook($payload);

        $this->assertNotNull($parsed);
        $this->assertSame('8801811223344', $parsed['external_user_id']);
        $this->assertSame('wamid.HBgLODgwMTgxMTIyMzM0FBR', $parsed['external_message_id']);
        $this->assertSame('Apnader delivery charge koto?', $parsed['text']);
        $this->assertSame('Tariq Hasan', $parsed['customer_name']);
        $this->assertSame('text', $parsed['type']);
    }

    public function test_whatsapp_driver_parses_interactive_button_replies(): void
    {
        $driver = new WhatsAppDriver();

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from'        => '8801711223344',
                                        'id'          => 'wamid.BTN01',
                                        'type'        => 'interactive',
                                        'interactive' => [
                                            'type'         => 'button_reply',
                                            'button_reply' => [
                                                'id'    => 'btn_order_confirm',
                                                'title' => 'অর্ডার কনফার্ম করুন',
                                            ],
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

        $this->assertNotNull($parsed);
        $this->assertSame('অর্ডার কনফার্ম করুন', $parsed['text']);
        $this->assertSame('interactive', $parsed['type']);
    }

    public function test_whatsapp_driver_parses_media_attachments(): void
    {
        $driver = new WhatsAppDriver();

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from'  => '8801711223344',
                                        'id'    => 'wamid.IMG01',
                                        'type'  => 'image',
                                        'image' => [
                                            'caption'   => 'Eita stock e ache?',
                                            'mime_type' => 'image/jpeg',
                                            'sha256'    => 'abcdef123456',
                                            'id'        => 'media_id_9999',
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

        $this->assertNotNull($parsed);
        $this->assertSame('Eita stock e ache?', $parsed['text']);
        $this->assertSame('image', $parsed['type']);
        $this->assertCount(1, $parsed['attachments']);
    }

    public function test_webhook_controller_dispatches_job_for_whatsapp_message(): void
    {
        Queue::fake();

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry'  => [
                [
                    'id'      => '10009988776655',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata'          => [
                                    'phone_number_id' => '109283746592817',
                                ],
                                'contacts'          => [
                                    ['profile' => ['name' => 'Fatima Begum']],
                                ],
                                'messages'          => [
                                    [
                                        'from' => '8801912345678',
                                        'id'   => 'wamid.TEST9988',
                                        'text' => ['body' => 'Amar order status bolun'],
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/webhook/whatsapp', $payload);

        $response->assertStatus(200);
        $this->assertSame('EVENT_RECEIVED', $response->getContent());

        Queue::assertPushed(ReceiveMessengerWebhookJob::class, function ($job) {
            return $job->channel === 'whatsapp'
                && $job->channelAccountId === $this->whatsappAccount->id
                && $job->payload['text'] === 'Amar order status bolun'
                && $job->payload['external_user_id'] === '8801912345678';
        });
    }

    public function test_whatsapp_driver_sends_outbound_message_via_graph_api(): void
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/109283746592817/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts'          => [['wa_id' => '8801912345678']],
                'messages'          => [['id' => 'wamid.OUTBOUND_12345']],
            ], 200),
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $this->whatsappAccount->id,
            'external_user_id'   => '8801912345678',
            'customer_name'      => 'Fatima Begum',
            'last_direction'     => 'inbound',
        ]);

        $driver = new WhatsAppDriver();
        $result = $driver->send($this->whatsappAccount, $conversation, 'Dhaka er moddhe delivery charge 70 taka.');

        $this->assertArrayHasKey('messages', $result);
        $this->assertSame('wamid.OUTBOUND_12345', $result['messages'][0]['id']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '109283746592817/messages')
                && $request['to'] === '8801912345678'
                && $request['text']['body'] === 'Dhaka er moddhe delivery charge 70 taka.'
                && $request->header('Authorization')[0] === 'Bearer EAAB_test_mock_whatsapp_token_99999';
        });
    }

    public function test_whatsapp_channel_ping_succeeds(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109283746592817*' => Http::response([
                'verified_name'        => 'Official Sales Desk',
                'display_phone_number' => '+880 1700-000000',
                'quality_rating'       => 'GREEN',
            ], 200),
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('channels.ping', $this->whatsappAccount));

        $response->assertSessionHas('success');
    }
}
