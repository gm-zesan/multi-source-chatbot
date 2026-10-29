<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\FAQ;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetrievalClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_sends_correct_payload_and_maps_results(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response([
                'results' => [
                    [
                        'id'           => 42,
                        'workspace_id' => 1,
                        'question'     => 'What is the return policy?',
                        'answer'       => '30 days money back guarantee.',
                        'score'        => 0.94,
                        'match_type'   => 'hybrid',
                    ],
                ],
            ], 200),
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('return policy', workspaceId: 1, topK: 3);

        $this->assertCount(1, $results);
        $this->assertEquals('What is the return policy?', $results->first()->faq->question);
        $this->assertEquals('30 days money back guarantee.', $results->first()->faq->answer);
        $this->assertEquals(0.94, $results->first()->finalScore);
        $this->assertEquals('hybrid', $results->first()->matchType);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8002/api/v1/search'
                && $request['query'] === 'return policy'
                && $request['workspace_id'] === 1
                && $request['top_k'] === 3;
        });
    }

    public function test_search_returns_empty_collection_for_empty_query(): void
    {
        Http::fake();

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('   ');

        $this->assertTrue($results->isEmpty());
        Http::assertNothingSent();
    }

    public function test_search_falls_back_to_database_when_http_fails(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response('Internal error', 500),
        ]);

        $workspace = \App\Models\Workspace::create(['name' => 'Test WS', 'slug' => 'test-ws']);

        $faq = FAQ::create([
            'workspace_id' => $workspace->id,
            'question'     => 'How do I cancel my subscription?',
            'answer'       => 'Click Cancel in account settings.',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('cancel subscription', workspaceId: $workspace->id, topK: 5);

        $this->assertNotEmpty($results);
        $this->assertEquals('How do I cancel my subscription?', $results->first()->faq->question);
        $this->assertEquals('keyword_fallback', $results->first()->matchType);
    }

    public function test_sync_faq_sends_post_request(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/api/v1/faqs/sync' => Http::response(['status' => 'indexed'], 200),
        ]);

        $workspace = \App\Models\Workspace::create(['name' => 'Test WS 2', 'slug' => 'test-ws-2']);

        $faq = FAQ::create([
            'workspace_id' => $workspace->id,
            'question'     => 'Test question',
            'answer'       => 'Test answer',
            'priority'     => 50,
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $success = $client->syncFaq($faq);

        $this->assertTrue($success);
        Http::assertSent(function ($request) use ($faq, $workspace) {
            return $request->url() === 'http://127.0.0.1:8002/api/v1/faqs/sync'
                && $request['id'] === $faq->id
                && $request['workspace_id'] === $workspace->id
                && $request['question'] === 'Test question';
        });
    }

    public function test_delete_faq_sends_delete_request(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/api/v1/faqs/99*' => Http::response(['status' => 'deleted'], 200),
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $success = $client->deleteFaq(99, workspaceId: 3);

        $this->assertTrue($success);
        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/api/v1/faqs/99')
                && str_contains($request->url(), 'workspace_id=3');
        });
    }

    public function test_health_check_returns_true_when_service_is_up(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $health = $client->health();

        $this->assertTrue($health['ok']);
        $this->assertNull($health['error']);
        $this->assertGreaterThanOrEqual(0, $health['latency_ms']);
    }

    public function test_base_url_resolves_from_config_and_defaults_to_port_8001(): void
    {
        // 1. Default config fallback
        config(['retrieval.base_url' => null]);
        $defaultClient = new RetrievalClient();
        $this->assertSame('http://127.0.0.1:8001', $defaultClient->baseUrl());

        // 2. Config override (simulating .env PYTHON_RETRIEVAL_URL)
        config(['retrieval.base_url' => 'http://ai-service-cluster.local:8001/']);
        $customClient = new RetrievalClient();
        $this->assertSame('http://ai-service-cluster.local:8001', $customClient->baseUrl());

        // 3. Constructor explicit override
        $explicitClient = new RetrievalClient('http://manual-host:9000/');
        $this->assertSame('http://manual-host:9000', $explicitClient->baseUrl());
    }

    public function test_fallback_with_null_workspace_fails_closed_without_unscoped_query(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response('Server Error', 500),
        ]);

        $ws1 = \App\Models\Workspace::create(['name' => 'Tenant 1', 'slug' => 'tenant-1']);
        $ws2 = \App\Models\Workspace::create(['name' => 'Tenant 2', 'slug' => 'tenant-2']);

        FAQ::create([
            'workspace_id' => $ws1->id,
            'question'     => 'What is the refund timeline?',
            'answer'       => 'Tenant 1 refund is 7 days.',
            'is_active'    => true,
        ]);

        FAQ::create([
            'workspace_id' => $ws2->id,
            'question'     => 'What is the refund timeline?',
            'answer'       => 'Tenant 2 refund is 14 days.',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('refund timeline', workspaceId: null);

        $this->assertTrue($results->isEmpty(), 'Fallback with null workspaceId must fail closed and return empty.');
    }

    public function test_fallback_with_invalid_or_non_positive_workspace_fails_safely(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response('Server Error', 500),
        ]);

        $ws = \App\Models\Workspace::create(['name' => 'Tenant Valid', 'slug' => 'tenant-valid']);
        FAQ::create([
            'workspace_id' => $ws->id,
            'question'     => 'How to reset password?',
            'answer'       => 'Click forgot password.',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');

        $resZero = $client->search('reset password', workspaceId: 0);
        $this->assertTrue($resZero->isEmpty(), 'Workspace ID 0 must fail closed.');

        $resNegative = $client->search('reset password', workspaceId: -5);
        $this->assertTrue($resNegative->isEmpty(), 'Negative Workspace ID must fail closed.');
    }

    public function test_fallback_with_valid_workspace_strictly_restricts_results_to_that_workspace(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response('Service Unavailable', 503),
        ]);

        $ws1 = \App\Models\Workspace::create(['name' => 'Tenant Alpha', 'slug' => 'tenant-alpha']);
        $ws2 = \App\Models\Workspace::create(['name' => 'Tenant Beta', 'slug' => 'tenant-beta']);

        $faq1 = FAQ::create([
            'workspace_id' => $ws1->id,
            'question'     => 'Where is my order parcel?',
            'answer'       => 'Alpha delivery portal.',
            'is_active'    => true,
        ]);

        FAQ::create([
            'workspace_id' => $ws2->id,
            'question'     => 'Where is my order parcel?',
            'answer'       => 'Beta confidential courier desk.',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('order parcel', workspaceId: $ws1->id);

        $this->assertCount(1, $results);
        $this->assertSame($faq1->id, $results->first()->faq->id);
        $this->assertSame($ws1->id, $results->first()->faq->workspace_id);
        $this->assertSame('Alpha delivery portal.', $results->first()->faq->answer);
        $this->assertSame('keyword_fallback', $results->first()->matchType);
    }

    public function test_downstream_python_http_error_cannot_bypass_tenant_isolation_through_fallback(): void
    {
        // Simulate Python rejecting missing workspace with HTTP 400
        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response([
                'detail' => 'workspace_id is required to ensure multi-tenant data isolation.'
            ], 400),
        ]);

        $ws = \App\Models\Workspace::create(['name' => 'Tenant Secret', 'slug' => 'tenant-secret']);
        FAQ::create([
            'workspace_id' => $ws->id,
            'question'     => 'Company confidential secret hotline',
            'answer'       => 'Direct executive line',
            'is_active'    => true,
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        // When workspaceId is null, Python throws 400; fallback must NOT leak the secret FAQ
        $results = $client->search('secret hotline', workspaceId: null);

        $this->assertTrue($results->isEmpty(), 'Downstream HTTP 400 on null workspace must not leak FAQs via fallback.');
    }

    public function test_search_maps_degraded_bm25_fallback_response_with_correct_scores_and_telemetry(): void
    {
        $ws = \App\Models\Workspace::create(['name' => 'BM25 Test WS', 'slug' => 'bm25-test-ws']);

        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response([
                'results' => [
                    [
                        'id'           => 101,
                        'workspace_id' => $ws->id,
                        'question'     => 'ডেলিভারি চার্জ কত?',
                        'answer'       => 'ঢাকার মধ্যে ৬০ টাকা।',
                        'score'        => 0.95,
                        'match_type'   => 'bm25',
                    ],
                ],
                'telemetry' => [
                    'degraded_bm25_fallback'     => true,
                    'tier_executed'              => 'tier1_bm25_fallback',
                    'total_retrieval_latency_ms' => 4.5,
                ],
            ], 200),
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('ডেলিভারি চার্জ', workspaceId: $ws->id, topK: 3);

        $this->assertCount(1, $results);
        $topHit = $results->first();
        $this->assertSame('bm25', $topHit->matchType);
        $this->assertEquals(0.95, $topHit->finalScore);
        $this->assertEquals(0.95, $topHit->keywordScore);
        $this->assertEquals(0.0, $topHit->semanticScore);
        $this->assertFalse($client->wasLastSearchFallback(), 'Successful BM25 fallback response must not trigger MySQL DB fallback.');

        $telemetry = $client->getLastTelemetry();
        $this->assertTrue($telemetry['degraded_bm25_fallback']);
        $this->assertSame('tier1_bm25_fallback', $telemetry['tier_executed']);
    }

    public function test_search_preserves_semantic_and_keyword_scores_from_hybrid_response(): void
    {
        $ws = \App\Models\Workspace::create(['name' => 'Hybrid Test WS', 'slug' => 'hybrid-test-ws']);

        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response([
                'results' => [
                    [
                        'id'             => 202,
                        'workspace_id'   => $ws->id,
                        'question'       => 'Laboratory Results',
                        'answer'         => 'Specimens are sorted by urgency.',
                        'score'          => 0.46,
                        'match_type'     => 'hybrid',
                        'semantic_score' => 0.22,
                        'keyword_score'  => 0.85,
                    ],
                ],
                'telemetry' => [
                    'degraded_bm25_fallback' => false,
                    'tier_executed'          => 'tier1_raw_fastpath',
                ],
            ], 200),
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('Laboratory Results', workspaceId: $ws->id, topK: 1);

        $this->assertCount(1, $results);
        $topHit = $results->first();
        $this->assertSame('hybrid', $topHit->matchType);
        $this->assertEquals(0.46, $topHit->finalScore);
        $this->assertEquals(0.22, $topHit->semanticScore);
        $this->assertEquals(0.85, $topHit->keywordScore);
    }

    public function test_search_maps_hybrid_with_missing_subscores_as_null(): void
    {
        $ws = \App\Models\Workspace::create(['name' => 'Missing Subscore WS', 'slug' => 'missing-subscore-ws']);

        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response([
                'results' => [
                    [
                        'id'           => 303,
                        'workspace_id' => $ws->id,
                        'question'     => 'Policy Inquiry',
                        'answer'       => 'Policy details content.',
                        'score'        => 0.88,
                        'match_type'   => 'hybrid',
                        // semantic_score and keyword_score omitted intentionally
                    ],
                ],
            ], 200),
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('Policy Inquiry', workspaceId: $ws->id, topK: 1);

        $this->assertCount(1, $results);
        $topHit = $results->first();
        $this->assertSame('hybrid', $topHit->matchType);
        $this->assertEquals(0.88, $topHit->finalScore);
        $this->assertNull($topHit->semanticScore, 'Missing semantic_score in hybrid must remain null rather than faking finalScore or 0.0');
        $this->assertNull($topHit->keywordScore, 'Missing keyword_score in hybrid must remain null');
    }

    public function test_search_maps_vector_mode_with_missing_subscores(): void
    {
        $ws = \App\Models\Workspace::create(['name' => 'Vector WS', 'slug' => 'vector-ws']);

        Http::fake([
            'http://127.0.0.1:8002/api/v1/search' => Http::response([
                'results' => [
                    [
                        'id'           => 404,
                        'workspace_id' => $ws->id,
                        'question'     => 'Vector Query',
                        'answer'       => 'Vector result text.',
                        'score'        => 0.91,
                        'match_type'   => 'vector',
                    ],
                ],
            ], 200),
        ]);

        $client = new RetrievalClient('http://127.0.0.1:8002');
        $results = $client->search('Vector Query', workspaceId: $ws->id, topK: 1);

        $this->assertCount(1, $results);
        $topHit = $results->first();
        $this->assertSame('vector', $topHit->matchType);
        $this->assertEquals(0.91, $topHit->finalScore);
        $this->assertEquals(0.91, $topHit->semanticScore);
        $this->assertEquals(0.0, $topHit->keywordScore);
    }

    public function test_sync_faq_payload_contains_lexicon_terms_across_domains(): void
    {
        $ws = \App\Models\Workspace::create(['name' => 'Cross Domain WS', 'slug' => 'cross-domain-ws']);
        $client = new RetrievalClient('http://127.0.0.1:8002');

        $domains = [
            'clinic' => [
                'question'        => 'দেরি হলে কী করব?',
                'answer'          => 'দেরি হলে ডাক্তারের সাথে পুনরায় অ্যাপয়েন্টমেন্ট শিডিউল করতে পারবেন।',
                'searchable_text' => 'clinic appointment delay doctor schedule',
                'expected_terms'  => ['দেরি', 'ডাক্তারের', 'অ্যাপয়েন্টমেন্ট', 'শিডিউল', 'clinic', 'appointment', 'delay', 'doctor'],
            ],
            'university' => [
                'question'        => 'ফি কত?',
                'answer'          => 'ভর্তি পরীক্ষার ফি ১০০০ টাকা।',
                'searchable_text' => 'university admission fee tuition',
                'expected_terms'  => ['ফি', 'ভর্তি', 'পরীক্ষার', 'university', 'admission', 'fee'],
            ],
            'hotel' => [
                'question'        => 'Can I extend it?',
                'answer'          => 'You can extend your hotel room booking by contacting reception.',
                'searchable_text' => 'hotel stay booking extend reservation',
                'expected_terms'  => ['extend', 'hotel', 'room', 'booking', 'reception', 'stay'],
            ],
            'retail' => [
                'question'        => 'How do I exchange my order?',
                'answer'          => 'You can request an exchange or refund within 7 days of delivery.',
                'searchable_text' => 'retail delivery exchange refund return',
                'expected_terms'  => ['exchange', 'order', 'refund', 'delivery', 'retail'],
            ],
        ];

        foreach ($domains as $domainKey => $data) {
            Http::fake([
                'http://127.0.0.1:8002/api/v1/faqs/sync' => Http::response(['status' => 'synced', 'id' => "faq-{$domainKey}", 'workspace_id' => $ws->id], 200),
            ]);

            $faq = FAQ::create([
                'workspace_id'    => $ws->id,
                'question'        => $data['question'],
                'answer'          => $data['answer'],
                'searchable_text' => $data['searchable_text'],
                'priority'        => 50,
                'is_active'       => true,
            ]);

            $success = $client->syncFaq($faq);
            $this->assertTrue($success, "Sync failed for domain: {$domainKey}");

            Http::assertSent(function ($request) use ($faq, $data, $domainKey) {
                if ($request->url() !== 'http://127.0.0.1:8002/api/v1/faqs/sync') {
                    return false;
                }
                $payload = $request->data();
                if (!isset($payload['lexicon_terms']) || !is_array($payload['lexicon_terms'])) {
                    return false;
                }
                $terms = $payload['lexicon_terms'];
                foreach ($data['expected_terms'] as $expected) {
                    if (!in_array($expected, $terms, true)) {
                        return false;
                    }
                }
                return true;
            });
        }
    }

    public function test_sync_faq_update_replaces_obsolete_lexicon_terms(): void
    {
        $ws = \App\Models\Workspace::create(['name' => 'Update Terms WS', 'slug' => 'update-terms-ws']);
        $client = new RetrievalClient('http://127.0.0.1:8002');

        // Initial hotel FAQ
        $faq = FAQ::create([
            'workspace_id'    => $ws->id,
            'question'        => 'Can I extend my stay?',
            'answer'          => 'Hotel room reservation can be extended.',
            'searchable_text' => 'hotel stay booking extend reservation',
            'priority'        => 50,
            'is_active'       => true,
        ]);

        Http::fake([
            'http://127.0.0.1:8002/api/v1/faqs/sync' => Http::response(['status' => 'synced', 'id' => $faq->id, 'workspace_id' => $ws->id], 200),
        ]);

        $this->assertTrue($client->syncFaq($faq));

        Http::assertSent(function ($request) {
            $terms = $request['lexicon_terms'] ?? [];
            return in_array('hotel', $terms, true) && in_array('reservation', $terms, true);
        });

        // Update to clinic FAQ
        $faq->update([
            'question'        => 'দেরি হলে কী করব?',
            'answer'          => 'ডাক্তারের অ্যাপয়েন্টমেন্ট বিলম্ব।',
            'searchable_text' => 'clinic delay doctor appointment',
        ]);

        Http::fake([
            'http://127.0.0.1:8002/api/v1/faqs/sync' => Http::response(['status' => 'synced', 'id' => $faq->id, 'workspace_id' => $ws->id], 200),
        ]);

        $this->assertTrue($client->syncFaq($faq));

        Http::assertSent(function ($request) {
            $terms = $request['lexicon_terms'] ?? [];
            $hasClinicTerms = in_array('দেরি', $terms, true) && in_array('clinic', $terms, true) && in_array('appointment', $terms, true);
            $hasNoOldTerms = !in_array('hotel', $terms, true) && !in_array('reservation', $terms, true);

            return $hasClinicTerms && $hasNoOldTerms;
        });
    }

    public function test_reload_lexicon_stub_returns_ok_and_workspace_id(): void
    {
        $client = new RetrievalClient('http://127.0.0.1:8002');

        $resultWithWs = $client->reloadLexicon(42);
        $this->assertTrue($resultWithWs['ok']);
        $this->assertSame(42, $resultWithWs['workspace_id']);

        $resultDefault = $client->reloadLexicon();
        $this->assertTrue($resultDefault['ok']);
        $this->assertSame(0, $resultDefault['workspace_id']);
    }

    public function test_extract_lexicon_terms_handles_empty_or_html_content_safely(): void
    {
        $ws = \App\Models\Workspace::create(['name' => 'Edge WS', 'slug' => 'edge-ws']);
        $client = new RetrievalClient('http://127.0.0.1:8002');

        // Empty content
        $emptyFaq = new FAQ([
            'workspace_id' => $ws->id,
            'question'     => '',
            'answer'       => '',
        ]);
        $emptyTerms = $client->extractLexiconTerms($emptyFaq);
        $this->assertSame([], $emptyTerms);

        // HTML content
        $htmlFaq = new FAQ([
            'workspace_id' => $ws->id,
            'question'     => '<h1>Special Pricing</h1>',
            'answer'       => '<p>Discounts for <b>students</b> &amp; seniors.</p>',
        ]);
        $htmlTerms = $client->extractLexiconTerms($htmlFaq);
        $this->assertContains('special', $htmlTerms);
        $this->assertContains('pricing', $htmlTerms);
        $this->assertContains('discounts', $htmlTerms);
        $this->assertContains('students', $htmlTerms);
        $this->assertContains('seniors', $htmlTerms);
        $this->assertNotContains('h1', $htmlTerms);
        $this->assertNotContains('p', $htmlTerms);
        $this->assertNotContains('b', $htmlTerms);
    }

    public function test_sync_faq_with_numeric_tokens_serializes_cleanly(): void
    {
        $ws = \App\Models\Workspace::create(['name' => 'Numeric WS', 'slug' => 'num-ws']);
        $client = new RetrievalClient('http://127.0.0.1:8002');

        $faq = FAQ::create([
            'workspace_id'     => $ws->id,
            'question'         => 'What is the COVID-19 protocol in Room 101?',
            'answer'           => 'Call 24/7 hotline. Admission fee is 2500 BDT.',
            'document_type'    => 'faq',
            'lifecycle_status' => \App\Enums\FaqLifecycleStatus::SYNCING,
            'is_active'        => false,
        ]);

        Http::fake([
            'http://127.0.0.1:8002/api/v1/faqs/sync' => Http::response(['status' => 'synced', 'id' => (string) $faq->id, 'workspace_id' => $ws->id], 200),
        ]);

        $success = $client->syncFaq($faq);
        $this->assertTrue($success);

        Http::assertSent(function ($request) {
            $terms = $request['lexicon_terms'] ?? [];
            $nonStrings = array_filter($terms, fn($t) => !is_string($t));
            return empty($nonStrings)
                && in_array('19', $terms, true)
                && in_array('101', $terms, true)
                && in_array('24', $terms, true)
                && in_array('2500', $terms, true);
        });
    }
}

