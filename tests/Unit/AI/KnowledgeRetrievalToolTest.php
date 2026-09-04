<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\FAQ;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class KnowledgeRetrievalToolTest extends TestCase
{
    public function test_description_and_schema(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $tool = new KnowledgeRetrievalTool($faqSearchMock);

        $this->assertNotEmpty((string) $tool->description());
        $this->assertStringContainsString('knowledge base', (string) $tool->description());
    }

    public function test_handles_query_and_returns_context_text(): void
    {
        $faq = new FAQ([
            'question' => 'How long does shipping take?',
            'answer' => 'Standard shipping takes 3-5 business days.',
        ]);

        $searchResult = new FAQSearchResult(
            faq: $faq,
            keywordScore: 0.9,
            semanticScore: 0.95,
            finalScore: 0.95,
            matchType: 'hybrid',
        );

        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->once())
            ->method('search')
            ->with('How long does shipping take?', 5, 1)
            ->willReturn(new EloquentCollection([$searchResult]));

        $tool = new KnowledgeRetrievalTool($faqSearchMock, workspaceId: 1);
        $result = $tool->handle(new Request(['query' => 'How long does shipping take?']));

        $this->assertStringContainsString('Article #1:', (string) $result);
        $this->assertStringContainsString('Question: How long does shipping take?', (string) $result);
        $this->assertStringContainsString('Standard shipping takes 3-5 business days.', (string) $result);
    }

    public function test_handles_empty_search_results(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->once())
            ->method('search')
            ->willReturn(new EloquentCollection([]));

        $tool = new KnowledgeRetrievalTool($faqSearchMock);
        $result = $tool->handle(new Request(['query' => 'Where is my order?']));

        $this->assertStringContainsString('No relevant knowledge base articles', (string) $result);
    }

    public function test_handles_empty_query(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->never())->method('search');

        $tool = new KnowledgeRetrievalTool($faqSearchMock);
        $result = $tool->handle(new Request(['query' => '   ']));

        $this->assertStringContainsString('No search query provided', (string) $result);
    }
}
