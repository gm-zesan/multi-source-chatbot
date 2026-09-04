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
use App\Models\Workspace;
use App\Services\AI\ActionSafetyService;
use App\Services\AI\CustomerSupportService;

echo "=================================================================================================\n";
echo "🎯 BENCHMARK: HYBRID ROUTER BOUNDARY & MIXED-INTENT STRESS TEST SUITE (52+ QUERIES)\n";
echo "=================================================================================================\n";

// Configure model provider
$experimentApiKey   = env('DEEPSEEK_API_KEY');
$experimentApiUrl   = env('DEEPSEEK_URL', env('PHASE2G2_DEEPSEEK_URL', 'https://api.deepseek.com'));

config([
    'ai.default'                => 'deepseek',
    'ai.default_model'          => 'deepseek-chat',
    'ai.providers.deepseek.key' => $experimentApiKey,
    'ai.providers.deepseek.url' => $experimentApiUrl,
]);

$router = new HybridRouter();
$actionSafety = new ActionSafetyService();
$customerSupportService = app(CustomerSupportService::class);

$ws = Workspace::firstOrCreate(['slug' => 'boundary-test-workspace'], ['name' => 'Boundary Stress Workspace']);
$chan = Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook', 'is_active' => true]);
$acc = ChannelAccount::firstOrCreate(
    ['external_id' => 'fb_acc_boundary'],
    ['channel_id' => $chan->id, 'workspace_id' => $ws->id, 'name' => 'Boundary Test Account', 'access_token' => 'tok_bnd', 'is_active' => true]
);

// ── 52 Comprehensive Test Cases across all 13 Categories ──
$matrix = [
    // ── 1. Pure Greeting / Chitchat ──
    ['cat' => '01. Pure Chat', 'lang' => 'EN', 'q' => 'hello there!', 'exp' => RouteType::CHAT],
    ['cat' => '01. Pure Chat', 'lang' => 'BN', 'q' => 'আসসালামু আলাইকুম, কেমন আছেন?', 'exp' => RouteType::CHAT],
    ['cat' => '01. Pure Chat', 'lang' => 'Banglish', 'q' => 'kemon asen vai?', 'exp' => RouteType::CHAT],
    ['cat' => '01. Pure Chat', 'lang' => 'EN', 'q' => 'who are you, are you a bot or human?', 'exp' => RouteType::CHAT],

    // ── 2. Greeting + Knowledge Question ──
    ['cat' => '02. Greeting+Knowledge', 'lang' => 'EN', 'q' => 'hi, how do I change my payment method?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '02. Greeting+Knowledge', 'lang' => 'BN', 'q' => 'সালাম ভাই, রিফান্ড পলিসি কি?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '02. Greeting+Knowledge', 'lang' => 'Banglish', 'q' => 'hello vai, invoice ta kothay pabo?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '02. Greeting+Knowledge', 'lang' => 'EN', 'q' => 'good morning! What plans are available?', 'exp' => RouteType::KNOWLEDGE],

    // ── 3. Greeting + Action Command ──
    ['cat' => '03. Greeting+Action', 'lang' => 'EN', 'q' => 'hello, please cancel my order #1024', 'exp' => RouteType::ACTION],
    ['cat' => '03. Greeting+Action', 'lang' => 'BN', 'q' => 'আসসালামু আলাইকুম ভাই, আমার অর্ডার #502 বাতিল করুন', 'exp' => RouteType::ACTION],
    ['cat' => '03. Greeting+Action', 'lang' => 'Banglish', 'q' => 'hi bro, order ta cancel kore den', 'exp' => RouteType::ACTION],
    ['cat' => '03. Greeting+Action', 'lang' => 'EN', 'q' => 'hey there, track order #2048', 'exp' => RouteType::ACTION],

    // ── 4. Thanks + Knowledge Question ──
    ['cat' => '04. Thanks+Knowledge', 'lang' => 'EN', 'q' => 'thanks, but how do I view my invoices?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '04. Thanks+Knowledge', 'lang' => 'BN', 'q' => 'ধন্যবাদ, কিন্তু ডেলিভারি চার্জ কত?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '04. Thanks+Knowledge', 'lang' => 'Banglish', 'q' => 'dhonnobad vai, password reset korbo kivabe?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '04. Thanks+Knowledge', 'lang' => 'EN', 'q' => 'thank you so much! Can I use multiple channels simultaneously?', 'exp' => RouteType::KNOWLEDGE],

    // ── 5. Thanks + Action Command ──
    ['cat' => '05. Thanks+Action', 'lang' => 'EN', 'q' => 'thanks, but cancel my order #1024', 'exp' => RouteType::ACTION],
    ['cat' => '05. Thanks+Action', 'lang' => 'BN', 'q' => 'ধন্যবাদ ভাই, কিন্তু অর্ডার #555 বাতিল করুন', 'exp' => RouteType::ACTION],
    ['cat' => '05. Thanks+Action', 'lang' => 'Banglish', 'q' => 'onek dhonnobad, amar order #99 cancel kore den', 'exp' => RouteType::ACTION],
    ['cat' => '05. Thanks+Action', 'lang' => 'EN', 'q' => 'thank you, please create a ticket for double billing issue', 'exp' => RouteType::ACTION],

    // ── 6. Question wording + Mutation keyword ──
    ['cat' => '06. Question+MutationKeyword', 'lang' => 'EN', 'q' => 'Can you tell me if I can cancel my order?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '06. Question+MutationKeyword', 'lang' => 'BN', 'q' => 'অর্ডার বাতিল করার নিয়ম কি?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '06. Question+MutationKeyword', 'lang' => 'Banglish', 'q' => 'order cancel kora jabe ki?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '06. Question+MutationKeyword', 'lang' => 'EN', 'q' => 'how do I cancel my subscription?', 'exp' => RouteType::KNOWLEDGE],

    // ── 7. Casual wording + Mutation command ──
    ['cat' => '07. Casual Mutation', 'lang' => 'Banglish', 'q' => 'order ta cancel kore den', 'exp' => RouteType::ACTION],
    ['cat' => '07. Casual Mutation', 'lang' => 'BN', 'q' => 'আমার অর্ডারটি বাতিল করে দিন', 'exp' => RouteType::ACTION],
    ['cat' => '07. Casual Mutation', 'lang' => 'Banglish', 'q' => 'vai payment method change kore den', 'exp' => RouteType::ACTION],
    ['cat' => '07. Casual Mutation', 'lang' => 'EN', 'q' => 'please cancel my order #777', 'exp' => RouteType::ACTION],

    // ── 8. Code-Switching (Bangla + English) ──
    ['cat' => '08. Code-Switching', 'lang' => 'Mixed', 'q' => 'hi vai, invoice ta kothay pabo?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '08. Code-Switching', 'lang' => 'Mixed', 'q' => 'vai amar order #1024 cancel kore den', 'exp' => RouteType::ACTION],
    ['cat' => '08. Code-Switching', 'lang' => 'Mixed', 'q' => 'security encryption kivabe kaj kore bolben?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '08. Code-Switching', 'lang' => 'Mixed', 'q' => 'open a ticket for double billing ভাই', 'exp' => RouteType::ACTION],

    // ── 9. Banglish Colloquial / Typo ──
    ['cat' => '09. Typo / Colloquial', 'lang' => 'Banglish', 'q' => 'payment kivabe chng korbo?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '09. Typo / Colloquial', 'lang' => 'Banglish', 'q' => 'order ta cancel kora jbe?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '09. Typo / Colloquial', 'lang' => 'Banglish', 'q' => 'vai refund policy ki bolbn?', 'exp' => RouteType::KNOWLEDGE],
    ['cat' => '09. Typo / Colloquial', 'lang' => 'Banglish', 'q' => 'passwrd reset kivbe krbo?', 'exp' => RouteType::KNOWLEDGE],

    // ── 10. Short Ambiguous / Bare Keyword ──
    ['cat' => '10. Bare Keyword', 'lang' => 'EN', 'q' => 'cancel', 'exp' => RouteType::UNCERTAIN],
    ['cat' => '10. Bare Keyword', 'lang' => 'EN', 'q' => 'change', 'exp' => RouteType::UNCERTAIN],
    ['cat' => '10. Bare Keyword', 'lang' => 'Mixed', 'q' => 'order cancel', 'exp' => RouteType::UNCERTAIN],
    ['cat' => '10. Bare Keyword', 'lang' => 'EN', 'q' => 'refund', 'exp' => RouteType::UNCERTAIN],

    // ── 11. Ambiguous Pronoun ──
    ['cat' => '11. Ambiguous Pronoun', 'lang' => 'EN', 'q' => 'cancel this', 'exp' => RouteType::UNCERTAIN],
    ['cat' => '11. Ambiguous Pronoun', 'lang' => 'Banglish', 'q' => 'eta cancel kore den', 'exp' => RouteType::UNCERTAIN],
    ['cat' => '11. Ambiguous Pronoun', 'lang' => 'EN', 'q' => 'please cancel it', 'exp' => RouteType::UNCERTAIN],
    ['cat' => '11. Ambiguous Pronoun', 'lang' => 'BN', 'q' => 'এটা পরিবর্তন করে দিন', 'exp' => RouteType::UNCERTAIN],

    // ── 12. Context-Dependent Confirmation / Standalone ──
    ['cat' => '12. Context Confirmation', 'lang' => 'EN', 'q' => 'yes', 'exp' => RouteType::CHAT],
    ['cat' => '12. Context Confirmation', 'lang' => 'BN', 'q' => 'হ্যাঁ', 'exp' => RouteType::CHAT],
    ['cat' => '12. Context Confirmation', 'lang' => 'EN', 'q' => 'no', 'exp' => RouteType::CHAT],
    ['cat' => '12. Context Confirmation', 'lang' => 'BN', 'q' => 'না', 'exp' => RouteType::CHAT],

    // ── 13. OOD + Conversational Greeting ──
    ['cat' => '13. OOD + Greeting', 'lang' => 'EN', 'q' => 'hi, what is the weather in Dhaka today?', 'exp' => RouteType::OOD],
    ['cat' => '13. OOD + Greeting', 'lang' => 'EN', 'q' => 'hello, recipe for chicken biryani', 'exp' => RouteType::OOD],
    ['cat' => '13. OOD + Greeting', 'lang' => 'BN', 'q' => 'হ্যালো ভাই, আজকের আবহাওয়া কেমন?', 'exp' => RouteType::OOD],
    ['cat' => '13. OOD + Greeting', 'lang' => 'EN', 'q' => 'good morning! Who is the president of Bangladesh?', 'exp' => RouteType::OOD],
];

$totalTests = count($matrix);
$passed = 0;
$results = [];

echo "\nEvaluating " . $totalTests . " Boundary Stress Queries across all 13 Categories...\n\n";

foreach ($matrix as $idx => $item) {
    $conv = Conversation::create([
        'channel_account_id' => $acc->id,
        'external_user_id'   => 'bnd_' . uniqid(),
        'status'             => 'active',
        'customer_name'      => 'Boundary Tester',
    ]);

    $t_start = microtime(true);
    $res = $customerSupportService->handleQuery($item['q'], $ws->id, $conv);
    $lat = round((microtime(true) - $t_start) * 1000, 2);

    $actualRoute = RouteType::from($res['route']);
    $isRouteCorrect = ($actualRoute === $item['exp']);

    // Check invariants
    $invariantOk = true;
    if ($actualRoute === RouteType::CHAT || $actualRoute === RouteType::OOD) {
        if ($res['retrieval_hits']->isNotEmpty()) {
            $invariantOk = false;
        }
    }

    $ok = $isRouteCorrect && $invariantOk;
    if ($ok) {
        $passed++;
    }

    $icon = $ok ? '✅' : '❌';
    $num = str_pad((string)($idx + 1), 2, ' ', STR_PAD_LEFT);
    echo "  {$icon} [{$num}/{$totalTests}] [{$item['cat']}] [{$item['lang']}] ({$lat} ms) \"{$item['q']}\"\n";
    echo "       ↳ Route: Expected=[{$item['exp']->value}], Actual=[{$actualRoute->value}] (Intent: {$res['routing_telemetry']['intent']}, Conf: {$res['confidence']})\n";
    echo "       ↳ Reply: \"" . mb_substr(str_replace("\n", " ", trim($res['reply'])), 0, 75) . "...\"\n";

    $results[] = [
        'cat'            => $item['cat'],
        'lang'           => $item['lang'],
        'query'          => $item['q'],
        'expected_route' => $item['exp']->value,
        'actual_route'   => $actualRoute->value,
        'intent'         => $res['routing_telemetry']['intent'],
        'confidence'     => $res['confidence'],
        'latency'        => $lat,
        'pass'           => $ok,
    ];

    usleep(100000); // 100ms spacing
}

// ── Multi-Turn E2E Multi-Language Verification ──
echo "\n=================================================================================================\n";
echo "🔄 MULTI-TURN MULTI-LANGUAGE ACTION CONFIRMATION & SAFETY VERIFICATION\n";
echo "=================================================================================================\n";

// 1. English Flow
$convEn = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'e2e_en_' . uniqid(), 'status' => 'active', 'customer_name' => 'EN Tester']);
echo "--- 1. English Multi-Turn Flow ---\n";
echo "User: \"Can you please cancel my order #1024?\"\n";
$r1En = $customerSupportService->generateReply($convEn, "Can you please cancel my order #1024?", $ws->id);
echo "Bot: \"{$r1En}\"\n";
$pending1En = $actionSafety->getPendingAction($convEn->fresh());
echo "Pending State: " . ($pending1En ? "Active ({$pending1En['action']} on order #{$pending1En['parameters']['order_id']})" : "None") . "\n";
echo "User: \"Yes, please do\"\n";
$r2En = $customerSupportService->generateReply($convEn->fresh(), "Yes, please do", $ws->id);
echo "Bot: \"{$r2En}\"\n";
$pending2En = $actionSafety->getPendingAction($convEn->fresh());
echo "Pending State After: " . ($pending2En ? "Still Active" : "Cleared (Executed & Safe)") . "\n";

// 2. Bangla Flow
$convBn = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'e2e_bn_' . uniqid(), 'status' => 'active', 'customer_name' => 'BN Tester']);
echo "\n--- 2. Bangla Multi-Turn Flow ---\n";
echo "User: \"আমার অর্ডার #2048 বাতিল করে দিন\"\n";
$r1Bn = $customerSupportService->generateReply($convBn, "আমার অর্ডার #2048 বাতিল করে দিন", $ws->id);
echo "Bot: \"{$r1Bn}\"\n";
$pending1Bn = $actionSafety->getPendingAction($convBn->fresh());
echo "Pending State: " . ($pending1Bn ? "Active ({$pending1Bn['action']} on order #{$pending1Bn['parameters']['order_id']})" : "None") . "\n";
echo "User: \"হ্যাঁ, বাতিল করুন\"\n";
$r2Bn = $customerSupportService->generateReply($convBn->fresh(), "হ্যাঁ, বাতিল করুন", $ws->id);
echo "Bot: \"{$r2Bn}\"\n";
$pending2Bn = $actionSafety->getPendingAction($convBn->fresh());
echo "Pending State After: " . ($pending2Bn ? "Still Active" : "Cleared (Executed & Safe)") . "\n";

// 3. Banglish Flow (Rejection Case)
$convBanglish = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'e2e_bg_' . uniqid(), 'status' => 'active', 'customer_name' => 'Banglish Tester']);
echo "\n--- 3. Banglish Multi-Turn Rejection Flow ---\n";
echo "User: \"order #3090 cancel kore den\"\n";
$r1Bg = $customerSupportService->generateReply($convBanglish, "order #3090 cancel kore den", $ws->id);
echo "Bot: \"{$r1Bg}\"\n";
$pending1Bg = $actionSafety->getPendingAction($convBanglish->fresh());
echo "Pending State: " . ($pending1Bg ? "Active ({$pending1Bg['action']} on order #{$pending1Bg['parameters']['order_id']})" : "None") . "\n";
echo "User: \"na, dorkar nei\"\n";
$r2Bg = $customerSupportService->generateReply($convBanglish->fresh(), "na, dorkar nei", $ws->id);
echo "Bot: \"{$r2Bg}\"\n";
$pending2Bg = $actionSafety->getPendingAction($convBanglish->fresh());
echo "Pending State After: " . ($pending2Bg ? "Still Active" : "Cleared (No Mutation, Rejection Handled)") . "\n";

// ── Summary Scorecard ──
echo "\n=================================================================================================\n";
echo "📊 BOUNDARY & MIXED-INTENT BENCHMARK SCORECARD\n";
echo "=================================================================================================\n";
printf("%-30s | %-8s | %-10s | %-12s | %-10s\n", "Boundary Category", "Count", "Pass Rate", "Mean Latency", "Status");
echo "-------------------------------------------------------------------------------------------------\n";

$categories = array_unique(array_column($matrix, 'cat'));
sort($categories);

foreach ($categories as $cat) {
    $subset = array_filter($results, fn($r) => $r['cat'] === $cat);
    $cnt = count($subset);
    $pCnt = count(array_filter($subset, fn($r) => $r['pass']));
    $rate = round(($pCnt / $cnt) * 100, 1);
    $lats = array_column($subset, 'latency');
    $meanLat = round(array_sum($lats) / count($lats), 2);
    printf("%-30s | %8d | %9.1f%% | %10.2f ms | %-10s\n", $cat, $cnt, $rate, $meanLat, ($rate === 100.0 ? 'PASSED' : 'CHECK'));
}

echo "-------------------------------------------------------------------------------------------------\n";
$allLats = array_column($results, 'latency');
$overallMeanLat = round(array_sum($allLats) / count($allLats), 2);
$overallPassRate = round(($passed / $totalTests) * 100, 1);
printf("%-30s | %8d | %9.1f%% | %10.2f ms | %-10s\n", "OVERALL BOUNDARY BENCHMARK", $totalTests, $overallPassRate, $overallMeanLat, ($passed === $totalTests ? '100% OPTIMAL' : 'FAILED'));
echo "=================================================================================================\n";
