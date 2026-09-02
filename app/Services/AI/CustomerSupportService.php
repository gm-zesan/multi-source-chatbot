<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\AI\Agents\ActionOrchestratorAgent;
use App\AI\Agents\ConversationalSupportAgent;
use App\AI\Agents\CustomerSupportAgent;
use App\AI\Agents\KnowledgeSupportAgent;
use App\AI\LLM\LLMClient;
use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\AI\Tools\CancelOrderTool;
use App\AI\Tools\CreateTicketTool;
use App\AI\Tools\GetOrderTool;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Business\BusinessSourceOfTruthService;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Services\Memory\ConversationMemoryService;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;

class CustomerSupportService
{
    private readonly HybridRouter $router;
    private readonly ActionSafetyService $actionSafety;
    private readonly ContextualQueryBuilder $contextualQueryBuilder;
    private readonly ConversationMemoryService $memoryService;
    private readonly BusinessSourceOfTruthService $businessService;
    private readonly LLMClient $llmClient;

    public function __construct(
        private readonly FAQSearch $faqSearch,
        private readonly ConversationService $conversationService,
        ?HybridRouter $router = null,
        ?ActionSafetyService $actionSafety = null,
        ?ContextualQueryBuilder $contextualQueryBuilder = null,
        ?ConversationMemoryService $memoryService = null,
        ?BusinessSourceOfTruthService $businessService = null,
        ?LLMClient $llmClient = null,
    ) {
        $this->router = $router ?? new HybridRouter();
        $this->actionSafety = $actionSafety ?? new ActionSafetyService();
        $this->contextualQueryBuilder = $contextualQueryBuilder ?? new ContextualQueryBuilder();
        $this->memoryService = $memoryService ?? app(ConversationMemoryService::class);
        $this->businessService = $businessService ?? new BusinessSourceOfTruthService();
        $this->llmClient = $llmClient ?? new LLMClient();
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

        // ── 1. Hybrid Routing ────────────────────────────────────────────────
        $routingResult = $this->router->route(
            query: $query,
            conversation: $conversation,
            workspaceId: $effectiveWorkspaceId,
        );

        // ── 1.5 Retrieve Memory & Live Business Source of Truth ─────────────
        $memoryContext = $this->memoryService->retrieveContext(
            conversation: $conversation,
            query: $query,
            workspaceId: $effectiveWorkspaceId,
        );

        $businessContext = $this->businessService->buildBusinessContext(
            query: $query,
            conversation: $conversation,
            workspaceId: $effectiveWorkspaceId,
        );

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
            RouteType::ACTION => $this->executeActionRoute(
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
        $replyText = $this->generateReply(
            conversation: $conversation,
            query: $message->body,
        );

        if (trim($replyText) === '') {
            return null;
        }

        return $this->saveOutboundReply(
            conversation: $conversation,
            replyText: $replyText,
            deliveryResponse: $deliveryResponse,
        );
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
        $t_start = microtime(true);

        $routingResult = $this->router->route(
            query: $query,
            conversation: $conversation,
            workspaceId: $workspaceId,
        );

        $retrievalHits = new \Illuminate\Database\Eloquent\Collection();
        $topHit = null;
        $answered = false;

        if ($routingResult->isKnowledge() || $routingResult->isUncertain()) {
            $contextualSignal = $this->contextualQueryBuilder->resolveContextualSignal($query, $conversation);
            $retrievalHits = $this->faqSearch->search(
                query: $query,
                perPage: 5,
                workspaceId: $workspaceId,
                conversation: $conversation,
                contextualSignal: $contextualSignal,
            );
            $topHit = $retrievalHits->first();
            $answered = $topHit !== null && $topHit->finalScore >= 0.45;
        }

        $groundedHits = $retrievalHits->filter(fn ($h) => $h->finalScore >= 0.45);

        // ── Retrieve Unified Memory & Live Business Data ─────
        $memoryContext = $this->memoryService->retrieveContext(
            conversation: $conversation,
            query: $query,
            workspaceId: $workspaceId,
        );

        $businessContext = $this->businessService->buildBusinessContext(
            query: $query,
            conversation: $conversation,
            workspaceId: $workspaceId,
        );

        $replyText = match ($routingResult->route) {
            RouteType::KNOWLEDGE => $this->promptKnowledgeAgent(
                conversation: $conversation,
                query: $query,
                workspaceId: $workspaceId,
                retrievedHits: $groundedHits,
                memoryContext: $memoryContext,
                businessContext: $businessContext,
            ),
            RouteType::CHAT => $this->promptConversationalAgent(
                conversation: $conversation,
                query: $query,
                memoryContext: $memoryContext,
            ),
            RouteType::ACTION => $this->executeActionRoute(
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

        $totalE2eMs = round((microtime(true) - $t_start) * 1000, 2);

        $suggestions = $routingResult->isUncertain() ? $this->getClarificationSuggestions($query) : [];
        $sources = $routingResult->isKnowledge() ? $this->formatGroundedSources($retrievalHits, $query) : [];
        $isHandoff = ($routingResult->route === RouteType::ACTION) ||
            (!empty($conversation?->metadata['handoff_to_human'])) ||
            (stripos($replyText ?? '', 'team member will contact you') !== false);

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
            'raw_llm_response' => [
                'provider' => config('ai.default', 'deepseek'),
                'model' => config('ai.default_model', 'deepseek-chat'),
                'raw_reply_text' => $replyText,
                'grounded_documents_count' => $groundedHits->count(),
                'grounded_faq_questions' => $groundedHits->map(fn($h) => $h->faq?->question)->values()->all(),
            ],
            'routing_telemetry' => array_merge($routingResult->toArray(), [
                'total_e2e_ms' => $totalE2eMs,
            ]),
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

        $searchQuery = $this->contextualQueryBuilder->buildContextualQuery($query, $conversation);

        $retrievalHits = $this->faqSearch->search(
            query: $searchQuery,
            perPage: 5,
            workspaceId: $workspaceId,
        );

        $groundedHits = $retrievalHits->filter(fn ($h) => $h->finalScore >= 0.45);

        return $this->promptKnowledgeAgent(
            conversation: $conversation,
            query: $query,
            workspaceId: $workspaceId,
            retrievedHits: $groundedHits,
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
    private function executeActionRoute(
        Conversation $conversation,
        string $query,
        int $workspaceId,
        RoutingResult $routingResult,
    ): string {
        $this->resetUncertainCount($conversation);

        // Clear any leftover pending action state safely
        $this->actionSafety->clearPendingAction($conversation);

        return "This is an action request. Our team member will contact you soon.";
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
        // Ensure no pending action is set
        if ($conversation->exists) {
            $this->actionSafety->clearPendingAction($conversation);
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

        $qLower = mb_strtolower($query);
        $isBengali = (bool) preg_match('/[\p{Bengali}]/u', $query);

        // 1. Cancellation / Refund related ambiguity
        if (str_contains($qLower, 'cancel') || str_contains($qLower, 'বাতিল') || str_contains($qLower, 'ক্যানসেল') || str_contains($qLower, 'refund') || str_contains($qLower, 'রিফান্ড')) {
            if ($isBengali) {
                return "আপনার প্রশ্নটি আরও ভালোভাবে বুঝতে অনুগ্রহ করে একটু বিস্তারিত বলুন।\n\nআপনি কি জানতে চাচ্ছেন:\n• অর্ডার বাতিলের নিয়ম ও পলিসি কী?\n• রিফান্ড কীভাবে কাজ করে?\n• অন্য কোনো তথ্য?";
            }
            return "Could you please provide a little more detail so I can better understand what you need?\n\nDid you mean:\n• Ask about the order cancellation policy\n• Learn how order cancellation works\n• Something else";
        }

        // 2. Change / Update / Payment related ambiguity
        if (str_contains($qLower, 'change') || str_contains($qLower, 'update') || str_contains($qLower, 'payment') || str_contains($qLower, 'card') || str_contains($qLower, 'পরিবর্তন') || str_contains($qLower, 'পেমেন্ট')) {
            if ($isBengali) {
                return "আপনার অনুরোধটি আরও স্পষ্টভাবে বুঝতে অনুগ্রহ করে বিস্তারিত বলুন।\n\nআপনি কি জানতে চাচ্ছেন:\n• পেমেন্ট মেথড বা কার্ড পরিবর্তনের নিয়ম\n• অ্যাকাউন্ট তথ্য আপডেট করার নিয়ম\n• অন্য কোনো বিষয়?";
            }
            return "Could you please provide a little more detail so I can better understand what you need?\n\nDid you mean:\n• How to change your payment method\n• How to update your account information\n• Something else";
        }

        // 3. Invoice / Billing related ambiguity
        if (str_contains($qLower, 'invoice') || str_contains($qLower, 'bill') || str_contains($qLower, 'ইনভয়েস') || str_contains($qLower, 'বিল')) {
            if ($isBengali) {
                return "আপনার ইনভয়েস সম্পর্কিত প্রশ্নটি বিস্তারিত জানালে সাহায্য করতে সুবিধা হবে।\n\nআপনি কি জানতে চাচ্ছেন:\n• ইনভয়েস বা বিল কোথায় পাওয়া যাবে\n• বিলিং হিস্টোরি দেখার নিয়ম\n• অন্য কোনো তথ্য?";
            }
            return "Could you please provide a little more detail so I can better understand what you need?\n\nAre you asking:\n• Where to find your invoices\n• How invoices and billing work\n• Something else";
        }

        // 4. Default Clarification
        if ($isBengali) {
            return "আপনার অনুরোধটি স্পষ্টভাবে বুঝতে পারিনি। অনুগ্রহ করে একটু বিস্তারিত বলুন—যেমন আমাদের বিভিন্ন প্ল্যান, বিলিং, অ্যাকাউন্ট সেটিংস অথবা প্ল্যাটফর্ম ফিচার সম্পর্কে জানতে চাইতে পারেন।";
        }

        return "Could you please provide a little more detail so I can better understand what you need? You can ask about our plans, billing, account settings, or general platform features.";
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

        // Check if CustomerSupportAgent was faked in testing
        if (CustomerSupportAgent::isFaked()) {
            $fakeAgent = new CustomerSupportAgent(
                conversation: $conversation,
                retrievalTool: new KnowledgeRetrievalTool($this->faqSearch, $workspaceId),
            );
            return (string) $fakeAgent->prompt($query, provider: $primaryProvider, model: $primaryModel);
        }

        $agent = new KnowledgeSupportAgent(
            conversation: $conversation,
            retrievedKnowledge: $retrievedHits,
            memoryContext: $memoryContext,
            businessContext: $businessContext,
        );

        // Tier 1: Try Primary Provider
        try {
            $response = $agent->prompt($query, provider: $primaryProvider, model: $primaryModel);
            return (string) $response;
        } catch (\Throwable $ePrimary) {
            Log::warning('[CustomerSupportService] Primary provider failed, attempting fallback provider', [
                'primary_provider'  => $primaryProvider,
                'fallback_provider' => $fallbackProvider,
                'error'             => $ePrimary->getMessage(),
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

        if (CustomerSupportAgent::isFaked()) {
            $fakeAgent = new CustomerSupportAgent(conversation: $conversation);
            return (string) $fakeAgent->prompt($query, provider: $primaryProvider, model: $primaryModel);
        }

        $agent = new ConversationalSupportAgent(
            conversation: $conversation,
            memoryContext: $memoryContext,
        );

        // Tier 1: Try Primary Provider
        try {
            return (string) $agent->prompt($query, provider: $primaryProvider, model: $primaryModel);
        } catch (\Throwable $ePrimary) {
            Log::warning('[CustomerSupportService] Primary conversational agent failed, attempting fallback', [
                'primary_provider'  => $primaryProvider,
                'fallback_provider' => $fallbackProvider,
                'error'             => $ePrimary->getMessage(),
            ]);

            // Tier 2: Try Fallback Provider
            if (!empty($fallbackProvider) && $fallbackProvider !== $primaryProvider) {
                try {
                    return (string) $agent->prompt($query, provider: $fallbackProvider, model: $fallbackModel);
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

    private function promptActionOrchestratorAgent(
        Conversation $conversation,
        string $query,
        int $workspaceId,
    ): string {
        try {
            $provider = config('ai.default', 'deepseek');
            $model = config('ai.default_model', 'deepseek-chat');

            $tools = [
                new CancelOrderTool(workspaceId: $workspaceId, conversation: $conversation),
                new GetOrderTool(workspaceId: $workspaceId, conversation: $conversation),
                new CreateTicketTool(workspaceId: $workspaceId, conversation: $conversation),
            ];

            $agent = new ActionOrchestratorAgent(
                conversation: $conversation,
                actionTools: $tools,
            );

            return (string) $agent->prompt($query, provider: $provider, model: $model);
        } catch (\Throwable $e) {
            Log::warning('[CustomerSupportService] Action orchestrator failed', [
                'error' => $e->getMessage(),
            ]);
            return "আপনার অ্যাকশন অনুরোধটি প্রসেস করা সম্ভব হয়নি। অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন।";
        }
    }

    private function defaultFallbackText(): string
    {
        return "I'm sorry, I couldn't find a direct answer to your question in our knowledge base. A support agent will be with you shortly!";
    }
}
