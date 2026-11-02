<?php

declare(strict_types=1);

namespace Tests\Feature\Retrieval;

use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * IMP-RET-002: MySQL FULLTEXT Integration & Hardening Verification.
 *
 * Requirements:
 * - Runs strictly against isolated disposable database: [chatbot_test_disposable].
 * - NEVER connects to [chatbot_core] or production data.
 * - Genuinely exercises MySQL InnoDB FULLTEXT MATCH(...) AGAINST(...) in BOOLEAN MODE.
 * - Verifies exact, phrase, multi-token, Bangla, Banglish, tenant isolation, and parameter safety.
 */
class MySQLFulltextIntegrationTest extends TestCase
{
    private string $originalDefaultConnection;
    private array $createdWorkspaceIds = [];
    private array $createdFaqIds = [];
    private array $executedQueries = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Verify availability of disposable MySQL configuration
        $dispConfig = config('database.connections.mysql_disposable');
        if (empty($dispConfig) || ($dispConfig['driver'] ?? '') !== 'mysql') {
            $this->markTestSkipped('mysql_disposable connection is not configured with MySQL driver.');
        }

        // 2. Strict safety assertion: Never connect to chatbot_core
        $configuredDb = (string) ($dispConfig['database'] ?? '');
        if ($configuredDb !== 'chatbot_test_disposable') {
            $this->fail("SAFETY VIOLATION: Configured database is [{$configuredDb}]. Only [chatbot_test_disposable] is permitted.");
        }

        // 3. Switch active connection to mysql_disposable
        $this->originalDefaultConnection = config('database.default');
        config(['database.default' => 'mysql_disposable']);
        DB::setDefaultConnection('mysql_disposable');

        // 4. Runtime verification of connected database and driver
        try {
            $driver = DB::connection()->getDriverName();
            $activeDb = DB::select('SELECT DATABASE() as db')[0]->db ?? '';

            $this->assertSame('mysql', $driver, "Active driver must be 'mysql', found: {$driver}");
            $this->assertSame('chatbot_test_disposable', $activeDb, "Active database must be 'chatbot_test_disposable', found: {$activeDb}");
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL disposable test database is unreachable: ' . $e->getMessage());
        }

        // 5. Query listener to capture SQL statements executed during tests
        $this->executedQueries = [];
        DB::listen(function ($query) {
            $this->executedQueries[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
            ];
        });

        // 6. Force downstream Python retrieval service to 503 so fallbackDatabaseSearch is triggered
        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response('Service Unavailable', 503),
            'http://127.0.0.1:8001/api/v1/search' => Http::response('Service Unavailable', 503),
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test records created during this test
        if (! empty($this->createdFaqIds)) {
            DB::table('faqs')->whereIn('id', $this->createdFaqIds)->delete();
        }
        if (! empty($this->createdWorkspaceIds)) {
            DB::table('workspaces')->whereIn('id', $this->createdWorkspaceIds)->delete();
        }

        // Restore default connection
        config(['database.default' => $this->originalDefaultConnection]);
        DB::setDefaultConnection($this->originalDefaultConnection);
        DB::purge('mysql_disposable');

        parent::tearDown();
    }

    private function createTestWorkspace(string $name): int
    {
        $wsId = (int) DB::table('workspaces')->insertGetId([
            'name'       => $name,
            'slug'       => Str::slug($name) . '-' . Str::random(8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdWorkspaceIds[] = $wsId;

        return $wsId;
    }

    private function createTestFaq(int $workspaceId, string $question, string $answer, ?string $searchableText = null): string
    {
        $faqId = (string) Str::uuid();
        DB::table('faqs')->insert([
            'id'              => $faqId,
            'workspace_id'    => $workspaceId,
            'document_type'   => 'faq',
            'question'        => $question,
            'answer'          => $answer,
            'searchable_text' => $searchableText ?? $question . ' ' . $answer,
            'is_active'       => 1,
            'lifecycle_status'=> 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $this->createdFaqIds[] = $faqId;

        return $faqId;
    }

    public function test_mysql_environment_safety_and_fulltext_index_availability(): void
    {
        // 1. Verify connected database
        $activeDb = DB::select('SELECT DATABASE() as db')[0]->db;
        $this->assertSame('chatbot_test_disposable', $activeDb);

        // 2. Verify InnoDB engine and FULLTEXT index on faqs table
        $indexes = DB::select('SHOW INDEX FROM faqs WHERE Key_name = ?', ['faqs_question_searchable_text_fulltext']);
        $this->assertNotEmpty($indexes, 'Composite FULLTEXT index [faqs_question_searchable_text_fulltext] must exist on faqs table.');

        $indexedColumns = array_map(fn ($idx) => $idx->Column_name, $indexes);
        $this->assertContains('question', $indexedColumns);
        $this->assertContains('searchable_text', $indexedColumns);
        $this->assertSame('FULLTEXT', $indexes[0]->Index_type);
    }

    public function test_fulltext_exact_match_execution_and_calibrated_scoring(): void
    {
        $wsId = $this->createTestWorkspace('MySQL FT Exact Workspace');
        $faqId = $this->createTestFaq(
            workspaceId: $wsId,
            question: 'What is the refund timeline?',
            answer: 'Refund is processed within 7 business days.',
            searchableText: 'refund timeline policy return 7 days',
        );

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $this->executedQueries = [];

        $results = $client->search('What is the refund timeline?', workspaceId: $wsId);

        // 1. Verify query execution
        $this->assertNotEmpty($results);
        $topHit = $results->first();
        $this->assertSame($faqId, $topHit->faq->id);
        $this->assertSame('keyword_fallback', $topHit->matchType);
        $this->assertSame(0.95, $topHit->finalScore, 'Exact question match must receive calibrated score of 0.95');

        // 2. Assert MySQL FULLTEXT query was genuinely executed
        $fulltextQueries = array_filter($this->executedQueries, function ($q) {
            return str_contains($q['sql'], 'MATCH(question, searchable_text) AGAINST(? IN BOOLEAN MODE)');
        });
        $this->assertNotEmpty($fulltextQueries, 'Genuinely executed MySQL FULLTEXT MATCH query must be present.');

        // 3. Assert secondary parameterized LIKE fallback query on FAQs was NOT called
        $likeFaqQueries = array_filter($this->executedQueries, function ($q) {
            return str_contains($q['sql'], 'select * from `faqs`') && str_contains($q['sql'], 'LIKE');
        });
        $this->assertEmpty($likeFaqQueries, 'Secondary LIKE query on FAQs must not be called when FULLTEXT succeeds.');
    }

    public function test_fulltext_phrase_match_and_result_ordering(): void
    {
        $wsId = $this->createTestWorkspace('MySQL FT Ordering Workspace');

        // Exact match FAQ
        $faqExact = $this->createTestFaq(
            workspaceId: $wsId,
            question: 'Delivery charge',
            answer: 'Delivery charge is 60 BDT inside Dhaka.',
            searchableText: 'delivery charge shipping fee inside dhaka',
        );

        // Partial match FAQ
        $faqPartial = $this->createTestFaq(
            workspaceId: $wsId,
            question: 'General courier delivery schedule',
            answer: 'Takes 2-3 days for package courier dispatch.',
            searchableText: 'general courier delivery schedule parcel',
        );

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('Delivery charge', workspaceId: $wsId, topK: 5);

        $this->assertCount(2, $results);
        // Ordering: Exact Delivery charge > General courier delivery
        $this->assertSame($faqExact, $results[0]->faq->id);
        $this->assertSame(0.95, $results[0]->finalScore);

        $this->assertSame($faqPartial, $results[1]->faq->id);
        $this->assertLessThan($results[0]->finalScore, $results[1]->finalScore);
    }

    public function test_fulltext_multi_token_search_with_stopwords(): void
    {
        $wsId = $this->createTestWorkspace('MySQL FT Stopwords Workspace');
        $faqId = $this->createTestFaq(
            workspaceId: $wsId,
            question: 'How to cancel an order after payment?',
            answer: 'You can cancel within 2 hours from your account dashboard.',
            searchableText: 'how to cancel an order after payment cancelation account',
        );

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $this->executedQueries = [];

        // Query containing English stopwords ('how', 'to', 'an', 'after')
        $results = $client->search('How to cancel an order after payment?', workspaceId: $wsId);

        $this->assertNotEmpty($results);
        $this->assertSame($faqId, $results->first()->faq->id);
        $this->assertGreaterThanOrEqual(0.85, $results->first()->finalScore);

        // Verify FULLTEXT query executed and succeeded
        $fulltextQueries = array_filter($this->executedQueries, function ($q) {
            return str_contains($q['sql'], 'MATCH(question, searchable_text) AGAINST(? IN BOOLEAN MODE)');
        });
        $this->assertNotEmpty($fulltextQueries, 'FULLTEXT query must handle queries with stopwords without 0-hit rejection.');
    }

    public function test_fulltext_bangla_and_banglish_queries(): void
    {
        $wsId = $this->createTestWorkspace('MySQL FT Multilingual Workspace');

        $faqBangla = $this->createTestFaq(
            workspaceId: $wsId,
            question: 'ডেলিভারি চার্জ কত?',
            answer: 'ঢাকার মধ্যে ৬০ টাকা, ঢাকার বাইরে ১২০ টাকা।',
            searchableText: 'ডেলিভারি চার্জ খরচ শিপিং ঢাকা',
        );

        $faqBanglish = $this->createTestFaq(
            workspaceId: $wsId,
            question: 'Order kobe pabo?',
            answer: 'Apnar order 3-5 diner moddhe delivery kora hobe.',
            searchableText: 'order kobe pabo delivery shomoy',
        );

        $client = new RetrievalClient('http://127.0.0.1:8002');

        // 1. Native Bangla test
        $resBangla = $client->search('ডেলিভারি চার্জ', workspaceId: $wsId);
        $this->assertNotEmpty($resBangla);
        $this->assertSame($faqBangla, $resBangla->first()->faq->id);
        $this->assertGreaterThanOrEqual(0.85, $resBangla->first()->finalScore);

        // 2. Banglish test
        $resBanglish = $client->search('order kobe pabo', workspaceId: $wsId);
        $this->assertNotEmpty($resBanglish);
        $this->assertSame($faqBanglish, $resBanglish->first()->faq->id);
        $this->assertSame(0.95, $resBanglish->first()->finalScore);
    }

    public function test_special_characters_and_malformed_inputs_sanitized_safely(): void
    {
        $wsId = $this->createTestWorkspace('MySQL FT Injection Workspace');
        $faqId = $this->createTestFaq(
            workspaceId: $wsId,
            question: 'Standard Delivery Policy',
            answer: 'Standard fee is 50 BDT.',
            searchableText: 'standard delivery policy shipping',
        );

        $client = new RetrievalClient('http://127.0.0.1:8002');

        // Test SQL injection payloads
        $payload1 = "' OR 1=1 --";
        $payload2 = "'; DROP TABLE faqs; --";
        $payload3 = "+-<>~*\"@:;!?,./\\";

        // Must execute cleanly without SQL syntax errors or database modification
        $res1 = $client->search($payload1, workspaceId: $wsId);
        $res2 = $client->search($payload2, workspaceId: $wsId);
        $res3 = $client->search($payload3, workspaceId: $wsId);

        $this->assertEmpty($res1);
        $this->assertEmpty($res2);
        $this->assertEmpty($res3);

        // Verify faqs table and test record still exist intact
        $recordExists = DB::table('faqs')->where('id', $faqId)->exists();
        $this->assertTrue($recordExists, 'Table must remain intact after malicious injection attempts.');
    }

    public function test_tenant_isolation_strictly_enforced_across_two_workspaces(): void
    {
        $wsA = $this->createTestWorkspace('Tenant A Workspace');
        $wsB = $this->createTestWorkspace('Tenant B Workspace');

        $faqA = $this->createTestFaq(
            workspaceId: $wsA,
            question: 'What is the refund timeline?',
            answer: 'Tenant A confidential refund is 7 days.',
            searchableText: 'refund timeline policy 7 days',
        );

        $faqB = $this->createTestFaq(
            workspaceId: $wsB,
            question: 'What is the refund timeline?',
            answer: 'Tenant B confidential refund is 30 days.',
            searchableText: 'refund timeline policy 30 days',
        );

        $client = new RetrievalClient('http://127.0.0.1:8002');

        // Search in Workspace A
        $resA = $client->search('refund timeline', workspaceId: $wsA);
        $this->assertCount(1, $resA);
        $this->assertSame($faqA, $resA->first()->faq->id);
        $this->assertSame($wsA, (int) $resA->first()->faq->workspace_id);
        $this->assertStringContainsString('Tenant A confidential', $resA->first()->faq->answer);

        // Search in Workspace B
        $resB = $client->search('refund timeline', workspaceId: $wsB);
        $this->assertCount(1, $resB);
        $this->assertSame($faqB, $resB->first()->faq->id);
        $this->assertSame($wsB, (int) $resB->first()->faq->workspace_id);
        $this->assertStringContainsString('Tenant B confidential', $resB->first()->faq->answer);
    }

    public function test_missing_or_invalid_workspace_id_fails_closed(): void
    {
        $wsId = $this->createTestWorkspace('Tenant Fail Closed WS');
        $this->createTestFaq($wsId, 'Public Question', 'Public Answer');

        $client = new RetrievalClient('http://127.0.0.1:8002');

        // Null workspace_id
        $resNull = $client->search('Public Question', workspaceId: null);
        $this->assertEmpty($resNull);

        // Zero workspace_id
        $resZero = $client->search('Public Question', workspaceId: 0);
        $this->assertEmpty($resZero);

        // Negative workspace_id
        $resNeg = $client->search('Public Question', workspaceId: -5);
        $this->assertEmpty($resNeg);
    }

    public function test_no_fulltext_hits_gracefully_falls_back_to_parameterized_search(): void
    {
        $wsId = $this->createTestWorkspace('Fallback Param WS');
        // FAQ containing 2-char token 'bk' (which is not indexed by InnoDB FULLTEXT min_token_size=3)
        $faqId = $this->createTestFaq(
            workspaceId: $wsId,
            question: 'Payment by bk',
            answer: 'Supported.',
            searchableText: 'payment bk',
        );

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $this->executedQueries = [];

        // Search query with 2-char token 'bk' (below InnoDB FULLTEXT min_token_size=3)
        $results = $client->search('bk', workspaceId: $wsId);

        $this->assertNotEmpty($results);
        $this->assertSame($faqId, $results->first()->faq->id);

        // Both FULLTEXT and parameterized queries are observed, demonstrating graceful failover
        $fulltextQueries = array_filter($this->executedQueries, function ($q) {
            return str_contains($q['sql'], 'MATCH(question, searchable_text) AGAINST(? IN BOOLEAN MODE)');
        });
        $this->assertNotEmpty($fulltextQueries, 'MySQL FULLTEXT query was attempted first.');

        $likeQueries = array_filter($this->executedQueries, function ($q) {
            return str_contains($q['sql'], 'select * from `faqs`') && str_contains($q['sql'], 'LIKE');
        });
        $this->assertNotEmpty($likeQueries, 'When FULLTEXT cannot index short 2-char tokens, parameterized LIKE gracefully catches it.');
    }
}
