<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Tools\BusinessAnalyticsTool;
use App\Services\Analytics\AnalyticsClient;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class BusinessAnalyticsToolTest extends TestCase
{
    public function test_description_and_schema(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $tool = new BusinessAnalyticsTool($clientMock);

        $this->assertNotEmpty((string) $tool->description());
        $this->assertStringContainsString('analytics', (string) $tool->description());

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
        $this->assertArrayHasKey('workspace_id', $schema);
    }

    public function test_execute_calls_analytics_client_correctly(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $clientMock->expects($this->once())
            ->method('query')
            ->with('total sales today', 1, [])
            ->willReturn([
                'status' => 'ok',
                'report' => 'Total sales today: ৳50,000',
                'raw_data' => ['total_amount' => 50000],
            ]);

        $tool = new BusinessAnalyticsTool($clientMock);
        $result = $tool->execute('total sales today', 1);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('Total sales today: ৳50,000', $result['report']);
    }

    public function test_handle_processes_ai_request_and_returns_report_string(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $clientMock->expects($this->once())
            ->method('query')
            ->with('monthly sales', 2, [])
            ->willReturn([
                'status' => 'ok',
                'report' => 'Monthly sales: ৳420,000',
            ]);

        $tool = new BusinessAnalyticsTool($clientMock, workspaceId: 2);
        $result = $tool->handle(new Request(['query' => 'monthly sales']));

        $this->assertSame('Monthly sales: ৳420,000', (string) $result);
    }

    public function test_handle_rejects_empty_query(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $clientMock->expects($this->never())->method('query');

        $tool = new BusinessAnalyticsTool($clientMock);
        $result = $tool->handle(new Request(['query' => '   ']));

        $this->assertSame('No analytics query provided.', (string) $result);
    }

    public function test_handle_uses_fallback_when_report_is_missing(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $clientMock->expects($this->once())
            ->method('query')
            ->willReturn(['status' => 'error']);

        $tool = new BusinessAnalyticsTool($clientMock, workspaceId: 1);
        $result = $tool->handle(new Request(['query' => 'top customer']));

        $this->assertSame('Could not retrieve analytics data.', (string) $result);
    }

    public function test_workspace_isolation_uses_configured_workspace_when_request_missing(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $clientMock->expects($this->once())
            ->method('query')
            ->with('due balance', 5, [])
            ->willReturn(['report' => 'Total Due: ৳15,000']);

        $tool = new BusinessAnalyticsTool($clientMock, workspaceId: 5);
        $tool->handle(new Request(['query' => 'due balance']));
    }
}
