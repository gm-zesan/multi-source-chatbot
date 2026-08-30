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
use App\Services\AI\CustomerSupportService;

echo "=================================================================================================\n";
echo "💬 COMPREHENSIVE SYNONYM CHATTING & CONVERSATIONAL INVARIANT BENCHMARK\n";
echo "=================================================================================================\n";

// Configure model provider
$experimentApiKey   = env('DEEPSEEK_API_KEY') ?: env('PHASE2G2_DEEPSEEK_API_KEY');
$experimentApiUrl   = env('DEEPSEEK_URL', env('PHASE2G2_DEEPSEEK_URL', 'https://api.deepseek.com'));

config([
    'ai.default'                => 'deepseek',
    'ai.default_model'          => 'deepseek-chat',
    'ai.providers.deepseek.key' => $experimentApiKey,
    'ai.providers.deepseek.url' => $experimentApiUrl,
]);

$router = new HybridRouter();
$customerSupportService = app(CustomerSupportService::class);

$ws = Workspace::firstOrCreate(['slug' => 'synonym-test-workspace'], ['name' => 'Synonym Test Workspace']);
$chan = Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook', 'is_active' => true]);
$acc = ChannelAccount::firstOrCreate(
    ['external_id' => 'fb_acc_synonym'],
    ['channel_id' => $chan->id, 'workspace_id' => $ws->id, 'name' => 'Synonym Chat Account', 'access_token' => 'tok_syn', 'is_active' => true]
);

$synonymMatrix = [
    // ── 1. Greetings & Openers ──
    ['q' => 'hi there', 'lang' => 'EN', 'type' => 'Opener'],
    ['q' => 'hey bro', 'lang' => 'EN', 'type' => 'Opener'],
    ['q' => 'good morning!', 'lang' => 'EN', 'type' => 'Opener'],
    ['q' => 'yo wassup', 'lang' => 'EN', 'type' => 'Opener'],
    ['q' => 'howdy', 'lang' => 'EN', 'type' => 'Opener'],
    ['q' => 'হ্যালো ভাই', 'lang' => 'BN', 'type' => 'Opener'],
    ['q' => 'আসসালামু আলাইকুম', 'lang' => 'BN', 'type' => 'Opener'],
    ['q' => 'নমস্কার দাদা', 'lang' => 'BN', 'type' => 'Opener'],
    ['q' => 'কি অবস্থা আপনার?', 'lang' => 'BN', 'type' => 'Opener'],
    ['q' => 'সব ভালো তো ভাই?', 'lang' => 'BN', 'type' => 'Opener'],
    ['q' => 'hello vai', 'lang' => 'Banglish', 'type' => 'Opener'],
    ['q' => 'ki khobor bro?', 'lang' => 'Banglish', 'type' => 'Opener'],
    ['q' => 'ki obostha vai?', 'lang' => 'Banglish', 'type' => 'Opener'],
    ['q' => 'kemon asen vaiya?', 'lang' => 'Banglish', 'type' => 'Opener'],

    // ── 2. Liveness, Presence & Identity Checks ──
    ['q' => 'are you there?', 'lang' => 'EN', 'type' => 'Presence Check'],
    ['q' => 'who are you?', 'lang' => 'EN', 'type' => 'Identity Check'],
    ['q' => 'are you a bot or human?', 'lang' => 'EN', 'type' => 'Identity Check'],
    ['q' => 'is anyone online right now?', 'lang' => 'EN', 'type' => 'Presence Check'],
    ['q' => 'can you hear me?', 'lang' => 'EN', 'type' => 'Liveness Check'],
    ['q' => 'কেউ আছেন?', 'lang' => 'BN', 'type' => 'Presence Check'],
    ['q' => 'আপনি কি মানুষ নাকি রোবট?', 'lang' => 'BN', 'type' => 'Identity Check'],
    ['q' => 'কে কথা বলছেন?', 'lang' => 'BN', 'type' => 'Identity Check'],
    ['q' => 'শুনতে পাচ্ছেন আমাকে?', 'lang' => 'BN', 'type' => 'Liveness Check'],
    ['q' => 'keu achen?', 'lang' => 'Banglish', 'type' => 'Presence Check'],
    ['q' => 'apni ki robot?', 'lang' => 'Banglish', 'type' => 'Identity Check'],
    ['q' => 'shunte pacchen vai?', 'lang' => 'Banglish', 'type' => 'Liveness Check'],

    // ── 3. Gratitude & Appreciation ──
    ['q' => 'thank you so much!', 'lang' => 'EN', 'type' => 'Gratitude'],
    ['q' => 'appreciate it bro', 'lang' => 'EN', 'type' => 'Gratitude'],
    ['q' => 'many thanks for your help', 'lang' => 'EN', 'type' => 'Gratitude'],
    ['q' => 'অনেক ধন্যবাদ ভাইয়া', 'lang' => 'BN', 'type' => 'Gratitude'],
    ['q' => 'থ্যাংক ইউ সো মাচ', 'lang' => 'BN', 'type' => 'Gratitude'],
    ['q' => 'অনেক উপকার হলো আপনার', 'lang' => 'BN', 'type' => 'Gratitude'],
    ['q' => 'onek dhonnobad vai', 'lang' => 'Banglish', 'type' => 'Gratitude'],
    ['q' => 'thank u so much', 'lang' => 'Banglish', 'type' => 'Gratitude'],
    ['q' => 'onek upokar holo vaiya', 'lang' => 'Banglish', 'type' => 'Gratitude'],

    // ── 4. Farewells & Goodbyes ──
    ['q' => 'goodbye take care', 'lang' => 'EN', 'type' => 'Goodbye'],
    ['q' => 'see you later bro', 'lang' => 'EN', 'type' => 'Goodbye'],
    ['q' => 'have a nice day!', 'lang' => 'EN', 'type' => 'Goodbye'],
    ['q' => 'বিদায় ভালো থাকবেন', 'lang' => 'BN', 'type' => 'Goodbye'],
    ['q' => 'আল্লাহ হাফেজ', 'lang' => 'BN', 'type' => 'Goodbye'],
    ['q' => 'পরে কথা হবে ভাই', 'lang' => 'BN', 'type' => 'Goodbye'],
    ['q' => 'allah hafez vai', 'lang' => 'Banglish', 'type' => 'Goodbye'],
    ['q' => 'pore kotha hobe', 'lang' => 'Banglish', 'type' => 'Goodbye'],
    ['q' => 'ajker moto ashi tata', 'lang' => 'Banglish', 'type' => 'Goodbye'],

    // ── 5. Compliments & Pleasantries ──
    ['q' => 'you are awesome!', 'lang' => 'EN', 'type' => 'Compliment'],
    ['q' => 'great customer service', 'lang' => 'EN', 'type' => 'Compliment'],
    ['q' => 'দারুণ লাগলো কথা বলে', 'lang' => 'BN', 'type' => 'Compliment'],
    ['q' => 'খুব সুন্দর সার্ভিস আপনাদের', 'lang' => 'BN', 'type' => 'Compliment'],
    ['q' => 'darun service vai', 'lang' => 'Banglish', 'type' => 'Compliment'],
    ['q' => 'bhalo laglo kotha bole', 'lang' => 'Banglish', 'type' => 'Compliment'],
];

$results = [];
$totalTests = count($synonymMatrix);
$passed = 0;

echo "\nEvaluating " . $totalTests . " Synonym Chatting Queries across 5 Conversational Dimensions...\n\n";

foreach ($synonymMatrix as $idx => $item) {
    $conv = Conversation::create([
        'channel_account_id' => $acc->id,
        'external_user_id'   => 'syn_' . uniqid(),
        'status'             => 'active',
        'customer_name'      => 'Synonym Tester',
    ]);

    $t_start = microtime(true);
    $res = $customerSupportService->handleQuery($item['q'], $ws->id, $conv);
    $lat = round((microtime(true) - $t_start) * 1000, 2);

    $isChat = ($res['route'] === 'chat');
    $hasReply = !empty(trim($res['reply']));
    $ok = $isChat && $hasReply;

    if ($ok) {
        $passed++;
    }

    $icon = $ok ? '✅' : '❌';
    $num = str_pad((string)($idx + 1), 2, ' ', STR_PAD_LEFT);
    echo "  {$icon} [{$num}/{$totalTests}] [{$item['lang']}|{$item['type']}] ({$lat} ms) \"{$item['q']}\"\n";
    echo "       ↳ Reply: \"" . mb_substr(str_replace("\n", " ", trim($res['reply'])), 0, 75) . "...\"\n";

    $results[] = [
        'type'    => $item['type'],
        'lang'    => $item['lang'],
        'query'   => $item['q'],
        'route'   => $res['route'],
        'pass'    => $ok,
        'latency' => $lat,
    ];

    usleep(100000); // 100ms spacing
}

echo "\n=================================================================================================\n";
echo "📊 SYNONYM CHATTING BENCHMARK SCORECARD\n";
echo "=================================================================================================\n";
printf("%-25s | %-8s | %-10s | %-12s | %-10s\n", "Chat Category", "Count", "Pass Rate", "Mean Latency", "Status");
echo "-------------------------------------------------------------------------------------------------\n";

$types = ['Opener', 'Presence Check', 'Identity Check', 'Liveness Check', 'Gratitude', 'Goodbye', 'Compliment'];

foreach ($types as $type) {
    $subset = array_filter($results, fn($r) => $r['type'] === $type);
    $cnt = count($subset);
    if ($cnt === 0) continue;
    $pCnt = count(array_filter($subset, fn($r) => $r['pass']));
    $rate = round(($pCnt / $cnt) * 100, 1);
    $lats = array_column($subset, 'latency');
    $meanLat = round(array_sum($lats) / count($lats), 2);
    printf("%-25s | %8d | %9.1f%% | %10.2f ms | %-10s\n", $type, $cnt, $rate, $meanLat, ($rate === 100.0 ? 'PASSED' : 'CHECK'));
}

echo "-------------------------------------------------------------------------------------------------\n";
$allLats = array_column($results, 'latency');
$overallMeanLat = round(array_sum($allLats) / count($allLats), 2);
$overallPassRate = round(($passed / $totalTests) * 100, 1);
printf("%-25s | %8d | %9.1f%% | %10.2f ms | %-10s\n", "OVERALL SYNONYM CHAT", $totalTests, $overallPassRate, $overallMeanLat, ($passed === $totalTests ? '100% OPTIMAL' : 'FAILED'));
echo "=================================================================================================\n";
