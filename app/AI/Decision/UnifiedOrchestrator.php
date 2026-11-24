<?php

declare(strict_types=1);

namespace App\AI\Decision;

use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\DTOs\ExecutionResponse;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Memory\ConversationMemoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Master Unified AI Orchestrator.
 *
 * Implements the streamlined 5-layer pipeline:
 * User Request -> Preflight Security -> Unified Decision Engine -> Policy Validation & Deterministic Execution -> Final Response
 */
class UnifiedOrchestrator
{
    /**
     * Frozen production engine release version.
     */
    public const ENGINE_VERSION = 'v2.4-RC1';

    public function __construct(
        private readonly DecisionEngineInterface $decisionEngine,
        private readonly DeterministicExecutionController $executionController,
        private readonly FinalResponseGenerator $responseGenerator,
        private readonly ?ConversationMemoryService $memoryService = null,
    ) {}

    /**
     * Process an incoming turn end-to-end.
     *
     * @param string $query Natural language user request
     * @param int $workspaceId Trusted workspace identifier
     * @param User|null $user Authenticated user
     * @param Conversation|null $conversation Active conversation context
     * @param string $channelSlug Entry channel (e.g. 'simulator', 'whatsapp', 'facebook', 'web_widget')
     * @param bool|null $isCustomer Explicit channel boundary override
     * @return array<string, mixed> Final structured response matching application contracts
     */
    public function process(
        string $query,
        int $workspaceId,
        ?User $user = null,
        ?Conversation $conversation = null,
        string $channelSlug = 'simulator',
        ?bool $isCustomer = null,
    ): array {
        $tStart = microtime(true);

        // ── Operational Rollout & Emergency Kill Switch Evaluation ──────────
        $rolloutMode = config('ai.rollout_mode', 'limited_canary');
        if ($rolloutMode === 'disabled') {
            Log::warning('[UnifiedOrchestrator] AI processing blocked: AI engine globally disabled');
            return $this->buildHandoffResponse($workspaceId, 'ai_engine_globally_disabled', $tStart);
        }

        if ($rolloutMode === 'limited_canary') {
            if ($workspaceId <= 0) {
                Log::warning("[UnifiedOrchestrator] AI processing blocked: Missing or invalid tenant context ({$workspaceId})");
                return $this->buildHandoffResponse($workspaceId, 'missing_tenant_context', $tStart);
            }

            try {
                $workspace = Workspace::find($workspaceId);
                if ($workspace === null || ! $workspace->isAiRolloutEnabled()) {
                    $reason = $workspace?->isAiKillSwitchActive()
                        ? 'workspace_kill_switch_active'
                        : ($workspace === null ? 'unknown_workspace_unauthorized' : 'workspace_not_in_canary_rollout');

                    Log::info("[UnifiedOrchestrator] AI processing halted for workspace {$workspaceId}: {$reason}");
                    return $this->buildHandoffResponse($workspaceId, $reason, $tStart, $workspace);
                }
            } catch (\Throwable $e) {
                Log::error('[UnifiedOrchestrator] Rollout evaluation failure, failing closed: ' . $e->getMessage());
                return $this->buildHandoffResponse($workspaceId, 'tenant_verification_failure', $tStart);
            }
        } elseif ($workspaceId > 0) {
            // General rollout mode ('all'): still strictly enforce per-workspace emergency kill switch
            try {
                $workspace = Workspace::find($workspaceId);
                if ($workspace !== null && $workspace->isAiKillSwitchActive()) {
                    Log::info("[UnifiedOrchestrator] AI processing halted for workspace {$workspaceId}: workspace_kill_switch_active");
                    return $this->buildHandoffResponse($workspaceId, 'workspace_kill_switch_active', $tStart, $workspace);
                }
            } catch (\Throwable $e) {
                Log::debug('[UnifiedOrchestrator] Kill switch check bypassed: ' . $e->getMessage());
            }
        }

        // ── Layer 1: Preflight & Security Context ────────────────────────────
        $isCustomerChannel = $isCustomer ?? $this->resolveCustomerChannel($user, $channelSlug, $conversation);
        $compactContext = $this->buildCompactContext($conversation, $query, $workspaceId);

        $request = new DecisionRequest(
            query: $query,
            workspaceId: $workspaceId,
            user: $user,
            conversation: $conversation,
            channelSlug: $channelSlug,
            isCustomer: $isCustomerChannel,
            compactContext: $compactContext,
        );

        // ── Layer 2: Unified Decision Engine (Single Structured Decision) ───
        $decision = $this->decisionEngine->decide($request);

        // ── Layer 3 & 4: Policy Validation & Deterministic Tool Execution ───
        $execution = $this->executionController->execute($decision, $request);

        // ── Layer 5: Final Grounded Response Generation ─────────────────────
        $finalOutput = $this->responseGenerator->generate($request, $decision, $execution);

        $totalMs = round((microtime(true) - $tStart) * 1000, 2);

        // Structured diagnostics and telemetry
        $finalOutput['decision_metadata'] = array_merge(
            ['engine_version' => self::ENGINE_VERSION],
            $decision->decisionMetadata,
            $execution->telemetry,
            ['total_orchestrator_ms' => $totalMs]
        );

        if (isset($finalOutput['latency_breakdown'])) {
            $finalOutput['latency_breakdown']['total_ms'] = $totalMs;
            $finalOutput['latency_breakdown']['total_e2e_ms'] = $totalMs;
        }

        if (isset($finalOutput['routing_telemetry'])) {
            $finalOutput['routing_telemetry']['total_e2e_ms'] = $totalMs;
        }

        return $finalOutput;
    }

    /**
     * Resolve whether channel is an untrusted public customer channel.
     */
    protected function resolveCustomerChannel(?User $user, string $channelSlug, ?Conversation $conversation): bool
    {
        $publicSlugs = ['facebook', 'messenger', 'whatsapp', 'instagram', 'telegram', 'web', 'website', 'web_widget', 'widget', 'public'];
        if (in_array(mb_strtolower($channelSlug), $publicSlugs, true)) {
            return true;
        }

        // Administrative channels (simulator, admin, dashboard, console)
        if ($user !== null) {
            $isOwnerOrManager = $user->hasRole('superadmin')
                || $user->hasRole('admin')
                || $user->hasRole('owner')
                || $user->hasRole('manager')
                || $user->can('view_analytics');

            if ($isOwnerOrManager) {
                return false;
            }
        }

        // Staff without analytics permissions in simulator is treated with customer security barriers
        return true;
    }

    /**
     * Extract compact conversation context (short-term dialogue turns + long-term graph memory).
     */
    protected function buildCompactContext(?Conversation $conversation, ?string $query = null, ?int $workspaceId = null): ?string
    {
        if ($conversation === null || !$conversation->exists) {
            return null;
        }

        $lines = [];

        // 1. Long-Term Graph Memory (Neo4j: Past preferences, orders, issues)
        if ($query !== null && $workspaceId !== null) {
            try {
                $memSvc = $this->memoryService ?? app(ConversationMemoryService::class);
                $graphMemory = $memSvc->retrieveContext(
                    conversation: $conversation,
                    query: $query,
                    workspaceId: $workspaceId,
                );
                if (!empty($graphMemory)) {
                    $lines[] = "[Customer Profile & Past Preferences]\n" . trim((string) $graphMemory);
                }
            } catch (\Throwable $e) {
                Log::debug('[UnifiedOrchestrator] Graph memory retrieval bypassed: ' . $e->getMessage());
            }
        }

        // 2. Short-Term Dialogue Context (recent substantive turns across 6-8 dialogue rounds)
        $messagesLimit = (int) config('ai.memory.max_messages', 16);
        $messages = $conversation->messages()
            ->latest('id')
            ->take($messagesLimit)
            ->get()
            ->reverse();

        if (!$messages->isEmpty()) {
            $dialogueLines = [];
            foreach ($messages as $msg) {
                $speaker = $msg->is_from_user ? 'User' : 'Assistant';
                $text = trim(strip_tags((string) ($msg->body ?? '')));
                if (mb_strlen($text) > 400) {
                    $text = mb_substr($text, 0, 400) . '...';
                }
                if ($text !== '') {
                    $dialogueLines[] = "{$speaker}: {$text}";
                }
            }
            if (!empty($dialogueLines)) {
                $lines[] = "[Recent Dialogue Turns]\n" . implode("\n", $dialogueLines);
            }
        }

        return !empty($lines) ? implode("\n\n", $lines) : null;
    }

    /**
     * Synthesize immediate safe handoff response when rollout is inactive or kill switch is engaged.
     *
     * @return array<string, mixed>
     */
    protected function buildHandoffResponse(int $workspaceId, string $reason, float $tStart, ?Workspace $workspace = null): array
    {
        $elapsed = round((microtime(true) - $tStart) * 1000, 2);
        $fallbackMessage = $workspace?->getFallbackMessage()
            ?? 'আমাদের AI সহকারী সাময়িকভাবে স্থগিত আছে। আমাদের সাপোর্ট টিম দ্রুত আপনার সাথে যোগাযোগ করবে।';

        return [
            'reply'                  => $fallbackMessage,
            'route'                  => 'human_handoff',
            'confidence'             => 1.0,
            'answered'               => false,
            'is_owner_pipeline'      => false,
            'suggestions'            => [],
            'sources'                => [],
            'is_handoff'             => true,
            'memory_context'         => null,
            'business_context'       => null,
            'retrieval_hits'         => new Collection(),
            'top_hit'                => null,
            'answerability_decision' => [
                'status'           => 'HANDOFF_KILL_SWITCH',
                'confidence_score' => 0.0,
                'grounded_count'   => 0,
                'reasons'          => [$reason],
            ],
            'follow_up_decision'     => null,
            'raw_llm_response'       => [
                'provider'                 => config('ai.default', 'deepseek'),
                'model'                    => config('ai.default_model', 'deepseek-chat'),
                'prompt_tokens'            => 0,
                'completion_tokens'        => 0,
                'total_tokens'             => 0,
                'router_tokens'            => [],
                'agent_tokens'             => [],
                'grounded_documents_count' => 0,
                'grounded_faq_questions'   => [],
            ],
            'routing_telemetry'      => [
                'route'             => 'human_handoff',
                'confidence'        => 1.0,
                'intent'            => 'human_handoff',
                'router_latency_ms' => 0.0,
                'total_e2e_ms'      => $elapsed,
                'signals'           => [
                    'contextual_signal' => 'HANDOFF_KILL_SWITCH',
                ],
            ],
            'lexicon_telemetry'      => [
                'canonical_concepts'  => [],
                'expansion_triggered' => false,
                'expanded_query'      => null,
                'reranker_applied'    => false,
                'reranker_reason'     => null,
                'first_pass_score'    => null,
                'tier_executed'       => null,
            ],
            'latency_breakdown'      => [
                'router_ms'              => 0.0,
                'context_resolution_ms'  => 0.0,
                'clarification_ms'       => 0.0,
                'memory_gate_ms'         => 0.0,
                'memory_retrieval_ms'    => 0.0,
                'business_context_ms'    => 0.0,
                'knowledge_retrieval_ms' => 0.0,
                'retrieval_ms'           => 0.0,
                'answerability_ms'       => 0.0,
                'llm_generation_ms'      => 0.0,
                'llm_ms'                 => 0.0,
                'total_ms'               => $elapsed,
                'total_e2e_ms'           => $elapsed,
            ],
            'decision_metadata'      => [
                'engine_version'     => self::ENGINE_VERSION,
                'kill_switch_active' => $workspace?->isAiKillSwitchActive() ?? ($reason === 'ai_engine_globally_disabled'),
                'rollout_enabled'    => false,
                'halt_reason'        => $reason,
                'total_orchestrator_ms' => $elapsed,
            ],
        ];
    }
}
