<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\AI\Agents\ConversationalSupportAgent;
use App\AI\Agents\KnowledgeSupportAgent;
use App\AI\LLM\LLMClient;
use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Business\BusinessSourceOfTruthService;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Memory\ConversationMemoryService;
use Illuminate\Support\Facades\Log;

class CustomerSupportService
{
    private readonly HybridRouter $router;
    private readonly ActionSafetyService $actionSafety;
    private readonly ContextualQueryBuilder $contextualQueryBuilder;
    private readonly ConversationMemoryService $memoryService;
    private readonly BusinessSourceOfTruthService $businessService;
    private readonly LLMClient $llmClient;
    private readonly SemanticAnswerabilityGate $answerabilityGate;
    private readonly ClarificationManager $clarificationManager;
    private readonly AnalyticsClient $analyticsClient;
    private readonly \App\AI\Tools\KnowledgeRetrievalTool $knowledgeRetrievalTool;
    private readonly \App\AI\Tools\BusinessAnalyticsTool $businessAnalyticsTool;
    private readonly \App\AI\Tools\ExcelAnalyticsTool $excelAnalyticsTool;
    private ?\Laravel\Ai\Responses\Data\Usage $lastLlmUsage = null;

    public function __construct(
        private readonly FAQSearch $faqSearch,
        private readonly ConversationService $conversationService,
        ?HybridRouter $router = null,
        ?ActionSafetyService $actionSafety = null,
        ?ContextualQueryBuilder $contextualQueryBuilder = null,
        ?ConversationMemoryService $memoryService = null,
        ?BusinessSourceOfTruthService $businessService = null,
        ?LLMClient $llmClient = null,
        ?SemanticAnswerabilityGate $answerabilityGate = null,
        ?ClarificationManager $clarificationManager = null,
        ?AnalyticsClient $analyticsClient = null,
        ?\App\AI\Tools\KnowledgeRetrievalTool $knowledgeRetrievalTool = null,
        ?\App\AI\Tools\BusinessAnalyticsTool $businessAnalyticsTool = null,
        ?\App\AI\Tools\ExcelAnalyticsTool $excelAnalyticsTool = null,
    ) {
        $this->router = $router ?? app(HybridRouter::class);
        $this->actionSafety = $actionSafety ?? app(ActionSafetyService::class);
        $this->memoryService = $memoryService ?? app(ConversationMemoryService::class);
        $this->contextualQueryBuilder = $contextualQueryBuilder ?? app(ContextualQueryBuilder::class);
        $this->businessService = $businessService ?? app(BusinessSourceOfTruthService::class);
        $this->llmClient = $llmClient ?? app(LLMClient::class);
        $this->answerabilityGate = $answerabilityGate ?? app(SemanticAnswerabilityGate::class);
        $this->clarificationManager = $clarificationManager ?? app(ClarificationManager::class);
        $this->analyticsClient = $analyticsClient ?? app(AnalyticsClient::class);
        $this->knowledgeRetrievalTool = $knowledgeRetrievalTool ?? app(\App\AI\Tools\KnowledgeRetrievalTool::class);
        $this->businessAnalyticsTool = $businessAnalyticsTool ?? app(\App\AI\Tools\BusinessAnalyticsTool::class);
        $this->excelAnalyticsTool = $excelAnalyticsTool ?? app(\App\AI\Tools\ExcelAnalyticsTool::class);
    }

    /**
     * Generate AI reply for a conversation query using Hybrid Routing + Selective Execution.
     */
    public function generateReply(
        Conversation $conversation,
        string $query,
        ?int $workspaceId = null,
    ): string {
        $effectiveWorkspaceId = $workspaceId
            ?? $conversation->channelAccount?->workspace_id
            ?? Workspace::first()?->id
            ?? 1;

        $t_start = microtime(true);

        // ── 1. Hybrid Routing (Evaluates with full dialogue context) ────────────────────────────────────────────────
        $routingResult = $this->router->route(
            query: $query,
            conversation: $conversation,
            workspaceId: $effectiveWorkspaceId,
        );

        // ── Phase M2: Context Resolution & Ambiguity Handling (Knowledge/Uncertain only) ─────
        $contextResult = null;
        if ($routingResult->isKnowledge() || $routingResult->isUncertain()) {
            $contextResult = $this->contextualQueryBuilder->resolveContext($query, $conversation);
            if ($contextResult->needsClarification() && $routingResult->isUncertain()) {
                $ambiguityResponse = $this->clarificationManager->handleAmbiguity(
                    conversation: $conversation,
                    rawQuery: $query,
                    contextResult: $contextResult,
                    workspaceId: $effectiveWorkspaceId,
                );
                return $ambiguityResponse['reply'];
            }
        }

        // ── 1.5 Retrieve Memory & Live Business Source of Truth (Skipped for Analytics) ─────
        $memoryContext = null;
        $businessContext = null;
        if (!$routingResult->isAnalytics()) {
            $memoryContext = $this->memoryService->retrieveContext(
                conversation: $conversation,
                query: $query,
                workspaceId: $effectiveWorkspaceId,
                contextResult: $contextResult,
            );

            $businessContext = $this->businessService->buildBusinessContext(
                query: $query,
                conversation: $conversation,
                workspaceId: $effectiveWorkspaceId,
            );
        }

        // ── 2. Route Execution ───────────────────────────────────────────────
        $replyText = match ($routingResult->route) {
            RouteType::KNOWLEDGE => $this->executeKnowledgeRoute(
                conversation: $conversation,
                query: $query,
                workspaceId: $effectiveWorkspaceId,
                memoryContext: $memoryContext,
                businessContext: $businessContext,
            ),
            RouteType::CHAT => $this->executeChatRoute(
                conversation: $conversation,
                query: $query,
                routingResult: $routingResult,
                memoryContext: $memoryContext,
            ),
            RouteType::ANALYTICS => $this->executeAnalyticsRoute(
                conversation: $conversation,
                query: $query,
                workspaceId: $effectiveWorkspaceId,
                routingResult: $routingResult,
            ),
            RouteType::OOD => $this->executeOodRoute(
                conversation: $conversation,
                query: $query,
            ),
            RouteType::UNCERTAIN => $this->executeUncertainRoute(
                conversation: $conversation,
                query: $query,
                routingResult: $routingResult,
            ),
        };

        $totalE2eMs = round((microtime(true) - $t_start) * 1000, 2);

        // Non-sensitive structured telemetry
        Log::info('[CustomerSupportService] Query processed with hybrid router', [
            'route' => $routingResult->route->value,
            'confidence' => $routingResult->confidence,
            'intent' => $routingResult->intent,
            'router_latency_ms' => $routingResult->routerLatencyMs,
            'total_e2e_ms' => $totalE2eMs,
            'workspace_id' => $effectiveWorkspaceId,
        ]);

        return $replyText ?? $this->defaultFallbackText();
    }

    /**
     * Save an outbound reply message along with channel delivery and routing telemetry.
     */
    public function saveOutboundReply(
        Conversation $conversation,
        string $replyText,
        array $deliveryResponse = [],
    ): Message {
        return $this->conversationService->saveOutgoing(
            conversation: $conversation,
            message: $replyText,
            response: array_merge($deliveryResponse, [
                'source' => 'customer_support_agent',
                'router_type' => 'hybrid_router',
                'provider' => config('ai.default', 'deepseek'),
                'model' => config('ai.default_model', 'deepseek-chat'),
            ]),
        );
    }

    /**
     * Process an incoming message for a live customer conversation (e.g. from Webhook Queue).
     */
    public function handleConversation(
        Conversation $conversation,
        Message $message,
        array $deliveryResponse = [],
    ): ?Message {
        $workspaceId = $conversation->channelAccount?->workspace_id ?? (int) (\App\Models\Workspace::first()?->id ?? 1);
        $result = $this->handleQuery(
            query: $message->body,
            workspaceId: $workspaceId,
            conversation: $conversation,
        );

        $replyText = $result['reply'] ?? '';

        if (trim($replyText) === '') {
            return null;
        }

        $savedMessage = $this->saveOutboundReply(
            conversation: $conversation,
            replyText: $replyText,
            deliveryResponse: array_merge($deliveryResponse, [
                'route'                  => $result['route'] ?? 'knowledge',
                'confidence'             => $result['confidence'] ?? 1.0,
                'answered'               => $result['answered'] ?? false,
                'total_time_ms'          => $result['routing_telemetry']['total_e2e_ms'] ?? null,
                'answerability_decision' => $result['answerability_decision'] ?? null,
                'routing_telemetry'      => $result['routing_telemetry'] ?? [],
                'llm_usage'              => [
                    'prompt_tokens'     => $result['raw_llm_response']['prompt_tokens'] ?? 0,
                    'completion_tokens' => $result['raw_llm_response']['completion_tokens'] ?? 0,
                    'total_tokens'      => $result['raw_llm_response']['total_tokens'] ?? 0,
                    'router_tokens'     => $result['raw_llm_response']['router_tokens'] ?? [],
                    'agent_tokens'      => $result['raw_llm_response']['agent_tokens'] ?? [],
                ],
            ]),
        );

        // Observer Pattern: Telemetry is purely an observer and never decision maker
        try {
            event(new \App\Events\AITelemetryRecorded(
                conversation: $conversation,
                outboundMessage: $savedMessage,
                query: $message->body,
                reply: $replyText,
                telemetry: [
                    'route'                  => $result['route'] ?? 'knowledge',
                    'confidence'             => $result['confidence'] ?? 1.0,
                    'answered'               => $result['answered'] ?? false,
                    'total_time_ms'          => $result['routing_telemetry']['total_e2e_ms'] ?? null,
                    'answerability_decision' => $result['answerability_decision'] ?? null,
                    'routing_telemetry'      => $result['routing_telemetry'] ?? [],
                    'llm_usage'              => [
                        'prompt_tokens'     => $result['raw_llm_response']['prompt_tokens'] ?? 0,
                        'completion_tokens' => $result['raw_llm_response']['completion_tokens'] ?? 0,
                        'total_tokens'      => $result['raw_llm_response']['total_tokens'] ?? 0,
                        'router_tokens'     => $result['raw_llm_response']['router_tokens'] ?? [],
                        'agent_tokens'      => $result['raw_llm_response']['agent_tokens'] ?? [],
                    ],
                    'provider'               => config('ai.default', 'deepseek'),
                    'model'                  => config('ai.default_model', 'deepseek-chat'),
                ],
                workspaceId: $workspaceId,
            ));
        } catch (\Throwable $e) {
            Log::warning('[CustomerSupportService] Telemetry dispatch failed safely (Observer pattern): ' . $e->getMessage());
        }

        return $savedMessage;
    }

    /**
     * Process an isolated query (e.g. from Chat Simulator or direct API).
     *
     * @return array{
     *     reply: string,
     *     route: string,
     *     confidence: float,
     *     retrieval_hits: \Illuminate\Database\Eloquent\Collection,
     *     top_hit: ?\App\Services\FAQ\FAQSearchResult,
     *     answered: bool,
     *     routing_telemetry: array,
     * }
     */
    public function handleQuery(string $query, int $workspaceId, ?Conversation $conversation = null): array
    {
        $this->lastLlmUsage = null;
        $t_start = microtime(true);

        // ── 1. Hybrid Router (Evaluates with full dialogue context) ──────────────────
        $t_router_start = microtime(true);
        $routingResult = $this->router->route(
            query: $query,
            conversation: $conversation,
            workspaceId: $workspaceId,
        );
        $routerLatencyMs = round((microtime(true) - $t_router_start) * 1000, 2);

        // ── Phase M2: Context Resolution & Context Ambiguity Handling (Knowledge/Uncertain only) ─────
        $contextResult = null;
        $contextResolutionMs = 0.0;
        $contextualSignal = null;
        if ($routingResult->isKnowledge() || $routingResult->isUncertain()) {
            $t_context_start = microtime(true);
            $contextResult = $this->contextualQueryBuilder->resolveContext($query, $conversation);
            $contextResolutionMs = round((microtime(true) - $t_context_start) * 1000, 2);
            $contextualSignal = $contextResult->resolvedQuery ?? ($contextResult->activeTopic ?? null);

            if ($contextResult->needsClarification() && $routingResult->isUncertain()) {
                $t_clarification_start = microtime(true);
                $clarificationResult = $this->clarificationManager->handleAmbiguity(
                    conversation: $conversation,
                    rawQuery: $query,
                    contextResult: $contextResult,
                    workspaceId: $workspaceId,
                );
                $clarificationMs = round((microtime(true) - $t_clarification_start) * 1000, 2);
                $totalE2eMs = round((microtime(true) - $t_start) * 1000, 2);

                $clarificationResult['latency_breakdown'] = [
                    'router_ms'              => $routerLatencyMs,
                    'context_resolution_ms'  => $contextResolutionMs,
                    'clarification_ms'       => $clarificationMs,
                    'memory_gate_ms'         => 0.0,
                    'memory_retrieval_ms'    => 0.0,
                    'business_context_ms'    => 0.0,
                    'knowledge_retrieval_ms' => 0.0,
                    'retrieval_ms'           => 0.0,
                    'answerability_ms'       => 0.0,
                    'llm_generation_ms'      => 0.0,
                    'llm_ms'                 => 0.0,
                    'total_e2e_ms'           => $totalE2eMs,
                    'total_ms'               => $totalE2eMs,
                    'retrieval_sub_stages'   => [],
                ];
                $clarificationResult['routing_telemetry']['total_e2e_ms'] = $totalE2eMs;

                return $clarificationResult;
            }
        }

        // ── Phase M3: Memory Relevance Gate & Unified Memory Context (Skipped for Analytics) ──
        $memoryContext = null;
        $memoryRetrievalMs = 0.0;
        $businessContext = null;
        $businessContextMs = 0.0;

        if (!$routingResult->isAnalytics()) {
            $t_memory_start = microtime(true);
            $memoryContext = $this->memoryService->retrieveContext(
                conversation: $conversation,
                query: $query,
                workspaceId: $workspaceId,
                contextResult: $contextResult,
            );
            $memoryRetrievalMs = round((microtime(true) - $t_memory_start) * 1000, 2);

            $t_business_start = microtime(true);
            $businessContext = $this->businessService->buildBusinessContext(
                query: $query,
                conversation: $conversation,
                workspaceId: $workspaceId,
            );
            $businessContextMs = round((microtime(true) - $t_business_start) * 1000, 2);
        }

        // ── Knowledge Retrieval & Semantic Answerability Gate ─────────────────────────
        $retrievalHits = new \Illuminate\Database\Eloquent\Collection();
        $topHit = null;
        $answered = false;
        $answerabilityDecision = null;
        $groundedHits = new \Illuminate\Database\Eloquent\Collection();
        $knowledgeRetrievalMs = 0.0;
        $answerabilityMs = 0.0;


        if ($routingResult->isKnowledge() || $routingResult->isUncertain()) {
            $t_retrieval_start = microtime(true);
            $retrievalHits = $this->knowledgeRetrievalTool->execute(
                query: $query,
                workspaceId: $workspaceId,
                conversation: $conversation,
                contextualSignal: $contextualSignal,
                perPage: 5,
            );
            $knowledgeRetrievalMs = round((microtime(true) - $t_retrieval_start) * 1000, 2);

            $t_gate_start = microtime(true);
            $answerabilityDecision = $this->answerabilityGate->evaluate($contextualSignal ?? $query, $retrievalHits, $routingResult);
            $answerabilityMs = round((microtime(true) - $t_gate_start) * 1000, 2);

            $topHit = $answerabilityDecision->topHit();
            $answered = $answerabilityDecision->isConfident();
            $groundedHits = $answerabilityDecision->groundedHits;
        }

        // ── Generation / Agent Dispatch (LLM) ─────────────────────────────────────────
        $t_llm_start = microtime(true);
        $replyText = match ($routingResult->route) {
            RouteType::KNOWLEDGE => (
                $answerabilityDecision !== null && $answerabilityDecision->isAmbiguous()
                    ? $this->executeUncertainRoute($conversation ?? new Conversation(), $query, $routingResult)
                    : (
                        $answerabilityDecision !== null && $answerabilityDecision->isUnanswerable()
                            ? $this->executeOodRoute($conversation, $query)
                            : $this->promptKnowledgeAgent(
                                conversation: $conversation,
                                query: $query,
                                workspaceId: $workspaceId,
                                retrievedHits: $groundedHits,
                                memoryContext: $memoryContext,
                                businessContext: $businessContext,
                            )
                    )
            ),
            RouteType::CHAT => $this->promptConversationalAgent(
                conversation: $conversation,
                query: $query,
                memoryContext: $memoryContext,
            ),
            RouteType::ANALYTICS => $this->executeAnalyticsRoute(
                conversation: $conversation ?? new Conversation(),
                query: $query,
                workspaceId: $workspaceId,
                routingResult: $routingResult,
            ),
            RouteType::OOD => $this->executeOodRoute(
                conversation: $conversation,
                query: $query,
            ),
            RouteType::UNCERTAIN => $this->executeUncertainRoute(
                conversation: $conversation ?? new Conversation(),
                query: $query,
                routingResult: $routingResult,
            ),
        };
        $llmGenerationMs = round((microtime(true) - $t_llm_start) * 1000, 2);

        $totalE2eMs = round((microtime(true) - $t_start) * 1000, 2);

        $suggestions = $routingResult->isUncertain() || ($answerabilityDecision !== null && $answerabilityDecision->isAmbiguous())
            ? $this->getClarificationSuggestions($query)
            : [];
        $sources = $routingResult->isKnowledge() ? $this->formatGroundedSources($groundedHits, $query) : [];
        $isHandoff = (!empty($conversation?->metadata['handoff_to_human'])) ||
            (stripos($replyText ?? '', 'team member will contact you') !== false);

        $retrievalTelemetry = $this->faqSearch->getLastTelemetry();
        $retrievalPromptTokens = (int) ($retrievalTelemetry['llm_usage']['prompt_tokens'] ?? $retrievalTelemetry['prompt_tokens'] ?? 0);
        $retrievalCompletionTokens = (int) ($retrievalTelemetry['llm_usage']['completion_tokens'] ?? $retrievalTelemetry['completion_tokens'] ?? 0);

        $usage = $this->lastLlmUsage ?? null;
        $agentPromptTokens = $usage?->promptTokens ?? 0;
        $agentCompletionTokens = $usage?->completionTokens ?? 0;

        $routerPromptTokens = (int) ($routingResult->routerUsage['prompt_tokens'] ?? 0);
        $routerCompletionTokens = (int) ($routingResult->routerUsage['completion_tokens'] ?? 0);

        $promptTokens = $agentPromptTokens + $routerPromptTokens + $retrievalPromptTokens;
        $completionTokens = $agentCompletionTokens + $routerCompletionTokens + $retrievalCompletionTokens;
        $totalTokens = $promptTokens + $completionTokens;

        return [
            'reply' => $replyText ?? $this->defaultFallbackText(),
            'route' => $routingResult->route->value,
            'confidence' => $routingResult->confidence,
            'suggestions' => $suggestions,
            'sources' => $sources,
            'is_handoff' => $isHandoff,
            'memory_context' => $memoryContext,
            'business_context' => $businessContext,
            'retrieval_hits' => $retrievalHits,
            'top_hit' => $topHit,
            'answered' => $answered,
            'answerability_decision' => $answerabilityDecision?->toArray(),
            'raw_llm_response' => [
                'provider' => config('ai.default', 'deepseek'),
                'model' => config('ai.default_model', 'deepseek-chat'),
                'raw_reply_text' => $replyText,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'router_tokens' => [
                    'prompt_tokens' => $routerPromptTokens,
                    'completion_tokens' => $routerCompletionTokens,
                    'total_tokens' => $routerPromptTokens + $routerCompletionTokens,
                ],
                'agent_tokens' => [
                    'prompt_tokens' => $agentPromptTokens,
                    'completion_tokens' => $agentCompletionTokens,
                    'total_tokens' => $agentPromptTokens + $agentCompletionTokens,
                ],
                'grounded_documents_count' => $groundedHits->count(),
                'grounded_faq_questions' => $groundedHits->map(fn($h) => $h->faq?->question)->values()->all(),
            ],
            'routing_telemetry' => array_merge($routingResult->toArray(), [
                'router_latency_ms' => $routerLatencyMs,
                'total_e2e_ms'      => $totalE2eMs,
            ]),
            'lexicon_telemetry' => $retrievalTelemetry,
            'latency_breakdown' => [
                'router_ms'              => $routerLatencyMs,
                'context_resolution_ms'  => $contextResolutionMs,
                'clarification_ms'       => 0.0,
                'memory_gate_ms'         => 0.0,
                'memory_retrieval_ms'    => $memoryRetrievalMs,
                'business_context_ms'    => $businessContextMs,
                'knowledge_retrieval_ms' => $knowledgeRetrievalMs,
                'retrieval_ms'           => $knowledgeRetrievalMs,
                'answerability_ms'       => $answerabilityMs,
                'llm_generation_ms'      => $llmGenerationMs,
                'llm_ms'                 => $llmGenerationMs,
                'prompt_tokens'          => $promptTokens,
                'completion_tokens'      => $completionTokens,
                'total_tokens'           => $totalTokens,
                'total_e2e_ms'           => $totalE2eMs,
                'total_ms'               => $totalE2eMs,
                'retrieval_sub_stages'   => $retrievalTelemetry,
            ],
        ];
    }

    /**
     * Get clean, structured clarification suggestion options for UNCERTAIN queries.
     *
     * @return string[]
     */
    public function getClarificationSuggestions(string $query): array
    {
        $qLower = mb_strtolower($query);

        if (str_contains($qLower, 'cancel') || str_contains($qLower, 'বাতিল') || str_contains($qLower, 'ক্যানসেল') || str_contains($qLower, 'refund') || str_contains($qLower, 'রিফান্ড')) {
            return [
                'Ask about the order cancellation policy',
                'Learn how order cancellation works',
                'Something else',
            ];
        }

        if (str_contains($qLower, 'change') || str_contains($qLower, 'update') || str_contains($qLower, 'payment') || str_contains($qLower, 'card') || str_contains($qLower, 'পরিবর্তন') || str_contains($qLower, 'পেমেন্ট')) {
            return [
                'How to change your payment method',
                'How to update your account information',
                'Something else',
            ];
        }

        if (str_contains($qLower, 'invoice') || str_contains($qLower, 'bill') || str_contains($qLower, 'ইনভয়েস') || str_contains($qLower, 'বিল')) {
            return [
                'Where to find your invoices',
                'How invoices and billing work',
                'Something else',
            ];
        }

        return [
            'View available subscription plans',
            'Ask about account & security settings',
            'General platform features',
        ];
    }

    /**
     * Format retrieved FAQ hits into structured citation/source references.
     *
     * @return array<int, array{id: string, question: string, category: string, score: float}>
     */
    public function formatGroundedSources(\Illuminate\Database\Eloquent\Collection $retrievalHits, string $query = ''): array
    {
        if ($query !== '' && $this->isGeneralConceptualQuery($query)) {
            return [];
        }

        $sources = [];
        foreach ($retrievalHits as $hit) {
            if ($hit->faq && $hit->finalScore >= 0.45) {
                $sources[] = [
                    'id'       => (string) $hit->faq->id,
                    'question' => $hit->faq->question,
                    'category' => $hit->faq->category?->name ?? 'General',
                    'score'    => round($hit->finalScore * 100, 1),
                ];
            }
        }
        return $sources;
    }

    /**
     * Determine if a query is a general conceptual / terminology question that does not require company FAQ citations.
     */
    public function isGeneralConceptualQuery(string $query): bool
    {
        $qLower = mb_strtolower(trim($query));

        // Terminology comparison patterns (e.g. "x and y ki same?", "difference between x and y")
        if (preg_match('/\b(same|ek jinish|ek|different|alada|difference|versus|vs|তুলনা|পার্থক্য)\b/u', $qLower) &&
            preg_match('/\b(login|signin|sign in|signup|sign up|register|registration|api|webhook|json|xml|http|https|rest|graphql|sync|async)\b/u', $qLower)) {
            return true;
        }

        // Generic definition patterns (e.g. "what is json", "what is an enterprise sla in cloud", "explain webhook")
        if (preg_match('/^(what is|what are|what does|explain|how does|ki|কী|কাকে বলে|বলতে কি বোঝায়)\s+([\p{L}\p{M}\w\s-]{0,25}\s+)?(json|api|webhook|rest|graphql|sla|uptime|oauth|jwt|http|https|tls|ssl|mvc|orm|database|cloud|saas|paas|iaas)\b/u', $qLower)) {
            return true;
        }

        // Elliptical concept follow-up patterns (e.g. "tahole signup?", "and registration?", "ar webhook?")
        if (preg_match('/^(tahole|taile|তাহলে|and|ar|আর|what about|how about)\s+(ki\s+)?(login|signin|sign in|signup|sign up|register|registration|api|webhook|json|xml|http|https|rest|graphql)\b/u', $qLower)) {
            return true;
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ROUTE HANDLERS
    // ─────────────────────────────────────────────────────────────────────────

    private function executeKnowledgeRoute(
        Conversation $conversation,
        string $query,
        int $workspaceId,
        ?string $memoryContext = null,
        ?string $businessContext = null,
    ): string {
        $this->resetUncertainCount($conversation);

        $contextualSignal = $this->contextualQueryBuilder->resolveContextualSignal($query, $conversation);

        $retrievalHits = $this->knowledgeRetrievalTool->execute(
            query: $query,
            workspaceId: $workspaceId,
            conversation: $conversation,
            contextualSignal: $contextualSignal,
            perPage: 5,
        );

        $decision = $this->answerabilityGate->evaluate($query, $retrievalHits, null);

        if ($decision->isAmbiguous()) {
            return $this->executeUncertainRoute(
                conversation: $conversation,
                query: $query,
                routingResult: new \App\AI\Routing\RoutingResult(
                    route: RouteType::UNCERTAIN,
                    confidence: 0.5,
                    intent: 'uncertain_ambiguous',
                ),
            );
        }

        if ($decision->isUnanswerable()) {
            return $this->executeOodRoute($conversation, $query);
        }

        return $this->promptKnowledgeAgent(
            conversation: $conversation,
            query: $query,
            workspaceId: $workspaceId,
            retrievedHits: $decision->groundedHits,
            memoryContext: $memoryContext,
            businessContext: $businessContext,
        );
    }

    private function executeChatRoute(
        Conversation $conversation,
        string $query,
        RoutingResult $routingResult,
        ?string $memoryContext = null,
    ): string {
        $this->resetUncertainCount($conversation);

        // Handle pending action rejection if triggered
        if ($routingResult->intent === 'action_rejection') {
            $this->actionSafety->clearPendingAction($conversation);
            return "অর্ডার বাতিলের অনুরোধটি বাতিল করা হয়েছে এবং কোনো পরিবর্তন করা হয়নি। আপনার অন্য কোনো প্রয়োজনে বলুন, সাহায্য করতে প্রস্তুত আছি!";
        }

        return $this->promptConversationalAgent(
            conversation: $conversation,
            query: $query,
            memoryContext: $memoryContext,
        );
    }

    /**
     * Handle ACTION capability with a deterministic human handoff.
     * In the current phase, AI SDK tools, multi-turn confirmation workflows,
     * and automatic database mutations are deferred to ensure zero accidental state changes.
     */
    /**
     * Dispatch ANALYTICS route to the Python Baseline Analytics Service.
     * Invariant: $workspaceId is strictly injected from trusted Laravel runtime.
     */
    private function executeAnalyticsRoute(
        Conversation $conversation,
        string $query,
        int $workspaceId,
        RoutingResult $routingResult,
    ): string {
        @set_time_limit(120);
        $this->resetUncertainCount($conversation);

        $history = [];
        if ($conversation->exists) {
            $recentMessages = $conversation->messages()
                ->latest('id')
                ->take(4)
                ->get()
                ->reverse();

            foreach ($recentMessages as $msg) {
                $history[] = [
                    'role' => $msg->is_from_user ? 'user' : 'assistant',
                    'content' => trim(strip_tags((string) ($msg->body ?? ''))),
                ];
            }
        }

        $analyticsResult = $this->businessAnalyticsTool->execute(
            query: $query,
            workspaceId: $workspaceId,
            history: $history,
        );

        return $analyticsResult['report'] ?? $this->defaultFallbackText();
    }

    private function resetUncertainCount(Conversation $conversation): void
    {
        $metadata = $conversation->metadata ?? [];
        if ($conversation->exists && !empty($metadata['uncertain_count'])) {
            $metadata['uncertain_count'] = 0;
            $conversation->metadata = $metadata;
            $conversation->save();
        }
    }

    private function executeOodRoute(?Conversation $conversation, string $query): string
    {
        return "দুঃখিত, এই বিষয়টি আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়। আমাদের সার্ভিস বা অ্যাকাউন্ট সম্পর্কিত কোনো প্রশ্ন থাকলে জানান, অথবা আমি আপনাকে একজন সাপোর্ট স্পেশালিস্টের সাথে যুক্ত করে দিতে পারি।";
    }

    /**
     * Handle UNCERTAIN route with clean Knowledge/Chat clarification and suggestions.
     * If user triggers UNCERTAIN 3 consecutive times in a conversation, automatically
     * hand off to a human team member with a deterministic notice.
     */
    private function executeUncertainRoute(
        Conversation $conversation,
        string $query,
        RoutingResult $routingResult,
    ): string {
        if ($routingResult->securityStatus === 'blocked_mutation') {
            return "এই ধরনের পরিবর্তন করার সুবিধা বর্তমানে সক্রিয় নেই।";
        }

        $metadata = $conversation->metadata ?? [];
        $uncertainCount = ($metadata['uncertain_count'] ?? 0) + 1;
        $metadata['uncertain_count'] = $uncertainCount;

        // 3 consecutive uncertain turns -> Trigger human handoff
        if ($uncertainCount >= 3) {
            $metadata['uncertain_count'] = 0;
            $metadata['handoff_to_human'] = true;
            $metadata['handoff_reason'] = '3_consecutive_uncertain_turns';
            $conversation->metadata = $metadata;
            if ($conversation->exists) {
                $conversation->save();
            }

            return "Our team member will contact you soon.";
        }

        $conversation->metadata = $metadata;
        if ($conversation->exists) {
            $conversation->save();
        }

        return $this->clarificationManager->handleAnalyticAmbiguity(
            conversation: $conversation,
            rawQuery: $query,
            routingResult: $routingResult,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AGENT PROMPT WRAPPERS WITH PROVIDER FALLBACK
    // ─────────────────────────────────────────────────────────────────────────

    private function promptKnowledgeAgent(
        ?Conversation $conversation,
        string $query,
        int $workspaceId,
        \Illuminate\Database\Eloquent\Collection $retrievedHits,
        ?string $memoryContext = null,
        ?string $businessContext = null,
    ): string {
        $primaryProvider = config('ai.default', 'deepseek');
        $primaryModel = config('ai.default_model', 'deepseek-chat');
        $fallbackProvider = config('ai.fallback_provider', 'openrouter');
        $fallbackModel = config('ai.fallback_model', 'openrouter/free');

        $agent = new KnowledgeSupportAgent(
            conversation: $conversation,
            retrievedKnowledge: $retrievedHits,
            memoryContext: $memoryContext,
            businessContext: $businessContext,
        );

        // Tier 1: Try Primary Provider
        try {
            $response = $agent->prompt($query, provider: $primaryProvider, model: $primaryModel);
            $this->lastLlmUsage = $response->usage ?? null;
            return (string) $response;
        } catch (\Throwable $ePrimary) {
            Log::warning('[CustomerSupportService] Primary provider failed, attempting fallback provider', [
                'primary_provider'  => $primaryProvider,
                'fallback_provider' => $fallbackProvider,
                'error'             => $ePrimary->getMessage(),
                'trace'             => $ePrimary->getTraceAsString(),
                'workspace_id'      => $workspaceId,
            ]);

            // Tier 2: Try Secondary Fallback Provider
            if (!empty($fallbackProvider) && $fallbackProvider !== $primaryProvider) {
                try {
                    $response = $agent->prompt($query, provider: $fallbackProvider, model: $fallbackModel);
                    return (string) $response;
                } catch (\Throwable $eFallback) {
                    Log::warning('[CustomerSupportService] Fallback provider also failed', [
                        'fallback_provider' => $fallbackProvider,
                        'error'             => $eFallback->getMessage(),
                    ]);
                }
            }

            // Tier 3: Deterministic Grounded Fallback
            $topHit = $retrievedHits->first();
            if ($topHit && $topHit->finalScore >= 0.45 && !empty($topHit->faq?->answer)) {
                return $topHit->faq->answer;
            }

            return $this->defaultFallbackText();
        }
    }

    private function promptConversationalAgent(
        ?Conversation $conversation,
        string $query,
        ?string $memoryContext = null,
    ): string {
        $primaryProvider = config('ai.default', 'deepseek');
        $primaryModel = config('ai.default_model', 'deepseek-chat');
        $fallbackProvider = config('ai.fallback_provider', 'openrouter');
        $fallbackModel = config('ai.fallback_model', 'openrouter/free');

        $agent = new ConversationalSupportAgent(
            conversation: $conversation,
            memoryContext: $memoryContext,
        );

        // Tier 1: Try Primary Provider
        try {
            $response = $agent->prompt($query, provider: $primaryProvider, model: $primaryModel);
            $this->lastLlmUsage = $response->usage ?? null;
            $text = trim((string) $response);
            $text = preg_replace('/<think>.*?<\/think>\s*/is', '', $text);
            $text = trim($text);
            if ($text !== '') {
                return $text;
            }
        } catch (\Throwable $ePrimary) {
            Log::warning('[CustomerSupportService] Primary conversational agent failed, attempting fallback', [
                'primary_provider'  => $primaryProvider,
                'fallback_provider' => $fallbackProvider,
                'error'             => $ePrimary->getMessage(),
            ]);
        }

        // Tier 2: Try Fallback Provider
        if (!empty($fallbackProvider) && $fallbackProvider !== $primaryProvider) {
            try {
                $fallbackResp = $agent->prompt($query, provider: $fallbackProvider, model: $fallbackModel);
                $fallbackText = trim((string) $fallbackResp);
                $fallbackText = preg_replace('/<think>.*?<\/think>\s*/is', '', $fallbackText);
                $fallbackText = trim($fallbackText);
                if ($fallbackText !== '') {
                    return $fallbackText;
                }
            } catch (\Throwable $eFallback) {
                Log::warning('[CustomerSupportService] Fallback conversational provider also failed', [
                    'fallback_provider' => $fallbackProvider,
                    'error'             => $eFallback->getMessage(),
                ]);
            }
        }

        // Tier 3: Deterministic Polite Greeting Fallback
        return "হ্যালো! আপনাকে কীভাবে সাহায্য করতে পারি?";
    }

    /**
     * Determine if an LLM provider exception is transient and eligible for retry.
     */
    private function isTransientError(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        // Quota exhaustion is non-transient — fail fast to grounded KB answer immediately
        if (str_contains($msg, 'free-models-per-day') || str_contains($msg, 'credits') || str_contains($msg, 'insufficient_quota')) {
            return false;
        }

        $transientPatterns = [
            '429', 'rate limit', 'rate-limit', 'too many requests',
            '504', 'gateway timeout', '502', 'bad gateway', '503', 'service unavailable',
            'timeout', 'timed out', 'operation was aborted', 'connection reset',
            'curl error 28', 'curl error 52', 'curl error 56',
        ];

        foreach ($transientPatterns as $pattern) {
            if (str_contains($msg, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function defaultFallbackText(): string
    {
        return "I'm sorry, I couldn't find a direct answer to your question in our knowledge base. A support agent will be with you shortly!";
    }
}
