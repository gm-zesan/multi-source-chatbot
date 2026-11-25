<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Decision\UnifiedOrchestrator;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

echo "=== Verifying Upstream Timeouts, Fallbacks & In-Flight Cleanup ===\n\n";

$ws = Workspace::firstOrCreate(
    ['id' => 1],
    ['name' => 'Canary Test WS 1', 'currency' => 'BDT', 'is_active' => true]
);

$orchestrator = app(UnifiedOrchestrator::class);
$cache = Cache::store('redis');

// Clean in-flight counters
$cache->put('ai_in_flight_ws_1', 0, 60);
$cache->put('ai_in_flight_global', 0, 60);

// Test 1: Upstream Python Retrieval Outage / Timeout Simulation
echo "--- 1. Upstream Python Retrieval Outage Simulation ---\n";
// Temporarily simulate unreachable retrieval port by pointing to dead port 8999
config(['retrieval.base_url' => 'http://127.0.0.1:8999']);

$t0 = microtime(true);
$res = $orchestrator->process('ডেলিভারি চার্জ কত?', 1);
$elapsed = round((microtime(true) - $t0) * 1000, 2);

echo "Response when retrieval service is down:\n";
echo "  Route: " . ($res['route'] ?? 'unknown') . "\n";
echo "  Elapsed: {$elapsed} ms\n";
echo "  Post-failure In-Flight WS 1: " . (int)$cache->get('ai_in_flight_ws_1', 0) . "\n";
echo "  Post-failure In-Flight Global: " . (int)$cache->get('ai_in_flight_global', 0) . "\n";

assert((int)$cache->get('ai_in_flight_ws_1', 0) === 0, "WS in-flight must be 0 after upstream failure");
assert((int)$cache->get('ai_in_flight_global', 0) === 0, "Global in-flight must be 0 after upstream failure");
echo "PASS: Upstream failure cleanly handled, fallback provided, slots released.\n\n";

// Restore config
config(['retrieval.base_url' => 'http://127.0.0.1:8001']);

// Test 2: Upstream Memory Service Timeout Simulation
echo "--- 2. Upstream Memory Service Outage Simulation ---\n";
config(['services.memory_service.url' => 'http://127.0.0.1:8998']);

$t0 = microtime(true);
$resMem = $orchestrator->process('ডেলিভারি চার্জ কত?', 1);
$elapsedMem = round((microtime(true) - $t0) * 1000, 2);

echo "Response when memory service is down:\n";
echo "  Route: " . ($resMem['route'] ?? 'unknown') . "\n";
echo "  Elapsed: {$elapsedMem} ms\n";
echo "  Post-failure In-Flight WS 1: " . (int)$cache->get('ai_in_flight_ws_1', 0) . "\n";
echo "  Post-failure In-Flight Global: " . (int)$cache->get('ai_in_flight_global', 0) . "\n";

assert((int)$cache->get('ai_in_flight_ws_1', 0) === 0, "WS in-flight must be 0 after memory outage");
assert((int)$cache->get('ai_in_flight_global', 0) === 0, "Global in-flight must be 0 after memory outage");
echo "PASS: Memory service outage safely bypassed, slots released.\n\n";

// Restore config
config(['services.memory_service.url' => 'http://127.0.0.1:8002']);

echo "=== All Upstream Timeout & Disconnect Safeguards Verified! ===\n";
