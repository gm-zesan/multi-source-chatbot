<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\CRM\CRMService;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatSimulatorController extends Controller
{
    public function __construct(
        private readonly CRMService $crmService,
        private readonly CustomerSupportService $customerSupportService,
        private readonly RetrievalClient $retrievalClient,
    ) {}

    /**
     * Display the Chat Simulator UI.
     */
    public function index(): View
    {
        return view('admin.simulator');
    }

    /**
     * Process an incoming customer message through the full pipeline:
     * 1. Extract CRM entities and persist contact profile.
     * 2. Run CustomerSupportService (Agent + Tool + Retrieval + LLM with multi-turn conversation memory).
     * 3. Return structured response with diagnostics.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $query = (string) $request->input('message');
        $workspaceId = $this->resolveWorkspaceId();
        $startTime = microtime(true);

        // ── 0. Resolve Multi-Turn Simulator Conversation ───────────────
        $conversation = $this->resolveSimulatorConversation($request, $workspaceId);

        // Save incoming user message to conversation history
        Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => $query,
        ]);

        // ── 1. CRM Entity Extraction & Contact Persistence ─────────────
        $crm = $this->crmService->processForWorkspace(
            workspaceId: $workspaceId,
            text: $query,
            name: Auth::user()?->name ?? 'Simulator User',
        );

        // ── 2. Run Unified CustomerSupportService with Conversation Memory 
        $tsStart = microtime(true);
        $supportResult = $this->customerSupportService->handleQuery(
            query: $query,
            workspaceId: $workspaceId,
            conversation: $conversation,
        );
        $tsLatency = round((microtime(true) - $tsStart) * 1000, 2);

        // Save AI outbound response to conversation history for next turn context
        if (!empty($supportResult['reply'])) {
            $this->customerSupportService->saveOutboundReply(
                conversation: $conversation,
                replyText: $supportResult['reply'],
            );

            // Dispatch asynchronous Graph Memory Ingestion (Non-blocking, port 8002)
            \App\Jobs\IngestConversationMemoryJob::dispatch($conversation);
        }

        // ── 3. Diagnostics & Structured Decision Trace ──────────────────
        $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);
        $topHit = $supportResult['top_hit'];
        $retrievalHits = $supportResult['retrieval_hits'];
        $answerabilityDecision = $supportResult['answerability_decision'] ?? null;
        $routerLatency = (float) ($supportResult['routing_telemetry']['router_latency_ms'] ?? 0.0);
        $llmLatency = max(0.0, round($totalElapsed - $routerLatency - $tsLatency, 1));
        $contextualSignal = $supportResult['routing_telemetry']['signals']['contextual_signal']
            ?? $supportResult['routing_telemetry']['intent']
            ?? 'COMMERCE_GENERAL';

        $decisionTrace = [
            'query'                => $query,
            'route'                => strtoupper((string) $supportResult['route']),
            'route_confidence'     => round($supportResult['confidence'] * 100, 1),
            'memory_decision'      => !empty($supportResult['memory_context']) ? 'USED' : 'BYPASSED',
            'memory_preview'       => !empty($supportResult['memory_context']) ? substr($supportResult['memory_context'], 0, 150) . '...' : null,
            'contextual_signal'    => strtoupper((string) $contextualSignal),
            'retrieval_summary'    => [
                'hits_count'       => $retrievalHits->count(),
                'top_score'        => $topHit ? round($topHit->finalScore * 100, 1) : 0.0,
                'top_doc_type'     => $topHit?->faq?->documentTypeLabel() ?? null,
                'top_question'     => $topHit?->faq?->question ?? null,
            ],
            'answerability_status' => strtoupper($answerabilityDecision['status'] ?? 'BYPASSED'),
            'answerability_score'  => round(($answerabilityDecision['confidence_score'] ?? 0.0) * 100, 1),
            'grounded_hit_count'   => (int) ($answerabilityDecision['grounded_count'] ?? count($supportResult['sources'] ?? [])),
            'llm_generation'       => [
                'provider'         => config('ai.default', 'deepseek'),
                'model'            => config('ai.default_model', 'deepseek-chat'),
                'status'           => !empty($supportResult['reply']) ? 'GENERATED' : 'FALLBACK',
            ],
            'latency_breakdown'    => [
                'router_ms'        => $routerLatency,
                'retrieval_ms'     => $tsLatency,
                'llm_ms'           => $llmLatency,
                'total_ms'         => $totalElapsed,
            ],
        ];

        return response()->json([
            'success'          => true,
            'query'            => $query,
            'reply'            => $supportResult['reply'],
            'route'            => $supportResult['route'],
            'suggestions'      => $supportResult['suggestions'] ?? [],
            'sources'          => $supportResult['sources'] ?? [],
            'is_handoff'       => $supportResult['is_handoff'] ?? false,
            'answered'         => $supportResult['answered'],
            'decision_trace'   => $decisionTrace,
            'answerability_decision' => $supportResult['answerability_decision'] ?? null,
            'raw_llm_response' => $supportResult['raw_llm_response'] ?? null,
            'confidence'       => $topHit ? round($topHit->finalScore * 100, 1) : 0.0,
            'match_type'       => $topHit?->matchType ?? 'none',
            'matched_faq'      => $topHit?->faq ? [
                'id'            => (string) $topHit->faq->id,
                'question'      => $topHit->faq->question,
                'answer'        => $topHit->faq->answer,
                'priority'      => $topHit->faq->priority,
                'document_type' => $topHit->faq->documentTypeLabel(),
            ] : null,

            'pipeline_diagnostics' => [
                'total_time_ms'          => $totalElapsed,
                'answerability_decision' => $supportResult['answerability_decision'] ?? null,
                'memory_context'         => $supportResult['memory_context'] ?? null,
                'has_memory_context'     => !empty($supportResult['memory_context']),
                'business_context'       => $supportResult['business_context'] ?? null,
                'has_business_context'   => !empty($supportResult['business_context']),
                'routing_telemetry'      => $supportResult['routing_telemetry'] ?? [],
                'crm_extracted'  => [
                    'has_data'   => $crm['has_data'],
                    'db_saved'   => $crm['db_saved'],
                    'contact_id' => $crm['contact_id'],
                    'emails'     => $crm['emails'],
                    'phones'     => $crm['phones'],
                    'websites'   => $crm['websites'],
                    'nid'        => $crm['nid'],
                ],
                'python_service' => $this->retrievalDiagnostics(),
                'typesense'      => [
                    'status'     => $retrievalHits->isNotEmpty() ? 'ok' : 'degraded',
                    'latency_ms' => $tsLatency,
                    'hits_found' => $retrievalHits->count(),
                    'error'      => null,
                ],
                'scores'         => [
                    'keyword_score'   => $topHit ? round($topHit->keywordScore * 100, 1) : 0.0,
                    'semantic_score'  => $topHit ? round($topHit->semanticScore * 100, 1) : 0.0,
                    'final_confidence'=> $topHit ? round($topHit->finalScore * 100, 1) : 0.0,
                    'threshold'       => 40.0,
                ],
            ],
        ]);
    }

    /**
     * Resolve effective workspace ID.
     */
    private function resolveWorkspaceId(): int
    {
        $user = Auth::user();

        return (int) (\App\Models\Workspace::first()?->id ?? 1);
    }

    /**
     * Gather Python service health diagnostics.
     *
     * @return array<string, mixed>
     */
    private function retrievalDiagnostics(): array
    {
        $pyHealth = $this->retrievalClient->health();

        return [
            'status'        => $pyHealth['ok'] ? 'ok' : 'degraded',
            'latency_ms'    => $pyHealth['latency_ms'],
            'dimensions'    => 768,
            'model'         => 'paraphrase-multilingual-mpnet-base-v2',
            'vector_sample' => [],
            'error'         => $pyHealth['error'],
        ];
    }

    private function resolveSimulatorConversation(Request $request, int $workspaceId): Conversation
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : 'default_sim_session';
        $sessionKey = 'simulator_conv_' . ($sessionId ?: 'default');

        $channel = Channel::first() ?? Channel::create([
            'name'   => 'Simulator Channel',
            'slug'   => 'simulator',
            'driver' => 'web',
        ]);

        $channelAccount = ChannelAccount::firstOrCreate(
            ['workspace_id' => $workspaceId, 'external_id' => 'sim_acc_' . $workspaceId],
            [
                'name'         => 'Simulator Account',
                'channel_id'   => $channel->id,
                'access_token' => 'simulator_token',
                'is_active'    => true,
            ]
        );

        return Conversation::firstOrCreate(
            [
                'channel_account_id' => $channelAccount->id,
                'external_user_id'   => $sessionKey,
            ],
            [
                'status'         => 'active',
                'customer_name'  => Auth::user()?->name ?? 'Simulator User',
                'last_direction' => 'inbound',
            ]
        );
    }

    /**
     * Clear simulator conversation history.
     */
    public function clear(Request $request): JsonResponse
    {
        $workspaceId = $this->resolveWorkspaceId();
        $conversation = $this->resolveSimulatorConversation($request, $workspaceId);
        $conversation->messages()->delete();
        $conversation->update(['metadata' => []]);

        return response()->json(['success' => true, 'message' => 'Simulator conversation history cleared.']);
    }
}



