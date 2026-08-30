<?php

declare(strict_types=1);

/**
 * =============================================================================
 * LATENCY BREAKDOWN PROFILER FOR: "notun akaunt kivabe khulbo?"
 * =============================================================================
 * Measures high-resolution millisecond latency for each isolated phase:
 *  Phase 1: Routing (HybridRouter heuristic vs LLM)
 *  Phase 2: Contextual Query Rewriting (ContextualQueryBuilder)
 *  Phase 3: Retrieval Pipeline:
 *           - Query Embedding (FastAPI /embed)
 *           - Typesense Hybrid Search
 *           - Query Expansion (if triggered)
 *  Phase 4: LLM Generation (KnowledgeSupportAgent prompt)
 *  Phase 5: Full E2E handleQuery Execution
 * =============================================================================
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Agents\KnowledgeSupportAgent;
use App\AI\Routing\HybridRouter;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\ContextualQueryBuilder;
use App\Services\AI\CustomerSupportService;
use App\Services\FAQ\FAQSearch;
use Illuminate\Support\Facades\Http;

$query = 'notun akaunt kivabe khulbo?';
$workspaceId = 1;

echo "=================================================================================================\n";
echo "⏱️ ISOLATED LATENCY PROFILER FOR: \"{$query}\"\n";
echo "=================================================================================================\n\n";

$ws = Workspace::find($workspaceId);
$channel = Channel::first() ?? Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
$acc = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws->id, 'channel_id' => $channel->id],
    ['name' => 'Acc Profile', 'external_id' => 'acc_prof_' . uniqid(), 'access_token' => 'tok_prof', 'is_active' => true]
);
$conv = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'prof_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);

// ─────────────────────────────────────────────────────────────────────────────
// 1. ROUTING PROFILING
// ─────────────────────────────────────────────────────────────────────────────
$router = app(HybridRouter::class);
$t0 = microtime(true);
$routingResult = $router->route($query, $conv);
$tRoutingMs = round((microtime(true) - $t0) * 1000, 2);

echo "1️⃣  ROUTING (HybridRouter):\n";
echo "    - Route:        {$routingResult->route->value}\n";
echo "    - Confidence:   {$routingResult->confidence}\n";
echo "    - Signals:      " . json_encode($routingResult->signals) . "\n";
echo "    - Latency:      {$tRoutingMs} ms\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 2. CONTEXTUAL QUERY REWRITING PROFILING
// ─────────────────────────────────────────────────────────────────────────────
$builder = app(ContextualQueryBuilder::class);
$t0 = microtime(true);
$searchQuery = $builder->buildContextualQuery($query, $conv);
$tRewriteMs = round((microtime(true) - $t0) * 1000, 2);

echo "2️⃣  CONTEXTUAL QUERY REWRITING (ContextualQueryBuilder):\n";
echo "    - Raw Query:    {$query}\n";
echo "    - Search Query: {$searchQuery}\n";
echo "    - Latency:      {$tRewriteMs} ms\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 3. RETRIEVAL PIPELINE ISOLATED BREAKDOWN
// ─────────────────────────────────────────────────────────────────────────────
$faqSearch = app(FAQSearch::class);

// 3a. FastAPI Embedding Service Call
$embedUrl = config('services.embedding.url', 'http://127.0.0.1:8001') . '/embed';
$t0 = microtime(true);
try {
    $embedRes = Http::timeout(5)->post($embedUrl, ['texts' => [$searchQuery]]);
    $tEmbedMs = round((microtime(true) - $t0) * 1000, 2);
    $embedStatus = $embedRes->status();
} catch (\Throwable $e) {
    $tEmbedMs = round((microtime(true) - $t0) * 1000, 2);
    $embedStatus = 'Error: ' . $e->getMessage();
}

// 3b. Full FAQSearch (Includes Embedding + Typesense Hybrid Search + Optional Expansion)
$t0 = microtime(true);
$retrievalHits = $faqSearch->search($searchQuery, perPage: 5, workspaceId: $workspaceId);
$tSearchMs = round((microtime(true) - $t0) * 1000, 2);

echo "3️⃣  RETRIEVAL PIPELINE:\n";
echo "    - FastEmbed (/embed): {$tEmbedMs} ms (Status: {$embedStatus})\n";
echo "    - Total FAQSearch:   {$tSearchMs} ms\n";
echo "    - Hits Found:        " . $retrievalHits->count() . "\n";
foreach ($retrievalHits as $idx => $hit) {
    $n = $idx + 1;
    echo "      #{$n}: {$hit->faq?->question} (Score: {$hit->finalScore})\n";
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// 4. LLM GENERATION PROFILING (KnowledgeSupportAgent)
// ─────────────────────────────────────────────────────────────────────────────
$provider = config('ai.default', 'deepseek');
$model = config('ai.default_model', 'deepseek-chat');

$groundedHits = $retrievalHits->filter(fn ($h) => $h->finalScore >= 0.45);
$agent = new KnowledgeSupportAgent(
    conversation: $conv,
    retrievedKnowledge: $groundedHits,
);

$t0 = microtime(true);
try {
    $llmReply = (string) $agent->prompt($query, provider: $provider, model: $model);
    $tLlmMs = round((microtime(true) - $t0) * 1000, 2);
    $llmStatus = 'Success';
} catch (\Throwable $e) {
    $tLlmMs = round((microtime(true) - $t0) * 1000, 2);
    $llmStatus = 'Exception: ' . $e->getMessage();
    $llmReply = 'N/A';
}

echo "4️⃣  LLM GENERATION (KnowledgeSupportAgent):\n";
echo "    - Provider / Model:  {$provider} / {$model}\n";
echo "    - Status:            {$llmStatus}\n";
echo "    - LLM Latency:       {$tLlmMs} ms\n";
echo "    - Output (Sample):   " . mb_substr(str_replace("\n", " ", $llmReply), 0, 100) . "...\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 5. FULL END-TO-END PIPELINE (CustomerSupportService->handleQuery)
// ─────────────────────────────────────────────────────────────────────────────
$service = app(CustomerSupportService::class);
$convE2E = Conversation::create(['channel_account_id' => $acc->id, 'external_user_id' => 'prof_e2e_' . uniqid(), 'status' => 'active', 'last_direction' => 'inbound']);

$t0 = microtime(true);
$e2eResult = $service->handleQuery($query, $workspaceId, $convE2E);
$tTotalE2eMs = round((microtime(true) - $t0) * 1000, 2);

echo "5️⃣  FULL PIPELINE SUMMARY (CustomerSupportService->handleQuery):\n";
echo "    - Total E2E Latency: {$tTotalE2eMs} ms\n";
echo "    - Breakdown Share:\n";
printf("      • Routing:         %8.2f ms (%5.1f%%)\n", $tRoutingMs, ($tRoutingMs / max(1, $tTotalE2eMs)) * 100);
printf("      • Context Rewriting:%7.2f ms (%5.1f%%)\n", $tRewriteMs, ($tRewriteMs / max(1, $tTotalE2eMs)) * 100);
printf("      • Retrieval:       %8.2f ms (%5.1f%%)\n", $tSearchMs, ($tSearchMs / max(1, $tTotalE2eMs)) * 100);
printf("      • LLM Generation:  %8.2f ms (%5.1f%%)\n", $tLlmMs, ($tLlmMs / max(1, $tTotalE2eMs)) * 100);
$otherMs = max(0, $tTotalE2eMs - ($tRoutingMs + $tRewriteMs + $tSearchMs + $tLlmMs));
printf("      • Framework/Other: %8.2f ms (%5.1f%%)\n", $otherMs, ($otherMs / max(1, $tTotalE2eMs)) * 100);
echo "=================================================================================================\n";
