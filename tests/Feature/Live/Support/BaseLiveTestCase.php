<?php

declare(strict_types=1);

namespace Tests\Feature\Live\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

abstract class BaseLiveTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Explicit Environment Gate
        $isLiveEnabled = env('LIVE_INTEGRATION_TEST', false) === true
            || env('LIVE_INTEGRATION_TEST', 'false') === 'true'
            || ($_SERVER['LIVE_INTEGRATION_TEST'] ?? 'false') === 'true';

        if (!$isLiveEnabled) {
            $this->markTestSkipped('Live integration tests skipped. Set LIVE_INTEGRATION_TEST=true to execute.');
        }

        // 2. Preflight Health Check: Python FastAPI Service (:8001)
        try {
            $pyHealth = Http::timeout(2)->get('http://127.0.0.1:8001/health');
            if (!$pyHealth->successful() || ($pyHealth->json('status') !== 'ok')) {
                $this->markTestSkipped('Python AI Service on 127.0.0.1:8001 is unreachable or not healthy.');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Python AI Service on 127.0.0.1:8001 is offline: ' . $e->getMessage());
        }

        // 3. Preflight Health Check: Typesense Daemon (:8108)
        try {
            $tsHealth = Http::timeout(2)->get('http://127.0.0.1:8108/health');
            if (!$tsHealth->successful() || ($tsHealth->json('ok') !== true)) {
                $this->markTestSkipped('Typesense Daemon on 127.0.0.1:8108 is unreachable or not healthy.');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Typesense Daemon on 127.0.0.1:8108 is offline: ' . $e->getMessage());
        }
    }
}
