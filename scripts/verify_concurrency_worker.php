<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Decision\UnifiedOrchestrator;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;

$workerId = $argv[1] ?? 'worker_1';
$wsId = (int) ($argv[2] ?? 1);

// Ensure workspace has canary enabled
$ws = Workspace::find($wsId);
if (!$ws) {
    echo json_encode(['error' => 'Workspace not found']) . PHP_EOL;
    exit(1);
}

$orchestrator = app(UnifiedOrchestrator::class);
$tStart = microtime(true);

$query = 'ডেলিভারি চার্জ কত?';
$result = $orchestrator->process($query, $wsId);
$tEnd = microtime(true);
$durationMs = round(($tEnd - $tStart) * 1000, 2);

$response = [
    'worker_id'   => $workerId,
    'workspace_id'=> $wsId,
    'route'       => $result['route'] ?? 'unknown',
    'halt_reason' => $result['decision_metadata']['halt_reason'] ?? null,
    'answered'    => $result['answered'] ?? false,
    'start_epoch' => $tStart,
    'end_epoch'   => $tEnd,
    'duration_ms' => $durationMs,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE) . PHP_EOL;
