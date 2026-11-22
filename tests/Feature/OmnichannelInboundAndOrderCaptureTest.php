<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Decision\UnifiedOrchestrator;
use App\AI\Orchestration\CustomerSupportService;
use App\Events\IncomingMessageReceived;
use App\Jobs\ReceiveMessengerWebhookJob;
use App\Listeners\RunFAQEngineListener;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Option 2: Omnichannel Inbound Webhooks & Automated Order/Lead Capture Test.
 *
 * Verifies end-to-end multi-channel customer ordering and lead generation:
 * 1. Web Chat Widget (synchronous HTTP endpoint /widget/chat)
 * 2. Facebook Messenger (ReceiveMessengerWebhookJob -> IncomingMessageReceived -> RunFAQEngineListener)
 * 3. WhatsApp Inbound pipeline (IncomingMessageReceived -> RunFAQEngineListener)
 * 4. Deduplication safeguards (same phone + conversation within 1 hour updates order)
 * 5. Strict multi-tenant isolation of captured orders
 */
class OmnichannelInboundAndOrderCaptureTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Workspace $otherWorkspace;
    private Channel $websiteChannel;
    private Channel $facebookChannel;
    private Channel $whatsappChannel;
    private ChannelAccount $websiteAccount;
    private ChannelAccount $facebookAccount;
    private ChannelAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(); // Prevent external network requests during channel delivery

        // Primary Merchant Workspace
        $this->workspace = Workspace::forceCreate([
            'id'            => 666001,
            'name'          => 'Aroma E-Commerce Store',
            'slug'          => 'aroma-ecom-store',
            'support_phone' => '01911223344',
            'is_active'     => true,
            'settings'      => [
                'order_auto_capture_enabled' => true,
            ],
        ]);

        // Separate Merchant Workspace for Isolation Testing
        $this->otherWorkspace = Workspace::forceCreate([
            'id'            => 666999,
            'name'          => 'Competitor Store',
            'slug'          => 'competitor-store',
            'is_active'     => true,
        ]);

        // Channels
        $this->websiteChannel = Channel::firstOrCreate(
            ['slug' => 'website'],
            ['name' => 'Website', 'is_active' => true]
        );

        $this->facebookChannel = Channel::firstOrCreate(
            ['slug' => 'facebook'],
            ['name' => 'Facebook Messenger', 'is_active' => true]
        );

        $this->whatsappChannel = Channel::firstOrCreate(
            ['slug' => 'whatsapp'],
            ['name' => 'WhatsApp', 'is_active' => true]
        );

        // Merchant Channel Accounts
        $this->websiteAccount = ChannelAccount::forceCreate([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->websiteChannel->id,
            'name'         => 'Aroma Web Widget',
            'external_id'  => 'aroma_widget_666',
            'access_token' => 'tok_widget_public',
            'is_active'    => true,
        ]);

        $this->facebookAccount = ChannelAccount::forceCreate([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->facebookChannel->id,
            'name'         => 'Aroma Facebook Page',
            'external_id'  => 'page_aroma_fb_666',
            'access_token' => 'tok_fb_page_token',
            'is_active'    => true,
        ]);

        $this->whatsappAccount = ChannelAccount::forceCreate([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->whatsappChannel->id,
            'name'         => 'Aroma WhatsApp Business',
            'external_id'  => 'wa_aroma_666',
            'access_token' => 'tok_wa_biz_token',
            'is_active'    => true,
        ]);
    }

    /**
     * Test 1: Inbound message via Web Chat Widget automatically creates a WorkspaceOrder.
     */
    public function test_web_chat_widget_captures_customer_order(): void
    {
        $response = $this->postJson('/widget/chat', [
            'channel_account_id' => $this->websiteAccount->id,
            'visitor_id'         => 'visitor_nadim_99',
            'visitor_name'       => 'তানভীর রহমান',
            'text'               => "আমার নাম তানভীর রহমান, ফোন: 01712345678, ঠিকানা: বাড়ি ১২, রোড ৫, মিরপুর ১০, ঢাকা। আমি ১টা Jamdani Sharee নিতে চাই।",
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verify WorkspaceOrder in DB
        $order = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01712345678')
            ->first();

        $this->assertNotNull($order, 'WorkspaceOrder record was not created for Web Chat customer.');
        $this->assertSame('website', $order->channel);
        $this->assertStringContainsString('তানভীর রহমান', $order->customer_name);
        $this->assertSame('01712345678', $order->customer_phone);
        $this->assertStringContainsString('মিরপুর ১০', (string) $order->delivery_address);
        $this->assertStringContainsString('Jamdani Sharee', (string) $order->product_summary);
        $this->assertSame('pending', $order->status);
    }

    /**
     * Test 2: Inbound message via Facebook Messenger webhook job pipeline creates a WorkspaceOrder.
     */
    public function test_facebook_messenger_webhook_job_captures_customer_order(): void
    {
        // 1. Simulate Messenger webhook payload
        $messengerPayload = [
            'external_user_id'    => 'psid_fahim_12345',
            'external_message_id' => 'mid_fb_msg_888999',
            'text'                => "নাম: ফাহিম আহমেদ, মোবাইল: 01811223344, ঠিকানা: বাসা ৪৫, সেক্টর ৩, উত্তরা, ঢাকা। ১টি Silk Panjabi অর্ডার করতে চাই",
            'type'                => 'text',
        ];

        // 2. Dispatch the Messenger Webhook Job synchronously
        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->facebookAccount->id,
            channel: 'facebook',
            payload: $messengerPayload,
        );
        $job->handle();

        // 3. Find created conversation and message
        $conversation = Conversation::where('channel_account_id', $this->facebookAccount->id)
            ->where('external_user_id', 'psid_fahim_12345')
            ->first();

        $this->assertNotNull($conversation, 'Conversation was not created by ReceiveMessengerWebhookJob.');

        $message = Message::where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($message, 'Inbound message was not saved.');

        // 4. Run the FAQ/CustomerSupport Listener that handles IncomingMessageReceived
        $event = new IncomingMessageReceived(
            conversation: $conversation,
            message: $message,
            account: $this->facebookAccount,
            rawPayload: $messengerPayload,
        );

        $listener = app(RunFAQEngineListener::class);
        $listener->handle($event);

        // 5. Verify order capture in database
        $order = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01811223344')
            ->first();

        $this->assertNotNull($order, 'WorkspaceOrder was not captured via Facebook Messenger webhook.');
        $this->assertSame('facebook', $order->channel);
        $this->assertStringContainsString('ফাহিম আহমেদ', $order->customer_name);
        $this->assertSame('01811223344', $order->customer_phone);
        $this->assertStringContainsString('উত্তরা, ঢাকা', (string) $order->delivery_address);
        $this->assertStringContainsString('Silk Panjabi', (string) $order->product_summary);
        $this->assertSame('pending', $order->status);
    }

    /**
     * Test 3: Inbound message via WhatsApp pipeline automatically creates a WorkspaceOrder.
     */
    public function test_whatsapp_inbound_pipeline_captures_customer_order(): void
    {
        $conversation = Conversation::forceCreate([
            'channel_account_id' => $this->whatsappAccount->id,
            'external_user_id'   => 'wa_id_samira_01',
            'customer_name'      => 'Samira Khan',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $message = Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => 'wa_msg_id_101',
            'direction'           => 'inbound',
            'type'                => 'text',
            'body'                => "Customer Name: Samira Khan, Phone: 01999887766, Address: House 10, Road 2, Dhanmondi, Dhaka. I want to buy 1 Terracotta Clay Pot",
            'status'              => 'delivered',
        ]);

        $event = new IncomingMessageReceived(
            conversation: $conversation,
            message: $message,
            account: $this->whatsappAccount,
            rawPayload: ['text' => $message->body],
        );

        $listener = app(RunFAQEngineListener::class);
        $listener->handle($event);

        $order = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01999887766')
            ->first();

        $this->assertNotNull($order, 'WorkspaceOrder was not captured via WhatsApp pipeline.');
        $this->assertSame('whatsapp', $order->channel);
        $this->assertStringContainsString('Samira Khan', $order->customer_name);
        $this->assertSame('01999887766', $order->customer_phone);
        $this->assertStringContainsString('Dhanmondi, Dhaka', (string) $order->delivery_address);
        $this->assertSame('pending', $order->status);
    }

    /**
     * Test 4: Deduplication logic prevents duplicate orders within 1 hour, updating existing order instead.
     */
    public function test_order_deduplication_updates_existing_record_within_one_hour(): void
    {
        // First order submission
        $this->postJson('/widget/chat', [
            'channel_account_id' => $this->websiteAccount->id,
            'visitor_id'         => 'visitor_dedup_01',
            'visitor_name'       => 'Ashiqur Rahman',
            'text'               => "নাম: আশিকুর রহমান, ফোন: 01755443322. ১টি Jamdani Sharee নিতে চাই।",
        ]);

        $initialCount = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01755443322')
            ->count();
        $this->assertSame(1, $initialCount);

        // Second submission 5 minutes later with address update
        $this->postJson('/widget/chat', [
            'channel_account_id' => $this->websiteAccount->id,
            'visitor_id'         => 'visitor_dedup_01',
            'visitor_name'       => 'Ashiqur Rahman',
            'text'               => "আমার ফোন 01755443322, আমার ঠিকানা বাড়ি ৭, রোড ৩, বনানী, ঢাকা।",
        ]);

        // Total order count should STILL be 1 (no duplicate order created)
        $subsequentCount = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01755443322')
            ->count();
        $this->assertSame(1, $subsequentCount, 'Duplicate WorkspaceOrder was incorrectly created instead of deduplicated.');

        // Address should have been updated on the existing record
        $order = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01755443322')
            ->first();
        $this->assertStringContainsString('বনানী, ঢাকা', (string) $order->delivery_address);
    }

    /**
     * Test 5: Strict multi-tenant isolation ensures orders are scoped strictly to the owning workspace.
     */
    public function test_merchant_order_multi_tenant_isolation(): void
    {
        // Create order in Primary Merchant
        $this->postJson('/widget/chat', [
            'channel_account_id' => $this->websiteAccount->id,
            'visitor_id'         => 'visitor_tenant_01',
            'visitor_name'       => 'Customer Isolated',
            'text'               => "নাম: আইসোলেটেড গ্রাহক, ফোন: 01300112233, ঠিকানা: খুলনা। ১টি সিল্ক পাঞ্জাবি নিতে চাই",
        ]);

        // Primary workspace must see the order
        $primaryOrders = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01300112233')
            ->get();
        $this->assertCount(1, $primaryOrders);

        // Competitor workspace MUST NOT see the order
        $competitorOrders = WorkspaceOrder::where('workspace_id', $this->otherWorkspace->id)
            ->where('customer_phone', '01300112233')
            ->get();
        $this->assertCount(0, $competitorOrders, 'Multi-tenant leak: Competitor workspace saw primary workspace order.');
    }
}
