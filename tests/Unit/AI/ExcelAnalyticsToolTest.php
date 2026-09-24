<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Tools\ExcelAnalyticsTool;
use App\Services\Analytics\AnalyticsClient;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ExcelAnalyticsToolTest extends TestCase
{
    public function test_description_and_schema(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $tool = new ExcelAnalyticsTool($clientMock);

        $this->assertNotEmpty((string) $tool->description());
        $this->assertStringContainsString('Excel', (string) $tool->description());

        $schemaMock = $this->createMock(\Illuminate\Contracts\JsonSchema\JsonSchema::class);
        $schemaMock->method('string')->willReturn(new class {
            public function description(string $d): object { return $this; }
        });
        $schemaMock->method('integer')->willReturn(new class {
            public function description(string $d): object { return $this; }
            public function default(int $v): object { return $this; }
        });

        $schema = $tool->schema($schemaMock);
        $this->assertArrayHasKey('question', $schema);
        $this->assertArrayHasKey('workspace_id', $schema);
        $this->assertArrayHasKey('file_id', $schema);
    }

    public function test_execute_delegates_to_analytics_client(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $clientMock->expects($this->once())
            ->method('queryExcel')
            ->with('What is total expense in Sheet2?', 1, 'file_123')
            ->willReturn([
                'status' => 'ok',
                'report' => 'Total expense: ৳75,000',
            ]);

        $tool = new ExcelAnalyticsTool($clientMock);
        $result = $tool->execute('What is total expense in Sheet2?', 1, 'file_123');

        $this->assertSame('ok', $result['status']);
        $this->assertSame('Total expense: ৳75,000', $result['report']);
    }

    public function test_handle_processes_ai_request(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $clientMock->expects($this->once())
            ->method('queryExcel')
            ->with('total employees', 3, null)
            ->willReturn([
                'status' => 'ok',
                'report' => 'Total employees: 25',
            ]);

        $tool = new ExcelAnalyticsTool($clientMock, workspaceId: 3);
        $result = $tool->handle(new Request(['question' => 'total employees']));

        $this->assertSame('Total employees: 25', (string) $result);
    }

    public function test_handle_rejects_empty_question(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $clientMock->expects($this->never())->method('queryExcel');

        $tool = new ExcelAnalyticsTool($clientMock);
        $result = $tool->handle(new Request(['question' => '   ']));

        $this->assertSame('No question provided for Excel spreadsheet analysis.', (string) $result);
    }

    public function test_handle_fallback_when_report_missing(): void
    {
        $clientMock = $this->createMock(AnalyticsClient::class);
        $clientMock->expects($this->once())
            ->method('queryExcel')
            ->willReturn(['status' => 'failed']);

        $tool = new ExcelAnalyticsTool($clientMock, workspaceId: 1);
        $result = $tool->handle(new Request(['question' => 'top salary']));

        $this->assertSame('Could not retrieve Excel analysis data.', (string) $result);
    }
}
