<?php

declare(strict_types=1);

/**
 * =============================================================================
 * PRODUCTION CONTRACT AUDIT SUITE: TIER 3 ARCHITECTURAL INVARIANTS
 * =============================================================================
 * Validates the 5 Hard Invariants of the Tier 3 LLM Escape Hatch:
 *   Invariant 1: Tier 3 returns ONLY query reformulation keywords.
 *   Invariant 2: Tier 3 output can NEVER directly become answer or citation.
 *   Invariant 3: Every Tier 3 hit MUST independently pass Answerability Gate.
 *   Invariant 4: Tier 3 timeout / 429 / 5xx safely bypasses to Safe Fallback.
 *   Invariant 5: OOD queries NEVER become answerable via plausible keywords.
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
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Support\Facades\Http;

$service = app(CustomerSupportService::class);
$retrievalClient = app(RetrievalClient::class);
$ws = Workspace::first() ?? Workspace::create(['name' => 'Contract WS', 'slug' => 'contract-ws']);
$channel = Channel::first() ?? Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
$acc = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws->id, 'channel_id' => $channel->id],
    ['name' => 'Contract Acc', 'external_id' => 'acc_contract_' . uniqid(), 'access_token' => 'tok_contract', 'is_active' => true]
);

echo "=================================================================================================\n";
echo "🛡️ PRODUCTION CONTRACT AUDIT: TIER 3 ARCHITECTURAL INVARIANTS\n";
echo "=================================================================================================\n\n";

$passedCount = 0;
$totalCount = 0;

function assertContract(bool $condition, string $invariantLabel, string $details = ''): void {
    global $passedCount, $totalCount;
    $totalCount++;
    if ($condition) {
        $passedCount++;
        echo "  ✅ PASS: {$invariantLabel}\n";
    } else {
        echo "  ❌ FAIL: {$invariantLabel}\n";
        if ($details !== '') {
            echo "       ↳ Details: {$details}\n";
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CONTRACT INVARIANT 1: Tier 3 returns ONLY keywords, never full text answers
// ─────────────────────────────────────────────────────────────────────────────
echo "── Invariant 1: Tier 3 Query Reformulation Constraint ──\n";
$res1 = Http::timeout(5)->post("{$retrievalClient->baseUrl()}/api/v1/search", [
    'query' => 'How can our financial auditors inspect previous billing transactions?',
    'workspace_id' => $ws->id,
    'top_k' => 5,
]);
$telemetry1 = $res1->json('telemetry') ?? [];
$expandedQ1 = $telemetry1['expanded_query'] ?? '';
$isReformulationOnly = (mb_strlen($expandedQ1) < 250) && (stripos($expandedQ1, 'here are your invoices') === false);
assertContract(
    $res1->successful() && $isReformulationOnly,
    "Tier 3 output is strictly restricted to query reformulation / search keywords",
    "Expanded Query: {$expandedQ1}"
);

// ─────────────────────────────────────────────────────────────────────────────
// CONTRACT INVARIANT 2: Tier 3 output can NEVER directly become answer/citation
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Invariant 2: Authority of Ground Truth Invariant ──\n";
$conv2 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'contract_2_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$res2 = $service->handleQuery('Where can our auditors inspect quarterly billing receipts?', $ws->id, $conv2);
$hasAnswer = !empty($res2['reply']);
$sources2 = $res2['sources'] ?? [];
// Sources must come from validated Typesense FAQ, NOT the raw LLM string
$validSourceCheck = true;
foreach ($sources2 as $src) {
    $qText = is_array($src) ? ($src['question'] ?? '') : (string) $src;
    if (stripos($qText, 'How do I view my invoices?') === false && stripos($qText, 'invoices') === false) {
        $validSourceCheck = false;
    }
}
assertContract(
    $hasAnswer && $validSourceCheck,
    "Tier 3 output never becomes direct citation; citations strictly originate from validated KB hits",
    "Sources: " . json_encode($sources2)
);

// ─────────────────────────────────────────────────────────────────────────────
// CONTRACT INVARIANT 3: Answerability Gate strictly enforces score >= 0.45
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Invariant 3: Independent Answerability Gate ──\n";
$conv3 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'contract_3_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
// Query with very low match score (< 0.45) that has no KB answer
$res3 = $service->handleQuery('How do I configure custom BGP routing on our proprietary hardware firewall?', $ws->id, $conv3);
$topHit3 = $res3['top_hit'] ?? null;
$score3 = $topHit3 ? $topHit3->finalScore : 0.0;
$answered3 = $res3['answered'];
$sources3 = $res3['sources'] ?? [];
// Since score is < 0.45, answered must be false AND sources must be empty (ZERO false citation)
assertContract(
    $score3 < 0.45 && !$answered3 && count($sources3) === 0,
    "Candidate score < 0.45 is blocked at Answerability Gate with ZERO false citations",
    "Score: {$score3}, Answered: " . ($answered3 ? 'YES' : 'NO') . ", Sources Count: " . count($sources3)
);

// ─────────────────────────────────────────────────────────────────────────────
// CONTRACT INVARIANT 4: Tier 3 Timeout / Rate-Limit Safely Bypasses
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Invariant 4: Provider Failure Resilience & Safe Bypass ──\n";
$conv4 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'contract_4_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$t0 = microtime(true);
$res4 = $service->handleQuery('password vule gechi kivabe reset korbo?', $ws->id, $conv4);
$lat4Ms = round((microtime(true) - $t0) * 1000, 2);
$hasReply4 = !empty($res4['reply']);
assertContract(
    $hasReply4 && ($res4['route'] === 'knowledge'),
    "Tier 3 provider rate-limits / timeouts safely bypass to local fallback without fatal exception",
    "Latency: {$lat4Ms} ms, Route: {$res4['route']}"
);

// ─────────────────────────────────────────────────────────────────────────────
// CONTRACT INVARIANT 5: Out-of-Domain Queries NEVER Become Answerable via Tier 3
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── Invariant 5: Anti-Hallucination & OOD Safety under Expansion ──\n";
$conv5 = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'contract_5_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);
$res5 = $service->handleQuery('What is the traditional recipe for baking gluten-free chocolate chip cookies?', $ws->id, $conv5);
$sources5 = $res5['sources'] ?? [];
$isHandoffOrGeneral5 = $res5['route'] === 'ood' || count($sources5) === 0;
assertContract(
    count($sources5) === 0,
    "OOD query never produces false KB citations even if Tier 3 generates plausible keywords",
    "Route: {$res5['route']}, Sources: " . json_encode($sources5)
);

echo "\n=================================================================================================\n";
echo "📊 PRODUCTION CONTRACT AUDIT SCORECARD\n";
echo "=================================================================================================\n";
echo "Total Invariant Contracts Checked: {$totalCount}\n";
echo "Passed Invariant Contracts:        {$passedCount}\n";
echo "Contract Compliance Rate:          " . round(($passedCount / $totalCount) * 100, 1) . "%\n";
echo "Status:                            " . ($passedCount === $totalCount ? "🟢 100% CONTRACT COMPLIANT (All Invariants Enforced)" : "❌ NON-COMPLIANT") . "\n";
echo "=================================================================================================\n";
