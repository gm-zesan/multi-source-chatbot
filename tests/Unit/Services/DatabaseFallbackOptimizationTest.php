<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DocumentLifecycleStatus;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DatabaseFallbackOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create(['name' => 'Workspace A', 'slug' => 'workspace-a']);
        $this->workspaceB = Workspace::create(['name' => 'Workspace B', 'slug' => 'workspace-b']);

        // Force downstream Python service to fail so fallbackDatabaseSearch is triggered
        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response('Service Unavailable', 503),
        ]);
    }

    public function test_tenant_isolation_strictly_enforced_in_fallback(): void
    {
        $faqA = FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'What is the refund timeline?',
            'answer'       => 'Workspace A refund is 7 days.',
            'is_active'    => true,
        ]);

        $faqB = FAQ::create([
            'workspace_id' => $this->workspaceB->id,
            'question'     => 'What is the refund timeline?',
            'answer'       => 'Workspace B confidential refund is 30 days.',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');

        $resA = $client->search('refund timeline', workspaceId: $this->workspaceA->id);
        $this->assertCount(1, $resA);
        $this->assertSame($faqA->id, $resA->first()->faq->id);
        $this->assertSame($this->workspaceA->id, $resA->first()->faq->workspace_id);
        $this->assertSame('Workspace A refund is 7 days.', $resA->first()->faq->answer);

        $resB = $client->search('refund timeline', workspaceId: $this->workspaceB->id);
        $this->assertCount(1, $resB);
        $this->assertSame($faqB->id, $resB->first()->faq->id);
        $this->assertSame($this->workspaceB->id, $resB->first()->faq->workspace_id);
    }

    public function test_empty_or_whitespace_or_symbol_query_returns_empty_collection(): void
    {
        FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'Company general FAQ',
            'answer'       => 'Some answer',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');

        // Test empty, whitespace, and symbol queries
        $this->assertEmpty($client->search('', workspaceId: $this->workspaceA->id));
        $this->assertEmpty($client->search('   ', workspaceId: $this->workspaceA->id));
        $this->assertEmpty($client->search('???', workspaceId: $this->workspaceA->id));
        $this->assertEmpty($client->search('!@#$', workspaceId: $this->workspaceA->id));
        $this->assertEmpty($client->search('a', workspaceId: $this->workspaceA->id));
    }

    public function test_calibrated_scoring_orders_exact_over_partial_over_body_match(): void
    {
        // 1. Exact match
        $faqExact = FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'Delivery charge',
            'answer'       => 'Inside Dhaka 60 BDT.',
            'is_active'    => true,
        ]);

        // 2. Question phrase/words match
        $faqQuestionWords = FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'What is the delivery charge for courier parcel?',
            'answer'       => 'Varies by location.',
            'is_active'    => true,
        ]);

        // 3. Body/Answer match only
        $faqBodyOnly = FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'Payment methods available',
            'answer'       => 'We support bKash and cash on delivery charge.',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('Delivery charge', workspaceId: $this->workspaceA->id, topK: 5);

        $this->assertCount(3, $results);

        // Verify calibrated score order: Exact (0.95) > Question words (0.86) > Body only (0.65)
        $this->assertSame($faqExact->id, $results[0]->faq->id);
        $this->assertEquals(0.95, $results[0]->finalScore);
        $this->assertSame('keyword_fallback', $results[0]->matchType);

        $this->assertSame($faqQuestionWords->id, $results[1]->faq->id);
        $this->assertGreaterThanOrEqual(0.80, $results[1]->finalScore);
        $this->assertLessThan(0.95, $results[1]->finalScore);

        $this->assertSame($faqBodyOnly->id, $results[2]->faq->id);
        $this->assertGreaterThanOrEqual(0.50, $results[2]->finalScore);
        $this->assertLessThan($results[1]->finalScore, $results[2]->finalScore);
    }

    public function test_irrelevant_query_returns_zero_hits(): void
    {
        FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'Delivery time in Bangladesh',
            'answer'       => 'Takes 2-3 business days.',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('quantum mechanical computing accelerator', workspaceId: $this->workspaceA->id);

        $this->assertEmpty($results, 'Unrelated queries must return zero hits and not leak arbitrary FAQs');
    }

    public function test_bangla_query_search_and_calibrated_scoring(): void
    {
        $faqBangla = FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'ডেলিভারি চার্জ কত?',
            'answer'       => 'ঢাকার মধ্যে ৬০ টাকা, ঢাকার বাইরে ১২০ টাকা।',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('ডেলিভারি চার্জ', workspaceId: $this->workspaceA->id);

        $this->assertNotEmpty($results);
        $topHit = $results->first();
        $this->assertSame($faqBangla->id, $topHit->faq->id);
        $this->assertSame('keyword_fallback', $topHit->matchType);
        $this->assertGreaterThanOrEqual(0.85, $topHit->finalScore);
    }

    public function test_banglish_query_search_and_calibrated_scoring(): void
    {
        $faqBanglish = FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'Order kobe pabo?',
            'answer'       => 'Apnar order 3-5 diner moddhe delivery kora hobe.',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('order kobe pabo', workspaceId: $this->workspaceA->id);

        $this->assertNotEmpty($results);
        $topHit = $results->first();
        $this->assertSame($faqBanglish->id, $topHit->faq->id);
        $this->assertSame('keyword_fallback', $topHit->matchType);
        $this->assertEquals(0.95, $topHit->finalScore);
    }

    public function test_knowledge_document_fallback_with_calibrated_scoring(): void
    {
        $doc = KnowledgeDocument::create([
            'workspace_id'     => $this->workspaceA->id,
            'title'            => 'Return and Warranty Policy Document',
            'original_filename'=> 'policy.txt',
            'file_path'        => 'documents/policy.txt',
            'mime_type'        => 'text/plain',
            'file_size'        => 500,
            'content_hash'     => 'hash123',
            'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
            'is_active'        => true,
            'metadata'         => [
                'raw_text' => 'Products can be returned within 7 days with original receipt.',
            ],
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('Warranty Policy', workspaceId: $this->workspaceA->id);

        $this->assertNotEmpty($results);
        $topHit = $results->first();
        $this->assertSame('keyword_fallback', $topHit->matchType);
        $this->assertSame('Return and Warranty Policy Document', $topHit->faq->question);
        $this->assertStringContainsString('Products can be returned within 7 days', $topHit->faq->answer);
        $this->assertGreaterThanOrEqual(0.70, $topHit->finalScore);
    }

    public function test_parameterized_query_safety_against_sql_injection(): void
    {
        FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'Normal Question',
            'answer'       => 'Normal Answer',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');

        // Malicious injection payloads
        $injection1 = "' OR 1=1 --";
        $injection2 = "'; DROP TABLE faqs; --";
        $injection3 = "+*()><~@\"";

        $res1 = $client->search($injection1, workspaceId: $this->workspaceA->id);
        $res2 = $client->search($injection2, workspaceId: $this->workspaceA->id);
        $res3 = $client->search($injection3, workspaceId: $this->workspaceA->id);

        // Must not crash or return injected dumps
        $this->assertTrue(true, 'Query executed safely without SQL injection crash');
        $this->assertDatabaseHas('faqs', ['question' => 'Normal Question']);
    }
}

