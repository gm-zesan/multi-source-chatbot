<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Decision\UnifiedOrchestrator;
use App\AI\Tools\CustomerOrderStatusTool;
use App\AI\DTOs\AgentContext;
use App\Events\ConversationEscalatedToHuman;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Commerce\OrderCaptureService;
use App\Services\Retention\AbandonedLeadRecoveryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

echo "=== AUDITING MERCHANT-READY 4-AGENT WORKFLOWS ===\n\n";

$ws = Workspace::firstOrCreate(
    ['id' => 1],
    [
        'name' => 'Fashion Fusion Merchant',
        'currency' => 'BDT',
        'is_active' => true,
        'settings' => [
            'ai_canary_enabled' => true,
            'ai_kill_switch' => false,
            'order_auto_capture' => true,
            'delivery_charge_inside_dhaka' => 60,
            'delivery_charge_outside_dhaka' => 120,
        ],
    ]
);

$channel = Channel::firstOrCreate(['slug' => 'website'], ['name' => 'Website', 'is_active' => true]);
$account = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws->id, 'channel_id' => $channel->id],
    ['name' => 'Storefront Web', 'external_id' => 'storefront_web_1', 'access_token' => 'tok_1', 'is_active' => true]
);

$conversation = Conversation::firstOrCreate(
    ['channel_account_id' => $account->id, 'external_user_id' => 'cust_tanvir_01'],
    ['status' => 'open', 'customer_name' => 'Tanvir Ahmed', 'last_direction' => 'inbound']
);

$orchestrator = app(UnifiedOrchestrator::class);
$orderCapture = app(OrderCaptureService::class);

// -------------------------------------------------------------
// 1. AI SALES AGENT: Product Inquiry, Idempotency & Auto Order Capture
// -------------------------------------------------------------
echo ">>> 1. Testing AI Sales Agent Workflow\n";
$inquiry = "আমি ১টা ব্ল্যাক পাঞ্জাবি অর্ডার করব, সাইজ XL, আমার নাম তানভীর, ফোন 01711998877, ঠিকানা বাসা ১২, রোড ৫, ধানমন্ডি, ঢাকা।";

echo "Customer Query: \"{$inquiry}\"\n";
$t0 = microtime(true);
$salesResult = $orchestrator->process(
    query: $inquiry,
    workspaceId: $ws->id,
    conversation: $conversation,
    channelSlug: 'website',
    isCustomer: true
);
$elapsedSales = round((microtime(true) - $t0) * 1000, 2);

echo "Sales Agent Output:\n";
echo "  Route: " . ($salesResult['route'] ?? 'unknown') . "\n";
echo "  Answered: " . ($salesResult['answered'] ? 'true' : 'false') . "\n";
echo "  Duration: {$elapsedSales} ms\n";
echo "  Reply Preview: " . mb_substr($salesResult['reply'] ?? '', 0, 150) . "...\n";

// Check if OrderCaptureService captured the order
$capturedOrder = $orderCapture->extractAndCapture($conversation, $inquiry, $salesResult['reply'] ?? '');
if ($capturedOrder) {
    echo "  [SUCCESS] Order Captured: Order #{$capturedOrder->order_number}\n";
    echo "    Customer: {$capturedOrder->customer_name}\n";
    echo "    Phone: {$capturedOrder->customer_phone}\n";
    echo "    Address: {$capturedOrder->delivery_address}\n";
    echo "    Product: {$capturedOrder->product_summary}\n";
    echo "    Status: {$capturedOrder->status}\n";
} else {
    echo "  [INFO] OrderCaptureService returned null.\n";
}

// 1.1 Test Duplicate Network Retry Idempotency
echo "\n  [TEST 1.1] Testing duplicate retry of identical order within 5 minutes...\n";
$retryOrder = $orderCapture->extractAndCapture($conversation, $inquiry);
$isDeduplicated = ($retryOrder && $capturedOrder && $retryOrder->id === $capturedOrder->id);
echo "  Duplicate Retry: " . ($isDeduplicated ? "[SUCCESS] Deduplicated (returned Order #{$retryOrder->id}, zero extra DB rows)" : "[BUG] Duplicate order row created") . "\n";

// 1.2 Test Legitimate Second Purchase for a Different Product within 5 minutes
echo "\n  [TEST 1.2] Testing legitimate second purchase within 5 minutes (Item Beta Casual Shirt)...\n";
$secondInquiry = "আমি ১টা ক্যাজুয়াল শার্টও নিতে চাই, সাইজ M, আমার নাম তানভীর, ফোন 01711998877, ঠিকানা বাসা ১২, রোড ৫, ধানমন্ডি, ঢাকা।";
$secondOrder = $orderCapture->extractAndCapture($conversation, $secondInquiry);
$isDistinctSecondOrder = ($secondOrder && $capturedOrder && $secondOrder->id !== $capturedOrder->id);
echo "  Second Order ID: #{$secondOrder->id} (Order Number: {$secondOrder->order_number})\n";
echo "  Second Order Product: {$secondOrder->product_summary}\n";
echo "  Second Order Result: " . ($isDistinctSecondOrder ? "[SUCCESS] Distinct second order created successfully!" : "[BUG] Blocked by false idempotency") . "\n";

// -------------------------------------------------------------
// 2. AI SUPPORT AGENT: Order Status, Adversarial IDOR & Escalation
// -------------------------------------------------------------
echo "\n>>> 2. Testing AI Support Agent Workflow\n";
$orderNumber = $capturedOrder ? $capturedOrder->order_number : 'ORD-261124-TEST';

// Scenario A: Legitimate Customer Order Status Query (Same Session)
$statusQuery = "আমার অর্ডার {$orderNumber} এর ডেলিভারি স্ট্যাটাস কি?";
echo "\n[Scenario A] Legitimate Customer Query: \"{$statusQuery}\"\n";
$t0 = microtime(true);
$statusResult = $orchestrator->process(
    query: $statusQuery,
    workspaceId: $ws->id,
    conversation: $conversation,
    channelSlug: 'website',
    isCustomer: true
);
$elapsedStatus = round((microtime(true) - $t0) * 1000, 2);
echo "Support Agent Output (Order Status):\n";
echo "  Route: " . ($statusResult['route'] ?? 'unknown') . "\n";
echo "  Duration: {$elapsedStatus} ms\n";
echo "  Reply Preview: " . mb_substr($statusResult['reply'] ?? '', 0, 150) . "...\n";

// Scenario A.1: Adversarial Cross-Session IDOR Probe (Attacker in different session)
echo "\n[Scenario A.1] Testing Cross-Session IDOR Defense (Attacker trying to probe Order #{$orderNumber})...\n";
$attackerConv = Conversation::firstOrCreate(
    ['channel_account_id' => $account->id, 'external_user_id' => 'attacker_session_99'],
    ['status' => 'open', 'customer_name' => 'Curious Stranger', 'last_direction' => 'inbound']
);
$orderStatusTool = new CustomerOrderStatusTool();
$attackerContext = AgentContext::forCustomer(
    workspaceId: $ws->id,
    channel: 'website',
    conversation: $attackerConv
);

// Attempt 1: Without phone verification
$probeWithoutPhone = $orderStatusTool->execute(['order_number' => $orderNumber], $attackerContext);
echo "  Cross-session lookup WITHOUT phone: " . (! $probeWithoutPhone->success && str_contains($probeWithoutPhone->error, 'নিরাপত্তার স্বার্থে') ? "[SUCCESS] Blocked closed: {$probeWithoutPhone->error}" : "[FAIL] PII Leaked!") . "\n";

// Attempt 2: With correct phone verification -> Street address must be completely redacted
$probeWithPhone = $orderStatusTool->execute(['order_number' => $orderNumber, 'phone' => '01711998877'], $attackerContext);
$isStreetRedacted = $probeWithPhone->success && str_contains($probeWithPhone->data['delivery_address'], 'সংরক্ষিত') && ! str_contains($probeWithPhone->data['delivery_address'], 'রোড ৫');
echo "  Cross-session lookup WITH phone: " . ($isStreetRedacted ? "[SUCCESS] Street address redacted ('{$probeWithPhone->data['delivery_address']}') and Phone masked ('{$probeWithPhone->data['customer_phone']}')" : "[FAIL] Address not redacted!") . "\n";

// Scenario A.2: Enumeration Rate Limiter (Brute-force lockout after 5 failures)
echo "\n[Scenario A.2] Testing Enumeration Rate Limiting (5 failures -> Lockout)...\n";
Cache::forget("order_lookup_fail_{$ws->id}_{$attackerConv->id}");
for ($i = 1; $i <= 5; $i++) {
    $orderStatusTool->execute(['order_number' => 'ORD-FAKE-' . $i], $attackerContext);
}
$lockedOutRes = $orderStatusTool->execute(['order_number' => 'ORD-FAKE-6'], $attackerContext);
$isRateLimited = (! $lockedOutRes->success && str_contains($lockedOutRes->error, 'Rate Limit Exceeded'));
echo "  6th invalid probe result: " . ($isRateLimited ? "[SUCCESS] Locked out by Rate Limiter ('{$lockedOutRes->error}')" : "[FAIL] Rate limiter did not engage") . "\n";
Cache::forget("order_lookup_fail_{$ws->id}_{$attackerConv->id}");

// Scenario B: Human Handoff on Escalation / Dissatisfaction
echo "\n[Scenario B] Customer Escalation & Manager Handoff Event...\n";
\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
$managerUser = User::firstOrCreate(
    ['email' => 'manager@fashionfusion.test'],
    ['name' => 'Support Manager', 'workspace_id' => $ws->id, 'password' => bcrypt('secret123')]
);
if (! $managerUser->hasRole('manager')) {
    $managerUser->assignRole('manager');
}

$escalationQuery = "আমি ড্যামেজ প্রোডাক্ট পেয়েছি, আপনারা কোনো রেসপন্স করছেন না! এখনই ম্যানেজারের সাথে কথা বলব।";
echo "Customer Escalation Query: \"{$escalationQuery}\"\n";

$eventDispatched = false;
Event::listen(ConversationEscalatedToHuman::class, function ($event) use (&$eventDispatched) {
    $eventDispatched = true;
});

// Record customer message in conversation
Message::create([
    'conversation_id' => $conversation->id,
    'direction'       => 'inbound',
    'type'            => 'text',
    'body'            => $escalationQuery,
]);
$conversation->update(['last_message' => $escalationQuery, 'last_direction' => 'inbound']);

$t0 = microtime(true);
$escalationResult = $orchestrator->process(
    query: $escalationQuery,
    workspaceId: $ws->id,
    conversation: $conversation,
    channelSlug: 'website',
    isCustomer: true
);
$elapsedEscalation = round((microtime(true) - $t0) * 1000, 2);

// Run automated lead intelligence / manager handoff (as executed by CustomerSupportService & RunFAQEngineListener)
app(\App\Services\AI\LeadIntelligenceService::class)->classifyAndTagConversation(
    conversation: $conversation,
    force: true
);

$conversation->refresh();
echo "Support Agent Output (Escalation):\n";
echo "  Route: " . ($escalationResult['route'] ?? 'unknown') . "\n";
echo "  Duration: {$elapsedEscalation} ms\n";
echo "  Priority: {$conversation->priority}\n";
echo "  Assigned Manager ID: " . ($conversation->assigned_to_user_id ?? 'None') . "\n";
echo "  Manager Notification Status: " . ($conversation->metadata['manager_notification_status'] ?? 'None') . "\n";
echo "  ConversationEscalatedToHuman Event Dispatched: " . ($eventDispatched ? "[YES]" : "[NO]") . "\n";
echo "  Halt Reason: " . ($escalationResult['decision_metadata']['halt_reason'] ?? 'none') . "\n";
echo "  Reply Preview: " . mb_substr($escalationResult['reply'] ?? '', 0, 150) . "...\n";

// -------------------------------------------------------------
// 3. AI BUSINESS INTELLIGENCE AGENT: Owner Analytics Queries
// -------------------------------------------------------------
echo "\n>>> 3. Testing AI Business Intelligence Agent Workflow (Owner Context)\n";

// Seed deterministic ground truth orders for workspace
$todayStr = Carbon::today()->toDateString();
$yesterdayStr = Carbon::yesterday()->toDateString();

$cust = \App\Models\AnalyticsCustomer::firstOrCreate(
    ['workspace_id' => $ws->id, 'phone' => '01711998877'],
    ['name' => 'Tanvir Ahmed', 'is_active' => true]
);

$seller = \App\Models\AnalyticsSalesperson::firstOrCreate(
    ['workspace_id' => $ws->id, 'employee_code' => 'REP-01'],
    ['name' => 'Store Rep', 'is_active' => true]
);

$prodA = \App\Models\AnalyticsProduct::firstOrCreate(
    ['workspace_id' => $ws->id, 'sku' => 'ALPHA-01'],
    ['name' => 'Item Alpha Panjabi', 'unit_price' => 3500.00, 'cost_price' => 1500.00, 'is_active' => true]
);

$prodB = \App\Models\AnalyticsProduct::firstOrCreate(
    ['workspace_id' => $ws->id, 'sku' => 'BETA-02'],
    ['name' => 'Item Beta Casual Shirt', 'unit_price' => 1000.00, 'cost_price' => 500.00, 'is_active' => true]
);

// Order 1: Completed Today (BDT 3,500.00, 1 pcs Item Alpha Panjabi)
$gtOrder1 = \App\Models\AnalyticsOrder::updateOrCreate(
    ['workspace_id' => $ws->id, 'order_number' => 'ORD-GT-TODAY'],
    [
        'customer_id'    => $cust->id,
        'salesperson_id' => $seller->id,
        'total_amount'   => 3500.00,
        'discount'       => 0.00,
        'net_amount'     => 3500.00,
        'status'         => 'completed',
        'order_date'     => $todayStr,
    ]
);
\App\Models\AnalyticsOrderItem::updateOrCreate(
    ['order_id' => $gtOrder1->id, 'product_id' => $prodA->id],
    ['quantity' => 1, 'unit_price' => 3500.00, 'subtotal' => 3500.00]
);

// Order 2: Completed Yesterday (BDT 2,000.00, 2 pcs Item Beta Casual Shirt)
$gtOrder2 = \App\Models\AnalyticsOrder::updateOrCreate(
    ['workspace_id' => $ws->id, 'order_number' => 'ORD-GT-YESTERDAY'],
    [
        'customer_id'    => $cust->id,
        'salesperson_id' => $seller->id,
        'total_amount'   => 2000.00,
        'discount'       => 0.00,
        'net_amount'     => 2000.00,
        'status'         => 'completed',
        'order_date'     => $yesterdayStr,
    ]
);
\App\Models\AnalyticsOrderItem::updateOrCreate(
    ['order_id' => $gtOrder2->id, 'product_id' => $prodB->id],
    ['quantity' => 2, 'unit_price' => 1000.00, 'subtotal' => 2000.00]
);

// Order 3: Pending Today (BDT 1,200.00) - MUST NOT be in completed sales
\App\Models\AnalyticsOrder::updateOrCreate(
    ['workspace_id' => $ws->id, 'order_number' => 'ORD-GT-PENDING'],
    [
        'customer_id'    => $cust->id,
        'salesperson_id' => $seller->id,
        'total_amount'   => 1200.00,
        'discount'       => 0.00,
        'net_amount'     => 1200.00,
        'status'         => 'pending',
        'order_date'     => $todayStr,
    ]
);

$ownerUser = User::where('workspace_id', $ws->id)->first() ?? User::whereHas('roles', fn($r) => $r->where('name', 'superadmin'))->first();

$biQuery = "আজকের মোট সেল এবং টপ সেলিং প্রোডাক্ট কোনগুলো?";
echo "Owner Query: \"{$biQuery}\"\n";
$t0 = microtime(true);
$biResult = $orchestrator->process(
    query: $biQuery,
    workspaceId: $ws->id,
    user: $ownerUser,
    conversation: null,
    channelSlug: 'simulator',
    isCustomer: false
);
$elapsedBi = round((microtime(true) - $t0) * 1000, 2);
echo "BI Agent Output (Owner Analytics):\n";
echo "  Route: " . ($biResult['route'] ?? 'unknown') . "\n";
echo "  Duration: {$elapsedBi} ms\n";
echo "  Tools Executed: " . json_encode($biResult['decision_metadata']['tools_executed'] ?? []) . "\n";
echo "  Reply Full:\n" . ($biResult['reply'] ?? '') . "\n";

// -------------------------------------------------------------
// 4. AI CRM & RETENTION AGENT: Lifecycle Tagging, Opt-out & Follow-up
// -------------------------------------------------------------
echo ">>> 4. Testing AI Follow-up & CRM Agent (Lifecycle Tags, Opt-out & Recovery Pipeline)\n";
$conversation->refresh();
echo "  Conversation Tags: " . json_encode($conversation->ai_tags) . "\n";
echo "  Conversation Priority: {$conversation->priority}\n";
echo "  Customer Name: {$conversation->customer_name}\n";

$recoveryService = app(AbandonedLeadRecoveryService::class);

// 4.1 Test Opt-Out Consent Guard
echo "\n  [TEST 4.1] Testing Marketing Opt-Out Consent Guard...\n";
$optOutConv = Conversation::updateOrCreate(
    ['channel_account_id' => $account->id, 'external_user_id' => 'optout_cust_01'],
    [
        'status'          => 'open',
        'customer_name'   => 'Opted Out Customer',
        'last_direction'  => 'inbound',
        'last_message'    => 'আর মেসেজ পাঠাবেন না',
        'last_message_at' => Carbon::now()->subHours(3),
        'ai_tags'         => ['hot_lead', 'needs_phone'],
        'metadata'        => ['opted_out_marketing' => true],
    ]
);

$eligibleList = $recoveryService->findEligibleConversations($ws->id);
$isOptOutExcluded = ! $eligibleList->contains('id', $optOutConv->id);
echo "  Opted-out customer discovery: " . ($isOptOutExcluded ? "[SUCCESS] Excluded from eligible recovery list" : "[BUG] Included in recovery list!") . "\n";

$directOptOutSend = $recoveryService->sendFollowUp($optOutConv);
echo "  Direct send to opted-out customer: " . (! $directOptOutSend['success'] && $directOptOutSend['reason'] === 'customer_opted_out' ? "[SUCCESS] Blocked with reason 'customer_opted_out'" : "[BUG] Follow-up sent to opted-out customer!") . "\n";

// 4.2 Test Eligible Customer Recovery Follow-up & Retention History
echo "\n  [TEST 4.2] Testing Eligible Customer Recovery Follow-up...\n";
$eligibleConv = Conversation::updateOrCreate(
    ['channel_account_id' => $account->id, 'external_user_id' => 'cart_abandoned_01'],
    [
        'status'          => 'open',
        'customer_name'   => 'Interested Shopper',
        'last_direction'  => 'inbound',
        'last_message'    => 'পাঞ্জাবির দাম কত?',
        'last_message_at' => Carbon::now()->subHours(3),
        'ai_tags'         => ['hot_lead', 'price_inquiry'],
        'metadata'        => [],
    ]
);

$isFoundEligible = $recoveryService->findEligibleConversations($ws->id)->contains('id', $eligibleConv->id);
echo "  Eligible customer discovery: " . ($isFoundEligible ? "[SUCCESS] Correctly identified as eligible" : "[BUG] Not detected as eligible") . "\n";

$sendResult = $recoveryService->sendFollowUp($eligibleConv);
$eligibleConv->refresh();
$hasFollowedUpFlag = (bool) ($eligibleConv->metadata['abandoned_lead_followed_up'] ?? false);
$hasRetentionHistory = ! empty($eligibleConv->metadata['retention_history']);
echo "  Follow-up dispatch result: " . ($sendResult['success'] ? "[SUCCESS] Follow-up sent" : "[FAIL] " . ($sendResult['reason'] ?? 'unknown')) . "\n";
echo "  Frequency Capping Flag: " . ($hasFollowedUpFlag ? "[SUCCESS] Set to true" : "[FAIL] Not set") . "\n";
echo "  Retention History Log: " . ($hasRetentionHistory ? "[SUCCESS] Recorded: " . json_encode($eligibleConv->metadata['retention_history']) : "[FAIL] Empty") . "\n";

echo "\n=== Workflow Audit Complete ===\n";
