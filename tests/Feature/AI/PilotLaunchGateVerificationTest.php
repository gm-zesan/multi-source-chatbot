<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Decision\UnifiedOrchestrator;
use App\AI\DTOs\AgentContext;
use App\AI\Tools\CustomerOrderStatusTool;
use App\AI\Tools\SalesAnalyticsTool;
use App\Channels\Facebook\FacebookDriver;
use App\Events\IncomingMessageReceived;
use App\Jobs\ReceiveMessengerWebhookJob;
use App\Listeners\RunFAQEngineListener;
use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PilotLaunchGateVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace1;
    protected Workspace $workspace2;
    protected ChannelAccount $account1;
    protected ChannelAccount $account2;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.rollout_mode' => 'all']);

        $this->workspace1 = Workspace::create([
            'name'      => 'Merchant One Boutique',
            'slug'      => 'merchant-one-boutique',
            'currency'  => 'BDT',
            'is_active' => true,
            'settings'  => [
                'ai_canary_enabled'  => true,
                'ai_kill_switch'     => false,
                'order_auto_capture' => true,
            ],
        ]);

        $this->workspace2 = Workspace::create([
            'name'      => 'Merchant Two Electronics',
            'slug'      => 'merchant-two-electronics',
            'currency'  => 'BDT',
            'is_active' => true,
            'settings'  => [
                'ai_canary_enabled'  => true,
                'ai_kill_switch'     => false,
            ],
        ]);

        $channel = Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook Messenger', 'is_active' => true]);

        $this->account1 = ChannelAccount::create([
            'workspace_id' => $this->workspace1->id,
            'channel_id'   => $channel->id,
            'name'         => 'Merchant 1 Page',
            'external_id'  => 'fb_page_1001',
            'access_token' => 'token_1001',
            'is_active'    => true,
        ]);

        $this->account2 = ChannelAccount::create([
            'workspace_id' => $this->workspace2->id,
            'channel_id'   => $channel->id,
            'name'         => 'Merchant 2 Page',
            'external_id'  => 'fb_page_2002',
            'access_token' => 'token_2002',
            'is_active'    => true,
        ]);
    }

    public function test_gate_6_ai_pause_enforcement_prevents_automated_replies_on_takeover(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->account1->id,
            'external_user_id'   => 'user_pause_test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // Human staff pauses AI for 1 hour
        $conversation->pauseAi(now()->addHour());
        $this->assertTrue($conversation->isAiPaused());

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'আমি অর্ডার করতে চাই',
        ]);

        // 1. UnifiedOrchestrator halts immediately
        $orchestrator = app(UnifiedOrchestrator::class);
        $result = $orchestrator->process(
            query: 'আমি অর্ডার করতে চাই',
            workspaceId: $this->workspace1->id,
            conversation: $conversation,
            channelSlug: 'facebook',
            isCustomer: true
        );

        $this->assertSame('conversation_ai_paused', $result['decision_metadata']['halt_reason'] ?? null);

        // 2. RunFAQEngineListener handles IncomingMessageReceived and strictly bails
        $listener = app(RunFAQEngineListener::class);
        $event = new IncomingMessageReceived(
            conversation: $conversation,
            message: $message,
            account: $this->account1,
            rawPayload: []
        );

        // Listener runs without exception and sends 0 outbound messages
        $listener->handle($event);

        $outboundCount = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->count();
        $this->assertSame(0, $outboundCount, 'AI sent outbound message even though conversation was paused!');
    }

    public function test_gate_2_meta_webhook_signature_verification_rejects_forgery(): void
    {
        Config::set('services.facebook.client_secret', 'test_secret_meta_key_99');

        $driver = new FacebookDriver();
        $rawPayload = json_encode(['object' => 'page', 'entry' => []]);

        // 1. Valid Signature
        $validHash = 'sha256=' . hash_hmac('sha256', $rawPayload, 'test_secret_meta_key_99');
        $validRequest = Request::create('/webhook/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $validHash,
            'CONTENT_TYPE'             => 'application/json',
        ], $rawPayload);

        $this->assertTrue($driver->verifyWebhookSignature($validRequest));

        // 2. Forged / Tampered Signature
        $invalidRequest = Request::create('/webhook/facebook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=forged_bad_hash_123',
            'CONTENT_TYPE'             => 'application/json',
        ], $rawPayload);

        $this->assertFalse($driver->verifyWebhookSignature($invalidRequest));

        // 3. WebhookController endpoint responds with 403 on forged signature
        $response = $this->call('POST', '/webhook/facebook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=forged_bad_hash_123',
            'CONTENT_TYPE'             => 'application/json',
        ], $rawPayload);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_gate_2_duplicate_webhook_mid_is_safely_dropped_without_duplicate_order(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->account1->id,
            'external_user_id'   => 'user_dup_webhook',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $payload = [
            'external_user_id'    => 'user_dup_webhook',
            'external_message_id' => 'mid.meta.message.test.dup.101',
            'text'                => 'আমি ১টা পাঞ্জাবি অর্ডার করব, নাম তানভীর, ফোন 01711998877, ঠিকানা ঢাকা',
            'type'                => 'text',
        ];

        // 1. First Webhook Execution
        $job1 = new ReceiveMessengerWebhookJob($this->account1->id, 'facebook', $payload);
        $job1->handle();

        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->count());

        // 2. Duplicate Webhook Retry with the exact same mid
        $job2 = new ReceiveMessengerWebhookJob($this->account1->id, 'facebook', $payload);
        $job2->handle();

        // Must still be exactly 1 message
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->count());
    }

    public function test_gate_5_bi_revenue_strictly_excludes_pending_and_cancelled_orders(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner = User::factory()->create(['workspace_id' => $this->workspace1->id]);
        $owner->assignRole('owner');

        $cust = AnalyticsCustomer::create(['workspace_id' => $this->workspace1->id, 'name' => 'Cust 1', 'is_active' => true]);
        $seller = AnalyticsSalesperson::create(['workspace_id' => $this->workspace1->id, 'employee_code' => 'SELLER-G5', 'name' => 'Seller 1', 'is_active' => true]);

        $today = Carbon::today()->toDateString();

        // 1. Completed order: ৳5,000 (MUST be in revenue)
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspace1->id,
            'customer_id'    => $cust->id,
            'salesperson_id' => $seller->id,
            'order_number'   => 'ORD-G5-COMPLETED',
            'total_amount'   => 5000.00,
            'discount'       => 0.00,
            'net_amount'     => 5000.00,
            'status'         => 'completed',
            'order_date'     => $today,
        ]);

        // 2. Pending order: ৳12,000 (MUST NOT be in completed revenue)
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspace1->id,
            'customer_id'    => $cust->id,
            'salesperson_id' => $seller->id,
            'order_number'   => 'ORD-G5-PENDING',
            'total_amount'   => 12000.00,
            'discount'       => 0.00,
            'net_amount'     => 12000.00,
            'status'         => 'pending',
            'order_date'     => $today,
        ]);

        // 3. Cancelled order: ৳8,000 (MUST NOT be in completed revenue)
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspace1->id,
            'customer_id'    => $cust->id,
            'salesperson_id' => $seller->id,
            'order_number'   => 'ORD-G5-CANCELLED',
            'total_amount'   => 8000.00,
            'discount'       => 0.00,
            'net_amount'     => 8000.00,
            'status'         => 'cancelled',
            'order_date'     => $today,
        ]);

        $tool = new SalesAnalyticsTool();
        $context = AgentContext::forUser(
            user: $owner,
            workspaceId: $this->workspace1->id,
            permissions: ['analytics.view_financials']
        );

        $result = $tool->execute([
            'start_date' => $today,
            'end_date'   => $today,
        ], $context);

        $this->assertTrue($result->success);
        // Revenue must be EXACTLY 5,000.00, NOT 17,000 or 25,000!
        $this->assertEquals(5000.00, (float) $result->data['total_revenue']);
        $this->assertSame(1, (int) $result->data['total_orders']);
    }

    public function test_gate_7_workspace_isolation_prevents_cross_tenant_order_and_bi_leakage(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner2 = User::factory()->create(['workspace_id' => $this->workspace2->id]);
        $owner2->assignRole('owner');

        // Workspace 1 has an order
        $order1 = WorkspaceOrder::create([
            'workspace_id'     => $this->workspace1->id,
            'order_number'     => 'ORD-WS1-SECRET',
            'channel'          => 'facebook',
            'customer_name'    => 'Confidential Client',
            'customer_phone'   => '01711223344',
            'delivery_address' => 'Secret Villa, Dhaka',
            'product_summary'  => 'Diamond Necklace',
            'order_amount'     => 150000.00,
            'status'           => 'pending',
        ]);

        $conv2 = Conversation::create([
            'channel_account_id' => $this->account2->id,
            'external_user_id'   => 'attacker_ws2',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // Attacker in Workspace 2 tries to lookup Workspace 1's order
        $tool = new CustomerOrderStatusTool();
        $contextWs2 = AgentContext::forCustomer(
            workspaceId: $this->workspace2->id,
            channel: 'facebook',
            conversation: $conv2
        );

        $result = $tool->execute([
            'order_number' => 'ORD-WS1-SECRET',
            'phone'        => '01711223344',
        ], $contextWs2);

        $this->assertTrue($result->success);
        // Must return not found in Workspace 2!
        $this->assertFalse($result->data['found']);
        $this->assertStringContainsString('কোনো অর্ডার খুঁজে পাওয়া যায়নি', $result->data['report']);
    }

    public function test_gate_8_workspace_emergency_kill_switch_immediately_halts_ai(): void
    {
        $this->workspace1->update([
            'settings' => array_merge($this->workspace1->settings ?? [], [
                'ai_kill_switch'   => true,
                'fallback_message' => 'আমাদের সার্ভিস সাময়িকভাবে স্থগিত রয়েছে।',
            ]),
        ]);

        $this->assertTrue($this->workspace1->fresh()->isAiKillSwitchActive());

        $orchestrator = app(UnifiedOrchestrator::class);
        $result = $orchestrator->process(
            query: 'আপনাদের পাঞ্জাবির দাম কত?',
            workspaceId: $this->workspace1->id,
            channelSlug: 'facebook',
            isCustomer: true
        );

        $this->assertSame('workspace_kill_switch_active', $result['decision_metadata']['halt_reason'] ?? null);
        $this->assertStringContainsString('আমাদের সার্ভিস সাময়িকভাবে স্থগিত রয়েছে', $result['reply']);
    }

    public function test_gate_2_database_unique_constraint_enforces_atomicity_and_allows_null_ids(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->account1->id,
            'external_user_id'   => 'user_uniq_test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // 1. First insert with external_message_id succeeds
        Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => 'mid.meta.unique.test.001',
            'direction'           => 'inbound',
            'type'                => 'text',
            'body'                => 'First insertion',
        ]);

        // 2. Second insert with identical external_message_id must throw unique constraint violation
        $duplicateCaught = false;
        try {
            Message::create([
                'conversation_id'     => $conversation->id,
                'external_message_id' => 'mid.meta.unique.test.001',
                'direction'           => 'inbound',
                'type'                => 'text',
                'body'                => 'Duplicate concurrent insertion',
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            $duplicateCaught = true;
        }

        $this->assertTrue($duplicateCaught, 'Database must reject duplicate (conversation_id, external_message_id) at schema level.');

        // 3. Null external_message_ids must NOT collide
        $nullMsg1 = Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => null,
            'direction'           => 'outbound',
            'type'                => 'text',
            'body'                => 'System reply 1',
        ]);

        $nullMsg2 = Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => null,
            'direction'           => 'outbound',
            'type'                => 'text',
            'body'                => 'System reply 2',
        ]);

        $this->assertNotNull($nullMsg1->id);
        $this->assertNotNull($nullMsg2->id);
        $this->assertNotEquals($nullMsg1->id, $nullMsg2->id);
    }

    public function test_gate_2_concurrent_race_in_webhook_job_is_safely_absorbed_without_duplicate_events(): void
    {
        Event::fake([IncomingMessageReceived::class]);

        $conversation = Conversation::create([
            'channel_account_id' => $this->account1->id,
            'external_user_id'   => 'user_concurrent_race_test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $payload = [
            'external_user_id'    => 'user_concurrent_race_test',
            'external_message_id' => 'mid.meta.concurrent.race.888',
            'text'                => 'অর্ডার কনফার্ম করুন',
            'type'                => 'text',
        ];

        // Worker 1 executes
        $job1 = new ReceiveMessengerWebhookJob($this->account1->id, 'facebook', $payload);
        $job1->handle();

        // Worker 2 executes with the same payload (simulating simultaneous worker delivery)
        $job2 = new ReceiveMessengerWebhookJob($this->account1->id, 'facebook', $payload);
        $job2->handle();

        // Database must have exactly 1 message
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('external_message_id', 'mid.meta.concurrent.race.888')->count());

        // Event must be dispatched exactly 1 time across both worker attempts
        Event::assertDispatched(IncomingMessageReceived::class, 1);
    }
}
