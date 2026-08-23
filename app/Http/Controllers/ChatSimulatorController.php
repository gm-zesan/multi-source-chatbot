<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
     * 2. Run CustomerSupportService (Agent + Tool + Retrieval + LLM).
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

        // ── 1. CRM Entity Extraction & Contact Persistence ─────────────
        $crm = $this->crmService->processForWorkspace(
            workspaceId: $workspaceId,
            text: $query,
            name: Auth::user()?->name ?? 'Simulator User',
        );

        // ── 2. Run Unified CustomerSupportService ────────────────────────
        $tsStart = microtime(true);
        $supportResult = $this->customerSupportService->handleQuery(
            query: $query,
            workspaceId: $workspaceId,
        );
        $tsLatency = round((microtime(true) - $tsStart) * 1000, 2);

        // ── 3. Diagnostics & Structured Response ────────────────────────
        $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);
        $topHit = $supportResult['top_hit'];
        $retrievalHits = $supportResult['retrieval_hits'];

        return response()->json([
            'success'     => true,
            'query'       => $query,
            'reply'       => $supportResult['reply'],
            'answered'    => $supportResult['answered'],
            'confidence'  => $topHit ? round($topHit->finalScore * 100, 1) : 0.0,
            'match_type'  => $topHit?->matchType ?? 'none',
            'matched_faq' => $topHit?->faq ? [
                'id'       => (string) $topHit->faq->id,
                'question' => $topHit->faq->question,
                'answer'   => $topHit->faq->answer,
                'priority' => $topHit->faq->priority,
            ] : null,

            'pipeline_diagnostics' => [
                'total_time_ms'  => $totalElapsed,
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

        if ($user && ! $user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value) && $user->workspace_id) {
            return (int) $user->workspace_id;
        }

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
}



