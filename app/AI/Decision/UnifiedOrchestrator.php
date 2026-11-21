<?php

declare(strict_types=1);

namespace App\AI\Decision;

use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\DTOs\ExecutionResponse;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Memory\ConversationMemoryService;
use Illuminate\Support\Facades\Log;

/**
 * Master Unified AI Orchestrator.
 *
 * Implements the streamlined 5-layer pipeline:
 * User Request -> Preflight Security -> Unified Decision Engine -> Policy Validation & Deterministic Execution -> Final Response
 */
class UnifiedOrchestrator
{
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
}
