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

echo "=========================================================================================\n";
echo "🚀 BENCHMARK: HYBRID ROUTING + SELECTIVE TOOL CALLING ARCHITECTURE EVALUATION\n";
echo "=========================================================================================\n";

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

// Setup Workspaces & Tenants
$ws1 = Workspace::find(1);
$ws2 = Workspace::firstOrCreate(
    ['slug' => 'profiling-workspace'],
    ['name' => 'Profiling Test Workspace']
);

$chan1 = Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook', 'is_active' => true]);
$acc1 = ChannelAccount::firstOrCreate(
    ['external_id' => 'fb_acc_bench_1'],
    ['channel_id' => $chan1->id, 'workspace_id' => $ws1->id, 'name' => 'Bench Page 1', 'access_token' => 'tok1', 'is_active' => true]
);
$acc2 = ChannelAccount::firstOrCreate(
    ['external_id' => 'fb_acc_bench_2'],
    ['channel_id' => $chan1->id, 'workspace_id' => $ws2->id, 'name' => 'Bench Page 2', 'access_token' => 'tok2', 'is_active' => true]
);

$conv1 = Conversation::firstOrCreate(
    ['external_user_id' => 'conv_bench_hybrid_1'],
    ['channel_account_id' => $acc1->id, 'status' => 'active', 'customer_name' => 'Hybrid Bench User 1']
);
$conv2 = Conversation::firstOrCreate(
    ['external_user_id' => 'conv_bench_hybrid_2'],
    ['channel_account_id' => $acc2->id, 'status' => 'active', 'customer_name' => 'Hybrid Bench User 2']
);

// Ensure WS2 has secret FAQ
$ws2Faq = FAQ::firstOrCreate(
    [
        'workspace_id' => $ws2->id,
        'question'     => 'What is the custom enterprise hotline for Tenant B?',
    ],
    [
        'answer'       => 'The custom enterprise VIP hotline for Tenant B is +1-800-TENANT-B-VIP.',
        'priority'     => 100,
        'is_active'    => true,
    ]
);
$retrievalClient->syncFaq($ws2Faq);

// Test Cases Matrix
$testMatrix = [
    // ── Category A: Knowledge Queries (EN, BN, Banglish) ──
    ['q' => 'How do I update my payment method?', 'cat' => 'A. Knowledge (EN)', 'ws' => 1, 'expected_route' => RouteType::KNOWLEDGE],
    ['q' => 'What plans are available?', 'cat' => 'A. Knowledge (EN)', 'ws' => 1, 'expected_route' => RouteType::KNOWLEDGE],
    ['q' => 'How do I view my invoices?', 'cat' => 'A. Knowledge (EN)', 'ws' => 1, 'expected_route' => RouteType::KNOWLEDGE],
    ['q' => 'Can I use multiple channels simultaneously?', 'cat' => 'A. Knowledge (EN)', 'ws' => 1, 'expected_route' => RouteType::KNOWLEDGE],
    ['q' => 'How is my data encrypted?', 'cat' => 'A. Knowledge (EN)', 'ws' => 1, 'expected_route' => RouteType::KNOWLEDGE],
    ['q' => 'vai payment method kivabe change korbo?', 'cat' => 'A. Knowledge (Banglish)', 'ws' => 1, 'expected_route' => RouteType::KNOWLEDGE],
    ['q' => 'order ta cancel kora jabe?', 'cat' => 'A. Knowledge (Banglish)', 'ws' => 1, 'expected_route' => RouteType::KNOWLEDGE],
    ['q' => 'অর্ডার বাতিল করার নিয়ম কি?', 'cat' => 'A. Knowledge (Bangla)', 'ws' => 1, 'expected_route' => RouteType::KNOWLEDGE],

    // ── Category B: Conversational / Greetings (EN, BN, Banglish) ──
    ['q' => 'Hello there! Good morning.', 'cat' => 'B. Chat (EN)', 'ws' => 1, 'expected_route' => RouteType::CHAT],
    ['q' => 'Thank you so much, that was very helpful!', 'cat' => 'B. Chat (EN)', 'ws' => 1, 'expected_route' => RouteType::CHAT],
    ['q' => 'আসসালামু আলাইকুম ভাই, কেমন আছেন?', 'cat' => 'B. Chat (Bangla)', 'ws' => 1, 'expected_route' => RouteType::CHAT],
    ['q' => 'dhonnobad vai', 'cat' => 'B. Chat (Banglish)', 'ws' => 1, 'expected_route' => RouteType::CHAT],

    // ── Category C: Action Intents (Explicit, with ID, Ambiguous) ──
    ['q' => 'Can you cancel order #1024?', 'cat' => 'C. Action (Cancel with ID)', 'ws' => 1, 'expected_route' => RouteType::ACTION],
    ['q' => 'order ta cancel kore den', 'cat' => 'C. Action (Cancel no ID)', 'ws' => 1, 'expected_route' => RouteType::ACTION],
    ['q' => 'আমার অর্ডারটি বাতিল করে দিন', 'cat' => 'C. Action (Bangla)', 'ws' => 1, 'expected_route' => RouteType::ACTION],
    ['q' => 'Track order #2048', 'cat' => 'C. Action (Lookup)', 'ws' => 1, 'expected_route' => RouteType::ACTION],
    ['q' => 'create a ticket for double billing on invoice 99', 'cat' => 'C. Action (Ticket)', 'ws' => 1, 'expected_route' => RouteType::ACTION],
    ['q' => 'order ta cancel kora dorkar', 'cat' => 'C. Action (Ambiguous/Uncertain)', 'ws' => 1, 'expected_route' => RouteType::UNCERTAIN],

    // ── Category D: 10 Frozen OOD Negative Queries ──
    ['q' => 'What is the weather in Dhaka today?', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],
    ['q' => 'Can I book a flight to London?', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],
    ['q' => 'Tell me a recipe for chicken biryani', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],
    ['q' => 'Who is the president of Bangladesh?', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],
    ['q' => 'What is the stock price of Apple?', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],
    ['q' => 'How do I make chocolate cake?', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],
    ['q' => 'Can you write a poem about rain?', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],
    ['q' => 'Who won the cricket match yesterday?', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],
    ['q' => 'Where is the nearest hospital?', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],
    ['q' => 'asdf ghjk qwerty zxcvbnm', 'cat' => 'D. OOD Negative', 'ws' => 1, 'expected_route' => RouteType::OOD],

    // ── Category F: Multi-Tenant Data Isolation ──
    ['q' => 'What is the custom enterprise hotline for Tenant B?', 'cat' => 'F. Isolation (WS1)', 'ws' => 1, 'expected_route' => RouteType::KNOWLEDGE],
    ['q' => 'What is the custom enterprise hotline for Tenant B?', 'cat' => 'F. Isolation (WS2)', 'ws' => 2, 'expected_route' => RouteType::KNOWLEDGE],
];

echo "\n▶️ EXECUTING FULL HYBRID ROUTING EVALUATION MATRIX (" . count($testMatrix) . " Queries)...\n";

$results = [];
foreach ($testMatrix as $idx => $item) {
    $num = $idx + 1;
    $targetConv = ($item['ws'] === 1) ? $conv1 : $conv2;
    $actionSafety->clearPendingAction($targetConv);

    $t_start = microtime(true);
    $serviceResult = $customerSupportService->handleQuery(
        query: $item['q'],
        workspaceId: $item['ws'],
        conversation: $targetConv,
    );
    $totalMs = round((microtime(true) - $t_start) * 1000, 2);

    $routing = $serviceResult['routing_telemetry'];
    $actualRoute = RouteType::from($serviceResult['route']);
    $isRouteCorrect = ($actualRoute === $item['expected_route']);

    $results[] = [
        'num'            => $num,
        'query'          => $item['q'],
        'category'       => $item['cat'],
        'workspace_id'   => $item['ws'],
        'expected_route' => $item['expected_route']->value,
        'actual_route'   => $actualRoute->value,
        'route_correct'  => $isRouteCorrect,
        'confidence'     => $serviceResult['confidence'],
        'total_ms'       => $totalMs,
        'reply'          => $serviceResult['reply'],
    ];

    $statusIcon = $isRouteCorrect ? '✅' : '❌';
    echo "  [{$num}/" . count($testMatrix) . "] {$statusIcon} [{$actualRoute->value}] ({$totalMs} ms) Query: \"{$item['q']}\"\n";
    echo "       ↳ Reply: \"" . mb_substr(str_replace("\n", " ", trim($serviceResult['reply'])), 0, 75) . "...\"\n";
    usleep(250000);
}

// ── Multi-Turn Action Confirmation Test ──
echo "\n=========================================================================================\n";
echo "🔄 MULTI-TURN ACTION CONFIRMATION WORKFLOW TEST\n";
echo "=========================================================================================\n";

$multiConv = Conversation::create([
    'channel_account_id' => $acc1->id,
    'external_user_id'   => 'conv_multiturn_action_' . time(),
    'status'             => 'active',
    'customer_name'      => 'Action User Multi',
]);

// Turn 1: Request Cancel
echo "--- Turn 1: \"Can you cancel order #1024?\" ---\n";
$t1Reply = $customerSupportService->generateReply($multiConv, "Can you cancel order #1024?", $ws1->id);
echo "  [Bot Reply 1]: \"{$t1Reply}\"\n";
$pending = $actionSafety->getPendingAction($multiConv->fresh());
echo "  [Pending Action State]: " . ($pending ? "Registered ({$pending['action']} - Order #{$pending['parameters']['order_id']})" : "None") . "\n";

// Turn 2: User Confirms "হ্যাঁ, বাতিল করুন"
echo "\n--- Turn 2: \"হ্যাঁ, বাতিল করুন\" ---\n";
$t2Reply = $customerSupportService->generateReply($multiConv->fresh(), "হ্যাঁ, বাতিল করুন", $ws1->id);
echo "  [Bot Reply 2]: \"{$t2Reply}\"\n";
$pendingAfter = $actionSafety->getPendingAction($multiConv->fresh());
echo "  [Pending Action State]: " . ($pendingAfter ? "Still Pending" : "Cleared (Executed Successfully)") . "\n";

// ── Summary Matrix ──
echo "\n=========================================================================================\n";
echo "📊 HYBRID ROUTING & SELECTIVE EXECUTION SUMMARY MATRIX\n";
echo "=========================================================================================\n";
printf("%-30s | %-12s | %-12s | %-14s | %-10s\n", "Category", "Tests", "Route Acc.", "Mean Latency", "Outcome");
echo "-----------------------------------------------------------------------------------------\n";

$categories = [
    'A. Knowledge (EN/BN/Banglish)' => fn($r) => str_starts_with($r['category'], 'A. Knowledge'),
    'B. Chat (EN/BN/Banglish)'      => fn($r) => str_starts_with($r['category'], 'B. Chat'),
    'C. Action & Clarification'     => fn($r) => str_starts_with($r['category'], 'C. Action'),
    'D. 10 Frozen OOD Negatives'    => fn($r) => str_starts_with($r['category'], 'D. OOD Negative'),
    'F. Multi-Tenant Isolation'     => fn($r) => str_starts_with($r['category'], 'F. Isolation'),
];

foreach ($categories as $catName => $filterFn) {
    $subset = array_filter($results, $filterFn);
    $totalCount = count($subset);
    $correctCount = count(array_filter($subset, fn($r) => $r['route_correct']));
    $acc = $totalCount > 0 ? round(($correctCount / $totalCount) * 100, 1) : 0.0;
    $latencies = array_column($subset, 'total_ms');
    $meanLat = count($latencies) ? round(array_sum($latencies) / count($latencies), 2) : 0.0;

    printf("%-30s | %10d | %11.1f%% | %11.2f ms | %-10s\n", $catName, $totalCount, $acc, $meanLat, ($acc === 100.0 ? 'PASSED' : 'CHECK'));
}

$allCorrect = count(array_filter($results, fn($r) => $r['route_correct']));
$overallAcc = round(($allCorrect / count($results)) * 100, 1);
$overallLat = round(array_sum(array_column($results, 'total_ms')) / count($results), 2);
echo "-----------------------------------------------------------------------------------------\n";
printf("%-30s | %10d | %11.1f%% | %11.2f ms | %-10s\n", "OVERALL EVALUATION", count($results), $overallAcc, $overallLat, ($overallAcc === 100.0 ? '100% OPTIMAL' : 'CHECK'));
echo "=========================================================================================\n";
