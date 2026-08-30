<?php

declare(strict_types=1);

/**
 * =============================================================================
 * KNOWLEDGE BEHAVIOR MATRIX & HYBRID RAG REGRESSION AUDIT
 * =============================================================================
 * Validates the core architectural separation:
 * 
 * UNDERSTOOD QUERY
 *        │
 *        ▼
 *  Relevant KB?
 *    ┌───┴───┐
 *   YES      NO
 *    │        │
 *    ▼        ▼
 * KB Grounded   General Knowledge
 *    │        │
 *    └────┬───┘
 *         ▼
 *      Response
 * 
 * NOT UNDERSTOOD
 *       │
 *       ▼
 *  Clarification (UNCERTAIN)
 *       │
 *       ▼
 *  Still NOT UNDERSTOOD
 *       │
 *       ▼
 *  Direct LLM / Human Handoff
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
use App\Services\AI\CustomerSupportService;

echo "=================================================================================================\n";
echo "🔬 KNOWLEDGE BEHAVIOR MATRIX & HYBRID RAG REGRESSION AUDIT\n";
echo "=================================================================================================\n";

$service = app(CustomerSupportService::class);
$ws = Workspace::find(1);
$channel = Channel::first() ?? Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
$acc = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws->id, 'channel_id' => $channel->id],
    ['name' => 'Acc Matrix', 'external_id' => 'acc_matrix_' . uniqid(), 'access_token' => 'tok_matrix', 'is_active' => true]
);

$totalTests = 0;
$passedTests = 0;

function assertMatrix(bool $condition, string $label, string $details = ''): void {
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
// 1. GENERAL KNOWLEDGE CONCEPTUAL QUERIES (KB = No, General LLM = Yes)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 1. General Knowledge Conceptual Queries (KB: No, General LLM: Yes) ──\n";

$genConceptQueries = [
    [
        'query'         => 'login and signin ki same?',
        'expectedTerms' => ['একই', 'same', 'লগইন', 'sign in'],
        'forbiddenTerms'=> ['non-profit', 'ডিসকাউন্ট', '50% discount'],
        'desc'          => 'Login vs Sign In (General equivalence)',
    ],
    [
        'query'         => 'login and signup ki same?',
        'expectedTerms' => ['আলাদা', 'not the same', 'নতুন', 'new', 'different'],
        'forbiddenTerms'=> ['non-profit', 'ডিসকাউন্ট'],
        'desc'          => 'Login vs SignUp (Clear distinction)',
    ],
    [
        'query'         => 'signup and register ki same?',
        'expectedTerms' => ['একই', 'same', 'নতুন', 'account'],
        'forbiddenTerms'=> ['AES-256', 'Martian'],
        'desc'          => 'SignUp vs Register (General equivalence)',
    ],
    [
        'query'         => 'what is JSON and where is it used?',
        'expectedTerms' => ['JavaScript Object Notation', 'data', 'format', 'key-value'],
        'forbiddenTerms'=> ['refund', 'Martian'],
        'desc'          => 'Technical Definition: JSON',
    ],
    [
        'query'         => 'what is an API and how does it work?',
        'expectedTerms' => ['Application Programming Interface', 'communication', 'software'],
        'forbiddenTerms'=> ['refund policy for Martian'],
        'desc'          => 'Technical Concept: API Definition',
    ],
    [
        'query'         => 'what is the difference between a webhook and an API?',
        'expectedTerms' => ['push', 'pull', 'real-time', 'HTTP', 'event'],
        'forbiddenTerms'=> ['50% discount for registered non-profit'],
        'desc'          => 'Technical Comparison: Webhook vs API',
    ],
];

foreach ($genConceptQueries as $item) {
    $conv = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'mat_gen_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
    $res = $service->handleQuery($item['query'], $ws->id, $conv);
    $reply = $res['reply'];

    $hasExpected = false;
    foreach ($item['expectedTerms'] as $t) {
        if (stripos($reply, $t) !== false) {
            $hasExpected = true;
            break;
        }
    }

    $hasForbidden = false;
    foreach ($item['forbiddenTerms'] as $f) {
        if (stripos($reply, $f) !== false) {
            $hasForbidden = true;
            break;
        }
    }

    assertMatrix(
        $res['route'] === 'knowledge' && $hasExpected && !$hasForbidden,
        $item['desc'],
        "Route: {$res['route']}, Reply: " . mb_substr(str_replace("\n", " ", $reply), 0, 90) . "..."
    );
    usleep(150000);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. COMPANY-SPECIFIC GROUNDED KB QUERIES (KB = Yes, Strict Citations)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 2. Company-Specific Grounded KB Queries (KB: Yes, Strict Citations) ──\n";

$companyKBQueries = [
    [
        'query'          => 'What subscription plans and pricing do you offer?',
        'expectedFacts'  => ['Free', 'Pro', 'Enterprise'],
        'expectedSource' => 'What plans are available?',
        'desc'           => 'Subscription Tiers grounded in company KB',
    ],
    [
        'query'          => 'How does your platform encrypt stored and in-transit data?',
        'expectedFacts'  => ['AES-256', 'TLS 1.3'],
        'expectedSource' => 'How is my data encrypted?',
        'desc'           => 'Data Encryption Standards grounded in company KB',
    ],
    [
        'query'          => 'How do I configure two-factor authentication (2FA)?',
        'expectedFacts'  => ['2FA', 'authenticator', 'Security', 'Settings'],
        'expectedSource' => 'How do I enable two-factor authentication?',
        'desc'           => '2FA Setup procedure grounded in company KB',
    ],
    [
        'query'          => 'নন-প্রফিট বা অলাভজনক প্রতিষ্ঠানের জন্য কি কোনো ডিসকাউন্ট সুবিধা আছে?',
        'expectedFacts'  => ['৫০%', '50%', 'ডিসকাউন্ট'],
        'expectedSource' => 'Do you offer discounts for non-profits?',
        'desc'           => 'Non-profit 50% discount grounded in Bengali query',
    ],
];

foreach ($companyKBQueries as $item) {
    $conv = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'mat_kb_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
    $res = $service->handleQuery($item['query'], $ws->id, $conv);
    $reply = $res['reply'];

    $hasFact = false;
    foreach ($item['expectedFacts'] as $f) {
        if (stripos($reply, $f) !== false) {
            $hasFact = true;
            break;
        }
    }

    $hasExpectedSource = false;
    foreach ($res['sources'] as $src) {
        if (stripos($src['question'], $item['expectedSource']) !== false) {
            $hasExpectedSource = true;
            break;
        }
    }

    assertMatrix(
        $res['route'] === 'knowledge' && $hasFact && $hasExpectedSource,
        $item['desc'],
        "Route: {$res['route']}, Sources Count: " . count($res['sources']) . ", Reply: " . mb_substr(str_replace("\n", " ", $reply), 0, 80) . "..."
    );
    usleep(150000);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. INTENT AMBIGUITY & CLARIFICATION (UNCERTAIN -> Suggestions)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 3. Intent Ambiguity & Clarification (UNCERTAIN -> Suggestions) ──\n";

$uncertainQueries = [
    'cancel',
    'update',
    'refund',
    'invoice',
    'change',
];

foreach ($uncertainQueries as $uq) {
    $conv = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'mat_unc_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
    $res = $service->handleQuery($uq, $ws->id, $conv);

    assertMatrix(
        $res['route'] === 'uncertain' && !empty($res['suggestions']),
        "Bare keyword '{$uq}' triggers UNCERTAIN clarification suggestions",
        "Route: {$res['route']}, Suggestions: " . json_encode($res['suggestions'])
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. UNCERTAIN FOLLOW-UP TO GROUNDED ANSWER RESOLUTION
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 4. UNCERTAIN Follow-Up to Grounded Answer Resolution ──\n";

$convTurn = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'mat_follow_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
// Turn 1: User sends ambiguous "cancel"
$resTurn1 = $service->handleQuery('cancel', $ws->id, $convTurn);
$service->saveOutboundReply($convTurn, $resTurn1['reply']);

// Turn 2: User clicks suggestion pill: "Ask about the order cancellation policy"
$resTurn2 = $service->handleQuery('Ask about the order cancellation policy', $ws->id, $convTurn);
$service->saveOutboundReply($convTurn, $resTurn2['reply']);

assertMatrix(
    $resTurn1['route'] === 'uncertain' && $resTurn2['route'] === 'knowledge',
    "Ambiguous 'cancel' (UNCERTAIN) seamlessly resolves to KNOWLEDGE on suggestion click",
    "Turn 1 Route: {$resTurn1['route']}, Turn 2 Route: {$resTurn2['route']}"
);

// ─────────────────────────────────────────────────────────────────────────────
// 5. ANTI-HALLUCINATION ON NON-EXISTENT COMPANY POLICIES
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── 5. Anti-Hallucination on Non-Existent Company Policies ──\n";

$adversarialCases = [
    'What is your refund policy for Martian rocket ships and asteroid mining tools?',
    'Do you provide a 99-year lifetime free subscription with infinite storage?',
    'আপনারা কি চাঁদে পার্সেল ডেলিভারি দেন?',
];

foreach ($adversarialCases as $adv) {
    $conv = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'mat_adv_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
    $res = $service->handleQuery($adv, $ws->id, $conv);
    $reply = $res['reply'];

    $hallucinated = (
        stripos($reply, 'our martian') !== false ||
        stripos($reply, 'yes, we provide a 99-year') !== false ||
        stripos($reply, 'yes, we deliver to the moon') !== false
    );

    $safeRefusal = (
        stripos($reply, 'sorry') !== false ||
        stripos($reply, 'not have') !== false ||
        stripos($reply, 'don\'t have') !== false ||
        stripos($reply, 'do not') !== false ||
        stripos($reply, 'দুঃখিত') !== false ||
        stripos($reply, 'নেই') !== false ||
        stripos($reply, 'না') !== false ||
        stripos($reply, 'দিই না') !== false ||
        stripos($reply, 'সম্ভব নয়') !== false ||
        stripos($reply, 'সম্ভব নয়') !== false ||
        stripos($reply, 'support') !== false ||
        stripos($reply, 'human') !== false ||
        $res['route'] === 'ood'
    );

    assertMatrix(
        !$hallucinated && $safeRefusal,
        "Adversarial non-existent topic rejected safely without hallucination",
        "Query: {$adv} | Reply: " . mb_substr(str_replace("\n", " ", $reply), 0, 75) . "..."
    );
    usleep(150000);
}

// ─────────────────────────────────────────────────────────────────────────────
// FINAL SCORECARD
// ─────────────────────────────────────────────────────────────────────────────
$passRate = round(($passedTests / $totalTests) * 100, 1);
echo "\n=================================================================================================\n";
echo "📊 KNOWLEDGE BEHAVIOR MATRIX SCORECARD\n";
echo "=================================================================================================\n";
printf("Total Behavioral Checks: %d\n", $totalTests);
printf("Passed Checks:           %d\n", $passedTests);
printf("Pass Rate:               %.1f%%\n", $passRate);
echo "Status:                  " . ($passRate === 100.0 ? "🟢 100% PASS (Production Hardened Behavior Matrix)" : "🟡 REVIEW NEEDED") . "\n";
echo "=================================================================================================\n";
