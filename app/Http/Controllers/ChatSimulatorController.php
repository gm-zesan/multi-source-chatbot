<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CRM\CRMService;
use App\Services\CRM\EntityExtractor;
use App\Services\CRM\EntityNormalizer;
use App\Services\FAQ\FAQAnswerEngine;
use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\Search\TypesenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatSimulatorController extends Controller
{
    public function __construct(
        private readonly EntityExtractor $extractor,
        private readonly EntityNormalizer $normalizer,
        private readonly CRMService $crmService,
        private readonly EmbeddingService $embeddings,
        private readonly TypesenseService $typesense,
        private readonly FAQAnswerEngine $answerEngine,
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
     * 4. FAQ Answer Engine Confidence & Auto-Reply Calculation
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
                \Illuminate\Support\Facades\Log::error('[ChatSimulator] CRM persistence error', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ── 2. Python FastAPI Embedding Diagnostics ─────────────────────
        $pythonDiag = [
            'status'       => 'unknown',
            'latency_ms'   => 0,
            'dimensions'   => 0,
            'model'        => 'unknown',
            'vector_sample'=> [],
            'error'        => null,
        ];

        $pyStart = microtime(true);
        try {
            $embedResponse = $this->embeddings->embed($query);
            $pythonDiag = [
                'status'       => 'ok',
                'latency_ms'   => round((microtime(true) - $pyStart) * 1000, 2),
                'dimensions'   => $embedResponse->dimensions,
                'model'        => config('embedding.model', 'paraphrase-multilingual-mpnet-base-v2'),
                'vector_sample'=> array_slice($embedResponse->vector, 0, 5), // First 5 floats for display
                'error'        => null,
            ];
        } catch (\Throwable $e) {
            $pythonDiag = [
                'status'     => 'failed',
                'latency_ms' => round((microtime(true) - $pyStart) * 1000, 2),
                'dimensions' => 0,
                'model'      => config('embedding.model', 'unknown'),
                'error'      => $e->getMessage(),
            ];
        }

        // ── 3. Typesense Search Diagnostics ─────────────────────────────
        $typesenseDiag = [
            'status'     => 'unknown',
            'latency_ms' => 0,
            'hits_found' => 0,
            'error'      => null,
        ];

        $tsStart = microtime(true);
        try {
            $tsHealth = $this->typesense->health();
            $exists = $this->typesense->collectionExists('faqs');

            if ($tsHealth['ok'] && $exists) {
                $typesenseDiag = [
                    'status'     => 'ok',
                    'latency_ms' => round((microtime(true) - $tsStart) * 1000, 2),
                    'hits_found' => 0, // Will be updated by FAQ answer engine run
                    'error'      => null,
                ];
            } else {
                $typesenseDiag = [
                    'status'     => 'degraded',
                    'latency_ms' => round((microtime(true) - $tsStart) * 1000, 2),
                    'error'      => ! $exists ? "Collection 'faqs' missing" : 'Typesense unreachable',
                ];
            }
        } catch (\Throwable $e) {
            $typesenseDiag = [
                'status'     => 'failed',
                'latency_ms' => round((microtime(true) - $tsStart) * 1000, 2),
                'error'      => $e->getMessage(),
            ];
        }

        // ── 4. Full FAQ Answer Pipeline Run ──────────────────────────────
        $answerResult = $this->answerEngine->answer(
            query: $query,
            workspaceId: $workspaceId,
        );

        $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);

        // Standard auto-reply output
        $replyText = $answerResult->answered && $answerResult->getAnswer()
            ? $answerResult->getAnswer()
            : "I'm sorry, I couldn't find a direct answer to your question in our knowledge base. A support agent will be with you shortly!";

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
