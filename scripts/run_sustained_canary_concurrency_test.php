<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "========================================================================================\n";
echo "  ENGINE v2.4-RC1 — SUSTAINED CONCURRENCY (5 WORKERS x 3 WAVES) AUDIT\n";
echo "========================================================================================\n\n";

$concurrency = 5;
$waves = 3;
$queries = [
    'ডেলিভারি চার্জ কত?',
    'ক্যাশ অন ডেলিভারি কি সম্ভব?',
    'আপনাদের অফিস বা শোরুম কোথায়?',
    'Apex Solutions-এর কুরিয়ার পার্টনার কে এবং ডেলিভারি চার্জ কত?',
    'আপনাদের রিটার্ন পলিসি কেমন?',
];

$workerScript = __DIR__ . '/concurrent_worker.php';
$allResults = [];
$waveMetrics = [];

$tOverallStart = microtime(true);

for ($w = 1; $w <= $waves; $w++) {
    echo "--- Wave {$w} of {$waves} ({$concurrency} Simultaneous OS Processes) ---\n";
    $pipes = [];
    $processes = [];
    $tWaveStart = microtime(true);

    for ($i = 0; $i < $concurrency; $i++) {
        $workerId = (($w - 1) * $concurrency) + ($i + 1);
        $wsId = ($i % 2 === 0) ? 1 : 2;
        $q = $queries[$i % count($queries)];

        $payload = json_encode([
            'worker_id'    => $workerId,
            'workspace_id' => $wsId,
            'query'        => $q,
            'channel'      => 'whatsapp',
            'is_customer'  => true,
        ]);

        $cmd = 'php ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($payload);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipeList);
        if (is_resource($proc)) {
            fclose($pipeList[0]);
            $processes[$i] = $proc;
            $pipes[$i] = $pipeList;
        }
    }

    $waveOutputs = [];
    foreach ($processes as $i => $proc) {
        $stdout = stream_get_contents($pipes[$i][1]);
        $stderr = stream_get_contents($pipes[$i][2]);
        fclose($pipes[$i][1]);
        fclose($pipes[$i][2]);
        $exitCode = proc_close($proc);

        $parsed = json_decode(trim($stdout), true);
        if (!$parsed) {
            $parsed = [
                'worker_id' => $i + 1,
                'success' => false,
                'elapsed_ms' => 0.0,
                'error' => $stderr ?: 'Empty or invalid JSON output',
            ];
        }
        $waveOutputs[] = $parsed;
        $allResults[] = $parsed;

        $statusStr = $parsed['success'] ? 'SUCCESS' : 'FAILED (' . ($parsed['error'] ?? 'unknown') . ')';
        echo sprintf("  Worker %d: %s in %.2f ms (Tokens: %d, Cost: $%.6f)\n",
            $parsed['worker_id'], $statusStr, $parsed['elapsed_ms'] ?? 0.0,
            $parsed['total_tokens'] ?? 0, $parsed['cost_usd'] ?? 0.0
        );
    }

    $waveDuration = round(microtime(true) - $tWaveStart, 2);
    $waveSuccess = count(array_filter($waveOutputs, fn($r) => $r['success'] ?? false));
    $waveLatencies = array_column($waveOutputs, 'elapsed_ms');
    sort($waveLatencies);

    $wP50 = $waveLatencies[(int) (count($waveLatencies) * 0.50)];
    $wP95 = $waveLatencies[(int) (count($waveLatencies) * 0.95)];
    $wMax = max($waveLatencies);

    $waveMetrics[$w] = [
        'wave'            => $w,
        'duration_s'      => $waveDuration,
        'success_rate'    => "{$waveSuccess}/{$concurrency}",
        'throughput_rps'  => round($concurrency / $waveDuration, 2),
        'p50_ms'          => $wP50,
        'p95_ms'          => $wP95,
        'max_ms'          => $wMax,
    ];
    echo sprintf("  => Wave %d Complete: %d/%d succeeded in %.2fs (Throughput: %.2f RPS, P50: %.1fms, P95: %.1fms, Max: %.1fms)\n\n",
        $w, $waveSuccess, $concurrency, $waveDuration, $concurrency / $waveDuration, $wP50, $wP95, $wMax
    );
}

$totalDuration = round(microtime(true) - $tOverallStart, 2);
$totalRequests = count($allResults);
$totalSuccess = count(array_filter($allResults, fn($r) => $r['success'] ?? false));
$allLatencies = array_column($allResults, 'elapsed_ms');
sort($allLatencies);

$p50 = $allLatencies[(int) ($totalRequests * 0.50)];
$p90 = $allLatencies[(int) ($totalRequests * 0.90)];
$p95 = $allLatencies[(int) ($totalRequests * 0.95)];
$max = max($allLatencies);
$min = min($allLatencies);
$avg = round(array_sum($allLatencies) / $totalRequests, 2);

$totalTokens = array_sum(array_column($allResults, 'total_tokens'));
$totalCostUsd = array_sum(array_column($allResults, 'cost_usd'));

$summary = [
    'audit_timestamp'       => now()->toIso8601String(),
    'execution_mode'        => 'SUSTAINED_MULTI_WAVE_CONCURRENCY',
    'concurrency_per_wave'  => $concurrency,
    'total_waves'           => $waves,
    'total_requests'        => $totalRequests,
    'successful_requests'   => $totalSuccess,
    'success_rate_percent'  => round(($totalSuccess / $totalRequests) * 100, 2),
    'overall_wall_clock_s'  => $totalDuration,
    'sustained_throughput_rps' => round($totalRequests / $totalDuration, 2),
    'latency_min_ms'        => $min,
    'latency_p50_ms'        => $p50,
    'latency_p90_ms'        => $p90,
    'latency_p95_ms'        => $p95,
    'latency_max_ms'        => $max,
    'latency_avg_ms'        => $avg,
    'total_tokens'          => $totalTokens,
    'avg_tokens_per_request'=> round($totalTokens / $totalRequests, 1),
    'total_cost_usd'        => round($totalCostUsd, 6),
    'cost_per_1k_bdt'       => round(($totalCostUsd / $totalRequests) * 1000 * 120, 2),
    'wave_breakdown'        => $waveMetrics,
];

file_put_contents(
    base_path('docs/product-audit/verification/2026-11-24_sustained_5_concurrency_load_metrics.json'),
    json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "========================================================================================\n";
echo "  SUSTAINED CONCURRENCY SCORECARD SUMMARY\n";
echo "========================================================================================\n";
echo sprintf("Total Dispatched:    %d requests (%d waves x %d workers)\n", $totalRequests, $waves, $concurrency);
echo sprintf("Success Rate:        %d/%d (%.1f%%)\n", $totalSuccess, $totalRequests, ($totalSuccess / $totalRequests) * 100);
echo sprintf("Sustained Throughput: %.2f req/sec across %.2fs wall-clock\n", $totalRequests / $totalDuration, $totalDuration);
echo sprintf("Latency Profile:     Min: %.1fms | P50: %.1fms | P95: %.1fms | Max: %.1fms\n", $min, $p50, $p95, $max);
echo sprintf("Token Economics:     Total: %d tokens | Cost: $%.6f USD (~৳%.2f BDT / 1k req)\n", $totalTokens, $totalCostUsd, ($totalCostUsd / $totalRequests) * 1000 * 120);
echo "✓ Results saved to docs/product-audit/verification/2026-11-24_sustained_5_concurrency_load_metrics.json\n";
