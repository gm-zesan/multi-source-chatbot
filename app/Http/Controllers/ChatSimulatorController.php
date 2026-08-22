<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Services\CRM\CRMService;
use App\Services\CRM\EntityExtractor;
use App\Services\CRM\EntityNormalizer;
use App\Services\FAQ\FAQAnswerEngine;
use App\Services\FAQ\FAQSearch;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ChatSimulatorController extends Controller
{
    public function __construct(
        private readonly EntityExtractor $extractor,
        private readonly EntityNormalizer $normalizer,
        private readonly CRMService $crmService,
        private readonly RetrievalClient $retrievalClient,
        private readonly FAQAnswerEngine $answerEngine,
        private readonly FAQSearch $faqSearch,
    ) {}

    /**
     * Display the Chat Simulator UI.
     */
    public function index(): View
    {
        return view('admin.simulator');
    }

    /**
     * Process a test message through the complete local pipeline:
     * 1. Contact / CRM Entity Extraction & Persistence
     * 2. Python FastAPI Embedding Service Call
     * 3. Typesense Vector & Hybrid Search
     * 4. FAQ Knowledge Retrieval & Laravel AI SDK Agent Execution
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $query = $request->input('message');
        $user = Auth::user();
        $workspaceId = ($user && $user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value)) ? null : $user?->workspace_id;
        $startTime = microtime(true);

        // ── 1. Contact / CRM Entity Extraction & Database Persistence ───
        $rawEntities = $this->extractor->extract($query);
        $crmEntities = $this->normalizer->normalize($rawEntities);

        $extractedEmails   = $crmEntities['contact']['emails'] ?? [];
        $extractedPhones   = $crmEntities['contact']['phones'] ?? [];
        $extractedWebsites = $crmEntities['contact']['websites'] ?? [];
        $extractedNid      = $crmEntities['document']['nid'] ?? null;

        $hasCrmData = ! empty($extractedEmails)
            || ! empty($extractedPhones)
            || ! empty($extractedWebsites)
            || ! empty($extractedNid);

        $savedContact = null;
        if ($hasCrmData) {
            $effectiveWorkspaceId = $workspaceId ?? \App\Models\Workspace::first()?->id ?? 1;
            try {
                $savedContact = $this->crmService->syncForWorkspace(
                    workspaceId: $effectiveWorkspaceId,
                    entities: $crmEntities,
                    name: $user?->name ?? 'Simulator User',
                );
            } catch (\Throwable $e) {
                // Log failure gracefully without breaking pipeline diagnostics
                Log::error('[ChatSimulator] CRM persistence error', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ── 2. Python Retrieval Service Diagnostics ─────────────────────
        $pythonDiag = [
            'status'       => 'unknown',
            'latency_ms'   => 0,
            'dimensions'   => 768,
            'model'        => 'paraphrase-multilingual-mpnet-base-v2',
            'vector_sample'=> [],
            'error'        => null,
        ];

        $pyHealth = $this->retrievalClient->health();
        $pythonDiag = [
            'status'       => $pyHealth['ok'] ? 'ok' : 'degraded',
            'latency_ms'   => $pyHealth['latency_ms'],
            'dimensions'   => 768,
            'model'        => 'paraphrase-multilingual-mpnet-base-v2',
            'vector_sample'=> [],
            'error'        => $pyHealth['error'],
        ];

        // ── 3. Retrieval Search Diagnostics ─────────────────────────────
        $tsStart = microtime(true);
        $retrievalHits = $this->faqSearch->search(query: $query, perPage: 5, workspaceId: $workspaceId);
        $typesenseDiag = [
            'status'     => $retrievalHits->isNotEmpty() ? 'ok' : 'degraded',
            'latency_ms' => round((microtime(true) - $tsStart) * 1000, 2),
            'hits_found' => $retrievalHits->count(),
            'error'      => null,
        ];

        // ── 4. FAQ Knowledge Retrieval & Laravel AI SDK Agent Execution ──
        $answerResult = $this->answerEngine->answer(
            query: $query,
            workspaceId: $workspaceId,
        );

        $retrievalTool = new KnowledgeRetrievalTool(
            faqSearch: $this->faqSearch,
            workspaceId: $workspaceId,
        );

        $agent = new CustomerSupportAgent(
            conversation: null,
            retrievalTool: $retrievalTool,
        );

        try {
            $provider = config('ai.default', 'openrouter');
            $model = config('ai.default_model', 'deepseek/deepseek-chat');
            $aiResponse = $agent->prompt($query, provider: $provider, model: $model);
            $replyText = (string) $aiResponse;
        } catch (\Throwable $e) {
            Log::warning('[ChatSimulator] AI Agent prompt fallback', [
                'error' => $e->getMessage(),
            ]);
            $replyText = $answerResult->answered && $answerResult->getAnswer()
                ? $answerResult->getAnswer()
                : "I'm sorry, I couldn't find a direct answer to your question in our knowledge base. A support agent will be with you shortly!";
        }

        $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'success'   => true,
            'query'     => $query,
            'reply'     => $replyText,
            'answered'  => $answerResult->answered,
            'confidence'=> round($answerResult->confidence, 2),
            'match_type'=> $answerResult->answered ? $answerResult->matchType : 'none',
            'matched_faq' => ($answerResult->answered && $answerResult->faq) ? [
                'id'       => $answerResult->faq->id,
                'question' => $answerResult->faq->question,
                'answer'   => $answerResult->faq->answer,
                'priority' => $answerResult->faq->priority,
            ] : null,

            'pipeline_diagnostics' => [
                'total_time_ms' => $totalElapsed,
                'crm_extracted' => [
                    'has_data'   => $hasCrmData,
                    'db_saved'   => $savedContact !== null,
                    'contact_id' => $savedContact?->id,
                    'emails'     => $extractedEmails,
                    'phones'     => $extractedPhones,
                    'websites'   => $extractedWebsites,
                    'nid'        => $extractedNid,
                ],
                'python_service' => $pythonDiag,
                'typesense'      => $typesenseDiag,
                'scores'         => [
                    'keyword_score'  => round($answerResult->keywordScore * 100, 1),
                    'semantic_score' => round($answerResult->semanticScore * 100, 1),
                    'final_confidence'=> round($answerResult->confidence, 1),
                    'threshold'      => 40.0,
                ],
            ],
        ]);
    }
}
