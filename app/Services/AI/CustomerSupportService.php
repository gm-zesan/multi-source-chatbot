<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use Illuminate\Support\Facades\Log;

class CustomerSupportService
{
    public function __construct(
        private readonly FAQSearch $faqSearch,
        private readonly ConversationService $conversationService,
    ) {}

    /**
     * Generate AI reply for a conversation query with Knowledge Tool grounding.
     */
    public function generateReply(
        Conversation $conversation,
        string $query,
        ?int $workspaceId = null,
    ): string {
        $effectiveWorkspaceId = $workspaceId
            ?? $conversation->channelAccount?->workspace_id
            ?? \App\Models\Workspace::first()?->id
            ?? 1;

        $retrievalTool = new KnowledgeRetrievalTool(
            faqSearch: $this->faqSearch,
            workspaceId: $effectiveWorkspaceId,
        );

        $agent = new CustomerSupportAgent(
            conversation: $conversation,
            retrievalTool: $retrievalTool,
        );

        $replyText = $this->promptAgent(
            agent: $agent,
            query: $query,
            workspaceId: $effectiveWorkspaceId,
        );

        return $replyText ?? $this->defaultFallbackText();
    }

    /**
     * Save an outbound reply message along with channel delivery metadata.
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
                'source'   => 'customer_support_agent',
                'provider' => config('ai.default', 'openrouter'),
                'model'    => config('ai.default_model', 'deepseek/deepseek-chat'),
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
     *     retrieval_hits: \Illuminate\Database\Eloquent\Collection,
     *     top_hit: ?\App\Services\FAQ\FAQSearchResult,
     *     answered: bool,
     * }
     */
    public function handleQuery(string $query, int $workspaceId, ?Conversation $conversation = null): array
    {
        $retrievalHits = $this->faqSearch->search(
            query: $query,
            perPage: 5,
            workspaceId: $workspaceId,
        );

        $retrievalTool = new KnowledgeRetrievalTool(
            faqSearch: $this->faqSearch,
            workspaceId: $workspaceId,
        );

        $agent = new CustomerSupportAgent(
            conversation: $conversation,
            retrievalTool: $retrievalTool,
        );

        $replyText = $this->promptAgent(
            agent: $agent,
            query: $query,
            workspaceId: $workspaceId,
            fallbackHits: $retrievalHits,
        );

        $topHit = $retrievalHits->first();

        return [
            'reply'          => $replyText ?? $this->defaultFallbackText(),
            'retrieval_hits' => $retrievalHits,
            'top_hit'        => $topHit,
            'answered'       => $topHit !== null,
        ];
    }

    /**
     * Prompt the agent with provider fallback.
     */
    private function promptAgent(
        CustomerSupportAgent $agent,
        string $query,
        int $workspaceId,
        ?\Illuminate\Database\Eloquent\Collection $fallbackHits = null,
    ): ?string {
        try {
            $provider = config('ai.default', 'openrouter');
            $model = config('ai.default_model', 'deepseek/deepseek-chat');

            $response = $agent->prompt($query, provider: $provider, model: $model);

            return (string) $response;
        } catch (\Throwable $e) {
            Log::warning('[CustomerSupportService] Agent prompt failed, executing fallback', [
                'error'        => $e->getMessage(),
                'workspace_id' => $workspaceId,
            ]);

            $hits = $fallbackHits ?? $this->faqSearch->search(query: $query, perPage: 1, workspaceId: $workspaceId);
            $topHit = $hits->first();

            return $topHit?->faq?->answer ?? $this->defaultFallbackText();
        }
    }

    /**
     * Default polite fallback when no answer is found.
     */
    private function defaultFallbackText(): string
    {
        return "I'm sorry, I couldn't find a direct answer to your question in our knowledge base. A support agent will be with you shortly!";
    }
}
