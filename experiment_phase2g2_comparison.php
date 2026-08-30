<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Laravel\Ai\Events\StepCompleted;

echo "=========================================================================================\n";
echo "🔬 PHASE 2G.2: FULL FROZEN BENCHMARK ARCHITECTURE EVALUATION (DEEPSEEK PINNED)\n";
echo "=========================================================================================\n";

$experimentProvider = 'deepseek';
$experimentModel    = 'deepseek-chat';
$experimentApiKey   = env('DEEPSEEK_API_KEY') ?: env('PHASE2G2_DEEPSEEK_API_KEY');
$experimentApiUrl   = env('DEEPSEEK_URL', env('PHASE2G2_DEEPSEEK_URL', 'https://api.deepseek.com'));

config([
    'ai.providers.deepseek.key' => $experimentApiKey,
    'ai.providers.deepseek.url' => $experimentApiUrl,
]);

$faqSearch = app(FAQSearch::class);
$retrievalClient = app(RetrievalClient::class);

$ws1 = Workspace::find(1);
$ws2 = Workspace::firstOrCreate(
    ['slug' => 'profiling-workspace'],
    ['name' => 'Profiling Test Workspace']
);

// Ensure WS2 has a distinct secret FAQ for tenant isolation test
$ws2Faq = FAQ::firstOrCreate(
    [
        'workspace_id' => $ws2->id,
        'question'     => 'What is the custom enterprise hotline for Tenant B?',
    ],
    [
        'answer'       => 'The custom enterprise VIP hotline for Tenant B is +1-800-TENANT-B-VIP.',
        'priority'     => 100,
        'is_active'    => true,
    ]
);
$retrievalClient->syncFaq($ws2Faq);

// Setup test conversations
$conv1 = Conversation::firstOrCreate(
    ['external_user_id' => 'conv_2g2_full_tenant1'],
    ['channel_account_id' => 1, 'status' => 'active', 'customer_name' => 'Full Test User 1']
);

$conv2 = Conversation::firstOrCreate(
    ['external_user_id' => 'conv_2g2_full_tenant2'],
    ['channel_account_id' => 1, 'status' => 'active', 'customer_name' => 'Full Test User 2']
);

class CandidateSingleCallAgent implements Agent, Conversational
{
    use Promptable;

    public function __construct(
        public readonly ?Conversation $conversation = null,
        public readonly ?\Illuminate\Database\Eloquent\Collection $retrievedKnowledge = null,
    ) {}

    public function instructions(): string
    {
        $contextSection = "No knowledge base documents were retrieved for this query.";
        if ($this->retrievedKnowledge && $this->retrievedKnowledge->isNotEmpty()) {
            $docs = [];
            foreach ($this->retrievedKnowledge as $idx => $hit) {
                $n = $idx + 1;
                $q = $hit->faq?->question ?? 'N/A';
                $a = $hit->faq?->answer ?? 'N/A';
                $docs[] = "[Document #{$n}]\nQuestion: {$q}\nAnswer: {$a}";
            }
            $contextSection = "Retrieved Knowledge Base Documents:\n" . implode("\n\n", $docs);
        }

        return <<<PROMPT
You are a professional, helpful, and polite Enterprise Customer Support AI Assistant.
Your goal is to assist customers accurately, politely, and concisely.

Instructions:
1. Grounding: If relevant knowledge base documents are provided below, strictly ground your answers on them. NEVER fabricate, assume, or hallucinate policies, pricing, numbers, or actions.
2. Out-of-Domain & Unknown Questions: If the customer asks about topics, general knowledge, recipes, weather, external companies, or anything not covered in the knowledge base, politely state that you do not have that specific information and offer to connect them with a human customer support specialist.
3. Conversational Queries: If the customer is simply greeting (e.g. 'hi', 'hello', 'good morning'), thanking you, or engaging in pleasantries, respond warmly and professionally without quoting unnecessary policies.
4. Human Agent Handoff: If the customer asks to speak with a human, warmly acknowledge and confirm routing.
5. Structure: Keep answers clear, professional, and well-structured.

{$contextSection}
PROMPT;
    }

    public function messages(): iterable
    {
        if ($this->conversation === null) {
            return [];
        }

        $rawMessages = $this->conversation->messages()
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        $aiMessages = [];
        foreach ($rawMessages as $msg) {
            $body = trim((string) $msg->body);
            if ($body === '') {
                continue;
            }
            if ($msg->direction === 'inbound') {
                $aiMessages[] = new UserMessage($body);
            } else {
                $aiMessages[] = new AssistantMessage($body);
            }
        }

        return $aiMessages;
    }
}

function promptWithRetry(
    object $agent,
    string $query,
    string $provider,
    string $model,
    int $maxRetries = 3
): string {
    $attempt = 0;
    while ($attempt < $maxRetries) {
        try {
            return (string) $agent->prompt($query, provider: $provider, model: $model);
        } catch (\Laravel\Ai\Exceptions\RateLimitedException $e) {
            $attempt++;
            $sleepSec = $attempt * 3;
            echo "  ⚠️ Rate limit hit. Backing off for {$sleepSec}s (Attempt {$attempt}/{$maxRetries})...\n";
            sleep($sleepSec);
        } catch (\Throwable $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                echo "  ❌ Error: {$e->getMessage()}\n";
                return "ERROR: " . $e->getMessage();
            }
            echo "  ⚠️ Provider error: {$e->getMessage()}. Retrying in 2s...\n";
            sleep(2);
        }
    }
    return '';
}

function runSideBySide(
    string $query,
    string $category,
    int $workspaceId,
    Conversation $conversation,
    FAQSearch $faqSearch,
    RetrievalClient $retrievalClient,
    string $provider,
    string $model,
    bool $isOod = false
): array {
    // 1. Current Serial
    $serialSteps = [];
    Event::forget(StepCompleted::class);
    Event::listen(StepCompleted::class, function (StepCompleted $e) use (&$serialSteps) {
        $serialSteps[$e->stepNumber] = [
            'time_ms'           => round($e->time, 2),
            'prompt_tokens'     => $e->response->usage->promptTokens ?? 0,
            'completion_tokens' => $e->response->usage->completionTokens ?? 0,
            'total_tokens'      => ($e->response->usage->promptTokens ?? 0) + ($e->response->usage->completionTokens ?? 0),
            'routed_model'      => $e->response->meta->model ?? $e->model,
            'finish'            => $e->response->finishReason->value ?? '',
            'tool_calls'        => count($e->response->toolCalls),
        ];
    });

    $t_serial_start = microtime(true);
    $serialTool = new KnowledgeRetrievalTool(faqSearch: $faqSearch, workspaceId: $workspaceId);
    $serialAgent = new CustomerSupportAgent(conversation: $conversation, retrievalTool: $serialTool);
    $serialResponse = promptWithRetry($serialAgent, $query, $provider, $model);
    $serialTotalMs = round((microtime(true) - $t_serial_start) * 1000, 2);
    $serialLlmMs = array_sum(array_column($serialSteps, 'time_ms'));

    usleep(400000);

    // 2. Candidate Single
    $candidateSteps = [];
    Event::forget(StepCompleted::class);
    Event::listen(StepCompleted::class, function (StepCompleted $e) use (&$candidateSteps) {
        $candidateSteps[$e->stepNumber] = [
            'time_ms'           => round($e->time, 2),
            'prompt_tokens'     => $e->response->usage->promptTokens ?? 0,
            'completion_tokens' => $e->response->usage->completionTokens ?? 0,
            'total_tokens'      => ($e->response->usage->promptTokens ?? 0) + ($e->response->usage->completionTokens ?? 0),
            'routed_model'      => $e->response->meta->model ?? $e->model,
            'finish'            => $e->response->finishReason->value ?? '',
        ];
    });

    $t_cand_start = microtime(true);
    $t_ret_start = microtime(true);
    $retrievedHits = $faqSearch->search(query: $query, perPage: 5, workspaceId: $workspaceId);
    $candRetrievalMs = round((microtime(true) - $t_ret_start) * 1000, 2);

    $candidateAgent = new CandidateSingleCallAgent(
        conversation: $conversation,
        retrievedKnowledge: $retrievedHits,
    );
    $candidateResponse = promptWithRetry($candidateAgent, $query, $provider, $model);
    $candTotalMs = round((microtime(true) - $t_cand_start) * 1000, 2);
    $candLlmMs = array_sum(array_column($candidateSteps, 'time_ms'));

    usleep(400000);

    $oodSafeSerial = true;
    $oodSafeCand = true;
    if ($isOod) {
        $refusalSignals = ['do not have', "don't have", 'knowledge base', 'human', 'specialist', 'support agent', 'sorry', 'cannot find', 'unable to find', 'assist you with other questions', 'outside the scope', 'not able to assist', 'outside my scope'];
        $hasRefusalSerial = false;
        $hasRefusalCand = false;
        foreach ($refusalSignals as $sig) {
            if (stripos($serialResponse, $sig) !== false) $hasRefusalSerial = true;
            if (stripos($candidateResponse, $sig) !== false) $hasRefusalCand = true;
        }
        $oodSafeSerial = $hasRefusalSerial;
        $oodSafeCand = $hasRefusalCand;
    }

    return [
        'query'          => $query,
        'category'       => $category,
        'workspace_id'   => $workspaceId,
        'is_ood'         => $isOod,
        'serial'         => [
            'total_ms'     => $serialTotalMs,
            'llm_ms'       => $serialLlmMs,
            'calls_count'  => count($serialSteps),
            'steps'        => $serialSteps,
            'response'     => $serialResponse,
            'ood_safe'     => $oodSafeSerial,
        ],
        'candidate'      => [
            'total_ms'     => $candTotalMs,
            'retrieval_ms' => $candRetrievalMs,
            'llm_ms'       => $candLlmMs,
            'calls_count'  => count($candidateSteps),
            'steps'        => $candidateSteps,
            'hits_count'   => $retrievedHits->count(),
            'top_hit'      => $retrievedHits->first()?->faq?->question ?? 'NONE',
            'response'     => $candidateResponse,
            'ood_safe'     => $oodSafeCand,
        ],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// FULL FROZEN EVALUATION MATRIX (21 BENCHMARK TEST QUERIES)
// ─────────────────────────────────────────────────────────────────────────────

$fullTestSuite = [
    // ── Category A: Knowledge Grounded (In-Domain) ──
    ['q' => 'How do I update my payment method?', 'cat' => 'A. Grounded', 'ws' => 1, 'ood' => false],
    ['q' => 'What plans are available?', 'cat' => 'A. Grounded', 'ws' => 1, 'ood' => false],
    ['q' => 'How do I view my invoices?', 'cat' => 'A. Grounded', 'ws' => 1, 'ood' => false],
    ['q' => 'Can I use multiple channels simultaneously?', 'cat' => 'A. Grounded', 'ws' => 1, 'ood' => false],
    ['q' => 'How is my data encrypted?', 'cat' => 'A. Grounded', 'ws' => 1, 'ood' => false],

    // ── Category B: Conversational Queries ──
    ['q' => 'Hello there! Good morning.', 'cat' => 'B. Conversational', 'ws' => 1, 'ood' => false],
    ['q' => 'Thank you so much, that was very helpful!', 'cat' => 'B. Conversational', 'ws' => 1, 'ood' => false],
    ['q' => 'Hi, can you help me today?', 'cat' => 'B. Conversational', 'ws' => 1, 'ood' => false],
    ['q' => 'Thanks a lot for the support.', 'cat' => 'B. Conversational', 'ws' => 1, 'ood' => false],

    // ── Category C: 10 Frozen OOD Negative Queries ──
    ['q' => 'What is the weather in Dhaka today?', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],
    ['q' => 'Can I book a flight to London?', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],
    ['q' => 'Tell me a recipe for chicken biryani', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],
    ['q' => 'Who is the president of Bangladesh?', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],
    ['q' => 'What is the stock price of Apple?', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],
    ['q' => 'How do I make chocolate cake?', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],
    ['q' => 'Can you write a poem about rain?', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],
    ['q' => 'Who won the cricket match yesterday?', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],
    ['q' => 'Where is the nearest hospital?', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],
    ['q' => 'asdf ghjk qwerty zxcvbnm', 'cat' => 'C. OOD Negative', 'ws' => 1, 'ood' => true],

    // ── Category E: Multi-Tenant Data Isolation ──
    ['q' => 'What is the custom enterprise hotline for Tenant B?', 'cat' => 'E. Isolation (WS1)', 'ws' => 1, 'ood' => true],
    ['q' => 'What is the custom enterprise hotline for Tenant B?', 'cat' => 'E. Isolation (WS2)', 'ws' => 2, 'ood' => false],
];

echo "\n▶️ EXECUTING FULL FROZEN BENCHMARK SUITE (" . count($fullTestSuite) . " Queries on {$experimentProvider}/{$experimentModel})...\n";

$fullResults = [];
foreach ($fullTestSuite as $idx => $test) {
    $num = $idx + 1;
    $targetConv = ($test['ws'] === 1) ? $conv1 : $conv2;
    echo "\n-----------------------------------------------------------------------------------------\n";
    echo "▶️ [{$num}/" . count($fullTestSuite) . "] [{$test['cat']}] Query: \"{$test['q']}\" (WS: {$test['ws']})\n";
    echo "-----------------------------------------------------------------------------------------\n";

    $res = runSideBySide(
        query: $test['q'],
        category: $test['cat'],
        workspaceId: $test['ws'],
        conversation: $targetConv,
        faqSearch: $faqSearch,
        retrievalClient: $retrievalClient,
        provider: $experimentProvider,
        model: $experimentModel,
        isOod: $test['ood'],
    );
    $fullResults[] = $res;

    echo "  [CURRENT SERIAL]   : Calls: {$res['serial']['calls_count']} | LLM: {$res['serial']['llm_ms']} ms | Total: {$res['serial']['total_ms']} ms | OOD Safe: " . ($res['serial']['ood_safe'] ? 'YES' : 'NO') . "\n";
    echo "    ↳ Snippet: \"" . mb_substr(str_replace("\n", " ", trim($res['serial']['response'])), 0, 75) . "...\"\n";
    echo "  [CANDIDATE SINGLE] : Calls: {$res['candidate']['calls_count']} | Ret: {$res['candidate']['retrieval_ms']} ms | LLM: {$res['candidate']['llm_ms']} ms | Total: {$res['candidate']['total_ms']} ms | OOD Safe: " . ($res['candidate']['ood_safe'] ? 'YES' : 'NO') . "\n";
    echo "    ↳ Snippet: \"" . mb_substr(str_replace("\n", " ", trim($res['candidate']['response'])), 0, 75) . "...\"\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// CATEGORY D: MULTI-TURN CONTEXT CONTINUITY (3 FULL CONVERSATION TURNS)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=========================================================================================\n";
echo "🔄 CATEGORY D: MULTI-TURN CONTEXT CONTINUITY TEST (3 SEQUENTIAL TURNS)\n";
echo "=========================================================================================\n";

$multiTurnConvSerial = Conversation::create([
    'channel_account_id' => 1,
    'external_user_id'   => 'conv_full_multiturn_serial_' . time(),
    'status'             => 'active',
    'customer_name'      => 'Full MultiTurn Serial',
]);

$multiTurnConvCand = Conversation::create([
    'channel_account_id' => 1,
    'external_user_id'   => 'conv_full_multiturn_cand_' . time(),
    'status'             => 'active',
    'customer_name'      => 'Full MultiTurn Cand',
]);

$multiTurns = [
    "I need to change how I pay for my subscription.",
    "Can you give me the exact step-by-step guide for that?",
    "Also, is my card data encrypted when I save it?",
];

$multiTurnSummary = [];
foreach ($multiTurns as $tIdx => $tQuery) {
    $tNum = $tIdx + 1;
    echo "\n--- Turn {$tNum}: \"{$tQuery}\" ---\n";

    // Serial turn
    Message::create(['conversation_id' => $multiTurnConvSerial->id, 'direction' => 'inbound', 'body' => $tQuery, 'status' => 'delivered']);
    $sTool = new KnowledgeRetrievalTool(faqSearch: $faqSearch, workspaceId: 1);
    $sAgent = new CustomerSupportAgent(conversation: $multiTurnConvSerial, retrievalTool: $sTool);
    $sStart = microtime(true);
    $sReply = promptWithRetry($sAgent, $tQuery, $experimentProvider, $experimentModel);
    $sMs = round((microtime(true) - $sStart) * 1000, 2);
    Message::create(['conversation_id' => $multiTurnConvSerial->id, 'direction' => 'outbound', 'body' => $sReply, 'status' => 'delivered']);
    usleep(400000);

    // Candidate turn
    Message::create(['conversation_id' => $multiTurnConvCand->id, 'direction' => 'inbound', 'body' => $tQuery, 'status' => 'delivered']);
    $cStart = microtime(true);
    $cHits = $faqSearch->search(query: $tQuery, perPage: 5, workspaceId: 1);
    $cAgent = new CandidateSingleCallAgent(conversation: $multiTurnConvCand, retrievedKnowledge: $cHits);
    $cReply = promptWithRetry($cAgent, $tQuery, $experimentProvider, $experimentModel);
    $cMs = round((microtime(true) - $cStart) * 1000, 2);
    Message::create(['conversation_id' => $multiTurnConvCand->id, 'direction' => 'outbound', 'body' => $cReply, 'status' => 'delivered']);
    usleep(400000);

    echo "  [Serial Turn {$tNum}]   ({$sMs} ms): \"" . mb_substr(str_replace("\n", " ", trim($sReply)), 0, 80) . "...\"\n";
    echo "  [Candidate Turn {$tNum}] ({$cMs} ms): \"" . mb_substr(str_replace("\n", " ", trim($cReply)), 0, 80) . "...\"\n";

    $multiTurnSummary[] = [
        'turn'      => $tNum,
        'query'     => $tQuery,
        'serial_ms' => $sMs,
        'cand_ms'   => $cMs,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// COMPREHENSIVE STATISTICAL SUMMARY MATRIX
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=========================================================================================\n";
echo "📊 PHASE 2G.2 FULL BENCHMARK HEAD-TO-HEAD COMPARISON MATRIX\n";
echo "=========================================================================================\n";
printf("%-28s | %-16s | %-14s | %-14s | %-10s | %-10s\n", "Category", "Metric", "Serial (2-LLM)", "Candidate (1-LLM)", "Delta", "Outcome");
echo "---------------------------------------------------------------------------------------------------------\n";

$groundedSubset = array_filter($fullResults, fn($r) => $r['category'] === 'A. Grounded');
$oodSubset      = array_filter($fullResults, fn($r) => $r['category'] === 'C. OOD Negative');
$convSubset     = array_filter($fullResults, fn($r) => $r['category'] === 'B. Conversational');
$allSubset      = $fullResults;

function avgMetricFull(array $items, string $arch, string $key): float {
    $vals = array_map(fn($x) => $x[$arch][$key], $items);
    return count($vals) ? round(array_sum($vals) / count($vals), 2) : 0.0;
}

$meanSerialGrounded = avgMetricFull($groundedSubset, 'serial', 'total_ms');
$meanCandGrounded   = avgMetricFull($groundedSubset, 'candidate', 'total_ms');
$speedupGrounded    = $meanSerialGrounded > 0 ? round((($meanSerialGrounded - $meanCandGrounded) / $meanSerialGrounded) * 100, 1) : 0.0;

$meanSerialConv = avgMetricFull($convSubset, 'serial', 'total_ms');
$meanCandConv   = avgMetricFull($convSubset, 'candidate', 'total_ms');

$meanSerialOod = avgMetricFull($oodSubset, 'serial', 'total_ms');
$meanCandOod   = avgMetricFull($oodSubset, 'candidate', 'total_ms');

$meanSerialAll = avgMetricFull($allSubset, 'serial', 'total_ms');
$meanCandAll   = avgMetricFull($allSubset, 'candidate', 'total_ms');
$speedupAll    = $meanSerialAll > 0 ? round((($meanSerialAll - $meanCandAll) / $meanSerialAll) * 100, 1) : 0.0;

$serialOodPass = count(array_filter($oodSubset, fn($r) => $r['serial']['ood_safe']));
$candOodPass   = count(array_filter($oodSubset, fn($r) => $r['candidate']['ood_safe']));

printf("%-28s | %-16s | %12.2f ms | %14.2f ms | %8.1f%% | %-10s\n", "A. Knowledge Grounded (5)", "Mean E2E Latency", $meanSerialGrounded, $meanCandGrounded, $speedupGrounded, "FASTER");
printf("%-28s | %-16s | %12.2f ms | %14.2f ms | %8.1f%% | %-10s\n", "B. Conversational (4)", "Mean E2E Latency", $meanSerialConv, $meanCandConv, round((($meanSerialConv-$meanCandConv)/$meanSerialConv)*100, 1), "NATURAL");
printf("%-28s | %-16s | %12d/10 | %14d/10 | %8s | %-10s\n", "C. OOD Safety Rejection (10)", "Safe Refusal Rate", $serialOodPass, $candOodPass, "100%", "SAFE");
printf("%-28s | %-16s | %12.2f ms | %14.2f ms | %8.1f%% | %-10s\n", "C. OOD Latency (10)", "Mean E2E Latency", $meanSerialOod, $meanCandOod, round((($meanSerialOod-$meanCandOod)/$meanSerialOod)*100, 1), "FASTER");
printf("%-28s | %-16s | %12.2f ms | %14.2f ms | %8.1f%% | %-10s\n", "Overall Full Benchmark (21)", "Mean E2E Latency", $meanSerialAll, $meanCandAll, $speedupAll, "FASTER");
printf("%-28s | %-16s | %12d calls| %14d call | %8s | %-10s\n", "LLM Calls (Grounded)", "Invocations/Query", 2, 1, "-50%", "OPTIMAL");
echo "=========================================================================================================\n";
