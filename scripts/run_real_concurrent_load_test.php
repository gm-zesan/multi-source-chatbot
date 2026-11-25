<?php

declare(strict_types=1);

/**
 * Entrepreneurs Automation — Multi-Process Real Concurrent Load Test & Token Billing Reconciliation
 *
 * Spawns truly simultaneous parallel OS worker processes across 3 concurrency tiers:
 *   - Tier 1: 5 concurrent workers
 *   - Tier 2: 10 concurrent workers
 *   - Tier 3: 20 concurrent workers
 *
 * Tests the entire live pipeline simultaneously:
 *   Laravel -> Python AI Service (Port 8001, MPS GPU) -> Typesense (Port 8108) -> DeepSeek Chat API
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Workspace;

echo "========================================================================================\n";
echo "   MULTI-PROCESS REAL CONCURRENT LOAD TEST & PROVIDER BILLING RECONCILIATION\n";
echo "   Execution Mode: TRUE SIMULTANEOUS OS PROCESSES (DeepSeek Chat API + Typesense)\n";
echo "========================================================================================\n\n";

$tiers = [5, 10, 20];

// Queries representing realistic customer traffic mix
$queries = [
    'ডেলিভারি চার্জ কত? Delivery charge inside and outside Dhaka',
    'পণ্য রিটার্ন বা ফেরত দেওয়ার নিয়ম কি?',
    'Mechanical Gaming Keyboard er dam koto?',
    'Do you sell prescription heart disease medicine?',
    'ক্যাশ অন ডেলিভারি সুবিধা আছে কি?',
    'অর্ডার ট্র্যাকিং করার উপায় কি?',
    'ঢাকার বাইরে ডেলিভারি পেতে কতদিন সময় লাগে?',
    'বিকাশে পেমেন্ট করলে কোনো ক্যাশব্যাক বা ডিসকাউন্ট পাওয়া যাবে কি?',
    'প্রোডাক্টে কোনো ম্যানুফ্যাকচারিং ডিফেক্ট থাকলে কিভাবে পরিবর্তন করবো?',
    'অর্ডার বাতিল করতে চাইলে কি করতে হবে?',
];

$allTierReports = [];

function executeConcurrentTier(int $concurrency, array $queries): array {
    echo "----------------------------------------------------------------------------------------\n";
    echo sprintf(">>> LAUNCHING TIER: %d SIMULTANEOUS PARALLEL WORKERS\n", $concurrency);
    echo "----------------------------------------------------------------------------------------\n";

    $workerScript = __DIR__ . '/concurrent_worker.php';
    $processes = [];
    $pipes = [];

    $tierStart = microtime(true);

    // 1. Spawn all N processes simultaneously in parallel
    for ($i = 0; $i < $concurrency; $i++) {
        $q = $queries[$i % count($queries)];
        $payload = json_encode([
            'worker_id'    => $i + 1,
            'workspace_id' => 1,
            'query'        => $q,
            'channel'      => 'whatsapp',
            'is_customer'  => true,
        ]);

        $cmd = ['php', $workerScript, $payload];
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $proc = proc_open($cmd, $descriptors, $pipes[$i]);
        if (is_resource($proc)) {
            // Set stdout non-blocking
            stream_set_blocking($pipes[$i][1], false);
            stream_set_blocking($pipes[$i][2], false);
            $processes[$i] = [
                'proc'   => $proc,
                'pipes'  => $pipes[$i],
                'stdout' => '',
                'stderr' => '',
                'done'   => false,
            ];
        }
    }

    echo sprintf("✓ Successfully launched %d worker processes in parallel at %s\n", count($processes), date('H:i:s.u'));
    echo "Waiting for concurrent completions...\n";

    // 2. Multi-process event loop: read non-blocking streams until all complete
    $activeCount = count($processes);
    while ($activeCount > 0) {
        $readPipes = [];
        foreach ($processes as $idx => $p) {
            if (!$p['done']) {
                $readPipes[] = $p['pipes'][1];
            }
        }

        if (empty($readPipes)) {
            break;
        }

        $write = null;
        $except = null;
        if (stream_select($readPipes, $write, $except, 0, 50000) > 0) {
            foreach ($processes as $idx => &$p) {
                if ($p['done']) {
                    continue;
                }
                $chunk = fread($p['pipes'][1], 8192);
                if ($chunk !== false && $chunk !== '') {
                    $p['stdout'] .= $chunk;
                }
                $errChunk = fread($p['pipes'][2], 8192);
                if ($errChunk !== false && $errChunk !== '') {
                    $p['stderr'] .= $errChunk;
                }

                $status = proc_get_status($p['proc']);
                if (!$status['running']) {
                    $remaining = stream_get_contents($p['pipes'][1]);
                    if ($remaining) {
                        $p['stdout'] .= $remaining;
                    }
                    fclose($p['pipes'][0]);
                    fclose($p['pipes'][1]);
                    fclose($p['pipes'][2]);
                    proc_close($p['proc']);
                    $p['done'] = true;
                    $activeCount--;
                }
            }
            unset($p);
        }
    }

    $tierEnd = microtime(true);
    $totalWallSec = round($tierEnd - $tierStart, 2);

    // 3. Collect and parse JSON telemetry from all workers
    $workerResults = [];
    foreach ($processes as $idx => $p) {
        $json = json_decode(trim($p['stdout']), true);
        if ($json) {
            $workerResults[] = $json;
        } else {
            $workerResults[] = [
                'worker_id'   => $idx + 1,
                'success'     => false,
                'elapsed_ms'  => 0,
                'error'       => 'Failed to parse worker stdout: ' . ($p['stderr'] ?: $p['stdout']),
                'total_tokens'=> 0,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'cost_usd'    => 0,
            ];
        }
    }

    $successCount = count(array_filter($workerResults, fn($r) => !empty($r['success'])));
    $latencies = array_column(array_filter($workerResults, fn($r) => !empty($r['success'])), 'elapsed_ms');
    sort($latencies);

    $count = count($latencies);
    $minLat = $count ? min($latencies) : 0;
    $maxLat = $count ? max($latencies) : 0;
    $avgLat = $count ? round(array_sum($latencies) / $count, 2) : 0;
    $p50 = $count ? $latencies[(int) floor($count * 0.50)] : 0;
    $p90 = $count ? $latencies[(int) floor($count * 0.90)] : 0;
    $p95 = $count ? $latencies[(int) floor($count * 0.95)] : 0;

    $throughputRps = $totalWallSec > 0 ? round($concurrency / $totalWallSec, 2) : 0;

    $totalPromptTokens = array_sum(array_column($workerResults, 'prompt_tokens'));
    $totalCompletionTokens = array_sum(array_column($workerResults, 'completion_tokens'));
    $totalTokens = array_sum(array_column($workerResults, 'total_tokens'));
    $totalCostUsd = array_sum(array_column($workerResults, 'cost_usd'));

    $avgPromptPerQuery = $concurrency ? round($totalPromptTokens / $concurrency, 1) : 0;
    $avgCompletionPerQuery = $concurrency ? round($totalCompletionTokens / $concurrency, 1) : 0;
    $avgCostPer1kUsd = $concurrency ? round(($totalCostUsd / $concurrency) * 1000, 4) : 0;
    $avgCostPer1kBdt = round($avgCostPer1kUsd * 120, 2);

    echo sprintf("Completed %d requests in %.2f seconds (Real Concurrency Throughput: %.2f req/sec)\n", $concurrency, $totalWallSec, $throughputRps);
    echo sprintf("Success: %d/%d (%.1f%%) | P50 Latency: %.2f ms | P95 Latency: %.2f ms | Max: %.2f ms\n", $successCount, $concurrency, ($successCount / $concurrency) * 100, $p50, $p95, $maxLat);
    echo sprintf("Tokens: %d Prompt + %d Completion = %d Total (Avg: %.1f tokens/query)\n", $totalPromptTokens, $totalCompletionTokens, $totalTokens, $totalTokens / max(1, $concurrency));
    echo sprintf("Actual Billed Cost: $%.6f USD (Rate: $%.4f USD / 1k queries ~ ৳%.2f BDT)\n\n", $totalCostUsd, $avgCostPer1kUsd, $avgCostPer1kBdt);

    return [
        'concurrency'               => $concurrency,
        'total_requests'            => $concurrency,
        'success_count'             => $successCount,
        'wall_clock_seconds'        => $totalWallSec,
        'throughput_rps'            => $throughputRps,
        'latency_min_ms'            => $minLat,
        'latency_p50_ms'            => $p50,
        'latency_p90_ms'            => $p90,
        'latency_p95_ms'            => $p95,
        'latency_max_ms'            => $maxLat,
        'latency_avg_ms'            => $avgLat,
        'total_prompt_tokens'       => $totalPromptTokens,
        'total_completion_tokens'   => $totalCompletionTokens,
        'total_tokens'              => $totalTokens,
        'avg_prompt_tokens'         => $avgPromptPerQuery,
        'avg_completion_tokens'     => $avgCompletionPerQuery,
        'actual_cost_usd'           => round($totalCostUsd, 6),
        'cost_per_1k_usd'           => $avgCostPer1kUsd,
        'cost_per_1k_bdt'           => $avgCostPer1kBdt,
    ];
}

// Temporarily increase daily canary limit for load testing
config(['ai.canary_daily_limit' => 500]);

foreach ($tiers as $tier) {
    $allTierReports[$tier] = executeConcurrentTier($tier, $queries);
    // 2 second breathing room between tiers
    sleep(2);
}

// Reset daily limit
config(['ai.canary_daily_limit' => 100]);

// Save final comprehensive concurrent telemetry
$reportFile = __DIR__ . '/../docs/product-audit/verification/2026-11-24_real_multi_process_concurrent_load_metrics.json';
file_put_contents($reportFile, json_encode([
    'audit_timestamp' => now()->toIso8601String(),
    'execution_mode'  => 'TRUE_MULTI_PROCESS_CONCURRENCY',
    'tiers_tested'    => $tiers,
    'tier_breakdown'  => $allTierReports,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "========================================================================================\n";
echo "   MULTI-PROCESS CONCURRENT LOAD TEST COMPLETE — REPORT SAVED\n";
echo "   File: docs/product-audit/verification/2026-11-24_real_multi_process_concurrent_load_metrics.json\n";
echo "========================================================================================\n";
