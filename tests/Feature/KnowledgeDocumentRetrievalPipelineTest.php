<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Enums\DocumentLifecycleStatus;
use App\Enums\IngestionPurpose;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\FAQ\FAQSearch;
use App\Services\Knowledge\KnowledgeDocumentService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KnowledgeDocumentRetrievalPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private KnowledgeDocumentService $documentService;
    private User $adminUser;

    /**
     * Dynamic state for the single unified HTTP fake.
     *
     * @var array|\Closure
     */
    private mixed $searchHits = [];
    private int $syncStatusCode = 200;
    private ?\Closure $syncCallback = null;
    private ?\Closure $deleteCallback = null;
    private array $syncedChunks = [];
    private array $deletedChunks = [];
    private array $httpTimeline = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->searchHits = [];
        $this->syncStatusCode = 200;
        $this->syncCallback = null;
        $this->deleteCallback = null;
        $this->syncedChunks = [];
        $this->deletedChunks = [];
        $this->httpTimeline = [];

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->workspace = Workspace::create([
            'name' => 'Acme Support',
            'slug' => 'acme-support',
        ]);

        $this->adminUser = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->adminUser->assignRole(RoleEnum::ADMIN->value);
        Auth::login($this->adminUser);

        $this->documentService = app(KnowledgeDocumentService::class);

        // Register a single unified HTTP fake that dynamically delegates to test properties
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();

            if (str_contains($url, '/api/v1/faqs/sync')) {
                $payload = $request->data();
                $this->syncedChunks[] = $payload;
                $this->httpTimeline[] = [
                    'action' => 'sync',
                    'id'     => $payload['id'] ?? null,
                    'url'    => $url,
                ];

                if ($this->syncCallback !== null) {
                    return ($this->syncCallback)($request);
                }

                if ($this->syncStatusCode !== 200) {
                    return Http::response(['detail' => 'Sync failed'], $this->syncStatusCode);
                }
                return Http::response(['status' => 'synced', 'id' => $payload['id'] ?? 'doc_test'], 200);
            }

            if (str_contains($url, '/api/v1/faqs/')) {
                $this->deletedChunks[] = $url;
                $this->httpTimeline[] = [
                    'action' => 'delete',
                    'url'    => $url,
                ];

                if ($this->deleteCallback !== null) {
                    return ($this->deleteCallback)($request);
                }

                return Http::response(['status' => 'deleted'], 200);
            }

            if (str_contains($url, '/api/v1/search')) {
                if (is_callable($this->searchHits)) {
                    return ($this->searchHits)($request);
                }

                $hits = is_array($this->searchHits) ? $this->searchHits : [];
                return Http::response([
                    'results' => $hits,
                    'total'   => count($hits),
                ], 200);
            }

            return Http::response([], 200);
        });
    }

    /**
     * 1. Uploading a supported text document and retrieving a unique fact from it.
     */
    public function test_uploading_text_document_indexes_chunks_and_retrieves_unique_fact(): void
    {
        $uniqueFact = 'Policy Code DEL-404: Free same-day replacement is guaranteed for all damaged packages.';
        $title = 'Delivery and Replacement Policy';

        // Upload text document via storeText
        $document = $this->documentService->storeText(
            title: $title,
            textContent: $uniqueFact,
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $this->workspace->id
        );

        $this->assertEquals(DocumentLifecycleStatus::ACTIVE, $document->lifecycle_status);
        $this->assertTrue($document->is_active);
        $this->assertGreaterThanOrEqual(1, $document->chunk_count);
        $this->assertNull($document->error_message);

        // Typesense returns the chunk
        $this->searchHits = [
            [
                'id'            => "doc_{$document->id}_0",
                'workspace_id'  => $this->workspace->id,
                'question'      => $title,
                'answer'        => $uniqueFact,
                'document_type' => 'knowledge_document',
                'score'         => 0.95,
                'match_type'    => 'hybrid',
            ],
        ];

        /** @var KnowledgeRetrievalTool $tool */
        $tool = app(KnowledgeRetrievalTool::class);
        $retrieved = $tool->execute('What is Policy Code DEL-404 replacement rule?', $this->workspace->id);

        $this->assertNotEmpty($retrieved);
        $this->assertTrue($retrieved->contains(function ($item) use ($uniqueFact) {
            return str_contains($item->faq->answer, 'Policy Code DEL-404') && str_contains($item->faq->answer, 'same-day replacement');
        }));
    }

    /**
     * 2. Uploading unsupported PDF/DOCX fails gracefully without falsely reporting success.
     */
    public function test_unsupported_pdf_or_docx_document_fails_gracefully_without_false_success(): void
    {
        // Simulate an uploaded PDF document
        $fakePdf = UploadedFile::fake()->create('company_manual.pdf', 100, 'application/pdf');

        $document = $this->documentService->store(
            file: $fakePdf,
            title: 'Company Manual',
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $this->workspace->id
        );

        $this->assertEquals(DocumentLifecycleStatus::FAILED, $document->lifecycle_status);
        $this->assertFalse($document->is_active);
        $this->assertNotNull($document->error_message);
        $this->assertStringContainsString('Unsupported file format for text extraction (.pdf)', $document->error_message);

        // Verify it cannot be retrieved via DB fallback or mapped results
        /** @var FAQSearch $search */
        $search = app(FAQSearch::class);
        $results = $search->search('Company Manual', $this->workspace->id);
        $this->assertCount(0, $results);
    }

    /**
     * 3. Verifying the retrieved context belongs to the correct workspace.
     */
    public function test_retrieved_context_enforces_strict_workspace_isolation(): void
    {
        $workspaceB = Workspace::create(['name' => 'Beta Corp', 'slug' => 'beta-corp']);
        $adminUserB = User::factory()->create([
            'workspace_id' => $workspaceB->id,
        ]);
        $adminUserB->assignRole(RoleEnum::ADMIN->value);

        $docA = $this->documentService->storeText(
            title: 'Doc Alpha',
            textContent: 'Confidential Workspace Alpha Token: WSK-ALPHA-1111',
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $this->workspace->id
        );

        Auth::login($adminUserB);
        $docB = $this->documentService->storeText(
            title: 'Doc Beta',
            textContent: 'Confidential Workspace Beta Token: WSK-BETA-2222',
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $workspaceB->id
        );
        Auth::login($this->adminUser);

        // Dynamic search hits responding based on requested workspace_id
        $this->searchHits = function (\Illuminate\Http\Client\Request $request) use ($docA, $docB, $workspaceB) {
            $payload = json_decode($request->body(), true) ?? [];
            $reqWs = $payload['workspace_id'] ?? null;

            if ($reqWs === $this->workspace->id) {
                return Http::response([
                    'results' => [
                        [
                            'id'            => "doc_{$docA->id}_0",
                            'workspace_id'  => $this->workspace->id,
                            'question'      => 'Doc Alpha',
                            'answer'        => 'Confidential Workspace Alpha Token: WSK-ALPHA-1111',
                            'document_type' => 'knowledge_document',
                            'score'         => 0.95,
                        ],
                    ],
                    'total' => 1,
                ], 200);
            }

            return Http::response([
                'results' => [
                    [
                        'id'            => "doc_{$docB->id}_0",
                        'workspace_id'  => $workspaceB->id,
                        'question'      => 'Doc Beta',
                        'answer'        => 'Confidential Workspace Beta Token: WSK-BETA-2222',
                        'document_type' => 'knowledge_document',
                        'score'         => 0.95,
                    ],
                ],
                'total' => 1,
            ], 200);
        };

        /** @var KnowledgeRetrievalTool $tool */
        $tool = app(KnowledgeRetrievalTool::class);

        // Query workspace A
        $resultsA = $tool->execute('Confidential Workspace Token', $this->workspace->id);
        $this->assertTrue($resultsA->contains(fn ($hit) => str_contains($hit->faq->answer, 'WSK-ALPHA-1111')));
        $this->assertFalse($resultsA->contains(fn ($hit) => str_contains($hit->faq->answer, 'WSK-BETA-2222')));

        // Query workspace B
        $resultsB = $tool->execute('Confidential Workspace Token', $workspaceB->id);
        $this->assertTrue($resultsB->contains(fn ($hit) => str_contains($hit->faq->answer, 'WSK-BETA-2222')));
        $this->assertFalse($resultsB->contains(fn ($hit) => str_contains($hit->faq->answer, 'WSK-ALPHA-1111')));
    }

    /**
     * 4. Preventing deleted or inactive documents from being retrieved.
     */
    public function test_preventing_deleted_or_inactive_documents_from_being_retrieved(): void
    {
        $doc = $this->documentService->storeText(
            title: 'Secret Promotion',
            textContent: 'Promo code SECRET50 grants 50% discount on all purchases.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $this->workspace->id
        );

        $chunkHit = [
            'id'            => "doc_{$doc->id}_0",
            'workspace_id'  => $this->workspace->id,
            'question'      => 'Secret Promotion',
            'answer'        => 'Promo code SECRET50 grants 50% discount on all purchases.',
            'document_type' => 'knowledge_document',
            'score'         => 0.95,
        ];

        // 1. Soft-delete the document
        $this->documentService->delete($doc);
        $this->assertSoftDeleted('knowledge_documents', ['id' => $doc->id]);

        // Mock Typesense returning stale hit (e.g. before asynchronous cleanup or if index lagged)
        $this->searchHits = [$chunkHit];

        /** @var FAQSearch $search */
        $search = app(FAQSearch::class);
        $results = $search->search('SECRET50 discount', $this->workspace->id);

        // RetrievalClient::mapResults MUST filter out deleted document
        $this->assertCount(0, $results);

        // 2. Inactive / Failed document
        $docInactive = $this->documentService->storeText(
            title: 'Inactive Policy',
            textContent: 'Inactive policy content should never be returned.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $this->workspace->id
        );
        $docInactive->update(['is_active' => false, 'lifecycle_status' => DocumentLifecycleStatus::FAILED]);

        $inactiveHit = [
            'id'            => "doc_{$docInactive->id}_0",
            'workspace_id'  => $this->workspace->id,
            'question'      => 'Inactive Policy',
            'answer'        => 'Inactive policy content should never be returned.',
            'document_type' => 'knowledge_document',
            'score'         => 0.95,
        ];

        $this->searchHits = [$inactiveHit];
        $resultsInactive = $search->search('Inactive policy content', $this->workspace->id);
        $this->assertCount(0, $resultsInactive);
    }

    /**
     * 5. Handling parsing, embedding, and indexing failures correctly.
     */
    public function test_handling_indexing_failures_correctly(): void
    {
        // Typesense sync endpoint returns 500 server error
        $this->syncStatusCode = 500;

        $doc = $this->documentService->storeText(
            title: 'Failing Document',
            textContent: 'Content that will fail during indexing sync.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $this->workspace->id
        );

        $this->assertEquals(DocumentLifecycleStatus::FAILED, $doc->lifecycle_status);
        $this->assertFalse($doc->is_active);
        $this->assertNotNull($doc->error_message);
        $this->assertStringContainsString('Failed to sync chunk', $doc->error_message);
    }

    /**
     * 6. Retrying ingestion without creating duplicate chunks.
     */
    public function test_retrying_ingestion_is_safe_and_idempotent_without_duplicate_chunks(): void
    {
        // 1. Initial attempt fails
        $this->syncStatusCode = 503;

        $doc = $this->documentService->storeText(
            title: 'Retryable Manual',
            textContent: 'Step 1: Restart device. Step 2: Clear cache. Step 3: Verify network.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $this->workspace->id
        );

        $this->assertEquals(DocumentLifecycleStatus::FAILED, $doc->lifecycle_status);
        $this->assertEquals(0, $doc->chunk_count);

        // 2. Retry with service healthy
        $this->syncStatusCode = 200;

        $retriedDoc = $this->documentService->retry($doc->id);

        $this->assertEquals(DocumentLifecycleStatus::ACTIVE, $retriedDoc->lifecycle_status);
        $this->assertTrue($retriedDoc->is_active);
        $this->assertEquals(1, $retriedDoc->chunk_count);
        $this->assertNull($retriedDoc->error_message);

        // 3. Retry again (re-indexing)
        $reRetried = $this->documentService->retry($retriedDoc->id);
        $this->assertEquals(DocumentLifecycleStatus::ACTIVE, $reRetried->lifecycle_status);
        $this->assertEquals(1, $reRetried->chunk_count);
    }

    /**
     * 7. Preserving existing FAQ retrieval behavior alongside knowledge documents.
     */
    public function test_preserves_existing_faq_retrieval_behavior(): void
    {
        // Standard FAQ
        $faq = FAQ::create([
            'workspace_id'  => $this->workspace->id,
            'question'      => 'What is the return window?',
            'answer'        => 'Our standard return window is 30 days from delivery date.',
            'document_type' => 'faq',
            'is_active'     => true,
        ]);

        // General Knowledge Document
        $doc = $this->documentService->storeText(
            title: 'Warehouse Operations Manual',
            textContent: 'Warehouse receiving hours are Monday through Friday, 8:00 AM to 4:00 PM EST.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $this->workspace->id
        );

        $this->searchHits = function (\Illuminate\Http\Client\Request $request) use ($faq, $doc) {
            $payload = json_decode($request->body(), true) ?? [];
            $q = strtolower($payload['query'] ?? '');

            if (str_contains($q, 'return')) {
                return Http::response([
                    'results' => [
                        [
                            'id'            => (string) $faq->id,
                            'workspace_id'  => $this->workspace->id,
                            'question'      => $faq->question,
                            'answer'        => $faq->answer,
                            'document_type' => 'faq',
                            'score'         => 0.98,
                        ],
                    ],
                    'total' => 1,
                ], 200);
            }

            return Http::response([
                'results' => [
                    [
                        'id'            => "doc_{$doc->id}_0",
                        'workspace_id'  => $this->workspace->id,
                        'question'      => $doc->title,
                        'answer'        => 'Warehouse receiving hours are Monday through Friday, 8:00 AM to 4:00 PM EST.',
                        'document_type' => 'knowledge_document',
                        'score'         => 0.94,
                    ],
                ],
                'total' => 1,
            ], 200);
        };

        /** @var FAQSearch $search */
        $search = app(FAQSearch::class);

        // FAQ Query
        $faqResults = $search->search('What is the return window?', $this->workspace->id);
        $this->assertCount(1, $faqResults);
        $this->assertEquals($faq->id, $faqResults->first()->faq->id);
        $this->assertEquals('faq', $faqResults->first()->faq->document_type);

        // Document Query
        $docResults = $search->search('Warehouse receiving hours', $this->workspace->id);
        $this->assertCount(1, $docResults);
        $this->assertEquals('knowledge_document', $docResults->first()->faq->document_type);
        $this->assertStringContainsString('Warehouse receiving hours', $docResults->first()->faq->answer);
    }

    /**
     * 8. Confirming the final answer uses retrieved document evidence rather than inventing unsupported details.
     */
    public function test_final_ai_answer_uses_retrieved_document_evidence(): void
    {
        $uniqueDocContent = 'Corporate VIP discount code VIP-TITANIUM-2026 provides 40% discount on all cloud subscriptions.';

        $doc = $this->documentService->storeText(
            title: 'VIP Partner Benefits 2026',
            textContent: $uniqueDocContent,
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $this->workspace->id
        );

        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);
        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Support Desk',
            'external_id'  => 'acc_test_01',
            'access_token' => 'token_test_01',
            'is_active'    => true,
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_test_01',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // Mock HybridRouter to classify as KNOWLEDGE
        $mockRouter = \Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(
                route: RouteType::KNOWLEDGE,
                confidence: 0.98,
                intent: 'vip_benefits',
                signals: [],
                entities: [],
                routerLatencyMs: 12.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));
        $this->app->instance(HybridRouter::class, $mockRouter);

        // Typesense returns the document chunk
        $this->searchHits = [
            [
                'id'            => "doc_{$doc->id}_0",
                'workspace_id'  => $this->workspace->id,
                'question'      => $doc->title,
                'answer'        => $uniqueDocContent,
                'document_type' => 'knowledge_document',
                'score'         => 0.96,
            ],
        ];

        $service = $this->app->make(CustomerSupportService::class);
        $reply = $service->generateReply(
            conversation: $conversation,
            query: 'What is the VIP discount code and percentage?',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('VIP-TITANIUM-2026', $reply);
        $this->assertStringContainsString('40%', $reply);
    }

    /**
     * Phase 2B - Defect 1: Restore a soft-deleted KNOWLEDGE_BASE document re-indexes its chunks
     * and makes its unique fact retrievable again through FAQSearch.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_restore_knowledge_document_reindexes_chunks_and_restores_retrievability(): void
    {
        $uniqueFact = 'DELTA-RESTORE-KEY-99721';
        $content = "Important document fact: The emergency security token is {$uniqueFact}.";

        $file = UploadedFile::fake()->createWithContent('restore_test.txt', $content);
        $doc = $this->documentService->store(
            file: $file,
            title: 'Restore Test Document',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $this->assertTrue($doc->is_active);
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertTrue($doc->is_active);
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertSame(1, $doc->chunk_count);
        $this->assertSame(2, $doc->version); // Initial store creates at v1; publication increments to v2
        $initialGenId = $doc->active_generation_id;
        $this->assertNotEmpty($initialGenId);

        // Soft-delete the document (M-02: increments version to fence in-flight workers)
        $this->deletedChunks = [];
        $deleteResult = $this->documentService->delete($doc);
        $this->assertTrue($deleteResult['success']);

        $doc->refresh();
        $this->assertTrue($doc->trashed());
        $this->assertFalse($doc->is_active);
        $this->assertSame(3, $doc->version); // delete() bumped v2 to v3
        $this->assertNull($doc->active_generation_id);

        // Verify delete cleaned up Typesense chunk
        $this->assertNotEmpty($this->deletedChunks);
        $this->assertStringContainsString("doc_{$doc->id}_", $this->deletedChunks[0]);

        // Prior to restore, FAQSearch returns 0 results
        $this->searchHits = [];
        $faqSearch = app(FAQSearch::class);
        $resultsBefore = $faqSearch->search(
            query: 'emergency security token',
            workspaceId: $this->workspace->id
        );
        $this->assertEmpty($resultsBefore);

        // Restore the document (restore increments version to 4, re-index publication increments to 5)
        $this->syncedChunks = [];
        $restoredDoc = $this->documentService->restore($doc->id);

        $this->assertFalse($restoredDoc->trashed());
        $this->assertTrue($restoredDoc->is_active);
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $restoredDoc->lifecycle_status);
        $this->assertSame(5, $restoredDoc->version);
        $this->assertSame(1, $restoredDoc->chunk_count);
        $restoredGenId = $restoredDoc->active_generation_id;
        $this->assertNotEmpty($restoredGenId);

        // Verify chunk was re-synced to Typesense during restore with generation ID
        $this->assertCount(1, $this->syncedChunks);
        $this->assertSame("doc_{$doc->id}_{$restoredGenId}_0", $this->syncedChunks[0]['id']);
        $this->assertStringContainsString($uniqueFact, $this->syncedChunks[0]['answer']);

        // Set searchHits to return the restored generation chunk
        $this->searchHits = [
            [
                'id'            => "doc_{$doc->id}_{$restoredGenId}_0",
                'workspace_id'  => $this->workspace->id,
                'question'      => $doc->title,
                'answer'        => $content,
                'document_type' => 'knowledge_document',
                'score'         => 0.98,
            ],
        ];

        // Unique fact is retrievable again through existing retrieval path
        $resultsAfter = $faqSearch->search(
            query: 'emergency security token',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($resultsAfter);
        $this->assertSame($doc->title, $resultsAfter->first()->faq->question);
        $this->assertStringContainsString($uniqueFact, $resultsAfter->first()->faq->answer);
    }

    /**
     * Phase 2B - Defect 2: Failed partial indexing immediately rolls back synced chunks,
     * records metadata, and subsequent retry leaves no orphaned chunks.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_partial_sync_failure_cleans_up_and_retry_leaves_no_orphaned_chunks(): void
    {
        // Prepare text that produces exactly 2 chunks (approx 650 chars)
        $chunk0Content = str_repeat('Alpha section content. ', 15);
        $chunk1Content = str_repeat('Beta section content. ', 15);
        $content = $chunk0Content . "\n\n" . $chunk1Content;
        $expectedCount = count($this->documentService->chunkText($content));
        $this->assertSame(2, $expectedCount);
        $file = UploadedFile::fake()->createWithContent('partial_fail.txt', $content);

        // Mock sync to succeed on chunk 0, but fail on chunk 1
        $this->syncCallback = function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            if (str_ends_with($data['id'] ?? '', '_1')) {
                return Http::response(['detail' => 'Simulated Typesense 500 error on chunk 1'], 500);
            }
            return Http::response(['status' => 'synced', 'id' => $data['id'] ?? 'doc_test'], 200);
        };

        $doc = $this->documentService->store(
            file: $file,
            title: 'Partial Sync Failure Test',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $this->assertSame(DocumentLifecycleStatus::FAILED, $doc->lifecycle_status);
        $this->assertFalse($doc->is_active);
        $this->assertStringContainsString('Failed to sync chunk 1', (string) $doc->error_message);

        // Verify chunk 0 was rolled back immediately during catch
        $rolledBackIds = array_filter($this->deletedChunks, fn ($url) => str_contains($url, "doc_{$doc->id}_") && str_ends_with(urldecode(parse_url($url, PHP_URL_PATH)), '_0'));
        $this->assertNotEmpty($rolledBackIds, 'Chunk 0 should be deleted upon sync failure.');

        // Now fix external service so all chunks succeed on retry
        $this->syncCallback = null;
        $this->syncStatusCode = 200;
        $this->deletedChunks = [];
        $this->syncedChunks = [];

        // Retry the document ingestion
        $retriedDoc = $this->documentService->retry($doc->id);

        $retriedDoc->refresh();
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $retriedDoc->lifecycle_status);
        $this->assertTrue($retriedDoc->is_active);
        $this->assertSame(2, $retriedDoc->chunk_count);
        $this->assertSame(3, $retriedDoc->version); // Failed initial store remained v1; retry bumped to v2; successful publication bumped to v3
        $this->assertNull($retriedDoc->error_message);

        // Both chunk 0 and chunk 1 are synced cleanly with no orphaned chunks
        $syncedIds = array_column($this->syncedChunks, 'id');
        $this->assertCount(2, $syncedIds);
        $this->assertStringEndsWith('_0', $syncedIds[0]);
        $this->assertStringEndsWith('_1', $syncedIds[1]);
    }

    /**
     * Phase 2B - Defect 2: If cleanup cannot be confirmed by the retrieval engine,
     * the document must abort and transition to FAILED instead of falsely becoming ACTIVE.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_cleanup_failure_prevents_document_from_becoming_active(): void
    {
        $file = UploadedFile::fake()->createWithContent('cleanup_fail.txt', 'Some valid document content.');
        $doc = $this->documentService->store(
            file: $file,
            title: 'Cleanup Failure Test',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);

        // Put document into a pending state with an abandoned crashed generation
        $meta = $doc->metadata ?? [];
        $meta['pending_generation_id'] = (string) \Illuminate\Support\Str::uuid();
        $meta['pending_chunk_count'] = 2;
        $doc->metadata = $meta;
        $doc->lifecycle_status = DocumentLifecycleStatus::PENDING_INGESTION;
        $doc->is_active = false;
        $doc->save();

        // Now simulate external deletion failure during cleanup of the abandoned generation
        $this->deleteCallback = function () {
            return Http::response(['error' => 'Typesense unavailable during delete'], 503);
        };
        $this->syncedChunks = [];

        // Manually trigger ingestion or retry which must clean up prior chunks
        $this->documentService->processKnowledgeBaseIngestion($doc);

        $doc->refresh();
        // Document must NOT be ACTIVE
        $this->assertSame(DocumentLifecycleStatus::FAILED, $doc->lifecycle_status);
        $this->assertFalse($doc->is_active);
        $this->assertStringContainsString('Failed to confirm cleanup', (string) $doc->error_message);
        // Chunks must NOT have been indexed after cleanup failed
        $this->assertEmpty($this->syncedChunks);
    }

    /**
     * Phase 2B.1 - Defect C-01: A stale indexing attempt cannot delete or overwrite
     * chunks belonging to a newer successful generation.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_stale_attempt_cannot_delete_or_overwrite_newer_generation_chunks(): void
    {
        $file = UploadedFile::fake()->createWithContent('stale_c01.txt', 'Document version 1 content.');
        $doc = $this->documentService->store(
            file: $file,
            title: 'Stale C01 Test',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $gen1Id = $doc->active_generation_id;
        $this->assertSame(2, $doc->version); // Published at v2
        $this->assertNotEmpty($gen1Id);

        // Now a newer operation (Worker B) increments version to 3 and indexes Generation 2
        $doc->increment('version');
        $gen2Id = (string) \Illuminate\Support\Str::uuid();
        $doc->update([
            'lifecycle_status'     => DocumentLifecycleStatus::ACTIVE,
            'is_active'            => true,
            'active_generation_id' => $gen2Id,
            'chunk_count'          => 1,
        ]);

        $this->assertSame(3, $doc->fresh()->version);
        $this->assertSame($gen2Id, $doc->fresh()->active_generation_id);

        // Now simulate Worker A (stale worker that started with expectedVersion = 2 and genA)
        $genAId = (string) \Illuminate\Support\Str::uuid();
        $this->deletedChunks = [];

        // Worker A attempts to conditionally update version 2
        $affected = KnowledgeDocument::where('id', $doc->id)
            ->where('workspace_id', $doc->workspace_id)
            ->where('version', 2)
            ->whereNull('deleted_at')
            ->update([
                'version'              => 3,
                'active_generation_id' => $genAId,
                'title'                => 'Outdated V2 Title',
            ]);

        // CAS correctly rejects Worker A
        $this->assertSame(0, $affected);

        // Worker A executes its rollback for its OWN generation chunks (genA)
        $this->documentService->cleanupGenerationChunks($doc, $genAId, 1);

        // Verify that Worker A's rollback targeted ONLY genA, and NEVER gen2!
        $this->assertNotEmpty($this->deletedChunks);
        foreach ($this->deletedChunks as $url) {
            $this->assertStringContainsString("doc_{$doc->id}_{$genAId}_0", $url);
            $this->assertStringNotContainsString("doc_{$doc->id}_{$gen2Id}_", $url);
        }

        // Active document state remains intact on Generation 2
        $currentDoc = $doc->fresh();
        $this->assertSame(3, $currentDoc->version);
        $this->assertSame($gen2Id, $currentDoc->active_generation_id);
        $this->assertTrue($currentDoc->is_active);
    }

    /**
     * Phase 2B.1 - Defect H-01: Failed cache-lock acquisition is non-destructive
     * and leaves the document's existing lifecycle status and active state unchanged.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_lock_contention_leaves_document_lifecycle_and_active_state_unchanged(): void
    {
        $file = UploadedFile::fake()->createWithContent('lock_test.txt', 'Document to test locking.');
        $doc = $this->documentService->store(
            file: $file,
            title: 'Lock Test Doc',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertTrue($doc->is_active);
        $this->assertNull($doc->error_message);
        $originalVersion = $doc->version;
        $originalGenId = $doc->active_generation_id;

        // Acquire lock externally to simulate another worker actively executing
        $lock = \Illuminate\Support\Facades\Cache::lock("knowledge_doc_indexing:{$doc->id}", 60);
        $this->assertTrue($lock->get());

        try {
            // Attempt concurrent ingestion - should exit non-destructively without changing status
            $this->documentService->processKnowledgeBaseIngestion($doc);

            $doc->refresh();
            // Document status must NOT be mutated to FAILED!
            $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
            $this->assertTrue($doc->is_active);
            $this->assertNull($doc->error_message);
            $this->assertSame($originalVersion, $doc->version);
            $this->assertSame($originalGenId, $doc->active_generation_id);
        } finally {
            $lock->release();
        }
    }

    /**
     * Phase 2B.1 - Defect M-02: Deletion racing with in-flight indexing cannot
     * republish a soft-deleted document.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_deletion_racing_with_indexing_cannot_republish_deleted_document(): void
    {
        $file = UploadedFile::fake()->createWithContent('racing_del.txt', 'Content for race test.');
        $doc = $this->documentService->store(
            file: $file,
            title: 'Race Deletion Test',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $expectedVersion = $doc->version;
        $staleGenId = (string) \Illuminate\Support\Str::uuid();

        // User deletes the document while Worker A is supposedly in-flight
        $this->documentService->delete($doc);

        $doc->refresh();
        $this->assertTrue($doc->trashed());
        $this->assertSame($expectedVersion + 1, $doc->version); // version was bumped by delete()
        $this->assertNull($doc->active_generation_id);

        // Stale Worker A finishes chunk sync and calls CAS update
        $affected = KnowledgeDocument::where('id', $doc->id)
            ->where('workspace_id', $doc->workspace_id)
            ->where('version', $expectedVersion) // version 1
            ->whereNull('deleted_at')
            ->update([
                'lifecycle_status'     => DocumentLifecycleStatus::ACTIVE,
                'is_active'            => true,
                'active_generation_id' => $staleGenId,
            ]);

        // Fencing check prevents stale worker from publishing to a deleted document
        $this->assertSame(0, $affected);

        $fresh = KnowledgeDocument::withTrashed()->find($doc->id);
        $this->assertTrue($fresh->trashed());
        $this->assertFalse($fresh->is_active);
        $this->assertNull($fresh->active_generation_id);
    }

    /**
     * Phase 2B.1 - Defect L-01: An abandoned or crashed attempt (hard crash before catch)
     * can be safely recovered and cleaned up using persisted pending attempt metadata.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_abandoned_attempt_recovered_without_relying_on_catch_block(): void
    {
        $file = UploadedFile::fake()->createWithContent('crash_test.txt', 'Crash recovery content.');
        $doc = $this->documentService->store(
            file: $file,
            title: 'Crash Recovery Test',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        // Simulate an abrupt hard process termination (OOM/SIGKILL) that left pending metadata
        $crashedGenId = (string) \Illuminate\Support\Str::uuid();
        $meta = $doc->metadata ?? [];
        $meta['pending_generation_id'] = $crashedGenId;
        $meta['pending_chunk_count'] = 3;
        $doc->metadata = $meta;
        $doc->save();

        $this->deletedChunks = [];

        // Subsequent cleanup discovers the crashed pending generation and cleans it up
        $this->documentService->cleanupKnowledgeBaseChunks($doc);

        $deletedUrls = implode(' ', $this->deletedChunks);
        $this->assertStringContainsString("doc_{$doc->id}_{$crashedGenId}_0", $deletedUrls);
        $this->assertStringContainsString("doc_{$doc->id}_{$crashedGenId}_1", $deletedUrls);
        $this->assertStringContainsString("doc_{$doc->id}_{$crashedGenId}_2", $deletedUrls);

        $doc->refresh();
        $this->assertArrayNotHasKey('pending_generation_id', $doc->metadata ?? []);
    }

    /**
     * Phase 2B - Defect 2: Re-indexing with fewer chunks purges old excess chunks safely.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_reindexing_with_fewer_chunks_deletes_excess_old_chunks(): void
    {
        $longContent = str_repeat('Chunk zero content words. ', 25) . "\n\n"
            . str_repeat('Chunk one content words. ', 25) . "\n\n"
            . str_repeat('Chunk two content words. ', 25);

        $initialExpectedChunks = count($this->documentService->chunkText($longContent));
        $this->assertGreaterThanOrEqual(3, $initialExpectedChunks);

        $file = UploadedFile::fake()->createWithContent('three_chunks.txt', $longContent);
        $doc = $this->documentService->store(
            file: $file,
            title: 'Three Chunks Document',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $initialGenId = $doc->active_generation_id;
        $this->assertSame($initialExpectedChunks, $doc->chunk_count);

        // Now update raw_text to a short content that only produces 1 chunk
        $doc->metadata = array_merge($doc->metadata ?? [], [
            'raw_text' => 'Short single chunk content.',
        ]);
        $doc->save();

        $this->deletedChunks = [];
        $this->syncedChunks = [];

        // Re-index
        $this->documentService->processKnowledgeBaseIngestion($doc);

        $doc->refresh();
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertSame(1, $doc->chunk_count);
        $newGenId = $doc->active_generation_id;
        $this->assertNotSame($initialGenId, $newGenId);

        // Verify that cleanup targeted previous generation chunk indices
        $deletedChunkStrings = implode(' ', $this->deletedChunks);
        for ($i = 0; $i < $initialExpectedChunks; $i++) {
            $this->assertStringContainsString("doc_{$doc->id}_{$initialGenId}_{$i}", $deletedChunkStrings);
        }

        // Verify new sync only indexed 1 chunk under newGenId
        $this->assertCount(1, $this->syncedChunks);
        $this->assertSame("doc_{$doc->id}_{$newGenId}_0", $this->syncedChunks[0]['id']);
    }

    /**
     * Phase 2B - Defect 2: Cleanup never affects unrelated documents or workspaces.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_cleanup_does_not_affect_unrelated_documents_or_workspaces(): void
    {
        $workspaceB = Workspace::create([
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);

        $docA = KnowledgeDocument::create([
            'workspace_id'         => $this->workspace->id,
            'title'                => 'Doc A',
            'original_filename'    => 'doc_a.txt',
            'file_path'            => 'docs/doc_a.txt',
            'mime_type'            => 'text/plain',
            'file_size'            => 100,
            'content_hash'         => 'hash_a',
            'purpose'              => IngestionPurpose::KNOWLEDGE_BASE,
            'chunk_count'          => 2,
            'active_generation_id' => 'gen-a-uuid',
            'version'              => 1,
            'is_active'            => true,
            'lifecycle_status'     => DocumentLifecycleStatus::ACTIVE,
        ]);

        $docB = KnowledgeDocument::create([
            'workspace_id'         => $workspaceB->id,
            'title'                => 'Doc B',
            'original_filename'    => 'doc_b.txt',
            'file_path'            => 'docs/doc_b.txt',
            'mime_type'            => 'text/plain',
            'file_size'            => 100,
            'content_hash'         => 'hash_b',
            'purpose'              => IngestionPurpose::KNOWLEDGE_BASE,
            'chunk_count'          => 2,
            'active_generation_id' => 'gen-b-uuid',
            'version'              => 1,
            'is_active'            => true,
            'lifecycle_status'     => DocumentLifecycleStatus::ACTIVE,
        ]);

        $this->deletedChunks = [];

        // Clean up Doc A
        $this->documentService->cleanupKnowledgeBaseChunks($docA);

        // Verify all delete calls target Doc A and Workspace A
        $this->assertNotEmpty($this->deletedChunks);
        foreach ($this->deletedChunks as $url) {
            $this->assertStringContainsString("doc_{$docA->id}_", $url);
            $this->assertStringContainsString("workspace_id={$this->workspace->id}", $url);
            $this->assertStringNotContainsString((string) $docB->id, $url);
            $this->assertStringNotContainsString("workspace_id={$workspaceB->id}", $url);
        }
    }

    /**
     * Phase 2B.2 - Test 1: Re-indexing does not delete the active generation before new-generation publication.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_reindexing_does_not_delete_active_generation_before_new_generation_publication(): void
    {
        $file = UploadedFile::fake()->createWithContent('active_doc.txt', 'Initial generation content for active document.');
        $doc = $this->documentService->store(
            file: $file,
            title: 'Active Document',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertTrue($doc->is_active);
        $initialGenId = $doc->active_generation_id;
        $this->assertNotEmpty($initialGenId);

        // Reset timeline before triggering re-indexing
        $this->httpTimeline = [];
        $this->deletedChunks = [];
        $this->syncedChunks = [];

        // Update content to produce a replacement generation
        $doc->metadata = array_merge($doc->metadata ?? [], [
            'raw_text' => 'Updated content for the replacement generation.',
        ]);
        $doc->save();

        $this->documentService->processKnowledgeBaseIngestion($doc);

        $doc->refresh();
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $newGenId = $doc->active_generation_id;
        $this->assertNotSame($initialGenId, $newGenId);

        // Trace the exact sequence in httpTimeline
        $firstDeleteIndexOfOldGen = null;
        $lastSyncIndexOfNewGen = null;

        foreach ($this->httpTimeline as $index => $event) {
            if ($event['action'] === 'sync' && str_contains($event['id'] ?? '', "_{$newGenId}_")) {
                $lastSyncIndexOfNewGen = $index;
            }
            if ($event['action'] === 'delete' && str_contains($event['url'] ?? '', "_{$initialGenId}_")) {
                if ($firstDeleteIndexOfOldGen === null) {
                    $firstDeleteIndexOfOldGen = $index;
                }
            }
        }

        $this->assertNotNull($lastSyncIndexOfNewGen, 'New generation chunks must be synced.');
        $this->assertNotNull($firstDeleteIndexOfOldGen, 'Old generation chunks must be cleaned up post-publication.');
        // PROOF: All syncs for the replacement generation completed BEFORE the old active generation was deleted
        $this->assertGreaterThan(
            $lastSyncIndexOfNewGen,
            $firstDeleteIndexOfOldGen,
            'Old active generation chunks must never be deleted before replacement generation is synced and published.'
        );
    }

    /**
     * Phase 2B.2 - Test 2: If syncing the replacement fails, the old active generation remains in Typesense and remains retrievable.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_sync_replacement_failure_preserves_old_active_generation_and_retrievability(): void
    {
        $uniqueFact = 'Policy FACT-99: Lifetime guarantee applies to all standard equipment.';
        $doc = $this->documentService->storeText(
            title: 'Guarantee Document',
            textContent: $uniqueFact,
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertTrue($doc->is_active);
        $activeGenId = $doc->active_generation_id;

        // Reset tracking
        $this->httpTimeline = [];
        $this->deletedChunks = [];

        // Simulate failure during sync of the replacement generation
        $this->syncCallback = function () {
            return Http::response(['detail' => 'Remote Typesense sync timeout'], 500);
        };

        // Attempt re-indexing with new content
        $doc->metadata = array_merge($doc->metadata ?? [], [
            'raw_text' => 'New text that will fail during sync.',
        ]);
        $doc->save();

        $this->documentService->processKnowledgeBaseIngestion($doc);

        $doc->refresh();
        // Document must RETAIN its ACTIVE status and previous active generation
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertTrue($doc->is_active);
        $this->assertSame($activeGenId, $doc->active_generation_id);

        // Verify that NO delete requests were issued for the active generation
        $deletedUrls = implode(' ', $this->deletedChunks);
        $this->assertStringNotContainsString("_{$activeGenId}_", $deletedUrls);

        // Verify that retrieval remains fully operational for the active generation chunk
        $this->searchHits = [
            [
                'id'           => "doc_{$doc->id}_{$activeGenId}_0",
                'question'     => 'Guarantee Policy',
                'answer'       => $uniqueFact,
                'document_type'=> 'knowledge_document',
                'workspace_id' => $this->workspace->id,
            ],
        ];

        $client = app(\App\Services\Retrieval\RetrievalClient::class);
        $results = $client->search(
            query: 'lifetime guarantee',
            workspaceId: $this->workspace->id
        );

        $this->assertNotEmpty($results);
        $this->assertTrue($results->contains(function ($item) use ($uniqueFact) {
            return str_contains($item->faq->answer, $uniqueFact);
        }));
    }

    /**
     * Phase 2B.2 - Test 3: Successful publication makes the new generation active, then cleans up the old generation.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_successful_publication_activates_new_generation_then_cleans_old_generation(): void
    {
        $doc = $this->documentService->storeText(
            title: 'Publication Sequence Doc',
            textContent: 'Initial text version 1.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $oldGenId = $doc->active_generation_id;

        $this->httpTimeline = [];
        $this->deletedChunks = [];

        $doc->metadata = array_merge($doc->metadata ?? [], [
            'raw_text' => 'Replacement text version 2.',
        ]);
        $doc->save();

        $this->documentService->processKnowledgeBaseIngestion($doc);

        $doc->refresh();
        $newGenId = $doc->active_generation_id;
        $this->assertNotSame($oldGenId, $newGenId);
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);

        // Must clean up old generation after publication
        $deletedUrls = implode(' ', $this->deletedChunks);
        $this->assertStringContainsString("_{$oldGenId}_0", $deletedUrls);
        $this->assertStringNotContainsString("_{$newGenId}_", $deletedUrls);

        // Pending generation metadata must be cleared
        $this->assertArrayNotHasKey('pending_generation_id', $doc->metadata ?? []);
    }

    /**
     * Phase 2B.2 - Test 4: A failed old-generation cleanup after publication does not remove or deactivate the newly published generation.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_failed_old_generation_cleanup_after_publication_does_not_deactivate_new_generation(): void
    {
        $doc = $this->documentService->storeText(
            title: 'Cleanup Failure After Pub Doc',
            textContent: 'Initial content for cleanup failure test.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $oldGenId = $doc->active_generation_id;

        // Configure delete to fail (e.g. 503)
        $this->deleteCallback = function () {
            return Http::response(['error' => 'Typesense unavailable during delete'], 503);
        };

        $doc->metadata = array_merge($doc->metadata ?? [], [
            'raw_text' => 'Updated content that successfully publishes.',
        ]);
        $doc->save();

        // Re-index
        $this->documentService->processKnowledgeBaseIngestion($doc);

        $doc->refresh();
        $newGenId = $doc->active_generation_id;
        $this->assertNotSame($oldGenId, $newGenId);

        // The newly published generation MUST remain ACTIVE and live despite old generation cleanup failing
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertTrue($doc->is_active);
        $this->assertSame($newGenId, $doc->active_generation_id);
    }

    /**
     * Phase 2B.2 - Test 5: A previous abandoned pending generation is cleaned before its tracking metadata is overwritten.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_previous_abandoned_pending_generation_cleaned_before_tracking_metadata_overwritten(): void
    {
        $doc = $this->documentService->storeText(
            title: 'Abandoned Gen Cleanup Doc',
            textContent: 'Active content for document.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $activeGenId = $doc->active_generation_id;

        // Simulate an abandoned attempt from a prior crash
        $abandonedGenId = (string) \Illuminate\Support\Str::uuid();
        $meta = $doc->metadata ?? [];
        $meta['pending_generation_id'] = $abandonedGenId;
        $meta['pending_chunk_count'] = 2;
        $doc->metadata = $meta;
        $doc->save();

        $this->httpTimeline = [];
        $this->deletedChunks = [];
        $this->syncedChunks = [];

        // Trigger ingestion
        $this->documentService->processKnowledgeBaseIngestion($doc);

        $doc->refresh();
        $newGenId = $doc->active_generation_id;

        // Verify that delete requests were issued for the abandoned generation
        $deletedUrls = implode(' ', $this->deletedChunks);
        $this->assertStringContainsString("_{$abandonedGenId}_0", $deletedUrls);
        $this->assertStringContainsString("_{$abandonedGenId}_1", $deletedUrls);

        // In the timeline, abandoned generation delete MUST occur before the new generation sync
        $deleteAbandonedIdx = null;
        $syncNewIdx = null;
        foreach ($this->httpTimeline as $index => $event) {
            if ($event['action'] === 'delete' && str_contains($event['url'] ?? '', "_{$abandonedGenId}_")) {
                if ($deleteAbandonedIdx === null) {
                    $deleteAbandonedIdx = $index;
                }
            }
            if ($event['action'] === 'sync' && str_contains($event['id'] ?? '', "_{$newGenId}_")) {
                if ($syncNewIdx === null) {
                    $syncNewIdx = $index;
                }
            }
        }

        $this->assertNotNull($deleteAbandonedIdx);
        $this->assertNotNull($syncNewIdx);
        $this->assertLessThan(
            $syncNewIdx,
            $deleteAbandonedIdx,
            'Abandoned generation must be cleaned before new generation chunks are synced.'
        );
    }

    /**
     * Phase 2B.2 - Test 6: If abandoned-generation cleanup fails, the old tracking reference is not lost.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_failed_abandoned_generation_cleanup_retains_tracking_reference(): void
    {
        $doc = $this->documentService->storeText(
            title: 'Abandoned Cleanup Fail Doc',
            textContent: 'Active content.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $doc->refresh();
        $activeGenId = $doc->active_generation_id;

        // Simulate an abandoned attempt from a prior crash
        $abandonedGenId = (string) \Illuminate\Support\Str::uuid();
        $meta = $doc->metadata ?? [];
        $meta['pending_generation_id'] = $abandonedGenId;
        $meta['pending_chunk_count'] = 2;
        $doc->metadata = $meta;
        $doc->save();

        // Simulate delete failure
        $this->deleteCallback = function () {
            return Http::response(['error' => 'Typesense unavailable during delete'], 503);
        };
        $this->syncedChunks = [];

        // Attempt ingestion
        $this->documentService->processKnowledgeBaseIngestion($doc);

        $doc->refresh();

        // The document must retain its active state and previous active generation
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertSame($activeGenId, $doc->active_generation_id);

        // CRITICAL: The metadata MUST still retain the abandoned generation reference so future retries can clean it!
        $this->assertSame($abandonedGenId, $doc->metadata['pending_generation_id'] ?? null);
        $this->assertSame(2, $doc->metadata['pending_chunk_count'] ?? null);

        // No new chunks were synced
        $this->assertEmpty($this->syncedChunks);
    }

    /**
     * Phase 2B.2 - Test 7: Repeated retries remain idempotent and do not delete unrelated documents, workspaces, or the active generation.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_repeated_retries_remain_idempotent_and_isolate_other_documents_and_workspaces(): void
    {
        $workspaceB = Workspace::create([
            'name' => 'Workspace B',
            'slug' => 'workspace-b',
        ]);

        $docA = $this->documentService->storeText(
            title: 'Doc A in Workspace A',
            textContent: 'Doc A content in workspace A.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );
        $docA->refresh();
        $activeGenA = $docA->active_generation_id;

        $docB = KnowledgeDocument::create([
            'workspace_id'         => $workspaceB->id,
            'title'                => 'Doc B in Workspace B',
            'original_filename'    => 'doc_b.txt',
            'file_path'            => 'docs/doc_b.txt',
            'mime_type'            => 'text/plain',
            'file_size'            => 100,
            'content_hash'         => 'hash_b',
            'purpose'              => IngestionPurpose::KNOWLEDGE_BASE,
            'chunk_count'          => 2,
            'active_generation_id' => 'gen-b-uuid',
            'version'              => 1,
            'is_active'            => true,
            'lifecycle_status'     => DocumentLifecycleStatus::ACTIVE,
        ]);

        $this->deletedChunks = [];

        // Repeat retry 3 times on Doc A
        for ($i = 0; $i < 3; $i++) {
            $this->documentService->retry($docA->id);
        }

        $docA->refresh();
        $docB->refresh();

        // Doc A remains active with its valid active generation
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $docA->lifecycle_status);
        $this->assertSame($activeGenA, $docA->active_generation_id);

        // Doc B is completely untouched
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $docB->lifecycle_status);
        $this->assertSame('gen-b-uuid', $docB->active_generation_id);

        // Deletions never targeted Doc B or Workspace B
        foreach ($this->deletedChunks as $url) {
            $this->assertStringNotContainsString((string) $docB->id, $url);
            $this->assertStringNotContainsString("workspace_id={$workspaceB->id}", $url);
        }
    }

    /**
     * Phase 2B.4 - Test 1: A successful publication increments version exactly once.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_successful_publication_increments_version_exactly_once(): void
    {
        $doc = $this->documentService->storeText(
            title: 'Single Publication Version Test',
            textContent: 'Initial content for single pub test.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );
        $doc->refresh();
        // Initial store created row at version 1; successful publication atomically incremented to 2
        $this->assertSame(2, $doc->version);

        $vBefore = $doc->version;
        $doc->metadata = array_merge($doc->metadata ?? [], [
            'raw_text' => 'Updated content version 2.',
        ]);
        $doc->save();

        $this->documentService->processKnowledgeBaseIngestion($doc);
        $doc->refresh();

        // Second successful publication incremented version exactly once more to 3
        $this->assertSame($vBefore + 1, $doc->version);
        $this->assertSame(3, $doc->version);
    }

    /**
     * Phase 2B.4 - Test 2: Two simulated competing CAS publications using the same expected version cannot both succeed;
     * the losing publication cannot overwrite the winner's active_generation_id or delete the winner's chunks.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_competing_cas_publications_with_same_expected_version_fence_losing_worker(): void
    {
        $doc = $this->documentService->storeText(
            title: 'Competing CAS Test',
            textContent: 'Initial base content.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );
        $doc->refresh();
        $expectedVersion = $doc->version; // e.g. 2
        $winningGenId = (string) \Illuminate\Support\Str::uuid();
        $losingGenId = (string) \Illuminate\Support\Str::uuid();

        // Simulate two workers (Worker 1 and Worker 2) that both prepared chunks starting from expectedVersion
        // Worker 1 finishes sync first and executes publication CAS
        $newVersion = $expectedVersion + 1;
        $worker1Affected = KnowledgeDocument::where('id', $doc->id)
            ->where('workspace_id', $doc->workspace_id)
            ->where('version', $expectedVersion)
            ->whereNull('deleted_at')
            ->update([
                'version'              => $newVersion,
                'lifecycle_status'     => DocumentLifecycleStatus::ACTIVE,
                'is_active'            => true,
                'chunk_count'          => 2,
                'active_generation_id' => $winningGenId,
                'error_message'        => null,
            ]);

        $this->assertSame(1, $worker1Affected);

        // Worker 2 (the competitor) finishes sync later and attempts publication CAS using the same expectedVersion
        $this->deletedChunks = [];
        $worker2Affected = KnowledgeDocument::where('id', $doc->id)
            ->where('workspace_id', $doc->workspace_id)
            ->where('version', $expectedVersion)
            ->whereNull('deleted_at')
            ->update([
                'version'              => $newVersion,
                'lifecycle_status'     => DocumentLifecycleStatus::ACTIVE,
                'is_active'            => true,
                'chunk_count'          => 2,
                'active_generation_id' => $losingGenId,
                'error_message'        => null,
            ]);

        // PROOF: Worker 2 CAS affected 0 rows because Worker 1 atomically changed version
        $this->assertSame(0, $worker2Affected);

        // When CAS returns 0, production service logic rolls back ONLY Worker 2's own generation chunks
        $this->documentService->cleanupGenerationChunks($doc, $losingGenId, 2);

        // Verify that only losingGenId was deleted, NEVER winningGenId
        $deletedUrls = implode(' ', $this->deletedChunks);
        $this->assertStringContainsString("_{$losingGenId}_0", $deletedUrls);
        $this->assertStringContainsString("_{$losingGenId}_1", $deletedUrls);
        $this->assertStringNotContainsString("_{$winningGenId}_", $deletedUrls);

        // Active document in database remains on Winner's state
        $doc->refresh();
        $this->assertSame($winningGenId, $doc->active_generation_id);
        $this->assertSame($newVersion, $doc->version);
        $this->assertTrue($doc->is_active);
    }

    /**
     * Phase 2B.4 - Test 3: Concurrent worker superseded in-flight rolls back safely through real service pipeline
     * without deactivating the document or touching winning chunks.
     *
     * [Test Classification: Mocked HTTP Service Integration - SQLite In-Memory Database]
     */
    public function test_concurrent_worker_superseded_in_flight_rolls_back_safely_through_service(): void
    {
        $doc = $this->documentService->storeText(
            title: 'Worker Stale Pipeline Test',
            textContent: 'Original base content.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );
        $doc->refresh();
        $initialActiveGen = $doc->active_generation_id;
        $initialVersion = $doc->version;

        // Simulate that while a re-indexing worker is in-flight syncing chunks,
        // a concurrent operation advances the version in DB behind the worker's back
        $this->deletedChunks = [];
        $this->syncCallback = function ($request) use ($doc) {
            KnowledgeDocument::where('id', $doc->id)->increment('version');
            return Http::response(['status' => 'synced', 'id' => $request->data()['id'] ?? 'test'], 200);
        };

        $doc->metadata = array_merge($doc->metadata ?? [], [
            'raw_text' => 'New text that will fail publication CAS because version was bumped.',
        ]);
        $doc->save();

        // Worker executes processKnowledgeBaseIngestion
        $this->documentService->processKnowledgeBaseIngestion($doc);

        $doc->refresh();
        // Since the document was already active, it RETAINS active status and initialActiveGen
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertSame($initialActiveGen, $doc->active_generation_id);

        // Verify that initialActiveGen chunks were NOT deleted
        $deletedUrls = implode(' ', $this->deletedChunks);
        $this->assertStringNotContainsString("_{$initialActiveGen}_", $deletedUrls);
    }
}

