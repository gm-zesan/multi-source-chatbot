<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Events\IncomingMessageReceived;
use App\Listeners\RunFAQEngineListener;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;

echo "=========================================================================================\n";
echo "🔬 PHASE 2F: GRANULAR LLM & AGENT PIPELINE LATENCY PROFILING (MULTI-RUN AUDIT)\n";
echo "=========================================================================================\n";

// Resolve target Workspace (Workspace 1 containing production indexed knowledge base)
$workspace = Workspace::find(1) ?? Workspace::firstOrCreate(
    ['slug' => 'main-workspace'],
    ['name' => 'Main Enterprise Workspace']
);

$channel = Channel::firstOrCreate(
    ['slug' => 'facebook'],
    ['name' => 'Facebook Messenger', 'is_active' => true]
);

$account = ChannelAccount::firstOrCreate(
    [
        'channel_id'   => $channel->id,
        'workspace_id' => $workspace->id,
        'external_id'  => 'profiling_fb_account_prod',
    ],
    [
        'name'         => 'Profiling Page',
        'access_token' => 'mock_token',
    ]
);

$conversation = Conversation::firstOrCreate(
    [
        'channel_account_id' => $account->id,
        'external_user_id'   => 'conv_profiling_test_customer_101',
    ],
    [
        'status'        => 'active',
        'customer_name' => 'Profiling Test Customer',
    ]
);

// Ensure conversation has realistic message history (2+ messages)
if ($conversation->messages()->count() < 2) {
    Message::create([
        'conversation_id'     => $conversation->id,
        'external_message_id' => 'msg_hist_prof_1',
        'direction'           => 'inbound',
        'body'                => 'Hello, I have a question about my account and subscription.',
        'status'              => 'delivered',
    ]);
    Message::create([
        'conversation_id'     => $conversation->id,
        'external_message_id' => 'msg_hist_prof_2',
        'direction'           => 'outbound',
        'body'                => 'Hello! Welcome to our support. How can I help you today?',
        'status'              => 'delivered',
    ]);
}

$testQueries = [
    "How do I update my payment method?",
    "Can I connect both Telegram and Facebook simultaneously?",
    "Can I change my plan?",
    "How do I view my invoices?",
    "How is my data encrypted?",
];

$faqSearch = app(FAQSearch::class);
$conversationService = app(ConversationService::class);
$customerSupportService = app(CustomerSupportService::class);
$listener = app(RunFAQEngineListener::class);

$runsData = [];

function formatTs(float $microtime): string {
    $sec = (int) $microtime;
    $ms = (int) (($microtime - $sec) * 1000);
    return date('H:i:s', $sec) . sprintf('.%03d', $ms);
}

for ($runIndex = 1; $runIndex <= count($testQueries); $runIndex++) {
    $query = $testQueries[$runIndex - 1];
    echo "\n-----------------------------------------------------------------------------------------\n";
    echo "▶️ RUN {$runIndex}/" . count($testQueries) . ": Query = \"{$query}\"\n";
    echo "-----------------------------------------------------------------------------------------\n";

    $eventsLog = [
        'steps' => [],
        'tools' => [],
    ];

    // Listen to Laravel AI SDK events
    Event::forget(StartingStep::class);
    Event::forget(StepCompleted::class);
    Event::forget(InvokingTool::class);
    Event::forget(ToolInvoked::class);

    Event::listen(StartingStep::class, function (StartingStep $e) use (&$eventsLog) {
        $eventsLog['steps'][$e->stepNumber]['start'] = microtime(true);
    });

    Event::listen(StepCompleted::class, function (StepCompleted $e) use (&$eventsLog) {
        $eventsLog['steps'][$e->stepNumber]['end'] = microtime(true);
        $eventsLog['steps'][$e->stepNumber]['provider_time_ms'] = $e->time;
        $eventsLog['steps'][$e->stepNumber]['is_final'] = $e->isFinalStep;
    });

    Event::listen(InvokingTool::class, function (InvokingTool $e) use (&$eventsLog) {
        $eventsLog['tools'][$e->toolInvocationId]['start'] = microtime(true);
        $eventsLog['tools'][$e->toolInvocationId]['tool_name'] = $e->tool->name();
    });

    Event::listen(ToolInvoked::class, function (ToolInvoked $e) use (&$eventsLog) {
        $eventsLog['tools'][$e->toolInvocationId]['end'] = microtime(true);
        $eventsLog['tools'][$e->toolInvocationId]['execution_time_ms'] = $e->time;
    });

    $stageTimings = [];

    // --- FULL QUEUE JOB LIFECYCLE START ---
    $t_job_start = microtime(true);

    // Create incoming message record
    $inboundMessage = Message::create([
        'conversation_id'     => $conversation->id,
        'external_message_id' => 'msg_prof_inbound_' . $runIndex . '_' . time(),
        'direction'           => 'inbound',
        'body'                => $query,
        'status'              => 'delivered',
    ]);

    $incomingEvent = new IncomingMessageReceived(
        conversation: $conversation,
        message: $inboundMessage,
        account: $account,
        rawPayload: [
            'external_message_id' => $inboundMessage->external_message_id,
            'text'                => $query,
        ],
    );

    // Stage 1: Prompt Construction
    $t_prompt_start = microtime(true);
    $retrievalTool = new KnowledgeRetrievalTool(
        faqSearch: $faqSearch,
        workspaceId: $workspace->id,
    );
    $agent = new CustomerSupportAgent(
        conversation: $conversation,
        retrievalTool: $retrievalTool,
    );
    $instructions = $agent->instructions();
    $t_prompt_end = microtime(true);
    $stageTimings['prompt_construction'] = [
        'start' => $t_prompt_start,
        'end'   => $t_prompt_end,
        'ms'    => round(($t_prompt_end - $t_prompt_start) * 1000, 2),
    ];

    // Stage 2: Conversation History / DB Query
    $t_history_start = microtime(true);
    $messages = $agent->messages();
    $t_history_end = microtime(true);
    $stageTimings['history_db'] = [
        'start' => $t_history_start,
        'end'   => $t_history_end,
        'ms'    => round(($t_history_end - $t_history_start) * 1000, 2),
    ];

    // Stage 3, 4, 5, 6: Agent Prompting & Tool Execution
    $provider = config('ai.default', 'openrouter');
    $model = config('ai.default_model', 'openrouter/free');

    $t_prompting_start = microtime(true);
    $response = $agent->prompt($query, provider: $provider, model: $model);
    $replyText = (string) $response;
    $t_prompting_end = microtime(true);

    // Extract step timings
    $step0 = $eventsLog['steps'][0] ?? null;
    $step1 = $eventsLog['steps'][1] ?? null;
    $firstTool = !empty($eventsLog['tools']) ? reset($eventsLog['tools']) : null;

    $initialLlmMs = $step0['provider_time_ms'] ?? 0.0;
    $finalLlmMs = $step1['provider_time_ms'] ?? 0.0;
    $frozenRetrievalMs = $firstTool['execution_time_ms'] ?? 0.0;

    // Tool dispatch overhead
    $toolDispatchMs = 0.0;
    if ($firstTool && isset($firstTool['start'], $firstTool['end'])) {
        $totalToolWallMs = ($firstTool['end'] - $firstTool['start']) * 1000;
        $toolDispatchMs = max(0.0, round($totalToolWallMs - $frozenRetrievalMs, 2));
    }

    // If only 1 step occurred without tool calling:
    if ($finalLlmMs == 0.0 && $initialLlmMs > 0.0 && $frozenRetrievalMs == 0.0) {
        $finalLlmMs = $initialLlmMs;
        $initialLlmMs = 0.0;
    }

    $stageTimings['initial_llm'] = [
        'start' => $step0['start'] ?? $t_prompting_start,
        'end'   => $step0['end'] ?? ($step0['start'] ?? $t_prompting_start),
        'ms'    => round($initialLlmMs, 2),
    ];

    $stageTimings['tool_dispatch'] = [
        'start' => $firstTool['start'] ?? $t_prompting_start,
        'end'   => $firstTool['end'] ?? ($firstTool['start'] ?? $t_prompting_start),
        'ms'    => round($toolDispatchMs, 2),
    ];

    $stageTimings['frozen_retrieval'] = [
        'start' => $firstTool['start'] ?? $t_prompting_start,
        'end'   => $firstTool['end'] ?? ($firstTool['start'] ?? $t_prompting_start),
        'ms'    => round($frozenRetrievalMs, 2),
    ];

    $stageTimings['final_llm'] = [
        'start' => $step1['start'] ?? ($step0['end'] ?? $t_prompting_start),
        'end'   => $step1['end'] ?? $t_prompting_end,
        'ms'    => round($finalLlmMs, 2),
    ];

    // Stage 7: Outbound Provider & Persistence
    $t_out_start = microtime(true);
    $outboundMessage = $customerSupportService->saveOutboundReply(
        conversation: $conversation,
        replyText: $replyText,
        deliveryResponse: [
            'simulated_channel_ack' => true,
            'channel'               => 'facebook',
            'delivered_at'          => now()->toIso8601String(),
        ]
    );
    $t_out_end = microtime(true);
    $stageTimings['outbound_persistence'] = [
        'start' => $t_out_start,
        'end'   => $t_out_end,
        'ms'    => round(($t_out_end - $t_out_start) * 1000, 2),
    ];

    // Stage 8: Whole Job Lifecycle
    $t_job_end = microtime(true);
    $totalJobMs = round(($t_job_end - $t_job_start) * 1000, 2);
    $stageTimings['total_lifecycle'] = [
        'start' => $t_job_start,
        'end'   => $t_job_end,
        'ms'    => $totalJobMs,
    ];

    $runsData[] = $stageTimings;

    echo "  [Start: " . formatTs($stageTimings['prompt_construction']['start']) . " -> End: " . formatTs($stageTimings['prompt_construction']['end']) . "] Prompt Construction       : {$stageTimings['prompt_construction']['ms']} ms\n";
    echo "  [Start: " . formatTs($stageTimings['history_db']['start']) . " -> End: " . formatTs($stageTimings['history_db']['end']) . "] History / DB Query        : {$stageTimings['history_db']['ms']} ms\n";
    echo "  [Start: " . formatTs($stageTimings['initial_llm']['start']) . " -> End: " . formatTs($stageTimings['initial_llm']['end']) . "] Initial LLM Call (Step 0) : {$stageTimings['initial_llm']['ms']} ms\n";
    echo "  [Start: " . formatTs($stageTimings['tool_dispatch']['start']) . " -> End: " . formatTs($stageTimings['tool_dispatch']['end']) . "] Tool Dispatch Overhead    : {$stageTimings['tool_dispatch']['ms']} ms\n";
    echo "  [Start: " . formatTs($stageTimings['frozen_retrieval']['start']) . " -> End: " . formatTs($stageTimings['frozen_retrieval']['end']) . "] Frozen Retrieval (Python) : {$stageTimings['frozen_retrieval']['ms']} ms\n";
    echo "  [Start: " . formatTs($stageTimings['final_llm']['start']) . " -> End: " . formatTs($stageTimings['final_llm']['end']) . "] Final LLM Call (Step 1)   : {$stageTimings['final_llm']['ms']} ms\n";
    echo "  [Start: " . formatTs($stageTimings['outbound_persistence']['start']) . " -> End: " . formatTs($stageTimings['outbound_persistence']['end']) . "] Outbound + Persistence    : {$stageTimings['outbound_persistence']['ms']} ms\n";
    echo "  ───────────────────────────────────────────────────────────────────────────────────────\n";
    echo "  [Start: " . formatTs($t_job_start) . " -> End: " . formatTs($t_job_end) . "] ⏱️ Total Queue Job Lifecycle : {$totalJobMs} ms\n";
    echo "  💬 Agent Response Snippet   : \"" . mb_substr(trim($replyText), 0, 80) . "...\"\n";
}

// Calculate Statistical Aggregates
function calcStageStats(array $runsData, string $stageKey): array {
    $vals = array_map(fn($r) => $r[$stageKey]['ms'], $runsData);
    sort($vals);
    $count = count($vals);
    $min = $vals[0];
    $max = $vals[$count - 1];
    $mean = array_sum($vals) / $count;
    $median = ($count % 2 === 0)
        ? ($vals[$count / 2 - 1] + $vals[$count / 2]) / 2
        : $vals[intdiv($count, 2)];
    return [
        'min'    => round($min, 2),
        'median' => round($median, 2),
        'max'    => round($max, 2),
        'mean'   => round($mean, 2),
    ];
}

$promptStats    = calcStageStats($runsData, 'prompt_construction');
$historyStats   = calcStageStats($runsData, 'history_db');
$initialLlmStats= calcStageStats($runsData, 'initial_llm');
$dispatchStats  = calcStageStats($runsData, 'tool_dispatch');
$retrievalStats = calcStageStats($runsData, 'frozen_retrieval');
$finalLlmStats  = calcStageStats($runsData, 'final_llm');
$outboundStats  = calcStageStats($runsData, 'outbound_persistence');
$lifecycleStats = calcStageStats($runsData, 'total_lifecycle');

echo "\n=========================================================================================\n";
echo "📊 PHASE 2F COMPONENT-BY-COMPONENT LATENCY STATISTICAL SUMMARY (5 RUNS):\n";
echo "=========================================================================================\n";
printf("%-28s | %-10s | %-10s | %-10s | %-10s | %-12s\n", "Pipeline Stage", "Min (ms)", "Median", "Max (ms)", "Mean (ms)", "% of Total");
echo "-----------------------------------------------------------------------------------------\n";
printf("%-28s | %10.2f | %10.2f | %10.2f | %10.2f | %11.1f%%\n", "1. Prompt Construction", $promptStats['min'], $promptStats['median'], $promptStats['max'], $promptStats['mean'], ($promptStats['mean'] / $lifecycleStats['mean']) * 100);
printf("%-28s | %10.2f | %10.2f | %10.2f | %10.2f | %11.1f%%\n", "2. History / DB Query", $historyStats['min'], $historyStats['median'], $historyStats['max'], $historyStats['mean'], ($historyStats['mean'] / $lifecycleStats['mean']) * 100);
printf("%-28s | %10.2f | %10.2f | %10.2f | %10.2f | %11.1f%%\n", "3. Initial LLM Call (Step 0)", $initialLlmStats['min'], $initialLlmStats['median'], $initialLlmStats['max'], $initialLlmStats['mean'], ($initialLlmStats['mean'] / $lifecycleStats['mean']) * 100);
printf("%-28s | %10.2f | %10.2f | %10.2f | %10.2f | %11.1f%%\n", "4. Tool Dispatch Overhead", $dispatchStats['min'], $dispatchStats['median'], $dispatchStats['max'], $dispatchStats['mean'], ($dispatchStats['mean'] / $lifecycleStats['mean']) * 100);
printf("%-28s | %10.2f | %10.2f | %10.2f | %10.2f | %11.1f%%\n", "5. Frozen Retrieval (Typesense)", $retrievalStats['min'], $retrievalStats['median'], $retrievalStats['max'], $retrievalStats['mean'], ($retrievalStats['mean'] / $lifecycleStats['mean']) * 100);
printf("%-28s | %10.2f | %10.2f | %10.2f | %10.2f | %11.1f%%\n", "6. Final LLM Call (Step 1)", $finalLlmStats['min'], $finalLlmStats['median'], $finalLlmStats['max'], $finalLlmStats['mean'], ($finalLlmStats['mean'] / $lifecycleStats['mean']) * 100);
printf("%-28s | %10.2f | %10.2f | %10.2f | %10.2f | %11.1f%%\n", "7. Outbound + Persistence", $outboundStats['min'], $outboundStats['median'], $outboundStats['max'], $outboundStats['mean'], ($outboundStats['mean'] / $lifecycleStats['mean']) * 100);
echo "-----------------------------------------------------------------------------------------\n";
printf("%-28s | %10.2f | %10.2f | %10.2f | %10.2f | %11.1f%%\n", "TOTAL QUEUE JOB LIFECYCLE", $lifecycleStats['min'], $lifecycleStats['median'], $lifecycleStats['max'], $lifecycleStats['mean'], 100.0);
echo "=========================================================================================\n\n";

echo "Phase 2F Latency Profile\n\n";
printf("Prompt Construction:       %8.2f ms\n", $promptStats['median']);
printf("History/DB:                %8.2f ms\n", $historyStats['median']);
printf("Initial LLM Call:          %8.2f ms\n", $initialLlmStats['median']);
printf("Tool Dispatch:             %8.2f ms\n", $dispatchStats['median']);
printf("Frozen Retrieval:          %8.2f ms\n", $retrievalStats['median']);
printf("Final LLM Call:            %8.2f ms\n", $finalLlmStats['median']);
printf("Outbound + Persistence:    %8.2f ms\n", $outboundStats['median']);
echo "--------------------------------\n";
printf("Total:                     %8.2f ms\n", $lifecycleStats['median']);
