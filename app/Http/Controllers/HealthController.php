<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NLP\Embedding\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
    ) {}

    /**
     * Comprehensive health check for monitoring/uptime checks.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'queue'    => $this->checkQueue(),
            'embedding'=> $this->checkEmbedding(),
            'cache'    => $this->checkCache(),
        ];

        $allHealthy = collect($checks)->every(fn ($c) => $c['healthy']);

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status'    => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'app'       => [
                'env'     => app()->environment(),
                'debug'   => app()->hasDebugModeEnabled(),
                'version' => '1.0.0',
            ],
            'checks' => $checks,
        ], $statusCode);
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            $latency = (microtime(true) - $start) * 1000;
            return ['healthy' => true, 'latency_ms' => round($latency, 2)];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $failedCount = DB::table('failed_jobs')->count();
            $pendingCount = DB::table('jobs')->count();

            $healthy = $failedCount < 10;

            return [
                'healthy'       => $healthy,
                'pending_jobs'  => $pendingCount,
                'failed_jobs'   => $failedCount,
            ];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkEmbedding(): array
    {
        try {
            $health = $this->embeddings->health();
            return [
                'healthy'    => $health['status'] === 'ok',
                'model'      => $health['model'],
                'latency_ms' => $health['latency_ms'],
            ];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = '_health_' . time();
            cache()->set($key, 1, 1);
            $value = cache()->get($key);
            cache()->forget($key);

            return ['healthy' => $value === 1];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }
}
