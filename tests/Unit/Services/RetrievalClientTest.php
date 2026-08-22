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
}
