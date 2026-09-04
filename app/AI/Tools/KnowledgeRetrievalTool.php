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
        private readonly FAQSearch|\App\Services\Retrieval\RetrievalClient $faqSearch,
        private readonly ?int $workspaceId = null,
    ) {}

    /**
     * Get the name of the tool for LLM function calling.
     */
    public function name(): string
    {
        return 'KnowledgeRetrievalTool';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search enterprise knowledge base for customer support policies, return rules, delivery info, and FAQs.';
    }

    /**
     * Define the JSON schema for parameters.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('The search query or customer question to retrieve knowledge for.')->required(),
        ];
    }

    /**
     * Execute the tool through the Laravel AI SDK Tool contract.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = (string) ($request['query'] ?? '');

        if (trim($query) === '') {
            return 'No search query provided.';
        }

        $results = $this->faqSearch->search(
            query: $query,
            perPage: 5,
            workspaceId: $this->workspaceId,
        );

        if ($results->isEmpty()) {
            return 'No relevant knowledge base articles or FAQs found for this inquiry.';
        }

        $context = [];
        foreach ($results as $index => $result) {
            $num = $index + 1;
            $question = $result->faq?->question ?? 'N/A';
            $answer = $result->faq?->answer ?? 'N/A';
            $context[] = "Article #{$num}:\nQuestion: {$question}\nAnswer: {$answer}";
        }

        return implode("\n\n", $context);
    }
}
