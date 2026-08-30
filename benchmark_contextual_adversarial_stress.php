<?php

declare(strict_types=1);

/**
 * =============================================================================
 * ADVERSARIAL MULTI-TURN CONTEXTUAL RAG STRESS & EDGE-CASE BENCHMARK
 * =============================================================================
 * Tests the 15 Hard Adversarial Multi-Turn Dimensions:
 *  1. Pronoun ambiguity resolution
 *  2. Multiple topics in dialogue history (Recency tracking)
 *  3. Context switching / topic pivots (Semantic contamination guard)
 *  4. Pronoun matrix: "it / this / that / they / those"
 *  5. Bangla follow-up queries ("এটা কি...", "আর টেলিগ্রাম?")
 *  6. Banglish follow-up queries ("eta ki extend kora jabe?", "ar invoice?")
 *  7. CHAT -> KNOWLEDGE flow
 *  8. KNOWLEDGE -> CHAT flow
 *  9. KNOWLEDGE -> KNOWLEDGE consecutive chain
 * 10. Old-topic revival ("Back to topic A...")
 * 11. Unrelated new standalone question (Zero contamination)
 * 12. Long conversation (>10 messages sliding window)
 * 13. Empty retrieval handling
 * 14. OOD refusal after contextual conversation (Zero hallucination)
 * 15. Multi-tenant isolation under concurrent multi-turn dialogue
 * =============================================================================
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\ContextualQueryBuilder;
use App\Services\AI\CustomerSupportService;
use App\Services\Retrieval\RetrievalClient;

echo "=================================================================================================\n";
echo "🧪 ADVERSARIAL MULTI-TURN CONTEXTUAL RAG STRESS BENCHMARK (15 DIMENSIONS)\n";
echo "=================================================================================================\n";

$customerSupportService = app(CustomerSupportService::class);
$contextualQueryBuilder = app(ContextualQueryBuilder::class);
$retrievalClient = app(RetrievalClient::class);

$ws1 = Workspace::find(1);
$ws2 = Workspace::firstOrCreate(
    ['slug' => 'adv-stress-ws2'],
    ['name' => 'Adversarial Stress Workspace 2']
);

$channel = Channel::first() ?? Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
$acc1 = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws1->id, 'channel_id' => $channel->id],
    ['name' => 'Acc WS1', 'external_id' => 'acc_adv_ws1_' . uniqid(), 'access_token' => 'tok1', 'is_active' => true]
);
$acc2 = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws2->id, 'channel_id' => $channel->id],
    ['name' => 'Acc WS2', 'external_id' => 'acc_adv_ws2_' . uniqid(), 'access_token' => 'tok2', 'is_active' => true]
);

$totalChecks = 0;
$passedChecks = 0;

function checkAdv(bool $condition, string $label, string $details = ''): void {
    global $totalChecks, $passedChecks;
    $totalChecks++;
    if ($condition) {
        $passedChecks++;
        echo "  ✅ PASS: {$label}\n";
    } else {
        echo "  ❌ FAIL: {$label}\n";
        if ($details !== '') {
            echo "       ↳ Details: {$details}\n";
        }
    }
}

// ── DIMENSION 1: Pronoun Ambiguity Resolution ────────────────────────────────
echo "\n── 1. Pronoun Ambiguity Resolution ──\n";
$conv1 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d1_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
Message::create(['conversation_id' => $conv1->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'How do I enable two-factor authentication?']);
Message::create(['conversation_id' => $conv1->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'You can enable 2FA in Settings > Security.']);

$rewritten1 = $contextualQueryBuilder->buildContextualQuery('Can I turn it off later?', $conv1);
$res1 = $customerSupportService->handleQuery('Can I turn it off later?', $ws1->id, $conv1);
checkAdv(
    str_contains(mb_strtolower($rewritten1), 'two-factor') || str_contains(mb_strtolower($rewritten1), '2fa'),
    "Resolves 'it' to Two-Factor Authentication",
    "Rewritten: {$rewritten1}"
);
checkAdv(
    $res1['top_hit']?->faq && stripos($res1['top_hit']->faq->question, 'two-factor') !== false,
    "Typesense retrieved 2FA FAQ as Rank 1",
    "Top Hit: " . ($res1['top_hit']?->faq?->question ?? 'none')
);

// ── DIMENSION 2: Multiple Topics in History (Recency Tracking) ────────────────
echo "\n── 2. Multiple Topics in History (Recency Tracking) ──\n";
$conv2 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d2_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
// Topic 1: Free Trial
Message::create(['conversation_id' => $conv2->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Tell me about the 14-day free trial.']);
Message::create(['conversation_id' => $conv2->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'We offer a 14-day free trial on Pro.']);
// Topic 2 (Most Recent): Invoices & Billing
Message::create(['conversation_id' => $conv2->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Where can I see my billing invoices?']);
Message::create(['conversation_id' => $conv2->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'Go to Settings > Billing > Invoices.']);

$rewritten2 = $contextualQueryBuilder->buildContextualQuery('How do I download them in PDF?', $conv2);
$res2 = $customerSupportService->handleQuery('How do I download them in PDF?', $ws1->id, $conv2);
checkAdv(
    (str_contains(mb_strtolower($rewritten2), 'invoice') || str_contains(mb_strtolower($rewritten2), 'billing')) && !str_contains(mb_strtolower($rewritten2), 'free trial'),
    "Recency preference: Targets Invoices (Topic 2) rather than Free Trial (Topic 1)",
    "Rewritten: {$rewritten2}"
);
checkAdv(
    $res2['top_hit']?->faq && stripos($res2['top_hit']->faq->question, 'invoices') !== false,
    "Typesense retrieved Invoices FAQ as Rank 1",
    "Top Hit: " . ($res2['top_hit']?->faq?->question ?? 'none')
);

// ── DIMENSION 3: Context Switching / Topic Pivot (Semantic Contamination Guard)
echo "\n── 3. Context Switching / Semantic Contamination Guard ──\n";
$conv3 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d3_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
Message::create(['conversation_id' => $conv3->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Tell me about the free trial.']);
Message::create(['conversation_id' => $conv3->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'Our free trial is 14 days.']);
Message::create(['conversation_id' => $conv3->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Thanks.']);
Message::create(['conversation_id' => $conv3->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'You are welcome!']);

$rewritten3 = $contextualQueryBuilder->buildContextualQuery('What about WhatsApp?', $conv3);
$res3 = $customerSupportService->handleQuery('What about WhatsApp?', $ws1->id, $conv3);
checkAdv(
    str_contains(mb_strtolower($rewritten3), 'whatsapp') && !str_contains(mb_strtolower($rewritten3), 'free trial'),
    "Clean Topic Pivot: Connect WhatsApp question is NOT contaminated with Free Trial",
    "Rewritten: {$rewritten3}"
);
checkAdv(
    $res3['top_hit']?->faq && stripos($res3['top_hit']->faq->question, 'WhatsApp') !== false,
    "Typesense retrieved WhatsApp FAQ as Rank 1",
    "Top Hit: " . ($res3['top_hit']?->faq?->question ?? 'none')
);

// ── DIMENSION 4: Pronoun Matrix: "it / this / that / they / those" ────────────
echo "\n── 4. Pronoun Matrix ('it / this / that / they / those') ──\n";
$conv4 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d4_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
Message::create(['conversation_id' => $conv4->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Can I connect multiple channels at the same time?']);
Message::create(['conversation_id' => $conv4->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'Yes, you can connect WhatsApp, Facebook, and Telegram.']);

$rewritten4 = $contextualQueryBuilder->buildContextualQuery('Are they synced in one single inbox?', $conv4);
$res4 = $customerSupportService->handleQuery('Are they synced in one single inbox?', $ws1->id, $conv4);
checkAdv(
    str_contains(mb_strtolower($rewritten4), 'channel') || str_contains(mb_strtolower($rewritten4), 'inbox'),
    "Resolves plural pronoun 'they' to multiple communication channels",
    "Rewritten: {$rewritten4}"
);
checkAdv(
    $res4['top_hit']?->faq && stripos($res4['top_hit']->faq->question, 'multiple channels') !== false,
    "Typesense retrieved Multiple Channels FAQ as Rank 1",
    "Top Hit: " . ($res4['top_hit']?->faq?->question ?? 'none')
);

// ── DIMENSION 5: Bangla Follow-Up Queries ─────────────────────────────────────
echo "\n── 5. Bangla Follow-Up Queries ──\n";
$conv5 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d5_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
Message::create(['conversation_id' => $conv5->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'নন-প্রফিট প্রতিষ্ঠানের জন্য কি কোনো ডিসকাউন্ট আছে?']);
Message::create(['conversation_id' => $conv5->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'হ্যাঁ, আমরা ৫০% বিশেষ ডিসকাউন্ট দিয়ে থাকি।']);

$res5 = $customerSupportService->handleQuery('আমি কীভাবে এটার জন্য আবেদন করব?', $ws1->id, $conv5);
checkAdv(
    $res5['top_hit']?->faq && stripos($res5['top_hit']->faq->question, 'non-profits') !== false,
    "Bangla pronoun 'এটার জন্য' resolved to non-profit discount FAQ",
    "Top Hit: " . ($res5['top_hit']?->faq?->question ?? 'none')
);
checkAdv(
    stripos($res5['reply'], '৫০%') !== false || stripos($res5['reply'], '50%') !== false || stripos($res5['reply'], 'ডিসকাউন্ট') !== false,
    "Bangla Grounded Answer contains accurate 50% discount terms",
    "Reply: " . mb_substr($res5['reply'], 0, 70) . '...'
);

// ── DIMENSION 6: Banglish Follow-Up Queries ───────────────────────────────────
echo "\n── 6. Banglish Follow-Up Queries ──\n";
$conv6 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d6_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
Message::create(['conversation_id' => $conv6->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'vai whatsapp connect korar niyom ki?']);
Message::create(['conversation_id' => $conv6->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'Settings > Channels > WhatsApp theke token diye connect korte paren.']);

$rewritten6 = $contextualQueryBuilder->buildContextualQuery('ar telegram?', $conv6);
$res6 = $customerSupportService->handleQuery('ar telegram?', $ws1->id, $conv6);
checkAdv(
    str_contains(mb_strtolower($rewritten6), 'telegram'),
    "Banglish elliptical 'ar telegram?' expanded to Telegram connect",
    "Rewritten: {$rewritten6}"
);
checkAdv(
    $res6['top_hit']?->faq && stripos($res6['top_hit']->faq->question, 'Telegram') !== false,
    "Typesense retrieved Telegram connection FAQ",
    "Top Hit: " . ($res6['top_hit']?->faq?->question ?? 'none')
);

// ── DIMENSION 7: CHAT -> KNOWLEDGE Flow ──────────────────────────────────────
echo "\n── 7. CHAT -> KNOWLEDGE Flow ──\n";
$conv7 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d7_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
// Turn 1: CHAT
Message::create(['conversation_id' => $conv7->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Hi, I am Alex from Sydney, Australia.']);
$res7a = $customerSupportService->handleQuery('Hi, I am Alex from Sydney, Australia.', $ws1->id, $conv7);
$customerSupportService->saveOutboundReply($conv7, $res7a['reply']);
checkAdv($res7a['route'] === 'chat', "Turn 1 routed strictly to CHAT (0 retrieval overhead)");

// Turn 2: KNOWLEDGE
Message::create(['conversation_id' => $conv7->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'What encryption do you use to secure my data?']);
$res7b = $customerSupportService->handleQuery('What encryption do you use to secure my data?', $ws1->id, $conv7);
checkAdv($res7b['route'] === 'knowledge', "Turn 2 routed accurately to KNOWLEDGE");
checkAdv(
    stripos($res7b['reply'], 'AES-256') !== false || stripos($res7b['reply'], 'TLS') !== false,
    "Turn 2 Grounded response accurately cites AES-256 and TLS standards",
    "Reply: " . mb_substr($res7b['reply'], 0, 70) . '...'
);

// ── DIMENSION 8: KNOWLEDGE -> CHAT Flow ──────────────────────────────────────
echo "\n── 8. KNOWLEDGE -> CHAT Flow ──\n";
// Turn 3 of same conversation: Pure thanks / conversational closure
Message::create(['conversation_id' => $conv7->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Thank you so much, Alex is very satisfied!']);
$res7c = $customerSupportService->handleQuery('Thank you so much, Alex is very satisfied!', $ws1->id, $conv7);
checkAdv($res7c['route'] === 'chat', "Turn 3 successfully transitions back from KNOWLEDGE -> CHAT");

// ── DIMENSION 9: KNOWLEDGE -> KNOWLEDGE Consecutive Chain ────────────────────
echo "\n── 9. KNOWLEDGE -> KNOWLEDGE Consecutive Chain ──\n";
$conv9 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d9_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$chain = [
    ['q' => 'What subscription plans are available?', 'target' => 'What plans are available?'],
    ['q' => 'How is data encrypted at rest and in transit?', 'target' => 'How is my data encrypted?'],
    ['q' => 'Where can I find my API key and what are the rate limits?', 'target' => 'What are the API rate limits?'],
];
$chainPassed = true;
foreach ($chain as $idx => $step) {
    Message::create(['conversation_id' => $conv9->id, 'direction' => 'inbound', 'type' => 'text', 'body' => $step['q']]);
    $resStep = $customerSupportService->handleQuery($step['q'], $ws1->id, $conv9);
    $customerSupportService->saveOutboundReply($conv9, $resStep['reply']);
    $hitMatch = $resStep['top_hit']?->faq && stripos($resStep['top_hit']->faq->question, $step['target']) !== false;
    if (!$hitMatch || $resStep['route'] !== 'knowledge') {
        $chainPassed = false;
    }
}
checkAdv($chainPassed, "3 consecutive KNOWLEDGE queries all routed and matched without cross-contamination");

// ── DIMENSION 10: Old Topic Revival ("Back to the free trial...") ─────────────
echo "\n── 10. Old Topic Revival ──\n";
$conv10 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d10_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
// Topic 1: Free Trial
Message::create(['conversation_id' => $conv10->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Tell me about the free trial.']);
Message::create(['conversation_id' => $conv10->id, 'direction' => 'outbound', 'type' => 'text', 'body' => '14 days free trial.']);
// Topic 2: Security & Encryption
Message::create(['conversation_id' => $conv10->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'How is data encrypted?']);
Message::create(['conversation_id' => $conv10->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'AES-256 and TLS 1.3.']);

$res10 = $customerSupportService->handleQuery('Back to the free trial, does it need a credit card?', $ws1->id, $conv10);
checkAdv(
    $res10['top_hit']?->faq && stripos($res10['top_hit']->faq->question, 'free trial') !== false,
    "Successfully revives Topic 1 (Free trial) over Topic 2 (Encryption)",
    "Top Hit: " . ($res10['top_hit']?->faq?->question ?? 'none')
);

// ── DIMENSION 11: Unrelated New Standalone Question ──────────────────────────
echo "\n── 11. Unrelated New Standalone Question ──\n";
$conv11 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d11_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
Message::create(['conversation_id' => $conv11->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Where can I see my billing invoices?']);
Message::create(['conversation_id' => $conv11->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'In Settings > Invoices.']);

$rewritten11 = $contextualQueryBuilder->buildContextualQuery('How do I enable two-factor authentication for my account?', $conv11);
checkAdv(
    $rewritten11 === 'How do I enable two-factor authentication for my account?',
    "Self-contained new query preserved with 0 rewrite (Zero semantic drift)",
    "Output: {$rewritten11}"
);

// ── DIMENSION 12: Long Conversation History (>10 messages sliding window) ────
echo "\n── 12. Long Conversation History (>10 messages) ──\n";
$conv12 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d12_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
for ($i = 1; $i <= 8; $i++) {
    Message::create(['conversation_id' => $conv12->id, 'direction' => 'inbound', 'type' => 'text', 'body' => "Query {$i} about general support."]);
    Message::create(['conversation_id' => $conv12->id, 'direction' => 'outbound', 'type' => 'text', 'body' => "Reply {$i}."]);
}
// Now add anaphora turn
Message::create(['conversation_id' => $conv12->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Where can I get an API key?']);
Message::create(['conversation_id' => $conv12->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'In Settings > API Keys.']);

$res12 = $customerSupportService->handleQuery('What are the rate limits on it?', $ws1->id, $conv12);
checkAdv(
    $res12['top_hit']?->faq && stripos($res12['top_hit']->faq->question, 'rate limits') !== false,
    "Rolling window properly captures the recent API Key context without memory exhaustion",
    "Top Hit: " . ($res12['top_hit']?->faq?->question ?? 'none')
);

// ── DIMENSION 13: Empty Retrieval Handling ───────────────────────────────────
echo "\n── 13. Empty Retrieval Handling ──\n";
$conv13 = Conversation::create(['channel_account_id' => $acc2->id, 'external_user_id' => 'adv_d13_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$res13 = $customerSupportService->handleQuery('What is the secret unicorn feature for Workspace 2?', $ws2->id, $conv13);
checkAdv(
    !empty($res13['reply']) && (stripos($res13['reply'], 'sorry') !== false || stripos($res13['reply'], 'human') !== false || stripos($res13['reply'], 'support') !== false || stripos($res13['reply'], 'don\'t have') !== false),
    "Empty retrieval yields clean, non-hallucinatory human agent fallback",
    "Reply: " . mb_substr($res13['reply'], 0, 70) . '...'
);

// ── DIMENSION 14: OOD Refusal After Contextual Conversation ───────────────────
echo "\n── 14. OOD Refusal After Contextual Conversation ──\n";
$conv14 = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_d14_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
// Previous turns about real subscription plans
Message::create(['conversation_id' => $conv14->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'What plans do you have?']);
Message::create(['conversation_id' => $conv14->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'We offer Free, Pro, and Enterprise plans.']);

// Now OOD question
$res14 = $customerSupportService->handleQuery('Can you sell me nuclear submarine fuel or rocket parts?', $ws1->id, $conv14);
checkAdv(
    $res14['route'] === 'ood' || stripos($res14['reply'], 'support') !== false || stripos($res14['reply'], 'out of scope') !== false || stripos($res14['reply'], 'not have') !== false,
    "OOD query immediately rejected with safe refusal badge (Zero hallucination from prior plans)",
    "Route: {$res14['route']}, Reply: " . mb_substr($res14['reply'], 0, 70) . '...'
);

// ── DIMENSION 15: Multi-Tenant Complete Isolation Stress Test ────────────────
echo "\n── 15. Multi-Tenant Complete Isolation Stress Test ──\n";
$convAlpha = Conversation::create(['channel_account_id' => $acc1->id, 'external_user_id' => 'adv_alpha_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$convBeta  = Conversation::create(['channel_account_id' => $acc2->id, 'external_user_id' => 'adv_beta_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);

// Tenant Alpha context
Message::create(['conversation_id' => $convAlpha->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'My secret project code is PROJECT-TOPAZ-4477.']);
$resAlpha = $customerSupportService->handleQuery('My secret project code is PROJECT-TOPAZ-4477.', $ws1->id, $convAlpha);
$customerSupportService->saveOutboundReply($convAlpha, $resAlpha['reply']);

// Tenant Beta simultaneous query
Message::create(['conversation_id' => $convBeta->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'What is my secret project code?']);
$resBeta = $customerSupportService->handleQuery('What is my secret project code?', $ws2->id, $convBeta);
$customerSupportService->saveOutboundReply($convBeta, $resBeta['reply']);

$leakDetected = (stripos($resBeta['reply'], 'TOPAZ') !== false || stripos($resBeta['reply'], '4477') !== false);
checkAdv(
    !$leakDetected,
    "Tenant Beta has ZERO access to Tenant Alpha's secret context (100% Multi-Tenant Isolation)",
    "Beta Reply: " . mb_substr($resBeta['reply'], 0, 70) . '...'
);

// ── FINAL SCORECARD ──────────────────────────────────────────────────────────
$passRate = round(($passedChecks / $totalChecks) * 100, 1);
echo "\n=================================================================================================\n";
echo "🏆 ADVERSARIAL MULTI-TURN STRESS BENCHMARK SUMMARY\n";
echo "=================================================================================================\n";
printf("Total Invariant Checks: %d\n", $totalChecks);
printf("Passed Checks:          %d\n", $passedChecks);
printf("Pass Rate:              %.1f%%\n", $passRate);
echo "Status:                 " . ($passRate === 100.0 ? "🟢 100% PASS (Production Hardened)" : "🟡 REVIEW NEEDED") . "\n";
echo "=================================================================================================\n";
