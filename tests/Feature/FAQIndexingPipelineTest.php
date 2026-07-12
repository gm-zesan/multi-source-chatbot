<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\FAQIndexJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\FAQ\FAQAnswerEngine;
use App\Services\FAQ\FAQIndexer;
use App\Services\FAQ\FAQScoreCalculator;
use App\Services\FAQ\FAQSearch;
use App\Services\NLP\Embedding\EmbeddingResponse;
use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\NLP\TextPreprocessor;
use App\Services\Search\TypesenseSearchResult;
use App\Services\Search\TypesenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FAQIndexingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private FAQ $faq;
    private TextPreprocessor $preprocessor;
    private EmbeddingService $embeddingMock;
    private TypesenseService $typesenseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preprocessor = TextPreprocessor::make();

        $this->workspace = Workspace::create(['name' => 'Test', 'slug' => 'test']);
        $channel = Channel::create(['slug' => 'test', 'name' => 'Test', 'is_active' => true]);
        $account = ChannelAccount::create([
            'channel_id' => $channel->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Test',
            'external_id' => 'ext_1',
            'access_token' => 'token',
        ]);
        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id' => 'test_user',
            'customer_name' => 'Test',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'body' => 'Test message',
            'direction' => 'inbound',
            'type' => 'text',
        ]);

        $this->faq = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question' => 'How do I reset my password?',
            'answer' => 'Go to settings and click reset password.',
            'priority' => 80,
            'hit_count' => 100,
            'is_active' => true,
        ]);
    }

    // ─── Fake EmbeddingService ───────────────────────────────────────

    private function fakeEmbeddingService(?float $dim = 0.01): EmbeddingService
    {
        $mock = $this->createMock(EmbeddingService::class);
        $mock->method('embed')->willReturn(
            new EmbeddingResponse(
                vector: array_fill(0, 768, $dim ?? 0.01),
                dimensions: 768,
                model: 'test-model',
            )
        );
        $mock->method('config')->willReturn('test-model');
        $mock->method('embedBatch')->willReturn(
            new \App\Services\NLP\Embedding\BatchEmbeddingResponse(
                items: [
                    new EmbeddingResponse(
                        vector: array_fill(0, 768, 0.01),
                        dimensions: 768,
                        model: 'test-model',
                    ),
                ],
                dimensions: 768,
                model: 'test-model',
            )
        );

        return $mock;
    }

    /**
     * Fake TypesenseService that tracks upserted documents in memory.
     */
    private function fakeTypesenseService(): TypesenseService
    {
        $mock = $this->createMock(TypesenseService::class);
        $mock->method('resolveCollectionName')->willReturnArgument(0);

        return $mock;
    }

    // ─── Tests ────────────────────────────────────────────────────────

    public function test_faq_index_job_builds_correct_document(): void
    {
        $embeddings = $this->fakeEmbeddingService();
        $typesense = $this->fakeTypesenseService();

        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $embeddings,
            typesense: $typesense,
        );

        $document = $indexer->buildDocument($this->faq);

        $this->assertArrayHasKey('id', $document);
        $this->assertArrayHasKey('workspace_id', $document);
        $this->assertArrayHasKey('question', $document);
        $this->assertArrayHasKey('answer', $document);
        $this->assertArrayHasKey('searchable_text', $document);
        $this->assertArrayHasKey('priority', $document);
        $this->assertArrayHasKey('embedding', $document);
        $this->assertArrayHasKey('is_active', $document);
        $this->assertArrayHasKey('created_at', $document);

        $this->assertSame((string) $this->faq->id, $document['id']);
        $this->assertSame($this->faq->workspace_id, $document['workspace_id']);
        $this->assertSame($this->faq->question, $document['question']);
        $this->assertSame($this->faq->answer, $document['answer']);
        $this->assertSame($this->faq->is_active, $document['is_active']);
        $this->assertCount(768, $document['embedding']);
    }

    public function test_faq_index_job_indexes_searchable_faq(): void
    {
        $typesense = $this->createMock(TypesenseService::class);
        $typesense->expects($this->once())
            ->method('upsertDocument')
            ->with('faqs', $this->arrayHasKey('id'));
        $typesense->expects($this->never())
            ->method('deleteDocument');

        $embeddings = $this->fakeEmbeddingService();
        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $embeddings,
            typesense: $typesense,
        );

        $job = new FAQIndexJob($this->faq, 'index');
        $job->handle($this->preprocessor, $embeddings, $typesense, $indexer);
    }

    public function test_faq_index_job_skips_inactive_faq(): void
    {
        $this->faq->update(['is_active' => false]);
        $faq = $this->faq->fresh();

        $typesense = $this->createMock(TypesenseService::class);
        $typesense->expects($this->once())
            ->method('deleteDocument')
            ->with('faqs', (string) $faq->id);
        $typesense->expects($this->never())
            ->method('upsertDocument');

        $embeddings = $this->fakeEmbeddingService();
        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $embeddings,
            typesense: $typesense,
        );

        $job = new FAQIndexJob($faq, 'index');
        $job->handle($this->preprocessor, $embeddings, $typesense, $indexer);
    }

    public function test_faq_index_job_skips_deleted_faq(): void
    {
        $this->faq->delete();
        $faq = $this->faq->fresh();

        $typesense = $this->createMock(TypesenseService::class);
        $typesense->expects($this->once())
            ->method('deleteDocument')
            ->with('faqs', (string) $faq->id);
        $typesense->expects($this->never())
            ->method('upsertDocument');

        $embeddings = $this->fakeEmbeddingService();
        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $embeddings,
            typesense: $typesense,
        );

        $job = new FAQIndexJob($faq, 'index');
        $job->handle($this->preprocessor, $embeddings, $typesense, $indexer);
    }

    public function test_faq_index_job_deletes_document_on_delete_action(): void
    {
        $typesense = $this->createMock(TypesenseService::class);
        $typesense->expects($this->once())
            ->method('deleteDocument')
            ->with('faqs', (string) $this->faq->id);

        $embeddings = $this->fakeEmbeddingService();
        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $embeddings,
            typesense: $typesense,
        );

        $job = new FAQIndexJob($this->faq, 'delete');
        $job->handle($this->preprocessor, $embeddings, $typesense, $indexer);
    }

    public function test_faq_search_falls_back_to_keyword_when_embedding_fails(): void
    {
        $embeddingFails = $this->createMock(EmbeddingService::class);
        $embeddingFails->method('embed')
            ->willThrowException(new \App\Services\NLP\Embedding\EmbeddingException('Service unreachable'));

        $typesense = $this->createMock(TypesenseService::class);
        // hybridSearch should NOT be called
        $typesense->expects($this->never())->method('hybridSearch');
        // search (keyword) SHOULD be called
        $typesense->expects($this->once())
            ->method('search')
            ->willReturn([
                'results' => [],
                'found' => 0,
                'page' => 1,
            ]);

        $search = new FAQSearch(
            preprocessor: $this->preprocessor,
            embeddings: $embeddingFails,
            typesense: $typesense,
        );

        $results = $search->search('test query', 10, $this->workspace->id);

        $this->assertCount(0, $results);
    }

    public function test_faq_search_uses_hybrid_when_embedding_succeeds(): void
    {
        $embeddings = $this->fakeEmbeddingService();

        $typesense = $this->createMock(TypesenseService::class);
        // hybridSearch SHOULD be called
        $typesense->expects($this->once())
            ->method('hybridSearch')
            ->willReturn([
                'results' => [],
                'found' => 0,
                'page' => 1,
            ]);
        // search (keyword) should NOT be called
        $typesense->expects($this->never())->method('search');

        $search = new FAQSearch(
            preprocessor: $this->preprocessor,
            embeddings: $embeddings,
            typesense: $typesense,
        );

        $results = $search->search('test query', 10, $this->workspace->id);

        $this->assertCount(0, $results);
    }

    public function test_faq_indexer_batch_skips_non_searchable(): void
    {
        $activeFaq = $this->faq;
        $inactiveFaq = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question' => 'Inactive question?',
            'answer' => 'Inactive answer.',
            'is_active' => false,
        ]);

        $typesense = $this->createMock(TypesenseService::class);
        $typesense->expects($this->once())
            ->method('deleteDocument')
            ->with('faqs', (string) $inactiveFaq->id);
        $typesense->expects($this->once())
            ->method('upsertDocuments')
            ->with('faqs', $this->callback(function (array $docs) {
                return count($docs) === 1 && $docs[0]['is_active'] === true;
            }));

        $embeddings = $this->fakeEmbeddingService();
        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $embeddings,
            typesense: $typesense,
        );

        $indexer->batchIndex(new \Illuminate\Database\Eloquent\Collection([$activeFaq, $inactiveFaq]));
    }

    public function test_faq_answer_engine_returns_unanswered_on_empty_query(): void
    {
        $embeddings = $this->fakeEmbeddingService();
        $searchMock = $this->createMock(FAQSearch::class);
        $searchMock->method('search')->willReturn(new \Illuminate\Database\Eloquent\Collection([]));
        $calculator = new FAQScoreCalculator();
        $engine = new FAQAnswerEngine(
            preprocessor: $this->preprocessor,
            search: $searchMock,
            calculator: $calculator,
        );

        $result = $engine->answer(
            query: '',
            workspaceId: $this->workspace->id,
        );

        $this->assertFalse($result->answered);
        $this->assertSame('none', $result->matchType);
    }

    public function test_faq_indexer_builds_document_without_embedding_on_failure(): void
    {
        $embeddingFails = $this->createMock(EmbeddingService::class);
        $embeddingFails->method('embed')
            ->willThrowException(new \App\Services\NLP\Embedding\EmbeddingException('Service down'));

        $typesense = $this->fakeTypesenseService();
        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $embeddingFails,
            typesense: $typesense,
        );

        $document = $indexer->buildDocument($this->faq);

        $this->assertArrayHasKey('embedding', $document);
        $this->assertEmpty($document['embedding']);
        $this->assertArrayHasKey('searchable_text', $document);
    }

    public function test_faq_index_job_is_dispatched_on_create(): void
    {
        Queue::fake();

        $faq = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question' => 'New question?',
            'answer' => 'New answer.',
            'is_active' => true,
        ]);

        // Simulate what FAQService::create() does
        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $this->fakeEmbeddingService(),
            typesense: $this->fakeTypesenseService(),
        );
        $indexer->dispatchIndex($faq, 'index');

        Queue::assertPushed(FAQIndexJob::class, function (FAQIndexJob $job) use ($faq) {
            return $job->faq->id === $faq->id && $job->action === 'index';
        });
    }

    public function test_faq_index_job_is_dispatched_on_delete(): void
    {
        Queue::fake();

        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $this->fakeEmbeddingService(),
            typesense: $this->fakeTypesenseService(),
        );
        $indexer->dispatchIndex($this->faq, 'delete');

        Queue::assertPushed(FAQIndexJob::class, function (FAQIndexJob $job) {
            return $job->faq->id === $this->faq->id && $job->action === 'delete';
        });
    }

    public function test_faq_index_job_handles_duplicate_indexing_gracefully(): void
    {
        $typesense = $this->createMock(TypesenseService::class);
        $typesense->expects($this->exactly(2))
            ->method('upsertDocument')
            ->with('faqs', $this->arrayHasKey('id'));

        $embeddings = $this->fakeEmbeddingService();
        $indexer = new FAQIndexer(
            preprocessor: $this->preprocessor,
            embeddings: $embeddings,
            typesense: $typesense,
        );

        // Dispatch twice — both should succeed
        $job1 = new FAQIndexJob($this->faq, 'index');
        $job1->handle($this->preprocessor, $embeddings, $typesense, $indexer);

        $job2 = new FAQIndexJob($this->faq, 'index');
        $job2->handle($this->preprocessor, $embeddings, $typesense, $indexer);
    }
}
