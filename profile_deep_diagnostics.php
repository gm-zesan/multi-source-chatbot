<?php

declare(strict_types=1);

/**
 * =============================================================================
 * DEEP LATENCY DIAGNOSTICS & PROFILING SUITE
 * =============================================================================
 * Dissects and measures the 3 main latency consumers:
 *   Phase A: Remote LLM Profiling (Model, TTFT, Tokens/sec, Total Duration)
 *   Phase B: Retrieval Pipeline Profiling (Embedding, Typesense, Query Expansion)
 *   Phase C: Framework & E2E Overhead Profiling (DB Transactions, Serialization, Logging)
 * =============================================================================
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Support\Facades\Http;

$query = 'notun akaunt kivabe khulbo?';
$workspaceId = 1;

echo "=================================================================================================\n";
echo "🔬 DEEP SYSTEM LATENCY DIAGNOSTICS FOR: \"{$query}\"\n";
echo "=================================================================================================\n\n";

$ws = Workspace::find($workspaceId);
$channel = Channel::first() ?? Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
$acc = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws->id, 'channel_id' => $channel->id],
    ['name' => 'Acc Diag', 'external_id' => 'acc_diag_' . uniqid(), 'access_token' => 'tok_diag', 'is_active' => true]
);
$conv = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'diag_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);

// ═════════════════════════════════════════════════════════════════════════════
// PHASE A: REMOTE LLM PROFILING
// ═════════════════════════════════════════════════════════════════════════════
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔴 PHASE A: REMOTE LLM PROFILING & TOKEN GENERATION ANALYSIS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$apiKey = config('ai.providers.openrouter.key', env('OPENROUTER_API_KEY'));
$defaultModel = config('ai.default_model', 'openrouter/free');

$systemPrompt = "You are a helpful customer support agent. Answer concisely in Bengali.";
$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $query],
];

$tLlmStart = microtime(true);
$ttftMs = null;
$fullContent = '';
$modelUsed = 'unknown';
$promptTokens = 0;
$completionTokens = 0;
$totalTokens = 0;

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'HTTP-Referer: http://localhost:8000',
    'X-Title: Chatbot Diagnostics',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $defaultModel,
    'messages' => $messages,
    'stream' => true,
    'stream_options' => ['include_usage' => true],
]));

$streamStart = microtime(true);

curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$ttftMs, $streamStart, &$fullContent, &$modelUsed, &$promptTokens, &$completionTokens, &$totalTokens) {
    $lines = explode("\n", $data);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line === 'data: [DONE]') {
            continue;
        }
        if (str_starts_with($line, 'data: ')) {
            $jsonStr = substr($line, 6);
            $parsed = json_decode($jsonStr, true);
            if ($parsed) {
                if (!empty($parsed['model'])) {
                    $modelUsed = $parsed['model'];
                }
                if (!empty($parsed['usage'])) {
                    $promptTokens = $parsed['usage']['prompt_tokens'] ?? $promptTokens;
                    $completionTokens = $parsed['usage']['completion_tokens'] ?? $completionTokens;
                    $totalTokens = $parsed['usage']['total_tokens'] ?? $totalTokens;
                }
                $delta = $parsed['choices'][0]['delta']['content'] ?? '';
                if ($delta !== '') {
                    if ($ttftMs === null) {
                        $ttftMs = round((microtime(true) - $streamStart) * 1000, 2);
                    }
                    $fullContent .= $delta;
                }
            }
        }
    }
    return strlen($data);
});

curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tLlmTotalMs = round((microtime(true) - $tLlmStart) * 1000, 2);
$generationTimeSec = max(0.001, ($tLlmTotalMs - ($ttftMs ?? 0)) / 1000);
$tokenSpeed = $completionTokens > 0 ? round($completionTokens / $generationTimeSec, 2) : 0;

echo "  • Requested Model:        {$defaultModel}\n";
echo "  • Actual Resolved Model:  {$modelUsed}\n";
echo "  • HTTP Status:            {$httpCode}\n";
echo "  • Time to First Token (TTFT): " . ($ttftMs ?? 'N/A') . " ms\n";
echo "  • Total Duration:         {$tLlmTotalMs} ms (" . round($tLlmTotalMs / 1000, 2) . " s)\n";
echo "  • Prompt Tokens:          {$promptTokens}\n";
echo "  • Completion Tokens:      {$completionTokens}\n";
echo "  • Generation Speed:       {$tokenSpeed} tokens/sec\n";
echo "  • Output Snippet:         " . mb_substr(str_replace("\n", " ", $fullContent), 0, 80) . "...\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// PHASE B: RETRIEVAL PIPELINE PROFILING
// ═════════════════════════════════════════════════════════════════════════════
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🟡 PHASE B: RETRIEVAL PIPELINE INTERNALS & EXPANSION PROFILING\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$retrievalClient = app(RetrievalClient::class);
$retrievalUrl = $retrievalClient->baseUrl();

// B1: FastAPI Health & Direct Network Latency
$t0 = microtime(true);
$healthRes = Http::timeout(2)->get("{$retrievalUrl}/health");
$tHealthMs = round((microtime(true) - $t0) * 1000, 2);

// B2: Full Python Search Call (Capturing Internal Telemetry)
$t0 = microtime(true);
$searchRes = Http::timeout(10)->post("{$retrievalUrl}/api/v1/search", [
    'query' => $query,
    'workspace_id' => $workspaceId,
    'top_k' => 5,
]);
$tSearchMs = round((microtime(true) - $t0) * 1000, 2);
$searchData = $searchRes->json();
$telemetry = $searchData['telemetry'] ?? [];

echo "  • Python Service URL:     {$retrievalUrl}\n";
echo "  • Network Ping (/health): {$tHealthMs} ms (Status: {$healthRes->status()})\n";
echo "  • Total Search Endpoint:  {$tSearchMs} ms\n";
echo "  • Telemetry Breakdown:\n";
echo "      - First-Pass Score:   " . ($telemetry['first_pass_score'] ?? 'N/A') . "\n";
echo "      - Expansion Triggered: " . (($telemetry['expansion_triggered'] ?? false) ? 'YES ⚠️ (Called LLM for Synonyms)' : 'NO (Fast-path)') . "\n";
if (!empty($telemetry['expanded_query'])) {
    echo "      - Expanded Query:     {$telemetry['expanded_query']}\n";
}
echo "      - Python Internal Ms: " . ($telemetry['latency_total_ms'] ?? 'N/A') . " ms\n";
echo "      - Returned Hits:      " . count($searchData['results'] ?? []) . "\n";
foreach (($searchData['results'] ?? []) as $i => $hit) {
    $idx = $i + 1;
    echo "         #{$idx}: {$hit['question']} (Score: {$hit['score']})\n";
}
echo "\n";

// ═════════════════════════════════════════════════════════════════════════════
// PHASE C: FRAMEWORK & E2E OVERHEAD PROFILING
// ═════════════════════════════════════════════════════════════════════════════
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🟠 PHASE C: FRAMEWORK, DATABASE & ORM OVERHEAD PROFILING\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// C1: DB History Load Latency
$t0 = microtime(true);
$messages = $conv->messages()->latest('id')->take(10)->get();
$tDbLoadMs = round((microtime(true) - $t0) * 1000, 2);

// C2: DB Outbound Message Save Latency
$t0 = microtime(true);
$savedMsg = $conv->messages()->create([
    'direction' => 'outbound',
    'type' => 'text',
    'body' => 'Test message body for timing',
]);
$tDbSaveMs = round((microtime(true) - $t0) * 1000, 2);

// C3: Full CustomerSupportService->handleQuery() run
$service = app(CustomerSupportService::class);
$convE2E = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'diag_e2e_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);

$t0 = microtime(true);
$resE2E = $service->handleQuery($query, $workspaceId, $convE2E);
$tE2ETotalMs = round((microtime(true) - $t0) * 1000, 2);

$routerMs = $resE2E['routing_telemetry']['router_latency_ms'] ?? 0.5;

echo "  • DB Conversation Load:   {$tDbLoadMs} ms\n";
echo "  • DB Message Persist:     {$tDbSaveMs} ms\n";
echo "  • Routing Execution:      {$routerMs} ms\n";
echo "  • Total E2E Execution:    {$tE2ETotalMs} ms\n";
echo "  • Complete Overhead Math:\n";
printf("      • Routing:            %8.2f ms (%5.1f%%)\n", $routerMs, ($routerMs / $tE2ETotalMs) * 100);
printf("      • Retrieval:          %8.2f ms (%5.1f%%)\n", $tSearchMs, ($tSearchMs / $tE2ETotalMs) * 100);
printf("      • LLM Prompt Call:    %8.2f ms (%5.1f%%)\n", $tLlmTotalMs, ($tLlmTotalMs / $tE2ETotalMs) * 100);
$residualOverhead = max(0, $tE2ETotalMs - ($routerMs + $tSearchMs + $tLlmTotalMs));
printf("      • Laravel Glue & I/O: %8.2f ms (%5.1f%%)\n", $residualOverhead, ($residualOverhead / $tE2ETotalMs) * 100);

echo "=================================================================================================\n";
