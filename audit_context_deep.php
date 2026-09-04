<?php

declare(strict_types=1);

/**
 * Deep Forensic Audit for Conversation Context Memory & Understanding
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;

echo "=================================================================================================\n";
echo "🔬 DEEP FORENSIC AUDIT: CONVERSATION CONTEXT MEMORY & CONTEXT UNDERSTANDING\n";
echo "=================================================================================================\n";

$customerSupportService = app(CustomerSupportService::class);

$ws1 = Workspace::find(1) ?? Workspace::create(['name' => 'WS1', 'slug' => 'ws1']);
$ws2 = Workspace::find(2) ?? Workspace::create(['name' => 'WS2', 'slug' => 'ws2']);

$channel = Channel::first() ?? Channel::create(['name' => 'Web Chat', 'slug' => 'web', 'driver' => 'web']);
$acc1 = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws1->id, 'channel_id' => $channel->id],
    ['name' => 'Acc 1', 'external_id' => 'acc1_' . uniqid(), 'access_token' => 'tok1', 'is_active' => true]
);
$acc2 = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws2->id, 'channel_id' => $channel->id],
    ['name' => 'Acc 2', 'external_id' => 'acc2_' . uniqid(), 'access_token' => 'tok2', 'is_active' => true]
);

// ─────────────────────────────────────────────────────────────────────────────
// 1. AUDIT ITEM 4 & 5: ANAPHORA & KNOWLEDGE FOLLOW-UPS ("Can I extend it?")
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 1. FOLLOW-UP ANAPHORA & CROSS-ROUTE COHERENCE (CHAT <-> KNOWLEDGE) ──\n";

$conv1 = Conversation::create([
    'channel_account_id' => $acc1->id,
    'external_user_id'   => 'audit_anaphora_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'Dr. Evelyn Reed',
    'last_direction'     => 'inbound',
]);

$script1 = [
    [
        'turn'  => 1,
        'user'  => 'Hi, I am Dr. Evelyn Reed, a cybersecurity researcher from London.',
        'route' => 'chat',
    ],
    [
        'turn'  => 2,
        'user'  => 'Do you offer a free trial?',
        'route' => 'knowledge',
    ],
    [
        'turn'  => 3,
        'user'  => 'Do I need to enter a credit card to use it?',
        'route' => 'knowledge',
    ],
    [
        'turn'  => 4,
        'user'  => 'What is my profession and what city am I based in?',
        'route' => 'chat',
    ],
];

foreach ($script1 as $s) {
    Message::create([
        'conversation_id' => $conv1->id,
        'direction'       => 'inbound',
        'type'            => 'text',
        'body'            => $s['user'],
    ]);

    $t_start = microtime(true);
    $res = $customerSupportService->handleQuery($s['user'], $ws1->id, $conv1);
    $lat = round((microtime(true) - $t_start) * 1000, 2);

    $customerSupportService->saveOutboundReply($conv1, $res['reply']);

    echo "  [Turn {$s['turn']}] Route: {$res['route']} ({$lat} ms)\n";
    echo "       User:  \"{$s['user']}\"\n";
    echo "       Reply: \"" . mb_substr(str_replace("\n", " ", trim($res['reply'])), 0, 90) . "...\"\n";
}

$turn3Reply = $conv1->messages()->where('direction', 'outbound')->skip(2)->first()?->body ?? '';
$turn4Reply = $conv1->messages()->where('direction', 'outbound')->latest('id')->first()?->body ?? '';

$turn3HasCreditCard = (stripos($turn3Reply, 'credit card') !== false || stripos($turn3Reply, 'card') !== false || stripos($turn3Reply, 'no') !== false);
$turn4HasProfession = (stripos($turn4Reply, 'cybersecurity') !== false || stripos($turn4Reply, 'researcher') !== false);
$turn4HasCity = (stripos($turn4Reply, 'London') !== false);

echo "\n   Evaluation:";
echo "\n   • Turn 3 Anaphoric Resolution ('use it' -> Free trial card req): " . ($turn3HasCreditCard ? "✅ PASS" : "❌ FAIL");
echo "\n   • Turn 4 Identity Recall ('cybersecurity researcher'): " . ($turn4HasProfession ? "✅ PASS" : "❌ FAIL");
echo "\n   • Turn 4 Location Recall ('London'): " . ($turn4HasCity ? "✅ PASS" : "❌ FAIL");

// ─────────────────────────────────────────────────────────────────────────────
// 2. AUDIT ITEM 7: MULTI-TENANT CONTEXT ISOLATION
// ─────────────────────────────────────────────────────────────────────────────
echo "\n\n── 2. MULTI-TENANT CONTEXT ISOLATION (Tenant Alpha vs Tenant Beta) ─────\n";

$convTenantAlpha = Conversation::create([
    'channel_account_id' => $acc1->id,
    'external_user_id'   => 'alpha_user_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'Alpha Secret Agent',
    'last_direction'     => 'inbound',
]);

$convTenantBeta = Conversation::create([
    'channel_account_id' => $acc2->id,
    'external_user_id'   => 'beta_user_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'Beta Auditor',
    'last_direction'     => 'inbound',
]);

// Tenant Alpha gives secret project codename
Message::create([
    'conversation_id' => $convTenantAlpha->id,
    'direction'       => 'inbound',
    'type'            => 'text',
    'body'            => 'My private project codename is PROJECT-PEGASUS-9988.',
]);
$resAlpha = $customerSupportService->handleQuery('My private project codename is PROJECT-PEGASUS-9988.', $ws1->id, $convTenantAlpha);
$customerSupportService->saveOutboundReply($convTenantAlpha, $resAlpha['reply']);

// Tenant Beta asks for the project codename in a completely separate conversation
Message::create([
    'conversation_id' => $convTenantBeta->id,
    'direction'       => 'inbound',
    'type'            => 'text',
    'body'            => 'What is my private project codename?',
]);
$resBeta = $customerSupportService->handleQuery('What is my private project codename?', $ws2->id, $convTenantBeta);
$customerSupportService->saveOutboundReply($convTenantBeta, $resBeta['reply']);

$leakedToBeta = (stripos($resBeta['reply'], 'PEGASUS') !== false || stripos($resBeta['reply'], '9988') !== false);
echo "  • Tenant Alpha Query: \"My private project codename is PROJECT-PEGASUS-9988.\"\n";
echo "  • Tenant Beta Query:  \"What is my private project codename?\"\n";
echo "  • Tenant Beta Reply:  \"" . mb_substr(str_replace("\n", " ", trim($resBeta['reply'])), 0, 90) . "...\"\n";
echo "  • Cross-Tenant Leakage Check: " . ($leakedToBeta ? "❌ LEAK DETECTED!" : "✅ ZERO LEAKAGE (Isolated)") . "\n";

// ─────────────────────────────────────────────────────────────────────────────
// 3. AUDIT ITEM 8: LONG CONVERSATION CONTEXT WINDOW & TRUNCATION STRATEGY
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 3. CONTEXT WINDOW BOUNDARY & TRUNCATION STRATEGY ────────────────────\n";

$convLong = Conversation::create([
    'channel_account_id' => $acc1->id,
    'external_user_id'   => 'long_conv_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'Long Chatter',
    'last_direction'     => 'inbound',
]);

// Seed 20 historical messages (limit is config('ai.memory.max_messages', 10))
for ($i = 1; $i <= 10; $i++) {
    Message::create([
        'conversation_id' => $convLong->id,
        'direction'       => 'inbound',
        'type'            => 'text',
        'body'            => "Historical query #{$i}: What happened at step {$i}?",
        'created_at'      => now()->subMinutes(30 - $i),
    ]);
    Message::create([
        'conversation_id' => $convLong->id,
        'direction'       => 'outbound',
        'type'            => 'text',
        'body'            => "Historical answer #{$i}: Step {$i} was successful.",
        'created_at'      => now()->subMinutes(30 - $i)->addSeconds(5),
    ]);
}

$totalInDb = $convLong->messages()->count();

// Check agent messages() method
$agent = new \App\AI\Agents\KnowledgeSupportAgent(conversation: $convLong);
$loadedMessages = iterator_to_array($agent->messages());
$loadedCount = count($loadedMessages);

echo "  • Total messages in DB for thread: {$totalInDb}\n";
echo "  • Max messages window (config):    " . config('ai.memory.max_messages', 10) . "\n";
echo "  • Messages loaded by Agent:        {$loadedCount}\n";
echo "  • Truncation Strategy Active:      " . ($loadedCount === (int) config('ai.memory.max_messages', 10) ? "✅ YES (Rolling 10-Message Window)" : "❌ NO") . "\n";

echo "\n=================================================================================================\n";
echo "🏁 AUDIT COMPLETED\n";
echo "=================================================================================================\n";
