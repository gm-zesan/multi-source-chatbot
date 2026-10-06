<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Analytics\AnalyticsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsClientTest extends TestCase
{
    /**
     * Test successful dispatch and strict workspace_id propagation.
     */
    public function test_query_dispatches_with_trusted_workspace_id(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/analytics/query' => Http::response([
                'success'               => true,
                'intent'                => 'cash_collection',
                'report'                => "📊 **Business Analytics Summary**\n- **Total Cash Collected:** ৳15,000.00",
                'sql'                   => 'SELECT COALESCE(SUM(amount), 0) AS total_cash FROM analytics_payments WHERE workspace_id = ?',
                'rows'                  => [['total_cash' => 15000.00]],
                'is_security_rejection' => false,
                'is_ambiguous'          => false,
                'latency_ms'            => 125.4,
            ], 200),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8002');
        $result = $client->query('Aj koto cashin hoise?', workspaceId: 7);

        // Assert HTTP payload sent to Python contains exact trusted workspace_id
        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8002/analytics/query'
                && $request['workspace_id'] === 7
                && $request['query'] === 'Aj koto cashin hoise?';
        });

        $this->assertTrue($result['success']);
        $this->assertSame('cash_collection', $result['intent']);
        $this->assertStringContainsString('৳15,000.00', $result['report']);
        $this->assertFalse($result['is_security_rejection']);
    }

    /**
     * Test multi-turn history and file_id propagation in queryExcel.
     */
    public function test_query_excel_dispatches_with_history_and_workspace_id(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/analytics/excel/query' => Http::response([
                'success' => true,
                'intent' => 'document_analytics',
                'report' => 'The total sales for yesterday was ৳45,000.',
                'sql' => 'SELECT SUM(amount) FROM sales_transactions',
                'rows' => [['sum' => 45000]],
                'resolved_source_id' => 'src_123',
                'resolved_source_name' => 'sales_2026.xlsx',
            ], 200),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8002');
        $history = [
            ['role' => 'user', 'content' => 'Show me sales for yesterday'],
            ['role' => 'assistant', 'content' => 'Yesterday total sales was 45000'],
        ];

        $result = $client->queryExcel(
            question: 'And how many orders was that?',
            workspaceId: 5,
            fileId: 'src_123',
            history: $history,
        );

        Http::assertSent(function ($request) use ($history) {
            return $request->url() === 'http://127.0.0.1:8002/analytics/excel/query'
                && $request['workspace_id'] === 5
                && $request['file_id'] === 'src_123'
                && $request['question'] === 'And how many orders was that?'
                && $request['history'] === $history;
        });

        $this->assertTrue($result['success']);
        $this->assertSame('sales_2026.xlsx', $result['resolved_source_name']);
    }

    /**
     * Test listing and deleting sources for a workspace.
     */
    public function test_list_and_delete_sources(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/analytics/sources/list*' => Http::response([
                'success' => true,
                'sources' => [
                    ['source_id' => 'src_123', 'name' => 'sales.xlsx', 'format' => 'xlsx'],
                ],
                'count' => 1,
            ], 200),
            'http://127.0.0.1:8002/analytics/sources/src_123*' => Http::response([
                'success' => true,
                'message' => 'Source deleted successfully.',
            ], 200),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8002');

        $list = $client->listSources(5);
        $this->assertTrue($list['success']);
        $this->assertCount(1, $list['sources']);

        $delete = $client->deleteSource(5, 'src_123');
        $this->assertTrue($delete['success']);
    }

    /**
     * Test graceful fallback when Python analytics service is unreachable.
     */
    public function test_graceful_handling_when_service_unavailable(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/analytics/query' => Http::response(null, 500),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8002');
        $result = $client->query('Total sales today', workspaceId: 1);

        $this->assertFalse($result['success']);
        $this->assertSame('service_unavailable', $result['intent']);
        $this->assertStringContainsString('Analytics Service Error', $result['report']);
    }
}
