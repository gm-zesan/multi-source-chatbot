<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\Conversation;
use App\Services\FAQ\FAQSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Company FAQ & Policy Knowledge Retrieval Tool.
 *
 * Pipeline Flow: HybridRouter (KNOWLEDGE Route) -> KnowledgeRetrievalTool -> FAQSearch -> Typesense/Vector DB
 */
class KnowledgeRetrievalTool implements Tool
{
    public function __construct(
        private readonly FAQSearch $faqSearch,
        private readonly ?int $workspaceId = null,
        private readonly int $topK = 5,
    ) {}

    /**
     * The description of what the tool does.
     */
    public function description(): Stringable|string
    {
        return 'Search the company knowledge base for official policies, delivery information, refunds, returns, product details, and frequently asked questions.';
    }

    /**
     * Standard programmatic execution method for direct routing execution.
     *
     * @param string $query Search query
     * @param int|null $workspaceId Multi-tenant workspace ID
     * @param Conversation|null $conversation Contextual conversation
     * @param string|null $contextualSignal Auxiliary context signal
     * @param int $perPage Number of results to retrieve
     * @return Collection<\App\Services\FAQ\FAQSearchResult>
     */
    public function execute(
        string $query,
        ?int $workspaceId = null,
        ?Conversation $conversation = null,
        ?string $contextualSignal = null,
        int $perPage = 5,
    ): Collection {
        $effectiveWorkspaceId = $workspaceId ?? $this->workspaceId;
        if ($effectiveWorkspaceId === null || $effectiveWorkspaceId <= 0) {
            \Illuminate\Support\Facades\Log::warning('[KnowledgeRetrievalTool] Missing or invalid workspace_id context for execute().');
            return new Collection([]);
        }

        try {
            return $this->faqSearch->search(
                query: $query,
                perPage: $perPage,
                workspaceId: $effectiveWorkspaceId,
                conversation: $conversation,
                contextualSignal: $contextualSignal,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[KnowledgeRetrievalTool] Downstream search failed: ' . $e->getMessage());
            return new Collection([]);
        }
    }

    /**
     * Execute the tool via Laravel AI Request.
     */
    public function handle(Request $request): Stringable|string
    {
        if ($this->workspaceId === null || $this->workspaceId <= 0) {
            \Illuminate\Support\Facades\Log::warning('[KnowledgeRetrievalTool] Invocation rejected: tool has missing or invalid runtime workspace context.');
            return 'Workspace context is missing or invalid.';
        }

        $query = trim((string) ($request['query'] ?? ''));
        if ($query === '') {
            return 'No search query provided.';
        }

        // Strictly enforce runtime $this->workspaceId; LLM-supplied workspace arguments are never trusted.
        $results = $this->execute(
            query: $query,
            workspaceId: $this->workspaceId,
            perPage: $this->topK,
        );

        if ($results->isEmpty()) {
            return 'No relevant knowledge base articles or FAQs found for the given query.';
        }

        $articles = [];
        foreach ($results as $index => $result) {
            $num = $index + 1;
            $faq = $result->faq;
            $q = $faq ? $faq->question : 'N/A';
            $a = $faq ? $faq->answer : 'N/A';
            $articles[] = "Article #{$num}:\nQuestion: {$q}\nAnswer: {$a}";
        }

        return implode("\n\n", $articles);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('The search query keyword or phrase to search knowledge base policies.'),
        ];
    }
}
