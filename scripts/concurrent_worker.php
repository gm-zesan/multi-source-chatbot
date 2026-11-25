<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Decision\UnifiedOrchestrator;
use App\Models\Conversation;
use App\Models\Workspace;

$rawInput = $argv[1] ?? '{}';
$params = json_decode($rawInput, true) ?: [];

$workerId = (int) ($params['worker_id'] ?? 1);
$workspaceId = (int) ($params['workspace_id'] ?? 1);
$query = (string) ($params['query'] ?? 'হ্যালো');
$channel = (string) ($params['channel'] ?? 'whatsapp');
$isCustomer = (bool) ($params['is_customer'] ?? true);

$tStart = microtime(true);
$orchestrator = app(UnifiedOrchestrator::class);

$result = [
    'worker_id'         => $workerId,
    'workspace_id'      => $workspaceId,
    'query'             => $query,
    'start_timestamp'   => $tStart,
    'end_timestamp'     => 0.0,
    'elapsed_ms'        => 0.0,
    'success'           => false,
    'route'             => 'unknown',
    'answered'          => false,
    'reply_length'      => 0,
    'prompt_tokens'     => 0,
    'completion_tokens' => 0,
    'total_tokens'      => 0,
    'provider'          => 'unknown',
    'model'             => 'unknown',
    'cost_usd'          => 0.0,
    'error'             => null,
];

try {
    $resp = $orchestrator->process(
        query: $query,
        workspaceId: $workspaceId,
        user: null,
        conversation: null,
        channelSlug: $channel,
        isCustomer: $isCustomer,
    );

    $tEnd = microtime(true);
    $elapsed = round(($tEnd - $tStart) * 1000, 2);

    $meta = $resp['decision_metadata'] ?? [];
    $rawLlm = $resp['raw_llm_response'] ?? [];

    $pTokens = (int) ($meta['prompt_tokens'] ?? $rawLlm['prompt_tokens'] ?? 0);
    $cTokens = (int) ($meta['completion_tokens'] ?? $rawLlm['completion_tokens'] ?? 0);
    $tTokens = (int) ($meta['total_tokens'] ?? $rawLlm['total_tokens'] ?? ($pTokens + $cTokens));

    // DeepSeek Chat pricing: $0.14 / 1M prompt, $0.28 / 1M completion
    $cost = ($pTokens * 0.14 / 1000000) + ($cTokens * 0.28 / 1000000);

    $result['end_timestamp']     = $tEnd;
    $result['elapsed_ms']        = $elapsed;
    $result['success']           = true;
    $result['route']             = (string) ($resp['route'] ?? 'unknown');
    $result['answered']          = (bool) ($resp['answered'] ?? false);
    $result['reply_length']      = mb_strlen((string) ($resp['reply'] ?? ''));
    $result['prompt_tokens']     = $pTokens;
    $result['completion_tokens'] = $cTokens;
    $result['total_tokens']      = $tTokens;
    $result['provider']          = (string) ($rawLlm['provider'] ?? 'deepseek');
    $result['model']             = (string) ($rawLlm['model'] ?? 'deepseek-chat');
    $result['cost_usd']          = round($cost, 7);
} catch (\Throwable $e) {
    $tEnd = microtime(true);
    $result['end_timestamp'] = $tEnd;
    $result['elapsed_ms']    = round(($tEnd - $tStart) * 1000, 2);
    $result['error']         = $e->getMessage();
}

echo json_encode($result);
