<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;

echo "=========================================================================================\n";
echo "🔬 PHASE 2G.1: OPENROUTER / LLM API TELEMETRY & VARIANCE AUDIT\n";
echo "=========================================================================================\n";

$workspace = Workspace::find(1) ?? Workspace::first();
$faqSearch = app(FAQSearch::class);

$account = \App\Models\ChannelAccount::where('workspace_id', $workspace->id)->first();
$conversation = Conversation::firstOrCreate(
    [
        'channel_account_id' => $account?->id ?? 1,
        'external_user_id'   => 'conv_telemetry_audit_101',
    ],
    [
        'status'        => 'active',
        'customer_name' => 'Telemetry Audit User',
    ]
);

if ($conversation->messages()->count() < 2) {
    Message::create([
        'conversation_id'     => $conversation->id,
        'external_message_id' => 'msg_audit_hist_1',
        'direction'           => 'inbound',
        'body'                => 'Hello! I need help with my account.',
        'status'              => 'delivered',
    ]);
    Message::create([
        'conversation_id'     => $conversation->id,
        'external_message_id' => 'msg_audit_hist_2',
        'direction'           => 'outbound',
        'body'                => 'Hello! Welcome. How can I assist you today?',
        'status'              => 'delivered',
    ]);
}

$testQueries = [
    "How do I update my payment method?",
    "Can I connect both Telegram and Facebook simultaneously?",
    "Can I change my plan?",
    "How do I view my invoices?",
    "How is my data encrypted?",
    "What plans are available?",
];

$telemetryAudit = [];

for ($i = 0; $i < count($testQueries); $i++) {
    $query = $testQueries[$i];
    echo "\n-----------------------------------------------------------------------------------------\n";
    echo "▶️ QUERY " . ($i + 1) . "/" . count($testQueries) . ": \"{$query}\"\n";
    echo "-----------------------------------------------------------------------------------------\n";

    $stepsData = [];

    Event::forget(StartingStep::class);
    Event::forget(StepCompleted::class);

    Event::listen(StepCompleted::class, function (StepCompleted $e) use (&$stepsData) {
        $resp = $e->response;
        $usage = $resp->usage;
        $meta = $resp->meta;
        $raw = method_exists($resp, 'raw') ? $resp->raw() : null;

        $stepsData[$e->stepNumber] = [
            'step'              => $e->stepNumber,
            'is_final'          => $e->isFinalStep,
            'wall_time_ms'      => round($e->time, 2),
            'requested_model'   => $e->model,
            'routed_model'      => $meta->model ?? $e->model,
            'provider'          => $meta->provider ?? 'unknown',
            'prompt_tokens'     => $usage->promptTokens ?? 0,
            'completion_tokens' => $usage->completionTokens ?? 0,
            'total_tokens'      => ($usage->promptTokens ?? 0) + ($usage->completionTokens ?? 0),
            'finish_reason'     => $resp->finishReason->value ?? 'unknown',
            'tool_calls_count'  => count($resp->toolCalls),
            'raw_id'            => $raw['id'] ?? null,
            'system_fingerprint'=> $raw['system_fingerprint'] ?? null,
        ];
    });

    $retrievalTool = new KnowledgeRetrievalTool(
        faqSearch: $faqSearch,
        workspaceId: $workspace->id,
    );

    $agent = new CustomerSupportAgent(
        conversation: $conversation,
        retrievalTool: $retrievalTool,
    );

    $provider = config('ai.default', 'openrouter');
    $model = config('ai.default_model', 'openrouter/free');

    $t0 = microtime(true);
    $response = $agent->prompt($query, provider: $provider, model: $model);
    $totalWallMs = round((microtime(true) - $t0) * 1000, 2);

    $telemetryAudit[] = [
        'query'         => $query,
        'total_wall_ms' => $totalWallMs,
        'steps'         => $stepsData,
    ];

    foreach ($stepsData as $sNum => $s) {
        $stepLabel = $sNum === 0 ? "Step 0 (Initial Tool Call Decision)" : "Step 1 (Final Grounded Generation)";
        echo "  [{$stepLabel}]\n";
        echo "    • Routed Model     : {$s['routed_model']} (Requested: {$s['requested_model']})\n";
        echo "    • Latency          : {$s['wall_time_ms']} ms\n";
        echo "    • Prompt Tokens    : {$s['prompt_tokens']}\n";
        echo "    • Completion Tokens: {$s['completion_tokens']} (Total: {$s['total_tokens']})\n";
        echo "    • Finish Reason    : {$s['finish_reason']}\n";
        echo "    • Tool Calls       : {$s['tool_calls_count']}\n";
    }
    echo "  ⏱️ Combined LLM Wall Time : " . array_sum(array_column($stepsData, 'wall_time_ms')) . " ms (Total Pipeline: {$totalWallMs} ms)\n";
}

echo "\n=========================================================================================\n";
echo "📊 PHASE 2G.1 OPENROUTER TELEMETRY SUMMARY MATRIX\n";
echo "=========================================================================================\n";
printf("%-30s | %-6s | %-32s | %-8s | %-8s | %-10s | %-12s\n", "Query", "Step", "Routed Model", "Prompt", "Compl.", "Latency", "Finish");
echo "---------------------------------------------------------------------------------------------------------------------\n";

foreach ($telemetryAudit as $audit) {
    $qSnippet = mb_substr($audit['query'], 0, 28);
    foreach ($audit['steps'] as $sNum => $s) {
        $modelSnippet = mb_substr($s['routed_model'], 0, 30);
        printf("%-30s | %-6s | %-32s | %8d | %8d | %8.2f ms | %-12s\n",
            $sNum === 0 ? $qSnippet : " ↳ (generation)",
            "Step " . $sNum,
            $modelSnippet,
            $s['prompt_tokens'],
            $s['completion_tokens'],
            $s['wall_time_ms'],
            $s['finish_reason']
        );
    }
    echo "---------------------------------------------------------------------------------------------------------------------\n";
}
