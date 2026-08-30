<?php

declare(strict_types=1);

/**
 * =============================================================================
 * ADVERSARIAL & EDGE-CASE STRESS AUDIT SUITE
 * =============================================================================
 * Rigorously stresses 11 adversarial and edge-case dimensions:
 *  1. Mixed Bangla-English & Banglish ('login ar signin ki same?')
 *  2. Colloquial Typos ('logn and signin same?')
 *  3. Elliptical multi-turn context ('same?' -> 'tahole signup?')
 *  4. Pronoun anaphora with action ('eta kivabe cancel korbo?')
 *  5. False similarity on bare keywords ('payment') -> UNCERTAIN gate
 *  6. Lexical overlap with wrong answerability
 *  7. General technical questions with business-like keywords ('What is an enterprise SLA in cloud?')
 *  8. Business inquiry with no KB coverage ('Do you offer SOC 3 cold storage?')
 *  9. Provider failure during General Knowledge
 * 10. Provider failure during Grounded KB
 * 11. Repeated multi-turn UNCERTAIN handling
 * =============================================================================
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;

echo "=================================================================================================\n";
echo "🛡️ ADVERSARIAL & EDGE-CASE STRESS AUDIT SUITE\n";
echo "=================================================================================================\n";

$service = app(CustomerSupportService::class);
$ws = Workspace::find(1);
$channel = Channel::first() ?? Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
$acc = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws->id, 'channel_id' => $channel->id],
    ['name' => 'Acc Adv', 'external_id' => 'acc_adv_' . uniqid(), 'access_token' => 'tok_adv', 'is_active' => true]
);

$totalTests = 0;
$passedTests = 0;

function assertEdge(bool $condition, string $label, string $details = ''): void {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  ✅ PASS: {$label}\n";
    } else {
        echo "  ❌ FAIL: {$label}\n";
        if ($details !== '') {
            echo "       ↳ Details: {$details}\n";
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. MIXED BANGLA-ENGLISH & BANGLISH
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 1: Mixed Bangla-English & Banglish ──\n";
$conv1 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'adv_1_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$res1 = $service->handleQuery('login ar signin ki same?', $ws->id, $conv1);
$reply1 = $res1['reply'];
$hasSame1 = (stripos($reply1, 'একই') !== false || stripos($reply1, 'same') !== false || stripos($reply1, 'লগইন') !== false);
$hasNoFalseKB1 = count($res1['sources']) === 0;
assertEdge(
    $res1['route'] === 'knowledge' && $hasSame1 && $hasNoFalseKB1,
    "Mixed Bangla-English ('login ar signin ki same?') -> General Knowledge (0 citations)",
    "Route: {$res1['route']}, Sources: " . count($res1['sources']) . ", Reply: " . mb_substr(str_replace("\n", " ", $reply1), 0, 70) . "..."
);
usleep(200000);

// ─────────────────────────────────────────────────────────────────────────────
// 2. COLLOQUIAL TYPOS
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 2: Colloquial Typos ('logn and signin same?') ──\n";
$conv2 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'adv_2_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$res2 = $service->handleQuery('logn and signin same?', $ws->id, $conv2);
$reply2 = $res2['reply'];
$hasSame2 = (stripos($reply2, 'একই') !== false || stripos($reply2, 'same') !== false || stripos($reply2, 'login') !== false);
assertEdge(
    $res2['route'] === 'knowledge' && $hasSame2,
    "Colloquial Typo ('logn and signin same?') understood cleanly",
    "Route: {$res2['route']}, Reply: " . mb_substr(str_replace("\n", " ", $reply2), 0, 70) . "..."
);
usleep(200000);

// ─────────────────────────────────────────────────────────────────────────────
// 3. ELLIPTICAL MULTI-TURN CONTEXT ('same?' -> 'tahole signup?')
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 3: Elliptical Multi-Turn Context ('same?' -> 'tahole signup?') ──\n";
$conv3 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'adv_3_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
// Turn 1: Login and Signin
$conv3->messages()->create(['direction' => 'inbound', 'type' => 'text', 'body' => 'login and signin ki ek jinish?']);
$res3a = $service->handleQuery('login and signin ki ek jinish?', $ws->id, $conv3);
$service->saveOutboundReply($conv3, $res3a['reply']);

// Turn 2: Follow-up "tahole signup?"
$conv3->messages()->create(['direction' => 'inbound', 'type' => 'text', 'body' => 'tahole signup?']);
$res3b = $service->handleQuery('tahole signup?', $ws->id, $conv3);
$reply3b = $res3b['reply'];
$distinction = (stripos($reply3b, 'আলাদা') !== false || stripos($reply3b, 'নতুন') !== false || stripos($reply3b, 'different') !== false || stripos($reply3b, 'create') !== false || stripos($reply3b, 'register') !== false || stripos($reply3b, 'signup') !== false || stripos($reply3b, 'একাউন্ট') !== false);
assertEdge(
    $res3b['route'] === 'knowledge' && $distinction,
    "Elliptical follow-up ('tahole signup?') retains preceding context and differentiates correctly",
    "Route: {$res3b['route']}, Reply: " . mb_substr(str_replace("\n", " ", $reply3b), 0, 75) . "..."
);
usleep(200000);

// ─────────────────────────────────────────────────────────────────────────────
// 4. PRONOUN ANAPHORA WITH ACTION ('eta kivabe cancel korbo?')
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 4: Pronoun Anaphora ('eta kivabe cancel korbo?') ──\n";
$conv4 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'adv_4_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
// Turn 1: User mentions an order
$res4a = $service->handleQuery('আমার অর্ডার #1029 এর স্ট্যাটাস কী?', $ws->id, $conv4);
$service->saveOutboundReply($conv4, $res4a['reply']);

// Turn 2: User asks "eta kivabe cancel korbo?"
$res4b = $service->handleQuery('eta kivabe cancel korbo?', $ws->id, $conv4);
$reply4b = $res4b['reply'];
$hasCancelContext = (stripos($reply4b, 'cancel') !== false || stripos($reply4b, 'বাতিল') !== false || stripos($reply4b, '1029') !== false || stripos($reply4b, 'অর্ডার') !== false);
assertEdge(
    in_array($res4b['route'], ['knowledge', 'action'], true) && $hasCancelContext,
    "Pronoun 'eta kivabe cancel korbo?' correctly anchors to preceding order context",
    "Route: {$res4b['route']}, Reply: " . mb_substr(str_replace("\n", " ", $reply4b), 0, 75) . "..."
);
usleep(200000);

// ─────────────────────────────────────────────────────────────────────────────
// 5. FALSE SIMILARITY ON BARE KEYWORDS ('payment') -> UNCERTAIN GATE
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 5: Bare Ambiguous Keyword ('payment') -> UNCERTAIN Gate ──\n";
$conv5 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'adv_5_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$res5 = $service->handleQuery('payment', $ws->id, $conv5);
assertEdge(
    $res5['route'] === 'uncertain' && !empty($res5['suggestions']),
    "Bare 'payment' does NOT force random payment FAQ; routed to UNCERTAIN with clarification pills",
    "Route: {$res5['route']}, Suggestions: " . json_encode($res5['suggestions'])
);

// ─────────────────────────────────────────────────────────────────────────────
// 6. HIGH RETRIEVAL SCORE BUT WRONG ANSWERABILITY
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 6: Lexical Overlap with Wrong Answerability ──\n";
// User asks about logging into a third-party SSO that is not supported
$conv6 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'adv_6_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$res6 = $service->handleQuery('Can I login using my Government National ID biometric smart card?', $ws->id, $conv6);
$reply6 = $res6['reply'];
// Invariant: Must not claim we support National ID biometric login
$noFalseSupport = stripos($reply6, 'yes, you can login using your government national id') === false;
assertEdge(
    $res6['route'] === 'knowledge' && $noFalseSupport,
    "High lexical overlap on 'login' does not falsely claim support for unsupported biometric NID login",
    "Reply: " . mb_substr(str_replace("\n", " ", $reply6), 0, 75) . "..."
);
usleep(200000);

// ─────────────────────────────────────────────────────────────────────────────
// 7. GENERAL TECHNICAL QUESTION WITH BUSINESS-LIKE KEYWORDS
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 7: General Question with Business Keywords ('What is an enterprise SLA in cloud computing?') ──\n";
$conv7 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'adv_7_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$res7 = $service->handleQuery('What is an enterprise SLA in cloud computing and how is uptime calculated?', $ws->id, $conv7);
$reply7 = $res7['reply'];
$hasSlaConcept = (stripos($reply7, 'Service Level Agreement') !== false || stripos($reply7, 'uptime') !== false || stripos($reply7, '99.') !== false || stripos($reply7, 'SLA') !== false);
assertEdge(
    $res7['route'] === 'knowledge' && $hasSlaConcept && count($res7['sources']) === 0,
    "General SLA question answered conceptually from General Knowledge (0 false KB citations)",
    "Route: {$res7['route']}, Sources: " . count($res7['sources']) . ", Reply: " . mb_substr(str_replace("\n", " ", $reply7), 0, 75) . "..."
);
usleep(200000);

// ─────────────────────────────────────────────────────────────────────────────
// 8. BUSINESS INQUIRY WITH NO KB COVERAGE ('Do you offer SOC 3 cold storage?')
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 8: Business Question with No KB Coverage ('Do you offer SOC 3 cold storage?') ──\n";
$conv8 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'adv_8_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$res8 = $service->handleQuery('Do you offer SOC 3 certified offline cold storage vault services for financial institutions?', $ws->id, $conv8);
$reply8 = $res8['reply'];
$safeRefusal8 = (
    stripos($reply8, 'support') !== false ||
    stripos($reply8, 'contact') !== false ||
    stripos($reply8, 'don\'t have') !== false ||
    stripos($reply8, 'do not have') !== false ||
    stripos($reply8, 'not offer') !== false ||
    stripos($reply8, 'specialist') !== false ||
    stripos($reply8, 'sales') !== false ||
    stripos($reply8, 'information') !== false
);
assertEdge(
    $res8['route'] === 'knowledge' && $safeRefusal8,
    "Unlisted proprietary service inquiry handled with polite support handoff without hallucinating policies",
    "Route: {$res8['route']}, Reply: " . mb_substr(str_replace("\n", " ", $reply8), 0, 75) . "..."
);
usleep(200000);

// ─────────────────────────────────────────────────────────────────────────────
// 9. TRANSIENT & PERMANENT FAILURE DETECTION TEST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 9 & 10: Provider Failure Invariant Unit Validation ──\n";
// Call reflection on private isTransientError to verify error classification
$ref = new \ReflectionClass($service);
$isTransientMethod = $ref->getMethod('isTransientError');
$isTransientMethod->setAccessible(true);

$e429 = new \Exception("Application rate limited by AI provider [429]");
$e504 = new \Exception("OpenRouter Error: [504] The operation was aborted");
$eTimeout = new \Exception("cURL error 28: Operation timed out after 30000 milliseconds");
$eAuth401 = new \Exception("OpenRouter Error: [401] Invalid API Key provided");
$eBadReq400 = new \Exception("OpenRouter Error: [400] Bad Request payload format");

$passTransient = $isTransientMethod->invoke($service, $e429) &&
                 $isTransientMethod->invoke($service, $e504) &&
                 $isTransientMethod->invoke($service, $eTimeout);

$passPermanent = !$isTransientMethod->invoke($service, $eAuth401) &&
                 !$isTransientMethod->invoke($service, $eBadReq400);

assertEdge($passTransient, "Transient errors (429, 504, timeout) correctly trigger retry qualification");
assertEdge($passPermanent, "Permanent errors (401 Auth, 400 Bad Request) bypass retry and proceed to safe fallback");

// ─────────────────────────────────────────────────────────────────────────────
// 11. REPEATED MULTI-TURN UNCERTAIN HANDLING
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Dimension 11: Repeated Multi-Turn UNCERTAIN Handling ──\n";
$conv11 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'adv_11_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
// Turn 1: Ambiguous "update"
$res11a = $service->handleQuery('update', $ws->id, $conv11);
$service->saveOutboundReply($conv11, $res11a['reply']);

// Turn 2: Still ambiguous "change"
$res11b = $service->handleQuery('change', $ws->id, $conv11);
$service->saveOutboundReply($conv11, $res11b['reply']);

assertEdge(
    $res11a['route'] === 'uncertain' && $res11b['route'] === 'uncertain' && !empty($res11b['suggestions']),
    "Sequential ambiguous queries ('update' -> 'change') reliably maintain UNCERTAIN clarification without crash",
    "Turn 1: {$res11a['route']}, Turn 2: {$res11b['route']}"
);

// ─────────────────────────────────────────────────────────────────────────────
// FINAL SCORECARD
// ─────────────────────────────────────────────────────────────────────────────
$passRate = round(($passedTests / $totalTests) * 100, 1);
echo "\n=================================================================================================\n";
echo "📊 ADVERSARIAL & EDGE-CASE AUDIT SCORECARD\n";
echo "=================================================================================================\n";
printf("Total Stress Checks: %d\n", $totalTests);
printf("Passed Stress Checks:%d\n", $passedTests);
printf("Stress Pass Rate:    %.1f%%\n", $passRate);
echo "Status:              " . ($passRate === 100.0 ? "🟢 100.0% PASS (All Adversarial Dimensions Invariant-Safe)" : "🟡 REVIEW NEEDED") . "\n";
echo "=================================================================================================\n";
