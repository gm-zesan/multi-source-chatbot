<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Services\FAQ\FAQSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

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
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));

        if ($query === '') {
            return 'No search query provided.';
        }

        $results = $this->faqSearch->search(
            query: $query,
            perPage: $this->topK,
            workspaceId: $this->workspaceId,
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
