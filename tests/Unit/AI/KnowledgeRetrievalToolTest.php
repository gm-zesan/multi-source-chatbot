<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\FAQ;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Exception;
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

        $schemaMock = $this->createMock(\Illuminate\Contracts\JsonSchema\JsonSchema::class);
        $schemaMock->method('string')->willReturn(new class {
            public function description(string $d): object { return $this; }
        });
        $schemaMock->method('integer')->willReturn(new class {
            public function description(string $d): object { return $this; }
            public function default(int $v): object { return $this; }
        });

        $schema = $tool->schema($schemaMock);
        $this->assertArrayHasKey('query', $schema);
        $this->assertArrayNotHasKey('workspace_id', $schema);
    }

    public function test_handles_valid_query_and_returns_context_text(): void
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

    public function test_execute_method_returns_collection_directly(): void
    {
        $faq = new FAQ([
            'question' => 'What is your return policy?',
            'answer' => 'Items can be returned within 7 days.',
        ]);

        $searchResult = new FAQSearchResult(
            faq: $faq,
            keywordScore: 0.85,
            semanticScore: 0.90,
            finalScore: 0.90,
            matchType: 'hybrid',
        );

        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->once())
            ->method('search')
            ->with('return policy', 3, 2)
            ->willReturn(new EloquentCollection([$searchResult]));

        $tool = new KnowledgeRetrievalTool($faqSearchMock, workspaceId: 1);
        $collection = $tool->execute('return policy', workspaceId: 2, perPage: 3);

        $this->assertInstanceOf(EloquentCollection::class, $collection);
        $this->assertCount(1, $collection);
        $this->assertSame('What is your return policy?', $collection->first()->faq->question);
    }

    public function test_handles_empty_search_results(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->once())
            ->method('search')
            ->willReturn(new EloquentCollection([]));

        $tool = new KnowledgeRetrievalTool($faqSearchMock, workspaceId: 1);
        $result = $tool->handle(new Request(['query' => 'Where is my order?']));

        $this->assertStringContainsString('No relevant knowledge base articles', (string) $result);
    }

    public function test_handles_empty_query(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->never())->method('search');

        $tool = new KnowledgeRetrievalTool($faqSearchMock, workspaceId: 1);
        $result = $tool->handle(new Request(['query' => '   ']));

        $this->assertStringContainsString('No search query provided', (string) $result);
    }

    public function test_handle_fails_closed_when_workspace_context_is_missing(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->never())->method('search');

        $tool = new KnowledgeRetrievalTool($faqSearchMock, workspaceId: null);
        $result = $tool->handle(new Request(['query' => 'Return policy']));

        $this->assertSame('Workspace context is missing or invalid.', (string) $result);
    }

    public function test_handle_fails_closed_when_workspace_context_is_invalid(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->never())->method('search');

        $tool = new KnowledgeRetrievalTool($faqSearchMock, workspaceId: -5);
        $result = $tool->handle(new Request(['query' => 'Return policy']));

        $this->assertSame('Workspace context is missing or invalid.', (string) $result);
    }

    public function test_handle_strictly_ignores_llm_supplied_workspace_id_override(): void
    {
        $faq = new FAQ([
            'question' => 'How to cancel?',
            'answer' => 'Click cancel in order details.',
        ]);

        $searchResult = new FAQSearchResult(
            faq: $faq,
            keywordScore: 0.9,
            semanticScore: 0.9,
            finalScore: 0.9,
            matchType: 'hybrid',
        );

        $faqSearchMock = $this->createMock(FAQSearch::class);
        // CRITICAL: Must search with authenticated runtime workspace (3), NOT caller-supplied (999)
        $faqSearchMock->expects($this->once())
            ->method('search')
            ->with('How to cancel?', 5, 3)
            ->willReturn(new EloquentCollection([$searchResult]));

        $tool = new KnowledgeRetrievalTool($faqSearchMock, workspaceId: 3);
        $result = $tool->handle(new Request([
            'query' => 'How to cancel?',
            'workspace_id' => 999, // Malicious override attempt from prompt injection
        ]));

        $this->assertStringContainsString('Article #1:', (string) $result);
    }

    public function test_execute_fails_closed_with_null_or_invalid_workspace(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->never())->method('search');

        $tool = new KnowledgeRetrievalTool($faqSearchMock, workspaceId: null);
        $collection = $tool->execute('test query', workspaceId: 0);

        $this->assertTrue($collection->isEmpty());
    }
}
