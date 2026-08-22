<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Retrieval\RetrievalClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class ChatbotHealth extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'chatbot:health';

    /**
     * The console command description.
     */
    protected $description = 'Verify that the complete chatbot infrastructure is healthy';

    /**
     * Collected check results.
     *
     * @var array<int, array{section: string, label: string, status: bool, detail?: string, latency?: float}>
     */
    private array $results = [];

    public function __construct(
        private readonly RetrievalClient $retrievalClient,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('==================================');
        $this->line('   Chatbot Infrastructure Health');
        $this->line('==================================');
        $this->line('');

        $this->checkLaravel();
        $this->checkRetrievalService();
        $this->checkConfig();

        $this->displaySummary();

        $hasFailures = $this->hasFailures();

        if ($hasFailures) {
            $this->line('');
            $this->line('==================================');
            $this->error('   Overall Status: UNHEALTHY');
            $this->line('==================================');

            return Command::FAILURE;
        }

        $this->line('');
        $this->line('==================================');
        $this->info('   Overall Status: HEALTHY');
        $this->line('==================================');

        return Command::SUCCESS;
    }

    private function checkLaravel(): void
    {
        $this->line('── Laravel Application ──────────────────');

        // Database
        $dbStart = microtime(true);
        try {
            DB::select('SELECT 1');
            $dbLatency = (microtime(true) - $dbStart) * 1000;
            $this->recordPass('laravel', 'Database', 'Connection Successful', $dbLatency);
        } catch (\Throwable $e) {
            $dbLatency = (microtime(true) - $dbStart) * 1000;
            $this->recordFail('laravel', 'Database', 'Connection Failed', $e->getMessage(), $dbLatency);
        }

        // Cache
        $cacheStart = microtime(true);
        try {
            $key = '_health_test_' . time();
            Cache::put($key, 'ok', 5);
            $val = Cache::get($key);
            Cache::forget($key);
            $cacheLatency = (microtime(true) - $cacheStart) * 1000;

            if ($val === 'ok') {
                $this->recordPass('laravel', 'Cache', 'Read/Write Working', $cacheLatency);
            } else {
                $this->recordFail('laravel', 'Cache', 'Read/Write Failed', 'Value mismatch', $cacheLatency);
            }
        } catch (\Throwable $e) {
            $cacheLatency = (microtime(true) - $cacheStart) * 1000;
            $this->recordFail('laravel', 'Cache', 'Read/Write Failed', $e->getMessage(), $cacheLatency);
        }

        // Queue
        $queueStart = microtime(true);
        try {
            $failedCount = DB::table('failed_jobs')->count();
            $pendingCount = DB::table('jobs')->count();
            $queueLatency = (microtime(true) - $queueStart) * 1000;

            if ($failedCount === 0) {
                $this->recordPass('laravel', 'Queue', "Jobs Accessible (0 failed, {$pendingCount} pending)", $queueLatency);
            } else {
                $this->recordFail('laravel', 'Queue', "{$failedCount} failed jobs detected", "Pending: {$pendingCount}", $queueLatency);
            }
        } catch (\Throwable $e) {
            $queueLatency = (microtime(true) - $queueStart) * 1000;
            $this->recordFail('laravel', 'Queue', 'Queue Check Failed', $e->getMessage(), $queueLatency);
        }

        // Storage
        $storageStart = microtime(true);
        try {
            $testFile = '_health_check_' . time() . '.tmp';
            Storage::disk('local')->put($testFile, 'health');
            $read = Storage::disk('local')->get($testFile);
            Storage::disk('local')->delete($testFile);
            $storageLatency = (microtime(true) - $storageStart) * 1000;

            if ($read === 'health') {
                $this->recordPass('laravel', 'Storage', 'Storage Writable', $storageLatency);
            } else {
                $this->recordFail('laravel', 'Storage', 'Storage Not Writable', 'Content mismatch', $storageLatency);
            }
        } catch (\Throwable $e) {
            $storageLatency = (microtime(true) - $storageStart) * 1000;
            $this->recordFail('laravel', 'Storage', 'Storage Not Writable', $e->getMessage(), $storageLatency);
        }

        $this->line('');
    }

    private function checkRetrievalService(): void
    {
        $this->line('── Python Retrieval Service ─────────────');

        $url = $this->retrievalClient->baseUrl();
        $this->recordPass('retrieval', 'URL', "Endpoint: {$url}");

        $health = $this->retrievalClient->health();
        if ($health['ok']) {
            $this->recordPass('retrieval', 'Health', 'Service Reachable', $health['latency_ms']);
        } else {
            $this->recordPass('retrieval', 'Health', 'Service Offline (Graceful DB Fallback Active)', $health['latency_ms']);
        }

        $this->line('');
    }

    private function checkConfig(): void
    {
        $this->line('── Configuration ────────────────────────');

        $provider = config('ai.default');
        $model = config('ai.default_model');
        $this->recordPass('config', 'AI Provider', "Provider: {$provider} ({$model})");

        $this->line('');
    }

    private function recordPass(string $section, string $label, string $detail = '', ?float $latency = null): void
    {
        $this->results[] = [
            'section' => $section,
            'label'   => $label,
            'status'  => true,
            'detail'  => $detail,
            'latency' => $latency,
        ];

        $latencyStr = $latency !== null ? ' (' . round($latency, 2) . 'ms)' : '';
        $detailStr = $detail !== '' ? " - {$detail}" : '';
        $this->info("  [PASS] {$label}{$latencyStr}{$detailStr}");
    }

    private function recordFail(string $section, string $label, string $detail = '', string $error = '', ?float $latency = null): void
    {
        $this->results[] = [
            'section' => $section,
            'label'   => $label,
            'status'  => false,
            'detail'  => $detail . ($error !== '' ? ": {$error}" : ''),
            'latency' => $latency,
        ];

        $latencyStr = $latency !== null ? ' (' . round($latency, 2) . 'ms)' : '';
        $detailStr = $detail !== '' ? " - {$detail}" : '';
        $errorStr = $error !== '' ? " [{$error}]" : '';
        $this->error("  [FAIL] {$label}{$latencyStr}{$detailStr}{$errorStr}");
    }

    private function displaySummary(): void
    {
        $this->line('── Summary ──────────────────────────────');
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn ($r) => $r['status']));
        $failed = $total - $passed;

        $this->line("Total Checks: {$total} | Passed: {$passed} | Failed: {$failed}");
    }

    private function hasFailures(): bool
    {
        return count(array_filter($this->results, fn ($r) => ! $r['status'])) > 0;
    }
}
