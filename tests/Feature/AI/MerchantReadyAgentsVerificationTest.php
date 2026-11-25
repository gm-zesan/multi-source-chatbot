<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AgentContext;
use App\AI\DTOs\DecisionRequest;
use App\AI\DTOs\ToolResult;
use App\AI\Decision\DeterministicExecutionController;
use App\AI\Decision\FinalResponseGenerator;
use App\AI\Decision\UnifiedDecisionEngine;
use App\AI\Decision\UnifiedOrchestrator;
use App\AI\Policies\ToolAuthorizationPolicy;
use App\AI\Tools\BusinessOverviewTool;
use App\AI\Tools\CustomerOrderStatusTool;
use App\AI\Tools\FinancialAnalyticsTool;
use App\AI\Tools\ProductPerformanceTool;
use App\Events\ConversationEscalatedToHuman;
use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Commerce\OrderCaptureService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MerchantReadyAgentsVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected ChannelAccount $account;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.rollout_mode' => 'all']);

        $this->workspace = Workspace::create([
            'name'      => 'Test Merchant Store',
            'slug'      => 'test-merchant-store',
            'currency'  => 'BDT',
            'is_active' => true,
            'settings'  => [
                'order_auto_capture_enabled' => true,
                'ai_canary_enabled'          => true,
            ],
        ]);

        $channel = Channel::firstOrCreate(['slug' => 'website'], ['name' => 'Website', 'is_active' => true]);
        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Web',
            'external_id'  => 'store_ext_1',
            'access_token' => 'token_1',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'customer_user_01',
            'customer_name'      => 'Tanvir Ahmed',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_customer_order_status_tool_resolves_live_workspace_order_with_dlp_protection(): void
    {
        $wsOrder = WorkspaceOrder::create([
            'workspace_id'          => $this->workspace->id,
            'conversation_id'       => $this->conversation->id,
            'order_number'          => 'ORD-261125-99ZZ',
            'channel'               => 'website',
            'customer_name'         => 'Tanvir Ahmed',
            'customer_phone'        => '01711998877',
            'delivery_address'      => 'House 12, Road 5, Dhanmondi, Dhaka',
            'product_summary'       => '1 Piece Premium Panjabi, Size XL',
            'order_amount'          => 2850.00,
            'status'                => 'pending',
            'courier_provider'      => 'steadfast',
            'courier_tracking_code' => 'STF-887711',
            'courier_status'        => 'In Transit',
            'customer_notes'        => 'Urgent delivery requested',
            'admin_notes'           => 'VIP wholesale rate applied - Cost BDT 1400',
        ]);

        $tool = new CustomerOrderStatusTool();
        $context = AgentContext::forCustomer(
            workspaceId: $this->workspace->id,
            channel: 'website',
            conversation: $this->conversation
        );

        $rawResult = $tool->execute(['order_number' => 'ORD-261125-99ZZ'], $context);

        $this->assertTrue($rawResult->success);
        $this->assertTrue($rawResult->data['found']);
        $this->assertSame('live_workspace_order', $rawResult->data['source']);
        $this->assertSame('ORD-261125-99ZZ', $rawResult->data['order_number']);
        $this->assertSame('Pending (অর্ডার প্রক্রিয়াধীন রয়েছে)', $rawResult->data['status']);

        // Test through Central Data Projection Policy (DLP)
        $policy = app(ToolAuthorizationPolicy::class);
        $projected = $policy->projectToolResult($rawResult, $context);

        $this->assertTrue($projected->success);
        $this->assertTrue($projected->data['found']);
        $this->assertSame('ORD-261125-99ZZ', $projected->data['order_number']);
        $this->assertSame('Steadfast', ucfirst((string) $projected->data['courier_provider']));
        $this->assertSame('STF-887711', $projected->data['courier_tracking_code']);
        $this->assertNotEmpty($projected->data['report']);

        // Verify zero leakage of internal admin notes or cost data
        $serialized = json_encode($projected->data);
        $this->assertStringNotContainsString('VIP wholesale rate', $serialized);
        $this->assertStringNotContainsString('Cost BDT 1400', $serialized);
        $this->assertStringNotContainsString('admin_notes', $serialized);
    }

    public function test_sales_agent_auto_order_capture_fastpath_returns_instant_confirmation(): void
    {
        $orchestrator = app(UnifiedOrchestrator::class);
        $inquiry = "আমি ১টা ব্ল্যাক পাঞ্জাবি অর্ডার করব, সাইজ XL, আমার নাম তানভীর, ফোন 01711998877, ঠিকানা বাসা ১২, রোড ৫, ধানমন্ডি, ঢাকা।";

        $tStart = microtime(true);
        $result = $orchestrator->process(
            query: $inquiry,
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
            channelSlug: 'website',
            isCustomer: true
        );
        $elapsedMs = (microtime(true) - $tStart) * 1000;

        $this->assertSame('order_capture', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertStringContainsString('আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে', $result['reply']);
        $this->assertStringContainsString('অর্ডার নম্বর:', $result['reply']);
        $this->assertStringContainsString('01711998877', $result['reply']);

        // Assert order exists in database
        $captured = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01711998877')
            ->first();

        $this->assertNotNull($captured);
        $this->assertSame('তানভীর', $captured->customer_name);
        $this->assertStringContainsString('পাঞ্জাবি', $captured->product_summary);
        $this->assertSame('pending', $captured->status);

        // Assert CRM lifecycle tags were assigned to conversation
        $this->conversation->refresh();
        $this->assertContains('order_placed', $this->conversation->ai_tags);
        $this->assertContains('hot_lead', $this->conversation->ai_tags);
        $this->assertSame('high', $this->conversation->priority);
    }

    public function test_bi_agent_analytics_tools_return_zero_raw_json_leakage(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $owner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $owner->assignRole('owner');

        $context = AgentContext::forUser(
            user: $owner,
            workspaceId: $this->workspace->id,
            permissions: ['analytics.view_financials', 'analytics.view_margins']
        );

        // 1. ProductPerformanceTool
        $productTool = new ProductPerformanceTool();
        $resProduct = $productTool->execute([
            'start_date' => Carbon::today()->subDays(7)->toDateString(),
            'end_date'   => Carbon::today()->toDateString(),
        ], $context);

        $this->assertTrue($resProduct->success);
        $this->assertArrayHasKey('report', $resProduct->data);
        $this->assertStringContainsString('প্রোডাক্ট পারফরম্যান্স', $resProduct->data['report']);

        // 2. BusinessOverviewTool
        $overviewTool = new BusinessOverviewTool();
        $resOverview = $overviewTool->execute([
            'period' => 'today',
        ], $context);

        $this->assertTrue($resOverview->success);
        $this->assertArrayHasKey('report', $resOverview->data);
        $this->assertStringContainsString('ব্যবসায়িক ওভারভিউ রিপোর্ট', $resOverview->data['report']);

        // 3. FinancialAnalyticsTool
        $finTool = new FinancialAnalyticsTool();
        $resFin = $finTool->execute([
            'start_date' => Carbon::today()->subDays(30)->toDateString(),
            'end_date'   => Carbon::today()->toDateString(),
        ], $context);

        $this->assertTrue($resFin->success);
        $this->assertArrayHasKey('report', $resFin->data);
        $this->assertStringContainsString('ফাইন্যান্সিয়াল রিপোর্ট', $resFin->data['report']);
    }

    public function test_customer_order_status_tool_enforces_ownership_and_masks_pii(): void
    {
        $conv2 = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'customer_user_02',
            'customer_name'      => 'Attacker / Stranger',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $order = WorkspaceOrder::create([
            'workspace_id'     => $this->workspace->id,
            'conversation_id'  => $this->conversation->id,
            'order_number'     => 'ORD-261125-SEC1',
            'channel'          => 'website',
            'customer_name'    => 'Tanvir Ahmed',
            'customer_phone'   => '01711998877',
            'delivery_address' => 'House 44, Road 11, Sector 3, Uttara, Dhaka',
            'product_summary'  => 'Exclusive Panjabi',
            'order_amount'     => 3200.00,
            'status'           => 'shipped',
        ]);

        $tool = new CustomerOrderStatusTool();
        $contextOtherCustomer = AgentContext::forCustomer(
            workspaceId: $this->workspace->id,
            channel: 'website',
            conversation: $conv2
        );

        // 1. Cross-session lookup WITHOUT phone fails with security verification requirement
        $resWithoutPhone = $tool->execute(['order_number' => 'ORD-261125-SEC1'], $contextOtherCustomer);
        $this->assertFalse($resWithoutPhone->success);
        $this->assertStringContainsString('নিরাপত্তার স্বার্থে অর্ডারের সাথে নিবন্ধিত মোবাইল নম্বরটি প্রদান করুন', $resWithoutPhone->error);

        // 2. Cross-session lookup WITH matching phone succeeds but masks PII
        $resWithPhone = $tool->execute([
            'order_number' => 'ORD-261125-SEC1',
            'phone'        => '01711998877',
        ], $contextOtherCustomer);

        $this->assertTrue($resWithPhone->success);
        $this->assertTrue($resWithPhone->data['found']);
        $this->assertSame('ORD-261125-SEC1', $resWithPhone->data['order_number']);

        // Assert sensitive house and road numbers are masked
        $this->assertStringNotContainsString('House 44', $resWithPhone->data['delivery_address']);
        $this->assertStringNotContainsString('Road 11', $resWithPhone->data['delivery_address']);
        $this->assertStringContainsString('সংরক্ষিত', $resWithPhone->data['delivery_address']);

        // Assert customer phone is masked
        $this->assertSame('017****8877', $resWithPhone->data['customer_phone']);
    }

    public function test_support_agent_records_real_manager_escalation_in_database(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $manager = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Store Support Manager',
            'email'        => 'manager@store.test',
        ]);
        $manager->assignRole('manager');

        $escalatedConv = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'customer_user_angry',
            'customer_name'      => 'Disgruntled Customer',
            'status'             => 'open',
            'priority'           => 'normal',
            'last_direction'     => 'inbound',
            'last_message'       => 'আমার প্রোডাক্ট নষ্ট এবং ডেলিভারি অনেক দেরি হয়েছে! বাজে সার্ভিস, আমি এখনই ম্যানেজারের সাথে কথা বলতে চাই।',
        ]);

        $leadService = app(\App\Services\AI\LeadIntelligenceService::class);
        $result = $leadService->classifyAndTagConversation($escalatedConv, force: true);

        $escalatedConv->refresh();

        // 1. Database Priority updated to urgent
        $this->assertSame('urgent', $escalatedConv->priority);

        // 2. Assigned to an active manager in the workspace
        $this->assertSame($manager->id, (int) $escalatedConv->assigned_to_user_id);

        // 3. Metadata escalation flags persisted
        $this->assertTrue($escalatedConv->metadata['handoff_to_human'] ?? false);
        $this->assertSame('manager', $escalatedConv->metadata['escalation_level'] ?? null);
        $this->assertNotEmpty($escalatedConv->metadata['escalated_at'] ?? null);

        // 4. Smart AI Tags assigned
        $this->assertContains('complaint', $escalatedConv->ai_tags);
        $this->assertContains('human_requested', $escalatedConv->ai_tags);
    }

    public function test_sales_agent_matches_catalog_and_enforces_idempotency(): void
    {
        $catalogItem = AnalyticsProduct::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'রয়েল সিল্ক পাঞ্জাবি',
            'sku'          => 'ROYAL-SILK-01',
            'unit_price'   => 3500.00,
            'cost_price'   => 1800.00,
            'is_active'    => true,
        ]);

        $orderService = app(OrderCaptureService::class);
        $message = "আমি ১টা রয়েল সিল্ক পাঞ্জাবি অর্ডার করব, সাইজ L, নাম রাফি, ফোন 01822334455, ঠিকানা মিরপুর ১০, ঢাকা";

        // First capture
        $order1 = $orderService->extractAndCapture($this->conversation, $message);
        $this->assertNotNull($order1);
        $this->assertSame('রয়েল সিল্ক পাঞ্জাবি, সাইজ L', $order1->product_summary);
        $this->assertEquals(3500.00, (float) $order1->order_amount);

        // Duplicate retry within 5 minutes (Idempotency Guard)
        $order2 = $orderService->extractAndCapture($this->conversation, $message);
        $this->assertNotNull($order2);
        $this->assertSame($order1->id, $order2->id);

        // Verify database has exactly 1 order row
        $totalOrders = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01822334455')
            ->count();
        $this->assertSame(1, $totalOrders);
    }

    public function test_crm_retention_agent_background_recovery_pipeline(): void
    {
        $abandonedConv = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'abandoned_lead_user',
            'customer_name'      => 'Abandoned Cart Shopper',
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'last_message'       => 'পাঞ্জাবির দাম কত?',
            'last_message_at'    => Carbon::now()->subHours(3),
            'ai_tags'            => ['hot_lead', 'needs_phone', 'price_inquiry'],
            'metadata'           => [],
        ]);

        $recoveryService = app(\App\Services\Retention\AbandonedLeadRecoveryService::class);

        // 1. Pipeline discovery finds eligible abandoned conversation
        $eligible = $recoveryService->findEligibleConversations($this->workspace->id);
        $this->assertTrue($eligible->contains('id', $abandonedConv->id));

        // 2. Automated Follow-up dispatch
        $result = $recoveryService->sendFollowUp($abandonedConv);
        $this->assertTrue($result['success']);

        // 3. Frequency capping recorded in metadata
        $abandonedConv->refresh();
        $this->assertTrue((bool) ($abandonedConv->metadata['abandoned_lead_followed_up'] ?? false));

        // 4. Repeated invocation is safely rejected by frequency cap
        $retryResult = $recoveryService->sendFollowUp($abandonedConv);
        $this->assertFalse($retryResult['success']);
        $this->assertSame('already_followed_up', $retryResult['reason']);
    }

    public function test_bi_agent_deterministic_ground_truth_accuracy_and_isolation(): void
    {
        $wsIsolated = Workspace::create([
            'name'      => 'Isolated Second Merchant',
            'slug'      => 'isolated-second-merchant',
            'currency'  => 'BDT',
            'is_active' => true,
        ]);

        $cust = AnalyticsCustomer::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Verified Customer',
            'phone'        => '01700112233',
            'is_active'    => true,
        ]);

        $seller = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Top Salesperson',
            'employee_code' => 'REP-001',
            'is_active'     => true,
        ]);

        $prodA = AnalyticsProduct::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Item Alpha Panjabi',
            'sku'          => 'ALPHA-01',
            'unit_price'   => 3500.00,
            'cost_price'   => 1500.00,
            'is_active'    => true,
        ]);

        $prodB = AnalyticsProduct::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Item Beta Casual Shirt',
            'sku'          => 'BETA-02',
            'unit_price'   => 1000.00,
            'cost_price'   => 500.00,
            'is_active'    => true,
        ]);

        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        // 1. Ground Truth Order 1: Completed, BDT 3,500 today, Product A (1 pcs)
        $ord1 = AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $cust->id,
            'salesperson_id' => $seller->id,
            'order_number'   => 'ORD-GT-01',
            'total_amount'   => 3500.00,
            'discount'       => 0.00,
            'net_amount'     => 3500.00,
            'status'         => 'completed',
            'order_date'     => $today,
        ]);
        AnalyticsOrderItem::create([
            'order_id'   => $ord1->id,
            'product_id' => $prodA->id,
            'quantity'   => 1,
            'unit_price' => 3500.00,
            'subtotal'   => 3500.00,
        ]);

        // 2. Ground Truth Order 2: Completed, BDT 2,000 yesterday, Product B (2 pcs)
        $ord2 = AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $cust->id,
            'salesperson_id' => $seller->id,
            'order_number'   => 'ORD-GT-02',
            'total_amount'   => 2000.00,
            'discount'       => 0.00,
            'net_amount'     => 2000.00,
            'status'         => 'completed',
            'order_date'     => $yesterday,
        ]);
        AnalyticsOrderItem::create([
            'order_id'   => $ord2->id,
            'product_id' => $prodB->id,
            'quantity'   => 2,
            'unit_price' => 1000.00,
            'subtotal'   => 2000.00,
        ]);

        // 3. Ground Truth Order 3: Pending, BDT 1,200 today (MUST NOT count in completed revenue)
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $cust->id,
            'salesperson_id' => $seller->id,
            'order_number'   => 'ORD-GT-PENDING',
            'total_amount'   => 1200.00,
            'discount'       => 0.00,
            'net_amount'     => 1200.00,
            'status'         => 'pending',
            'order_date'     => $today,
        ]);

        // 4. Ground Truth Order 4: Cancelled, BDT 5,000 today (MUST NOT count in completed revenue)
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $cust->id,
            'salesperson_id' => $seller->id,
            'order_number'   => 'ORD-GT-CANCELLED',
            'total_amount'   => 5000.00,
            'discount'       => 0.00,
            'net_amount'     => 5000.00,
            'status'         => 'cancelled',
            'order_date'     => $today,
        ]);

        // 5. Ground Truth Order 5: Workspace 2 Order, BDT 15,000 today (MUST NOT bleed into Workspace 1)
        AnalyticsOrder::create([
            'workspace_id'   => $wsIsolated->id,
            'customer_id'    => AnalyticsCustomer::create(['workspace_id' => $wsIsolated->id, 'name' => 'Foreign Cust', 'is_active' => true])->id,
            'salesperson_id' => AnalyticsSalesperson::create(['workspace_id' => $wsIsolated->id, 'name' => 'Foreign Seller', 'employee_code' => 'FOR-9', 'is_active' => true])->id,
            'order_number'   => 'ORD-GT-ISOLATED',
            'total_amount'   => 15000.00,
            'discount'       => 0.00,
            'net_amount'     => 15000.00,
            'status'         => 'completed',
            'order_date'     => $today,
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner = User::factory()->create(['workspace_id' => $this->workspace->id]);
        $owner->assignRole('owner');

        $context = AgentContext::forUser(
            user: $owner,
            workspaceId: $this->workspace->id,
            permissions: ['analytics.view_financials', 'analytics.view_margins']
        );

        $salesTool = new \App\AI\Tools\SalesAnalyticsTool();

        // 1. Verify Today's Sales: EXACTLY BDT 3,500.00 and 1 order
        $resToday = $salesTool->execute([
            'start_date' => $today,
            'end_date'   => $today,
        ], $context);

        $this->assertTrue($resToday->success);
        $this->assertEquals(3500.00, $resToday->data['total_revenue']);
        $this->assertSame(1, $resToday->data['total_orders']);

        // 2. Verify Yesterday's Sales: EXACTLY BDT 2,000.00 and 1 order
        $resYesterday = $salesTool->execute([
            'start_date' => $yesterday,
            'end_date'   => $yesterday,
        ], $context);

        $this->assertTrue($resYesterday->success);
        $this->assertEquals(2000.00, $resYesterday->data['total_revenue']);
        $this->assertSame(1, $resYesterday->data['total_orders']);

        // 3. Verify Product Performance: Item Alpha is #1 with 3500 revenue
        $productTool = new ProductPerformanceTool();
        $resProduct = $productTool->execute([
            'start_date' => $yesterday,
            'end_date'   => $today,
            'limit'      => 5,
        ], $context);

        $this->assertTrue($resProduct->success);
        $this->assertNotEmpty($resProduct->data['products']);
        $topProduct = $resProduct->data['products'][0];
        $this->assertSame('Item Alpha Panjabi', $topProduct['name']);
        $this->assertEquals(3500.00, $topProduct['revenue']);
        $this->assertSame(1, $topProduct['units_sold']);
    }

    public function test_sales_agent_allows_legitimate_second_order_for_different_product_within_5_minutes(): void
    {
        $orderService = app(OrderCaptureService::class);
        $message1 = "আমি ১টা পাঞ্জাবি অর্ডার করব, সাইজ XL, নাম তানভীর, ফোন 01711998877, ঠিকানা ঢাকা";
        $order1 = $orderService->extractAndCapture($this->conversation, $message1);
        $this->assertNotNull($order1);

        // Immediate second order for different product
        $message2 = "আমি ১টা শার্টও নিতে চাই, সাইজ M, নাম তানভীর, ফোন 01711998877, ঠিকানা ঢাকা";
        $order2 = $orderService->extractAndCapture($this->conversation, $message2);
        $this->assertNotNull($order2);

        $this->assertNotSame($order1->id, $order2->id);
        $this->assertNotSame($order1->order_number, $order2->order_number);
        $this->assertStringContainsString('পাঞ্জাবি', $order1->product_summary);
        $this->assertStringContainsString('শার্ট', $order2->product_summary);

        // Retry of message 1 is deduplicated to order1
        $retry1 = $orderService->extractAndCapture($this->conversation, $message1);
        $this->assertSame($order1->id, $retry1->id);

        $ordersCount = WorkspaceOrder::where('workspace_id', $this->workspace->id)
            ->where('customer_phone', '01711998877')
            ->count();
        $this->assertSame(2, $ordersCount);
    }

    public function test_customer_order_status_tool_rate_limits_after_5_failed_attempts(): void
    {
        $tool = new CustomerOrderStatusTool();
        $context = AgentContext::forCustomer(
            workspaceId: $this->workspace->id,
            channel: 'website',
            conversation: $this->conversation
        );

        // 5 consecutive invalid lookups
        for ($i = 1; $i <= 5; $i++) {
            $res = $tool->execute(['order_number' => 'ORD-INVALID-' . $i], $context);
            $this->assertTrue($res->success);
            $this->assertFalse($res->data['found']);
        }

        // 6th attempt must trigger rate limit lockout
        $res6 = $tool->execute(['order_number' => 'ORD-INVALID-6'], $context);
        $this->assertFalse($res6->success);
        $this->assertStringContainsString('Rate Limit Exceeded', $res6->error);
    }

    public function test_support_agent_dispatches_conversation_escalated_to_human_event(): void
    {
        Event::fake([ConversationEscalatedToHuman::class]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create(['workspace_id' => $this->workspace->id]);
        $manager->assignRole('manager');

        $escalatedConv = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_escalate_event',
            'customer_name'      => 'Angry Buyer',
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'last_message'       => 'ভুল প্রোডাক্ট পাঠাইছেন, অভিযোগ করতে চাই, ম্যানেজারের সাথে কথা বলব।',
        ]);

        $leadService = app(\App\Services\AI\LeadIntelligenceService::class);
        $leadService->classifyAndTagConversation($escalatedConv, force: true);

        $escalatedConv->refresh();
        $this->assertSame('urgent', $escalatedConv->priority);
        $this->assertSame('dispatched', $escalatedConv->metadata['manager_notification_status'] ?? null);

        Event::assertDispatched(ConversationEscalatedToHuman::class, function ($event) use ($escalatedConv, $manager) {
            return $event->conversation->id === $escalatedConv->id
                && $event->manager?->id === $manager->id
                && $event->priority === 'urgent'
                && $event->reason === 'customer_complaint';
        });
    }

    public function test_crm_retention_agent_respects_opt_out_consent_and_records_retention_history(): void
    {
        $recoveryService = app(\App\Services\Retention\AbandonedLeadRecoveryService::class);

        // 1. Opted-out customer is excluded from discovery
        $optedOutConv = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'opted_out_lead',
            'customer_name'      => 'Opted Out User',
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'last_message'       => 'দাম কত?',
            'last_message_at'    => Carbon::now()->subHours(3),
            'ai_tags'            => ['hot_lead', 'needs_phone'],
            'metadata'           => ['opted_out_marketing' => true],
        ]);

        $eligible = $recoveryService->findEligibleConversations($this->workspace->id);
        $this->assertFalse($eligible->contains('id', $optedOutConv->id));

        $directResult = $recoveryService->sendFollowUp($optedOutConv);
        $this->assertFalse($directResult['success']);
        $this->assertSame('customer_opted_out', $directResult['reason']);

        // 2. Opted-in customer receives follow-up and retention_history is recorded
        $normalConv = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'normal_lead',
            'customer_name'      => 'Normal User',
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'last_message'       => 'পাঞ্জাবি পছন্দ হয়েছে',
            'last_message_at'    => Carbon::now()->subHours(3),
            'ai_tags'            => ['hot_lead'],
            'metadata'           => [],
        ]);

        $sendResult = $recoveryService->sendFollowUp($normalConv);
        $this->assertTrue($sendResult['success']);

        $normalConv->refresh();
        $this->assertTrue($normalConv->metadata['abandoned_lead_followed_up']);
        $this->assertIsArray($normalConv->metadata['retention_history'] ?? null);
        $history = $normalConv->metadata['retention_history'];
        $this->assertNotEmpty($history);
        $this->assertSame(1, $history[0]['attempt']);
        $this->assertSame('delivered', $history[0]['status']);
    }
}
