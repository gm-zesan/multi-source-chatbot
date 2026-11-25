<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Decision\UnifiedOrchestrator;
use App\AI\Tools\CustomerOrderStatusTool;
use App\AI\DTOs\AgentContext;
use App\Enums\FaqLifecycleStatus;
use App\Enums\RoleEnum;
use App\Events\ConversationEscalatedToHuman;
use App\Models\AnalyticsProduct;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Commerce\OrderCaptureService;
use App\Services\Courier\CourierManager;
use App\Services\Retention\AbandonedLeadRecoveryService;
use App\Services\Workspace\WorkspaceProvisioningService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

echo "=== FIRST MERCHANT ONBOARDING & OPERATIONAL SIMULATION ===\n\n";

$auditEvidence = [];

// =========================================================================
// STEP 1: Merchant Registration & Workspace Provisioning
// =========================================================================
echo ">>> STEP 1: Provisioning New Merchant Workspace (Dhaka Boutique)...\n";
$provisioningService = app(WorkspaceProvisioningService::class);

$testEmail = 'sultana_' . Str::lower(Str::random(4)) . '@dhakaboutique.test';
$businessName = 'Dhaka Boutique';

$ownerUser = $provisioningService->provisionNewTenant(
    businessName: $businessName,
    userData: [
        'name'              => 'Sultana Begum',
        'email'             => $testEmail,
        'password'          => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]
);

$workspace = $ownerUser->workspace;
echo "  [SUCCESS] Workspace Provisioned:\n";
echo "    ID: {$workspace->id}\n";
echo "    Name: {$workspace->name}\n";
echo "    Slug: {$workspace->slug}\n";
echo "    Owner: {$ownerUser->name} ({$ownerUser->email})\n";
echo "    Role: " . implode(', ', $ownerUser->getRoleNames()->toArray()) . "\n";
echo "    Currency: {$workspace->currency}\n";

$auditEvidence['step1_provisioning'] = [
    'workspace_id' => $workspace->id,
    'slug'         => $workspace->slug,
    'owner_email'  => $ownerUser->email,
    'role'         => $ownerUser->getRoleNames()->toArray(),
];

// =========================================================================
// STEP 2: Business Profile, Delivery Rates & Agent Settings
// =========================================================================
echo "\n>>> STEP 2: Configuring Business Profile, Delivery Fees & AI Settings...\n";
$workspace->update([
    'support_phone'    => '01811223344',
    'support_email'    => 'contact@dhakaboutique.test',
    'business_address' => 'Shop 14, Banani Super Market, Dhaka - 1213',
    'operating_hours'  => [
        'weekdays' => '10:00 AM - 09:00 PM',
        'weekend'  => '11:00 AM - 07:00 PM',
    ],
    'settings' => array_merge($workspace->settings ?? [], [
        'order_auto_capture'          => true,
        'ai_canary_enabled'           => true,
        'ai_kill_switch'              => false,
        'delivery_charge_inside_dhaka' => 70,
        'delivery_charge_outside_dhaka'=> 130,
        'unanswerable_message'        => 'আমাদের প্রতিনিধি শীঘ্রই আপনার সাথে যোগাযোগ করবেন।',
    ]),
]);
$workspace->refresh();
echo "  [SUCCESS] Settings Saved:\n";
echo "    Support Phone: {$workspace->support_phone}\n";
echo "    Address: {$workspace->business_address}\n";
echo "    Delivery Fees: Dhaka ৳{$workspace->settings['delivery_charge_inside_dhaka']}, Outside ৳{$workspace->settings['delivery_charge_outside_dhaka']}\n";
echo "    AI Auto Capture: " . ($workspace->settings['order_auto_capture'] ? 'Enabled' : 'Disabled') . "\n";

// =========================================================================
// STEP 3: Ingesting Product Catalog & Business Policies
// =========================================================================
echo "\n>>> STEP 3: Ingesting Product Catalog & Business Knowledge Base...\n";

// 3.1 Products
$prod1 = AnalyticsProduct::create([
    'workspace_id' => $workspace->id,
    'name'         => 'সিল্ক জামদানি শাড়ি (Silk Jamdani)',
    'sku'          => 'JAM-SILK-01',
    'unit_price'   => 4500.00,
    'cost_price'   => 2800.00,
    'is_active'    => true,
]);

$prod2 = AnalyticsProduct::create([
    'workspace_id' => $workspace->id,
    'name'         => 'কটন এম্ব্রয়ডারি কুর্তি (Cotton Kurti)',
    'sku'          => 'COT-KURTI-02',
    'unit_price'   => 1800.00,
    'cost_price'   => 950.00,
    'is_active'    => true,
]);

echo "  [SUCCESS] 2 Products Ingested:\n";
echo "    1. {$prod1->name} (SKU: {$prod1->sku}, Price: ৳{$prod1->unit_price})\n";
echo "    2. {$prod2->name} (SKU: {$prod2->sku}, Price: ৳{$prod2->unit_price})\n";

// 3.2 FAQs / Policies
$faqPolicy = FAQ::create([
    'workspace_id'     => $workspace->id,
    'question'         => 'আপনাদের রিটার্ন ও রিফান্ড পলিসি কি?',
    'answer'           => 'পণ্য গ্রহণের ৩ দিনের মধ্যে কোনো ত্রুটি বা সাইজ সমস্যা থাকলে ফ্রি এক্সচেঞ্জ বা ১০০% রিফান্ড সুবিধা রয়েছে। ডেলিভারি ম্যানের সামনে চেক করে নেওয়ার অনুরোধ রইল।',
    'document_type'    => 'faq',
    'lifecycle_status' => FaqLifecycleStatus::ACTIVE,
    'is_active'        => true,
    'created_by'       => $ownerUser->id,
]);

$faqDelivery = FAQ::create([
    'workspace_id'     => $workspace->id,
    'question'         => 'ডেলিভারি চার্জ কত এবং কত দিনে পাওয়া যায়?',
    'answer'           => 'ঢাকার ভেতর ডেলিভারি চার্জ ৭০ টাকা এবং সময় ১-২ কার্যদিবস। ঢাকার বাইরে ডেলিভারি চার্জ ১৩০ টাকা এবং সময় ২-৩ কার্যদিবস।',
    'document_type'    => 'faq',
    'lifecycle_status' => FaqLifecycleStatus::ACTIVE,
    'is_active'        => true,
    'created_by'       => $ownerUser->id,
]);

echo "  [SUCCESS] 2 Custom Policies/FAQs Ingested:\n";
echo "    1. {$faqPolicy->question}\n";
echo "    2. {$faqDelivery->question}\n";

// =========================================================================
// STEP 4: Channel Integration (Facebook Page & Web Widget)
// =========================================================================
echo "\n>>> STEP 4: Connecting Channels (Facebook Page & Web Widget)...\n";
$fbChannel = Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook Messenger', 'is_active' => true]);
$fbAccount = ChannelAccount::create([
    'workspace_id' => $workspace->id,
    'channel_id'   => $fbChannel->id,
    'name'         => 'Dhaka Boutique Official Page',
    'display_name' => 'Dhaka Boutique FB',
    'external_id'  => 'fb_page_dhaka_boutique_01',
    'access_token' => 'EAAB_test_mock_token_dhaka',
    'settings'     => ['page_id' => 'fb_page_dhaka_boutique_01'],
    'is_active'    => true,
]);

$widgetAccount = ChannelAccount::where('workspace_id', $workspace->id)
    ->whereHas('channel', fn($q) => $q->where('slug', 'website'))
    ->first();

echo "  [SUCCESS] Channels Connected:\n";
echo "    - Facebook Page: ID {$fbAccount->id} (Page: {$fbAccount->external_id})\n";
echo "    - Web Chat Widget: ID {$widgetAccount->id} (Key: {$widgetAccount->external_id})\n";

// =========================================================================
// STEP 5: Courier Integration Setup (Steadfast Courier)
// =========================================================================
echo "\n>>> STEP 5: Configuring Courier Partner (Steadfast Courier)...\n";
$courierManager = app(CourierManager::class);
$courierManager->saveCourierSettings($workspace, [
    'default_provider' => 'steadfast',
    'steadfast' => [
        'api_key'    => 'stf_merchant_api_key_xyz',
        'secret_key' => 'stf_merchant_secret_key_abc',
        'is_active'  => true,
    ],
]);
$savedCourierSettings = $courierManager->getCourierSettings($workspace);
echo "  [SUCCESS] Courier Partner Configured: Default Provider = {$savedCourierSettings['default_provider']}\n";
echo "    Steadfast Active: " . ($savedCourierSettings['steadfast']['is_active'] ? 'Yes' : 'No') . "\n";

// =========================================================================
// STEP 6: Customer Simulation 1 — Sales Inquiry & Auto Order Capture
// =========================================================================
echo "\n>>> STEP 6: Customer Simulation 1 — Sales Inquiry & Auto Order Capture...\n";
$orchestrator = app(UnifiedOrchestrator::class);
$orderCapture = app(OrderCaptureService::class);

$customerConv = Conversation::create([
    'channel_account_id' => $fbAccount->id,
    'external_user_id'   => 'fb_user_farhana_88',
    'customer_name'      => 'Farhana Yasmin',
    'status'             => 'open',
    'last_direction'     => 'inbound',
]);

$inquiryText = "আমি ১টা সিল্ক জামদানি শাড়ি অর্ডার করতে চাই, আমার নাম ফারহানা, ফোন 01911223344, ঠিকানা বাসা ৪, রোড ১১, ব্লক ডি, বনানী, ঢাকা।";
echo "  Customer Message: \"{$inquiryText}\"\n";

$t0 = microtime(true);
$salesResult = $orchestrator->process(
    query: $inquiryText,
    workspaceId: $workspace->id,
    conversation: $customerConv,
    channelSlug: 'facebook',
    isCustomer: true
);
$elapsedSales = round((microtime(true) - $t0) * 1000, 2);

echo "  Sales Agent Response ({$elapsedSales} ms):\n";
echo "    Route: " . ($salesResult['route'] ?? 'unknown') . "\n";
echo "    Reply Preview:\n" . mb_substr($salesResult['reply'] ?? '', 0, 200) . "...\n";

// Capture order
$capturedOrder = $orderCapture->extractAndCapture($customerConv, $inquiryText, $salesResult['reply'] ?? '');
if ($capturedOrder) {
    echo "  [SUCCESS] Order Captured in Database:\n";
    echo "    Order Number: #{$capturedOrder->order_number}\n";
    echo "    Customer: {$capturedOrder->customer_name} ({$capturedOrder->customer_phone})\n";
    echo "    Product: {$capturedOrder->product_summary}\n";
    echo "    Amount: ৳" . number_format((float) $capturedOrder->order_amount, 2) . "\n";
    echo "    Status: {$capturedOrder->status}\n";
} else {
    echo "  [FAIL] Order was not captured!\n";
}

// =========================================================================
// STEP 7: Customer Simulation 2 — Order Status Tracking (Support Agent)
// =========================================================================
echo "\n>>> STEP 7: Customer Simulation 2 — Order Status Tracking...\n";
$statusQuery = "আমার অর্ডার #{$capturedOrder->order_number} এর বর্তমান অবস্থা কি?";
echo "  Customer Message: \"{$statusQuery}\"\n";

$t0 = microtime(true);
$statusResult = $orchestrator->process(
    query: $statusQuery,
    workspaceId: $workspace->id,
    conversation: $customerConv,
    channelSlug: 'facebook',
    isCustomer: true
);
$elapsedStatus = round((microtime(true) - $t0) * 1000, 2);

echo "  Support Agent Response ({$elapsedStatus} ms):\n";
echo "    Route: " . ($statusResult['route'] ?? 'unknown') . "\n";
echo "    Reply Preview:\n" . mb_substr($statusResult['reply'] ?? '', 0, 200) . "...\n";

// =========================================================================
// STEP 8: Customer Simulation 3 — Complaint & Manager Escalation
// =========================================================================
echo "\n>>> STEP 8: Customer Simulation 3 — Complaint & Human Manager Escalation...\n";
$complaintText = "শাড়ির রঙ ছবিতে যেমন ছিল তেমন আসে নাই, ত্রুটি আছে। অভিযোগ করতে চাই, এখনই ম্যানেজারের সাথে কথা বলব।";
echo "  Customer Complaint: \"{$complaintText}\"\n";

$escalationEventFired = false;
Event::listen(ConversationEscalatedToHuman::class, function ($event) use (&$escalationEventFired) {
    $escalationEventFired = true;
});

Message::create([
    'conversation_id' => $customerConv->id,
    'direction'       => 'inbound',
    'type'            => 'text',
    'body'            => $complaintText,
]);
$customerConv->update(['last_message' => $complaintText, 'last_direction' => 'inbound']);

$t0 = microtime(true);
$complaintResult = $orchestrator->process(
    query: $complaintText,
    workspaceId: $workspace->id,
    conversation: $customerConv,
    channelSlug: 'facebook',
    isCustomer: true
);
$elapsedComplaint = round((microtime(true) - $t0) * 1000, 2);

app(\App\Services\AI\LeadIntelligenceService::class)->classifyAndTagConversation($customerConv, force: true);
$customerConv->refresh();

echo "  Escalation Output ({$elapsedComplaint} ms):\n";
echo "    Priority: {$customerConv->priority}\n";
echo "    Assigned Manager: " . ($customerConv->assignedTo?->name ?? 'None') . " (ID: {$customerConv->assigned_to_user_id})\n";
echo "    Escalation Level: " . ($customerConv->metadata['escalation_level'] ?? 'None') . "\n";
echo "    Manager Notification Status: " . ($customerConv->metadata['manager_notification_status'] ?? 'None') . "\n";
echo "    ConversationEscalatedToHuman Event: " . ($escalationEventFired ? "[YES]" : "[NO]") . "\n";
echo "    AI Tags: " . json_encode($customerConv->ai_tags) . "\n";
echo "    AI Reply Preview:\n" . mb_substr($complaintResult['reply'] ?? '', 0, 200) . "...\n";

// =========================================================================
// STEP 9: Owner Business Intelligence & Analytics
// =========================================================================
echo "\n>>> STEP 9: Owner Business Intelligence Query...\n";

// Seed deterministic AnalyticsOrder for Dhaka Boutique
$analyticsCust = \App\Models\AnalyticsCustomer::firstOrCreate(
    ['workspace_id' => $workspace->id, 'phone' => '01911223344'],
    ['name' => 'Farhana Yasmin', 'is_active' => true]
);
$analyticsSeller = \App\Models\AnalyticsSalesperson::firstOrCreate(
    ['workspace_id' => $workspace->id, 'employee_code' => 'REP-DB-' . $workspace->id],
    ['name' => 'Online Sales Rep', 'is_active' => true]
);
$analyticsOrd = \App\Models\AnalyticsOrder::create([
    'workspace_id'   => $workspace->id,
    'customer_id'    => $analyticsCust->id,
    'salesperson_id' => $analyticsSeller->id,
    'order_number'   => 'ORD-DB-' . $workspace->id . '-01',
    'total_amount'   => 4500.00,
    'discount'       => 0.00,
    'net_amount'     => 4500.00,
    'status'         => 'completed',
    'order_date'     => Carbon::today()->toDateString(),
]);
\App\Models\AnalyticsOrderItem::create([
    'order_id'   => $analyticsOrd->id,
    'product_id' => $prod1->id,
    'quantity'   => 1,
    'unit_price' => 4500.00,
    'subtotal'   => 4500.00,
]);

$biQuery = "আজকের মোট সেল এবং আজকের টপ প্রোডাক্ট কোনটি?";
echo "  Owner Query: \"{$biQuery}\"\n";

$t0 = microtime(true);
$biResult = $orchestrator->process(
    query: $biQuery,
    workspaceId: $workspace->id,
    user: $ownerUser,
    conversation: null,
    channelSlug: 'simulator',
    isCustomer: false
);
$elapsedBi = round((microtime(true) - $t0) * 1000, 2);

echo "  BI Agent Response ({$elapsedBi} ms):\n";
echo "    Route: " . ($biResult['route'] ?? 'unknown') . "\n";
echo "    Reply:\n" . ($biResult['reply'] ?? '') . "\n";

// =========================================================================
// STEP 10: CRM & Retention Lifecycle Check
// =========================================================================
echo "\n>>> STEP 10: CRM & Retention Lifecycle Check...\n";
$recoveryService = app(AbandonedLeadRecoveryService::class);

// Create an abandoned cart conversation
$abandonedConv = Conversation::create([
    'channel_account_id' => $fbAccount->id,
    'external_user_id'   => 'fb_user_abandoned_77',
    'customer_name'      => 'Ayesha Siddika',
    'status'             => 'open',
    'last_direction'     => 'inbound',
    'last_message'       => 'কটন কুর্তির সাইজ কি কি আছে?',
    'last_message_at'    => Carbon::now()->subHours(3),
    'ai_tags'            => ['hot_lead', 'needs_phone'],
    'metadata'           => [],
]);

$eligibleLeads = $recoveryService->findEligibleConversations($workspace->id);
$isLeadFound = $eligibleLeads->contains('id', $abandonedConv->id);
echo "  Abandoned Lead Detection: " . ($isLeadFound ? "[SUCCESS] Identified for automated follow-up" : "[FAIL] Not detected") . "\n";

echo "\n=== FIRST MERCHANT ONBOARDING SIMULATION COMPLETE ===\n";
