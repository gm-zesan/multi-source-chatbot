<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Services\Analytics\AnalyticsClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsAuthenticationAndIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('analytics.api_key', 'test-secure-analytics-key-999');
        Config::set('analytics.base_url', 'http://127.0.0.1:8001');
    }

    public function test_analytics_client_attaches_api_key_header_to_all_queries(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/analytics/query' => Http::response([
                'success' => true,
                'intent' => 'business_analytics',
                'report' => 'Report summary',
                'rows' => [],
            ], 200),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8001', apiKey: 'test-secure-analytics-key-999');
        $result = $client->query('total revenue', workspaceId: 10);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8001/analytics/query'
                && $request->hasHeader('X-API-Key', 'test-secure-analytics-key-999')
                && $request->hasHeader('Authorization', 'Bearer test-secure-analytics-key-999')
                && $request['workspace_id'] === 10;
        });

        $this->assertTrue($result['success']);
    }

    public function test_analytics_client_attaches_auth_to_excel_queries(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/analytics/excel/query' => Http::response([
                'success' => true,
                'report' => 'Excel report',
            ], 200),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8001', apiKey: 'test-secure-analytics-key-999');
        $client->queryExcel('how many items sold?', workspaceId: 10, fileId: 'src_abc');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8001/analytics/excel/query'
                && $request->hasHeader('X-API-Key', 'test-secure-analytics-key-999')
                && $request['file_id'] === 'src_abc'
                && $request['workspace_id'] === 10;
        });
    }

    public function test_analytics_client_attaches_auth_to_source_listing_and_deletion(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/analytics/sources/list*' => Http::response([
                'success' => true,
                'sources' => [],
            ], 200),
            'http://127.0.0.1:8001/analytics/sources/src_123*' => Http::response([
                'success' => true,
                'message' => 'Deleted',
            ], 200),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8001');

        $client->listSources(workspaceId: 5);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/analytics/sources/list')
                && $request->hasHeader('X-API-Key', 'test-secure-analytics-key-999');
        });

        $client->deleteSource(workspaceId: 5, sourceId: 'src_123');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/analytics/sources/src_123')
                && $request->hasHeader('X-API-Key', 'test-secure-analytics-key-999');
        });
    }

    public function test_unauthenticated_rejection_is_handled_gracefully_by_client(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/analytics/query' => Http::response([
                'detail' => 'Invalid or missing API key',
            ], 401),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8001', apiKey: 'wrong-key');
        $result = $client->query('total revenue', workspaceId: 10);

        $this->assertFalse($result['success']);
        $this->assertSame('service_unavailable', $result['intent']);
        $this->assertStringContainsString('HTTP 401', $result['report']);
    }
}
