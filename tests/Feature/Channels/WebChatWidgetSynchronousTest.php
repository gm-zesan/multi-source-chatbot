<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Models\WorkspaceUsageQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebChatWidgetSynchronousTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Channel $websiteChannel;
    private ChannelAccount $websiteAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Widget Test Workspace',
            'slug' => 'widget-ws',
        ]);

        $this->websiteChannel = Channel::create([
            'name'   => 'Website',
            'slug'   => 'website',
            'driver' => 'website',
            'is_active' => true,
        ]);

        $this->websiteAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->websiteChannel->id,
            'name'         => 'Storefront Widget Account',
            'external_id'  => 'widget_acc_1001',
            'access_token' => 'widget_token_abc',
            'is_active'    => true,
        ]);

        // Mock LLM API call to return a predictable response
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello! Welcome to our store. How may I assist you today?',
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_visitor_can_send_message_and_receive_synchronous_json_reply(): void
    {
        $payload = [
            'channel'            => 'website',
            'channel_account_id' => $this->websiteAccount->id,
            'visitor_id'         => 'vis_session_9999',
            'visitor_name'       => 'Sultana Begum',
            'text'               => 'Do you deliver outside Dhaka?',
        ];

        $response = $this->postJson(route('widget.chat'), $payload);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'reply',
            'conversation_id',
            'timestamp',
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('reply'));

        // Verify conversation was persisted
        $conversation = Conversation::where('external_user_id', 'vis_session_9999')->first();
        $this->assertNotNull($conversation);
        $this->assertSame($this->workspace->id, $conversation->channelAccount->workspace_id);

        // Verify inbound message was stored
        $inboundMsg = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->first();
        $this->assertNotNull($inboundMsg);
        $this->assertSame('Do you deliver outside Dhaka?', $inboundMsg->body);
    }

    public function test_api_v1_widget_chat_endpoint_also_works(): void
    {
        $payload = [
            'visitor_id'   => 'vis_api_123',
            'visitor_name' => 'API Visitor',
            'text'         => 'What are your working hours?',
            'workspace_id' => $this->workspace->id,
        ];

        $response = $this->postJson('/api/v1/widget/chat', $payload);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('reply'));
    }

    public function test_widget_enforces_saas_quota_limits(): void
    {
        // Set usage to exceed quota
        $quota = WorkspaceUsageQuota::currentForWorkspace($this->workspace->id);
        $quota->update([
            'conversation_count' => 1000,
            'conversation_quota' => 1000,
            'is_unlimited'       => false,
        ]);

        $payload = [
            'channel_account_id' => $this->websiteAccount->id,
            'visitor_id'         => 'vis_quota_blocked',
            'text'               => 'Hello anyone there?',
        ];

        $response = $this->postJson(route('widget.chat'), $payload);

        $response->assertStatus(429);
        $this->assertFalse($response->json('success'));
        $this->assertTrue($response->json('quota_exceeded'));
    }

    public function test_consecutive_messages_from_same_visitor_reuse_conversation(): void
    {
        $payload1 = [
            'channel_account_id' => $this->websiteAccount->id,
            'visitor_id'         => 'vis_repeat_01',
            'text'               => 'Message 1',
        ];

        $res1 = $this->postJson(route('widget.chat'), $payload1);
        $res1->assertOk();
        $convId1 = $res1->json('conversation_id');

        $payload2 = [
            'channel_account_id' => $this->websiteAccount->id,
            'visitor_id'         => 'vis_repeat_01',
            'text'               => 'Message 2',
        ];

        $res2 = $this->postJson(route('widget.chat'), $payload2);
        $res2->assertOk();
        $convId2 = $res2->json('conversation_id');

        $this->assertSame($convId1, $convId2);
        $this->assertEquals(2, Message::where('conversation_id', $convId1)->where('direction', 'inbound')->count());
    }

    public function test_widget_js_script_is_served_with_correct_utf8_headers(): void
    {
        $response = $this->get('/widget/chat.js');
        $response->assertOk();
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('charset=utf-8', strtolower($response->headers->get('Content-Type') ?? ''));

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertStringContainsString('agy-chat-bubble', $content);
        $this->assertStringContainsString('\u0986\u09b8\u09b8\u09be\u09b2\u09be\u09ae\u09c1', $content);
    }
}
