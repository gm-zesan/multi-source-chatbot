<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Cache;

echo "===================================================================\n";
echo " LIVE CONCURRENCY ENFORCEMENT & PEAK SIMULTANEOUS OVERLAP AUDIT\n";
echo "===================================================================\n\n";

$cache = (config('database.redis.default') && extension_loaded('redis')) ? Cache::store('redis') : Cache::store();

// Clean any stale keys
$cache->put('ai_in_flight_ws_1', 0, 60);
$cache->put('ai_in_flight_ws_2', 0, 60);
$cache->put('ai_in_flight_global', 0, 60);

// -------------------------------------------------------------------------
// PHASE 1: Workspace Concurrency Limit (Cap: 5)
// -------------------------------------------------------------------------
echo ">>> PHASE 1: Testing Workspace Concurrency Limit (Cap: 5 on WS 1)\n";
echo "    Dispatching 7 simultaneous worker processes...\n";

$workersCount = 7;
$pipes = [];
$processes = [];
$tLaunchPhase1 = microtime(true);

for ($i = 1; $i <= $workersCount; $i++) {
    $cmd = 'php ' . escapeshellarg(__DIR__ . '/verify_concurrency_worker.php') . " worker_ws1_{$i} 1";
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $pipes[$i]);
    if (is_resource($proc)) {
        $processes[$i] = $proc;
    }
}

$phase1Results = [];
foreach ($processes as $i => $proc) {
    $stdout = stream_get_contents($pipes[$i][1]);
    $stderr = stream_get_contents($pipes[$i][2]);
    fclose($pipes[$i][0]);
    fclose($pipes[$i][1]);
    fclose($pipes[$i][2]);
    proc_close($proc);

    $json = json_decode(trim($stdout), true);
    if ($json) {
        $phase1Results[] = $json;
    } else {
        $phase1Results[] = [
            'worker_id' => "worker_ws1_{$i}",
            'raw_stdout' => $stdout,
            'raw_stderr' => $stderr,
        ];
    }
}
$phase1WallClock = round(microtime(true) - $tLaunchPhase1, 3);

$phase1Admitted = [];
$phase1Rejected = [];
foreach ($phase1Results as $res) {
    if (($res['halt_reason'] ?? null) === 'canary_workspace_concurrency_exceeded') {
        $phase1Rejected[] = $res;
        echo sprintf("  [-] %s: REJECTED (reason: %s, duration: %.2f ms, start: %.4f, end: %.4f)\n",
            $res['worker_id'], $res['halt_reason'], $res['duration_ms'] ?? 0, $res['start_epoch'] ?? 0, $res['end_epoch'] ?? 0);
    } else {
        $phase1Admitted[] = $res;
        echo sprintf("  [+] %s: ADMITTED (route: %s, duration: %.2f ms, start: %.4f, end: %.4f)\n",
            $res['worker_id'], $res['route'] ?? 'unknown', $res['duration_ms'] ?? 0, $res['start_epoch'] ?? 0, $res['end_epoch'] ?? 0);
    }
}

// Compute Simultaneous Overlap Window for Admitted Workers
$phase1OverlapStart = 0.0;
$phase1OverlapEnd = PHP_FLOAT_MAX;
if (count($phase1Admitted) === 5) {
    foreach ($phase1Admitted as $adm) {
        $phase1OverlapStart = max($phase1OverlapStart, $adm['start_epoch']);
        $phase1OverlapEnd = min($phase1OverlapEnd, $adm['end_epoch']);
    }
}
$phase1OverlapMs = round(($phase1OverlapEnd - $phase1OverlapStart) * 1000, 2);

echo "\n--- Phase 1 Forensics ---\n";
echo "  Total Dispatched: " . count($phase1Results) . "\n";
echo "  Admitted:         " . count($phase1Admitted) . " / 5\n";
echo "  Rejected:         " . count($phase1Rejected) . "\n";
echo "  Overlap Window:   [" . number_format($phase1OverlapStart, 4) . " -> " . number_format($phase1OverlapEnd, 4) . "]\n";
echo "  Overlap Duration: " . $phase1OverlapMs . " ms\n";

$rejectionInsideOverlap = true;
foreach ($phase1Rejected as $rej) {
    $inside = ($rej['start_epoch'] >= $phase1OverlapStart && $rej['start_epoch'] <= $phase1OverlapEnd);
    if (!$inside) {
        $rejectionInsideOverlap = false;
    }
    echo sprintf("  Rejection Timing: %s started at %.4f (Inside 5-slot overlap: %s)\n",
        $rej['worker_id'], $rej['start_epoch'], $inside ? 'YES' : 'NO');
}
echo "  Peak Simultaneous Concurrency Established: " . ($phase1OverlapMs > 0 && count($phase1Admitted) === 5 ? "YES (5 slots simultaneous)" : "NO") . "\n";
echo "  In-Flight WS 1 Post-Test: " . (int) $cache->get('ai_in_flight_ws_1', 0) . "\n\n";

// -------------------------------------------------------------------------
// PHASE 2: Global Concurrency Limit (Cap: 10 across WS 1 & WS 2)
// -------------------------------------------------------------------------
echo ">>> PHASE 2: Testing Global Concurrency Limit (Cap: 10 across WS 1 & WS 2)\n";
echo "    Dispatching 11 simultaneous worker processes (5 on WS1, 5 on WS2, 1 on WS2)...\n";

$phase2Pipes = [];
$phase2Processes = [];
$tLaunchPhase2 = microtime(true);

// 5 workers on WS1, 6 workers on WS2 (total 11)
$plan = [
    ['id' => 'p2_ws1_1', 'ws' => 1],
    ['id' => 'p2_ws1_2', 'ws' => 1],
    ['id' => 'p2_ws1_3', 'ws' => 1],
    ['id' => 'p2_ws1_4', 'ws' => 1],
    ['id' => 'p2_ws1_5', 'ws' => 1],
    ['id' => 'p2_ws2_1', 'ws' => 2],
    ['id' => 'p2_ws2_2', 'ws' => 2],
    ['id' => 'p2_ws2_3', 'ws' => 2],
    ['id' => 'p2_ws2_4', 'ws' => 2],
    ['id' => 'p2_ws2_5', 'ws' => 2],
    ['id' => 'p2_ws2_6', 'ws' => 2],
];

foreach ($plan as $idx => $p) {
    $cmd = 'php ' . escapeshellarg(__DIR__ . '/verify_concurrency_worker.php') . " {$p['id']} {$p['ws']}";
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $phase2Pipes[$idx]);
    if (is_resource($proc)) {
        $phase2Processes[$idx] = $proc;
    }
}

$phase2Results = [];
foreach ($phase2Processes as $idx => $proc) {
    $stdout = stream_get_contents($phase2Pipes[$idx][1]);
    $stderr = stream_get_contents($phase2Pipes[$idx][2]);
    fclose($phase2Pipes[$idx][0]);
    fclose($phase2Pipes[$idx][1]);
    fclose($phase2Pipes[$idx][2]);
    proc_close($proc);

    $json = json_decode(trim($stdout), true);
    if ($json) {
        $phase2Results[] = $json;
    } else {
        $phase2Results[] = [
            'worker_id' => $plan[$idx]['id'],
            'raw_stdout' => $stdout,
            'raw_stderr' => $stderr,
        ];
    }
}
$phase2WallClock = round(microtime(true) - $tLaunchPhase2, 3);

$phase2Admitted = [];
$phase2Rejected = [];
foreach ($phase2Results as $res) {
    $halt = $res['halt_reason'] ?? null;
    if ($halt !== null) {
        $phase2Rejected[] = $res;
        echo sprintf("  [-] %s (WS %d): REJECTED (reason: %s, duration: %.2f ms, start: %.4f)\n",
            $res['worker_id'], $res['workspace_id'], $halt, $res['duration_ms'] ?? 0, $res['start_epoch'] ?? 0);
    } else {
        $phase2Admitted[] = $res;
        echo sprintf("  [+] %s (WS %d): ADMITTED (route: %s, duration: %.2f ms, start: %.4f, end: %.4f)\n",
            $res['worker_id'], $res['workspace_id'], $res['route'] ?? 'unknown', $res['duration_ms'] ?? 0, $res['start_epoch'] ?? 0, $res['end_epoch'] ?? 0);
    }
}

// Compute Simultaneous Overlap Window for 10 Admitted Workers
$phase2OverlapStart = 0.0;
$phase2OverlapEnd = PHP_FLOAT_MAX;
if (count($phase2Admitted) === 10) {
    foreach ($phase2Admitted as $adm) {
        $phase2OverlapStart = max($phase2OverlapStart, $adm['start_epoch']);
        $phase2OverlapEnd = min($phase2OverlapEnd, $adm['end_epoch']);
    }
}
$phase2OverlapMs = round(($phase2OverlapEnd - $phase2OverlapStart) * 1000, 2);

echo "\n--- Phase 2 Forensics ---\n";
echo "  Total Dispatched: " . count($phase2Results) . "\n";
echo "  Admitted:         " . count($phase2Admitted) . " / 10\n";
echo "  Rejected:         " . count($phase2Rejected) . "\n";
echo "  Overlap Window:   [" . number_format($phase2OverlapStart, 4) . " -> " . number_format($phase2OverlapEnd, 4) . "]\n";
echo "  Overlap Duration: " . $phase2OverlapMs . " ms\n";
echo "  In-Flight WS 1 Post-Test: " . (int) $cache->get('ai_in_flight_ws_1', 0) . "\n";
echo "  In-Flight WS 2 Post-Test: " . (int) $cache->get('ai_in_flight_ws_2', 0) . "\n";
echo "  In-Flight Global Post-Test: " . (int) $cache->get('ai_in_flight_global', 0) . "\n\n";

// Save Full Metrics Output
$metrics = [
    'timestamp' => date('c'),
    'phase1_workspace_concurrency' => [
        'cap' => 5,
        'dispatched' => count($phase1Results),
        'admitted' => count($phase1Admitted),
        'rejected' => count($phase1Rejected),
        'wall_clock_s' => $phase1WallClock,
        'overlap_window_start' => $phase1OverlapStart,
        'overlap_window_end' => $phase1OverlapEnd,
        'overlap_duration_ms' => $phase1OverlapMs,
        'all_rejected_inside_overlap' => $rejectionInsideOverlap,
        'results' => $phase1Results,
    ],
    'phase2_global_concurrency' => [
        'cap' => 10,
        'dispatched' => count($phase2Results),
        'admitted' => count($phase2Admitted),
        'rejected' => count($phase2Rejected),
        'wall_clock_s' => $phase2WallClock,
        'overlap_window_start' => $phase2OverlapStart,
        'overlap_window_end' => $phase2OverlapEnd,
        'overlap_duration_ms' => $phase2OverlapMs,
        'results' => $phase2Results,
    ],
    'post_test_counters' => [
        'ai_in_flight_ws_1' => (int) $cache->get('ai_in_flight_ws_1', 0),
        'ai_in_flight_ws_2' => (int) $cache->get('ai_in_flight_ws_2', 0),
        'ai_in_flight_global' => (int) $cache->get('ai_in_flight_global', 0),
    ],
];

file_put_contents(
    __DIR__ . '/../docs/product-audit/verification/2026-11-24_concurrency_enforcement_live_metrics.json',
    json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "Metrics written to docs/product-audit/verification/2026-11-24_concurrency_enforcement_live_metrics.json\n";
echo "===================================================================\n";
