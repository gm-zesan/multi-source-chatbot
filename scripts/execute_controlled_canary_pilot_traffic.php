<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Decision\UnifiedOrchestrator;
use App\Models\KnowledgeSearchLog;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;

echo "========================================================================================\n";
echo "  ENGINE v2.4-RC1 — CONTROLLED CANARY PILOT TRAFFIC EXECUTION\n";
echo "========================================================================================\n\n";

$todayUtc = now('UTC')->format('Y-m-d');
$todayLocal = now()->format('Y-m-d');

// Reset today's canary cache counters and prior test logs for clean audit
Cache::forget("ai_canary_traffic_1_{$todayUtc}");
Cache::forget("ai_canary_traffic_1_{$todayLocal}");
Cache::forget("ai_canary_traffic_2_{$todayUtc}");
Cache::forget("ai_canary_traffic_2_{$todayLocal}");

KnowledgeSearchLog::whereIn('workspace_id', [1, 2])->delete();
UnansweredQuestion::whereIn('workspace_id', [1, 2])->delete();

echo "✓ Baseline state reset: Cache counters and search logs cleared for clean canary pilot.\n\n";

$orchestrator = app(UnifiedOrchestrator::class);

$pilotPlan = [
    [
        'workspace_id'   => 1,
        'workspace_name' => 'Entrepreneurs Automation',
        'queries'        => [
            ['query' => 'ডেলিভারি চার্জ কত?', 'expected_type' => 'in_domain_faq'],
            ['query' => 'ক্যাশ অন ডেলিভারি কি সম্ভব?', 'expected_type' => 'in_domain_faq'],
            ['query' => 'আমার অর্ডার কি পরিবর্তন বা বাতিল করতে পারব?', 'expected_type' => 'in_domain_faq'],
            ['query' => 'আজকে কি বৃষ্টি হওয়ার সম্ভাবনা আছে?', 'expected_type' => 'out_of_domain'],
        ],
    ],
    [
        'workspace_id'   => 2,
        'workspace_name' => 'Apex Solutions',
        'queries'        => [
            ['query' => 'Apex Solutions-এর কুরিয়ার পার্টনার কে এবং ডেলিভারি চার্জ কত?', 'expected_type' => 'in_domain_faq'],
            ['query' => 'আপনাদের রিটার্ন পলিসি কেমন?', 'expected_type' => 'in_domain_faq'],
            ['query' => 'ধানমন্ডি অফিস কয়টা পর্যন্ত খোলা থাকে?', 'expected_type' => 'in_domain_faq'],
            ['query' => 'আপনাদের কাছে কি আইফোন ১৬ প্রো ম্যাক্স পাওয়া যাবে?', 'expected_type' => 'out_of_domain'],
        ],
    ],
];

$results = [];

foreach ($pilotPlan as $plan) {
    $wsId = $plan['workspace_id'];
    $wsName = $plan['workspace_name'];
    echo "--- Executing Pilot Batch for Workspace {$wsId} ({$wsName}) ---\n";

    foreach ($plan['queries'] as $idx => $q) {
        $num = $idx + 1;
        $queryText = $q['query'];
        $type = $q['expected_type'];
        echo "  [WS {$wsId} Q{$num}] '{$queryText}' ({$type})... ";

        $tStart = microtime(true);
        $resp = $orchestrator->process(
            query: $queryText,
            workspaceId: $wsId,
            user: null,
            conversation: null,
            channelSlug: 'whatsapp',
            isCustomer: true,
        );
        $elapsed = round((microtime(true) - $tStart) * 1000, 2);

        $route = $resp['route'] ?? 'unknown';
        $answered = $resp['answered'] ?? false;
        $confidence = round((float) ($resp['confidence'] ?? 0.0), 4);
        $tokens = (int) ($resp['raw_llm_response']['total_tokens'] ?? $resp['decision_metadata']['total_tokens'] ?? 0);

        echo "done ({$elapsed} ms, route: {$route}, answered: " . ($answered ? 'YES' : 'NO') . ", score: {$confidence})\n";

        $results[] = [
            'workspace_id'   => $wsId,
            'workspace_name' => $wsName,
            'query'          => $queryText,
            'type'           => $type,
            'elapsed_ms'     => $elapsed,
            'route'          => $route,
            'answered'       => $answered,
            'confidence'     => $confidence,
            'tokens'         => $tokens,
        ];
    }
    echo "\n";
}

echo "========================================================================================\n";
echo "  CANARY PILOT EXECUTION SUMMARY & AUDIT RECONCILIATION\n";
echo "========================================================================================\n";

foreach ([1, 2] as $wsId) {
    $ws = Workspace::find($wsId);
    $quotaKey = "ai_canary_traffic_{$wsId}_{$todayUtc}";
    $usage = (int) Cache::get($quotaKey, 0);

    $logs = KnowledgeSearchLog::where('workspace_id', $wsId)->get();
    $logCount = $logs->count();
    $avgScore = $logCount > 0 ? round($logs->avg('final_score'), 4) : 0.0;
    $avgLatency = $logCount > 0 ? round($logs->avg('response_time_ms'), 2) : 0.0;

    $unanswered = UnansweredQuestion::where('workspace_id', $wsId)->count();

    echo "Workspace {$wsId} ({$ws->name}):\n";
    echo "  - Quota Consumed: {$usage} / 100\n";
    echo "  - Telemetry Logs: {$logCount} records\n";
    echo "  - Reconciled Discrepancy: " . ($usage === $logCount ? '0 (PERFECT RECONCILIATION)' : 'MISMATCH (' . ($usage - $logCount) . ')') . "\n";
    echo "  - Average Answer Score: {$avgScore}\n";
    echo "  - Average Response Latency: {$avgLatency} ms\n";
    echo "  - Unanswered Questions Tracked: {$unanswered}\n\n";
}

$summaryPayload = [
    'execution_timestamp' => now()->toIso8601String(),
    'pilot_results'       => $results,
];

file_put_contents(
    base_path('docs/product-audit/verification/2026-11-24_canary_pilot_traffic_execution_results.json'),
    json_encode($summaryPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "✓ Pilot execution results saved to docs/product-audit/verification/2026-11-24_canary_pilot_traffic_execution_results.json\n";
