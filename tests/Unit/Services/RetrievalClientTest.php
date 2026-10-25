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
}

