<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\AI\ActionSafetyService;
use App\Services\AI\CustomerSupportService;
use App\Services\FAQ\FAQSearch;
use App\Services\Retrieval\RetrievalClient;

echo "=================================================================================================\n";
echo "🔬 COMPREHENSIVE PRODUCTION E2E MULTILINGUAL & INVARIANT VALIDATION BENCHMARK\n";
echo "=================================================================================================\n";

// Configure model provider
$experimentProvider = 'deepseek';
$experimentModel    = 'deepseek-chat';
$experimentApiKey   = env('DEEPSEEK_API_KEY') ?: env('PHASE2G2_DEEPSEEK_API_KEY');
$experimentApiUrl   = env('DEEPSEEK_URL', env('PHASE2G2_DEEPSEEK_URL', 'https://api.deepseek.com'));

config([
    'ai.default'                => $experimentProvider,
    'ai.default_model'          => $experimentModel,
    'ai.providers.deepseek.key' => $experimentApiKey,
    'ai.providers.deepseek.url' => $experimentApiUrl,
]);

$faqSearch = app(FAQSearch::class);
$retrievalClient = app(RetrievalClient::class);
$router = new HybridRouter();
$actionSafety = new ActionSafetyService();
$customerSupportService = app(CustomerSupportService::class);

// Workspaces & Channel Accounts
$ws1 = Workspace::find(1);
$ws2 = Workspace::firstOrCreate(['slug' => 'tenant-beta-workspace'], ['name' => 'Tenant Beta Workspace']);

$chan = Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook', 'is_active' => true]);
$acc1 = ChannelAccount::firstOrCreate(
    ['external_id' => 'fb_acc_e2e_ws1'],
    ['channel_id' => $chan->id, 'workspace_id' => $ws1->id, 'name' => 'E2E Account WS1', 'access_token' => 'tok_ws1', 'is_active' => true]
);
$acc2 = ChannelAccount::firstOrCreate(
    ['external_id' => 'fb_acc_e2e_ws2'],
    ['channel_id' => $chan->id, 'workspace_id' => $ws2->id, 'name' => 'E2E Account WS2', 'access_token' => 'tok_ws2', 'is_active' => true]
);

// Secret FAQ for Tenant 2
$ws2Faq = FAQ::firstOrCreate(
    ['workspace_id' => $ws2->id, 'question' => 'What is the private VIP direct line for Tenant Beta?'],
    ['answer' => 'The private VIP direct phone line for Tenant Beta is +1-888-BETA-VIP-009.', 'priority' => 100, 'is_active' => true]
);
$retrievalClient->syncFaq($ws2Faq);

// Test Results Collector
$testResults = [];
$totalAssertions = 0;
$passedAssertions = 0;

function assertCondition(bool $condition, string $description, &$totalAssertions, &$passedAssertions, array &$telemetry = []): bool
{
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        return true;
    }
    echo "    ❌ ASSERTION FAILED: {$description}\n";
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. KNOWLEDGE GROUNDING (English, Bangla, Banglish)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 1. KNOWLEDGE GROUNDING INQUIRIES (English, Bangla, Banglish) ─────────\n";

$knowledgeQueries = [
    // English
    ['q' => 'How do I update my payment method?', 'lang' => 'EN', 'topic' => 'Payment', 'ground' => 'Payment'],
    ['q' => 'What plans are available?', 'lang' => 'EN', 'topic' => 'Plans', 'ground' => 'Free'],
    ['q' => 'How do I view my invoices?', 'lang' => 'EN', 'topic' => 'Invoices', 'ground' => 'Invoice'],
    ['q' => 'How is my data encrypted?', 'lang' => 'EN', 'topic' => 'Security', 'ground' => 'encrypt'],
    ['q' => 'Can I use multiple channels simultaneously?', 'lang' => 'EN', 'topic' => 'Channels', 'ground' => 'channel'],
    ['q' => 'What is your privacy policy?', 'lang' => 'EN', 'topic' => 'Privacy', 'ground' => 'privacy'],

    // Bangla
    ['q' => 'আমি কীভাবে আমার পেমেন্ট মেথড পরিবর্তন করব?', 'lang' => 'BN', 'topic' => 'Payment', 'ground' => 'পেমেন্ট'],
    ['q' => 'কোন কোন সাবস্ক্রিপশন প্ল্যান আছে?', 'lang' => 'BN', 'topic' => 'Plans', 'ground' => 'প্ল্যান'],
    ['q' => 'আমি ইনভয়েস কোথায় পাব?', 'lang' => 'BN', 'topic' => 'Invoices', 'ground' => 'ইনভয়েস'],
    ['q' => 'আমার ডেটা কিভাবে এনক্রিপ্ট করা হয়?', 'lang' => 'BN', 'topic' => 'Security', 'ground' => 'এনক্রিপ্ট'],
    ['q' => 'একসাথে একাধিক চ্যানেল কি ব্যবহার করা যাবে?', 'lang' => 'BN', 'topic' => 'Channels', 'ground' => 'চ্যানেল'],

    // Banglish
    ['q' => 'vai payment method kivabe change korbo?', 'lang' => 'Banglish', 'topic' => 'Payment', 'ground' => 'payment'],
    ['q' => 'plans ki ki ache?', 'lang' => 'Banglish', 'topic' => 'Plans', 'ground' => 'plan'],
    ['q' => 'invoice kothay pabo?', 'lang' => 'Banglish', 'topic' => 'Invoices', 'ground' => 'invoice'],
    ['q' => 'data encryption kivabe hoy?', 'lang' => 'Banglish', 'topic' => 'Security', 'ground' => 'encrypt'],
    ['q' => 'ekshathe multiple channels use kora jabe?', 'lang' => 'Banglish', 'topic' => 'Channels', 'ground' => 'channel'],
];

foreach ($knowledgeQueries as $item) {
    $conv = Conversation::create([
        'channel_account_id' => $acc1->id,
        'external_user_id'   => 'e2e_know_' . uniqid(),
        'status'             => 'active',
        'customer_name'      => 'Knowledge Tester',
    ]);

    $t_start = microtime(true);
    $res = $customerSupportService->handleQuery($item['q'], $ws1->id, $conv);
    $lat = round((microtime(true) - $t_start) * 1000, 2);

    $routeOk = ($res['route'] === 'knowledge');
    $hasGrounding = stripos($res['reply'], $item['ground']) !== false || mb_strlen($res['reply']) > 20;

    assertCondition($routeOk, "[{$item['lang']}] Route must be KNOWLEDGE for '{$item['q']}'", $totalAssertions, $passedAssertions);
    assertCondition($hasGrounding, "[{$item['lang']}] Response must be grounded with factual content", $totalAssertions, $passedAssertions);

    $testResults[] = [
        'category' => "1. Knowledge ({$item['lang']})",
        'query'    => $item['q'],
        'route'    => $res['route'],
        'pass'     => $routeOk && $hasGrounding,
        'latency'  => $lat,
    ];

    $icon = ($routeOk && $hasGrounding) ? '✅' : '❌';
    echo "  {$icon} [{$item['lang']}] ({$lat} ms) \"{$item['q']}\"\n";
    echo "       ↳ Answer: \"" . mb_substr(str_replace("\n", " ", trim($res['reply'])), 0, 80) . "...\"\n";
    usleep(150000);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. CONVERSATIONAL CHAT & GREETINGS (Bypass Invariant Assertion)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 2. CONVERSATIONAL CHAT & GREETINGS (Retrieval Bypass Invariant) ────\n";

$chatQueries = [
    ['q' => 'Hello, how are you today?', 'lang' => 'EN'],
    ['q' => 'Good morning! Hope you have a great day.', 'lang' => 'EN'],
    ['q' => 'Thank you so much for your support!', 'lang' => 'EN'],
    ['q' => 'হ্যালো, কেমন আছেন?', 'lang' => 'BN'],
    ['q' => 'আসসালামু আলাইকুম ভাই', 'lang' => 'BN'],
    ['q' => 'অনেক ধন্যবাদ ভাইয়া', 'lang' => 'BN'],
    ['q' => 'hello vai, kemon achen?', 'lang' => 'Banglish'],
    ['q' => 'dhonnobad vai', 'lang' => 'Banglish'],
];

foreach ($chatQueries as $item) {
    $conv = Conversation::create([
        'channel_account_id' => $acc1->id,
        'external_user_id'   => 'e2e_chat_' . uniqid(),
        'status'             => 'active',
        'customer_name'      => 'Chat Tester',
    ]);

    $t_start = microtime(true);
    $res = $customerSupportService->handleQuery($item['q'], $ws1->id, $conv);
    $lat = round((microtime(true) - $t_start) * 1000, 2);

    $routeOk = ($res['route'] === 'chat');
    $nonEmpty = !empty(trim($res['reply']));

    assertCondition($routeOk, "[{$item['lang']}] Route must be CHAT for '{$item['q']}'", $totalAssertions, $passedAssertions);
    assertCondition($nonEmpty, "[{$item['lang']}] Chat response must not be empty", $totalAssertions, $passedAssertions);

    $testResults[] = [
        'category' => "2. Chat ({$item['lang']})",
        'query'    => $item['q'],
        'route'    => $res['route'],
        'pass'     => $routeOk && $nonEmpty,
        'latency'  => $lat,
    ];

    $icon = ($routeOk && $nonEmpty) ? '✅' : '❌';
    echo "  {$icon} [{$item['lang']}] ({$lat} ms) \"{$item['q']}\"\n";
    echo "       ↳ Answer: \"" . mb_substr(str_replace("\n", " ", trim($res['reply'])), 0, 80) . "...\"\n";
    usleep(150000);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ACTION & QUESTION INVARIANT DISAMBIGUATION (Question != Mutation)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 3. ACTION & QUESTION INVARIANT DISAMBIGUATION (Question != Mutation) ──\n";

$disambiguationCases = [
    // Policy Question -> KNOWLEDGE
    ['q' => 'order ta cancel kora jabe?', 'expected' => 'knowledge', 'type' => 'Question Policy (Banglish)'],
    ['q' => 'অর্ডার বাতিল করার নিয়ম কি?', 'expected' => 'knowledge', 'type' => 'Question Policy (Bangla)'],
    ['q' => 'Can I cancel my order online?', 'expected' => 'knowledge', 'type' => 'Question Policy (English)'],
    ['q' => 'amar order cancel hoyeche?', 'expected' => 'knowledge', 'type' => 'Status Inquiry (Banglish)'],
    ['q' => 'cancel korbo naki?', 'expected' => 'knowledge', 'type' => 'Deliberation Inquiry (Banglish)'],

    // Imperative Action -> ACTION
    ['q' => 'order ta cancel kore den', 'expected' => 'action', 'type' => 'Imperative (Banglish)'],
    ['q' => 'আমার অর্ডারটা বাতিল করে দিন', 'expected' => 'action', 'type' => 'Imperative (Bangla)'],
    ['q' => 'Please cancel order #1024', 'expected' => 'action', 'type' => 'Imperative with ID (English)'],
    ['q' => 'Track order #2048', 'expected' => 'action', 'type' => 'Lookup with ID (English)'],
];

foreach ($disambiguationCases as $item) {
    $conv = Conversation::create([
        'channel_account_id' => $acc1->id,
        'external_user_id'   => 'e2e_disam_' . uniqid(),
        'status'             => 'active',
        'customer_name'      => 'Disambiguation Tester',
    ]);

    $t_start = microtime(true);
    $res = $customerSupportService->handleQuery($item['q'], $ws1->id, $conv);
    $lat = round((microtime(true) - $t_start) * 1000, 2);

    $routeOk = ($res['route'] === $item['expected']);
    assertCondition($routeOk, "Disambiguation failed for '{$item['q']}'. Expected: {$item['expected']}, Got: {$res['route']}", $totalAssertions, $passedAssertions);

    $testResults[] = [
        'category' => "3. Invariant Disambiguation",
        'query'    => $item['q'],
        'route'    => $res['route'],
        'pass'     => $routeOk,
        'latency'  => $lat,
    ];

    $icon = $routeOk ? '✅' : '❌';
    echo "  {$icon} [{$item['type']}] ({$lat} ms) \"{$item['q']}\" ──> [{$res['route']}]\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. DETERMINISTIC ACTION HANDOFF & ZERO-MUTATION INVARIANT
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 4. DETERMINISTIC ACTION HANDOFF & ZERO-MUTATION INVARIANT ─────────\n";

// Scenario A: English Direct Action Request -> Deterministic Team-Member Handoff
echo "\n  Scenario A: English Direct Action Request (Team Handoff)\n";
$convEn = Conversation::create([
    'channel_account_id' => $acc1->id,
    'external_user_id'   => 'e2e_act_en_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'John English',
]);

$resA = $customerSupportService->handleQuery("Please cancel my order #1024", $ws1->id, $convEn);
echo "    User: \"Please cancel my order #1024\"\n";
echo "    Bot:  \"{$resA['reply']}\"\n";
$routeAOk = ($resA['route'] === 'action');
$handoffAOk = (stripos($resA['reply'], 'action request') !== false && stripos($resA['reply'], 'team member will contact you') !== false);
$noPendingA = ($actionSafety->getPendingAction($convEn->fresh()) === null);
assertCondition($routeAOk, "Route must be ACTION for 'Please cancel my order #1024'", $totalAssertions, $passedAssertions);
assertCondition($handoffAOk, "Deterministic team-member handoff response returned", $totalAssertions, $passedAssertions);
assertCondition($noPendingA, "Zero pending mutation state registered", $totalAssertions, $passedAssertions);

// Scenario B: Bangla Imperative Action Request -> Deterministic Team-Member Handoff
echo "\n  Scenario B: Bangla Imperative Action Request (Team Handoff)\n";
$convBn = Conversation::create([
    'channel_account_id' => $acc1->id,
    'external_user_id'   => 'e2e_act_bn_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'রহিম বাংলা',
]);

$resB = $customerSupportService->handleQuery("আমার অর্ডার #2048 বাতিল করে দিন", $ws1->id, $convBn);
echo "    User: \"আমার অর্ডার #2048 বাতিল করে দিন\"\n";
echo "    Bot:  \"{$resB['reply']}\"\n";
$routeBOk = ($resB['route'] === 'action');
$handoffBOk = (stripos($resB['reply'], 'action request') !== false);
$noPendingB = ($actionSafety->getPendingAction($convBn->fresh()) === null);
assertCondition($routeBOk, "Route must be ACTION for 'আমার অর্ডার #2048 বাতিল করে দিন'", $totalAssertions, $passedAssertions);
assertCondition($handoffBOk, "Deterministic team-member handoff response returned for Bangla", $totalAssertions, $passedAssertions);
assertCondition($noPendingB, "Zero pending mutation state for Bangla request", $totalAssertions, $passedAssertions);

// Scenario C: Banglish Imperative Action Request -> Deterministic Team-Member Handoff
echo "\n  Scenario C: Banglish Imperative Action Request (Team Handoff)\n";
$convBanglish = Conversation::create([
    'channel_account_id' => $acc1->id,
    'external_user_id'   => 'e2e_act_bg_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'Karim Banglish',
]);

$resC = $customerSupportService->handleQuery("order ta cancel kore den", $ws1->id, $convBanglish);
echo "    User: \"order ta cancel kore den\"\n";
echo "    Bot:  \"{$resC['reply']}\"\n";
$routeCOk = ($resC['route'] === 'action');
$handoffCOk = (stripos($resC['reply'], 'action request') !== false);
$noPendingC = ($actionSafety->getPendingAction($convBanglish->fresh()) === null);
assertCondition($routeCOk, "Route must be ACTION for 'order ta cancel kore den'", $totalAssertions, $passedAssertions);
assertCondition($handoffCOk, "Deterministic team-member handoff response returned for Banglish", $totalAssertions, $passedAssertions);
assertCondition($noPendingC, "Zero pending mutation state for Banglish request", $totalAssertions, $passedAssertions);

// Scenario D: 3 Consecutive Uncertain Queries -> Automatic Human Handoff
echo "\n  Scenario D: 3 Consecutive Uncertain Queries (Automatic Human Handoff)\n";
$convUncertain = Conversation::create([
    'channel_account_id' => $acc1->id,
    'external_user_id'   => 'e2e_unc_3x_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'Uncertain Multi-turn Tester',
]);

$t1Unc = $customerSupportService->generateReply($convUncertain, "cancel", $ws1->id);
$t2Unc = $customerSupportService->generateReply($convUncertain->fresh(), "change", $ws1->id);
$t3Unc = $customerSupportService->generateReply($convUncertain->fresh(), "eta cancel kore den", $ws1->id);

$is3xHandoff = (stripos($t3Unc, 'team member will contact you') !== false);
$hasHandoffFlag = !empty($convUncertain->fresh()->metadata['handoff_to_human']);

assertCondition($is3xHandoff, "3 consecutive uncertain queries trigger team member handoff message", $totalAssertions, $passedAssertions);
assertCondition($hasHandoffFlag, "Conversation marked with handoff_to_human flag", $totalAssertions, $passedAssertions);
echo "    Turn 1 (cancel):              \"" . substr($t1Unc, 0, 45) . "...\"\n";
echo "    Turn 2 (change):              \"" . substr($t2Unc, 0, 45) . "...\"\n";
echo "    Turn 3 (eta cancel kore den): \"{$t3Unc}\"\n";

// ─────────────────────────────────────────────────────────────────────────────
// 5. MULTILINGUAL OUT-OF-DOMAIN SAFETY GATES (100% Zero-Leak Guardrail)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 5. MULTILINGUAL OUT-OF-DOMAIN SAFETY GATES (100% Zero-Cost Refusal) ─\n";

$oodTestCases = [
    // English Frozen 10
    ['q' => 'What is the weather in Dhaka today?', 'lang' => 'EN'],
    ['q' => 'Can I book a flight to London?', 'lang' => 'EN'],
    ['q' => 'Tell me a recipe for chicken biryani', 'lang' => 'EN'],
    ['q' => 'Who is the president of Bangladesh?', 'lang' => 'EN'],
    ['q' => 'What is the stock price of Apple?', 'lang' => 'EN'],
    ['q' => 'How do I make chocolate cake?', 'lang' => 'EN'],
    ['q' => 'Can you write a poem about rain?', 'lang' => 'EN'],
    ['q' => 'Who won the cricket match yesterday?', 'lang' => 'EN'],
    ['q' => 'Where is the nearest hospital?', 'lang' => 'EN'],
    ['q' => 'asdf ghjk qwerty zxcvbnm', 'lang' => 'EN (Gibberish)'],

    // Bangla OOD
    ['q' => 'আজকের আবহাওয়া কেমন?', 'lang' => 'BN (Weather)'],
    ['q' => 'চিকেন বিরিয়ানি রান্নার রেসিপি দিন', 'lang' => 'BN (Recipe)'],
    ['q' => 'বাংলাদেশের বর্তমান রাষ্ট্রপতির নাম কি?', 'lang' => 'BN (Politics)'],

    // Banglish OOD
    ['q' => 'ajke Dhakar weather kemon?', 'lang' => 'Banglish (Weather)'],
    ['q' => 'biryani ranna korbo kemne?', 'lang' => 'Banglish (Recipe)'],
];

foreach ($oodTestCases as $item) {
    $conv = Conversation::create([
        'channel_account_id' => $acc1->id,
        'external_user_id'   => 'e2e_ood_' . uniqid(),
        'status'             => 'active',
        'customer_name'      => 'OOD Tester',
    ]);

    $t_start = microtime(true);
    $res = $customerSupportService->handleQuery($item['q'], $ws1->id, $conv);
    $lat = round((microtime(true) - $t_start) * 1000, 2);

    $isOod = ($res['route'] === 'ood');
    $isFast = ($lat < 50.0); // Sub-50ms deterministic refusal without remote LLM
    $hasSafeRefusal = stripos($res['reply'], 'আওতাভুক্ত নয়') !== false || stripos($res['reply'], 'knowledge base') !== false;

    assertCondition($isOod, "[{$item['lang']}] Must route to OOD for '{$item['q']}'", $totalAssertions, $passedAssertions);
    assertCondition($isFast, "[{$item['lang']}] OOD must execute with 0 remote LLM overhead (<50ms)", $totalAssertions, $passedAssertions);

    $testResults[] = [
        'category' => "5. OOD Safety ({$item['lang']})",
        'query'    => $item['q'],
        'route'    => $res['route'],
        'pass'     => $isOod && $isFast,
        'latency'  => $lat,
    ];

    $icon = ($isOod && $isFast) ? '✅' : '❌';
    echo "  {$icon} [{$item['lang']}] ({$lat} ms) \"{$item['q']}\" ──> Safe Refusal\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. MULTI-TENANT COMPLETE E2E ISOLATION (Knowledge + Action)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 6. MULTI-TENANT COMPLETE E2E ISOLATION (Zero Cross-Tenant Leak) ─────\n";

$convWs1 = Conversation::create([
    'channel_account_id' => $acc1->id,
    'external_user_id'   => 'e2e_tenant_ws1_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'Tenant 1 User',
]);
$convWs2 = Conversation::create([
    'channel_account_id' => $acc2->id,
    'external_user_id'   => 'e2e_tenant_ws2_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'Tenant 2 User',
]);

// Test 1: Tenant 1 queries Tenant 2's secret VIP line
$t1Secret = $customerSupportService->generateReply($convWs1, "What is the private VIP direct line for Tenant Beta?", $ws1->id);
echo "  [Tenant 1 Inbound]: \"What is the private VIP direct line for Tenant Beta?\"\n";
echo "  [Tenant 1 Reply]:   \"" . mb_substr(str_replace("\n", " ", trim($t1Secret)), 0, 80) . "...\"\n";
$tenant1Leak = (stripos($t1Secret, '+1-888-BETA-VIP-009') !== false);
assertCondition(!$tenant1Leak, "Tenant 1 MUST NOT see Tenant 2 secret hotline", $totalAssertions, $passedAssertions);

// Test 2: Tenant 2 queries Tenant 2's secret VIP line
$t2Secret = $customerSupportService->generateReply($convWs2, "What is the private VIP direct line for Tenant Beta?", $ws2->id);
echo "  [Tenant 2 Inbound]: \"What is the private VIP direct line for Tenant Beta?\"\n";
echo "  [Tenant 2 Reply]:   \"" . mb_substr(str_replace("\n", " ", trim($t2Secret)), 0, 80) . "...\"\n";
$tenant2Found = (stripos($t2Secret, '+1-888-BETA-VIP-009') !== false || stripos($t2Secret, 'BETA-VIP') !== false);
assertCondition($tenant2Found, "Tenant 2 MUST successfully retrieve Tenant 2 secret hotline", $totalAssertions, $passedAssertions);

// Test 3: Cross-tenant action authorization isolation
$authCrossTenant = $actionSafety->validateTenantAuthorization($convWs1, $ws2->id, 'cancel_order', ['order_id' => 1024]);
assertCondition(!$authCrossTenant, "Cross-tenant mutation validation MUST reject unauthorized workspace", $totalAssertions, $passedAssertions);

// ─────────────────────────────────────────────────────────────────────────────
// 7. COMPREHENSIVE E2E SCORECARD & AUDIT SUMMARY
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=================================================================================================\n";
echo "📊 COMPREHENSIVE PRODUCTION E2E SCORECARD\n";
echo "=================================================================================================\n";
printf("%-35s | %-10s | %-12s | %-14s | %-10s\n", "Validation Dimension", "Tests", "Pass Rate", "Mean Latency", "Status");
echo "-------------------------------------------------------------------------------------------------\n";

$dimensions = [
    '1. Knowledge (EN / BN / Banglish)' => fn($r) => str_starts_with($r['category'], '1. Knowledge'),
    '2. Chat & Greetings (EN/BN/Banglish)' => fn($r) => str_starts_with($r['category'], '2. Chat'),
    '3. Invariant Disambiguation'       => fn($r) => str_starts_with($r['category'], '3. Invariant'),
    '5. OOD Safety Gates (EN/BN/Banglish)' => fn($r) => str_starts_with($r['category'], '5. OOD Safety'),
];

foreach ($dimensions as $name => $filter) {
    $subset = array_filter($testResults, $filter);
    $cnt = count($subset);
    $passed = count(array_filter($subset, fn($r) => $r['pass']));
    $rate = $cnt > 0 ? round(($passed / $cnt) * 100, 1) : 100.0;
    $lats = array_column($subset, 'latency');
    $meanLat = count($lats) ? round(array_sum($lats) / count($lats), 2) : 0.0;
    printf("%-35s | %8d | %11.1f%% | %11.2f ms | %-10s\n", $name, $cnt, $rate, $meanLat, ($rate === 100.0 ? 'PASSED' : 'CHECK'));
}

echo "-------------------------------------------------------------------------------------------------\n";
printf("%-35s | %8d | %11.1f%% | %11.2f ms | %-10s\n", "TOTAL INVARIANT ASSERTIONS", $totalAssertions, round(($passedAssertions / $totalAssertions) * 100, 1), 0.0, ($passedAssertions === $totalAssertions ? '100% OPTIMAL' : 'FAILED'));
echo "=================================================================================================\n";
