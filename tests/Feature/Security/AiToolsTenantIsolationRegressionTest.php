<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\AI\Tools\BusinessAnalyticsTool;
use App\AI\Tools\ExcelAnalyticsTool;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\FAQ;
use App\Services\Analytics\AnalyticsClient;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AiToolsTenantIsolationRegressionTest extends TestCase
{
    private function createDummySchemaMock(): JsonSchema
    {
        $mock = $this->createMock(JsonSchema::class);
        $mock->method('string')->willReturn(new class {
            public function description(string $d): object { return $this; }
        });
        $mock->method('integer')->willReturn(new class {
            public function description(string $d): object { return $this; }
            public function default(int $v): object { return $this; }
        });
        return $mock;
    }

    public function test_all_ai_tool_schemas_strictly_omit_workspace_id(): void
    {
        $schemaMock = $this->createDummySchemaMock();

        $knowledgeTool = new KnowledgeRetrievalTool($this->createMock(FAQSearch::class));
        $businessTool = new BusinessAnalyticsTool($this->createMock(AnalyticsClient::class));
        $excelTool = new ExcelAnalyticsTool($this->createMock(AnalyticsClient::class));

        $this->assertArrayNotHasKey(
            'workspace_id',
            $knowledgeTool->schema($schemaMock),
            'KnowledgeRetrievalTool schema must not expose workspace_id to the LLM.'
        );
        $this->assertArrayNotHasKey(
            'workspace_id',
            $businessTool->schema($schemaMock),
            'BusinessAnalyticsTool schema must not expose workspace_id to the LLM.'
        );
        $this->assertArrayNotHasKey(
            'workspace_id',
            $excelTool->schema($schemaMock),
            'ExcelAnalyticsTool schema must not expose workspace_id to the LLM.'
        );
    }

    public function test_knowledge_retrieval_tool_cannot_be_manipulated_by_llm_workspace_override(): void
    {
        $trustedWorkspaceId = 42;
        $attackerSuppliedWorkspaceId = 999;

        $faq = new FAQ(['question' => 'How to refund?', 'answer' => 'Contact support.']);
        $result = new FAQSearchResult($faq, 0.9, 0.9, 0.9, 'hybrid');

        $faqSearch = $this->createMock(FAQSearch::class);
        // CRITICAL CHECK: Downstream search MUST be called with trustedWorkspaceId (42), NEVER attacker's (999)
        $faqSearch->expects($this->once())
            ->method('search')
            ->with('How to refund?', 5, $trustedWorkspaceId)
            ->willReturn(new EloquentCollection([$result]));

        $tool = new KnowledgeRetrievalTool($faqSearch, workspaceId: $trustedWorkspaceId);

        // Adversarial LLM tool call payload attempting prompt-injection cross-tenant exfiltration
        $request = new Request([
            'query' => 'How to refund?',
            'workspace_id' => $attackerSuppliedWorkspaceId,
        ]);

        $output = (string) $tool->handle($request);
        $this->assertStringContainsString('Article #1:', $output);
        $this->assertStringContainsString('How to refund?', $output);
    }

    public function test_business_analytics_tool_cannot_be_manipulated_by_llm_workspace_override(): void
    {
        $trustedWorkspaceId = 15;
        $attackerSuppliedWorkspaceId = 999;

        $analyticsClient = $this->createMock(AnalyticsClient::class);
        // CRITICAL CHECK: Downstream Analytics query MUST be called with trustedWorkspaceId (15), NEVER attacker's (999)
        $analyticsClient->expects($this->once())
            ->method('query')
            ->with('Total orders', $trustedWorkspaceId, [])
            ->willReturn([
                'status' => 'ok',
                'report' => 'Orders count: 120',
            ]);

        $tool = new BusinessAnalyticsTool($analyticsClient, workspaceId: $trustedWorkspaceId);

        $request = new Request([
            'query' => 'Total orders',
            'workspace_id' => $attackerSuppliedWorkspaceId,
        ]);

        $output = (string) $tool->handle($request);
        $this->assertSame('Orders count: 120', $output);
    }

    public function test_excel_analytics_tool_cannot_be_manipulated_by_llm_workspace_override(): void
    {
        $trustedWorkspaceId = 7;
        $attackerSuppliedWorkspaceId = 999;

        $analyticsClient = $this->createMock(AnalyticsClient::class);
        // CRITICAL CHECK: Downstream Excel query MUST be called with trustedWorkspaceId (7), NEVER attacker's (999)
        $analyticsClient->expects($this->once())
            ->method('queryExcel')
            ->with('Total expenses', $trustedWorkspaceId, 'sheet1.xlsx', null)
            ->willReturn([
                'status' => 'ok',
                'report' => 'Total expenses: ৳30,000',
            ]);

        $tool = new ExcelAnalyticsTool($analyticsClient, workspaceId: $trustedWorkspaceId);

        $request = new Request([
            'question' => 'Total expenses',
            'workspace_id' => $attackerSuppliedWorkspaceId,
            'file_id' => 'sheet1.xlsx',
        ]);

        $output = (string) $tool->handle($request);
        $this->assertSame('Total expenses: ৳30,000', $output);
    }

    public function test_all_ai_tools_fail_closed_when_runtime_workspace_is_missing_or_invalid(): void
    {
        $invalidWorkspaces = [null, 0, -1, -999];

        foreach ($invalidWorkspaces as $ws) {
            $knowledgeTool = new KnowledgeRetrievalTool($this->createMock(FAQSearch::class), workspaceId: $ws);
            $businessTool = new BusinessAnalyticsTool($this->createMock(AnalyticsClient::class), workspaceId: $ws);
            $excelTool = new ExcelAnalyticsTool($this->createMock(AnalyticsClient::class), workspaceId: $ws);

            $this->assertSame(
                'Workspace context is missing or invalid.',
                (string) $knowledgeTool->handle(new Request(['query' => 'test']))
            );
            $this->assertSame(
                'Workspace context is missing or invalid.',
                (string) $businessTool->handle(new Request(['query' => 'test']))
            );
            $this->assertSame(
                'Workspace context is missing or invalid.',
                (string) $excelTool->handle(new Request(['question' => 'test']))
            );
        }
    }

    public function test_programmatic_execute_fails_closed_when_workspace_is_invalid(): void
    {
        $knowledgeTool = new KnowledgeRetrievalTool($this->createMock(FAQSearch::class));
        $businessTool = new BusinessAnalyticsTool($this->createMock(AnalyticsClient::class));
        $excelTool = new ExcelAnalyticsTool($this->createMock(AnalyticsClient::class));

        // Knowledge retrieval execute with invalid workspace returns empty collection
        $col = $knowledgeTool->execute('test', workspaceId: -1);
        $this->assertTrue($col->isEmpty());

        // Business analytics execute with invalid workspace returns error payload
        $resBusiness = $businessTool->execute('test', workspaceId: 0);
        $this->assertFalse($resBusiness['success']);
        $this->assertSame('error', $resBusiness['status']);

        // Excel analytics execute with invalid workspace returns error payload
        $resExcel = $excelTool->execute('test', workspaceId: -10);
        $this->assertFalse($resExcel['success']);
        $this->assertSame('error', $resExcel['status']);
    }
}

