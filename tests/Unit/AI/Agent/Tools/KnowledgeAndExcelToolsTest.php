<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Agent\Tools;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\User;
use App\Models\Workspace;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Tools\KnowledgeIntelligenceTool;
use App\AI\Tools\LegacyExcelAnalyticsTool;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\DTOs\Contract3\AnalyticsExecutionResponse;
use App\Services\Analytics\DTOs\Contract3\ExecutionAuthorizationGrant;
use App\Services\Analytics\DTOs\Contract3\PreparedQueryArtifact;
use App\Services\Analytics\DTOs\Contract3\SemanticQueryPlan;
use App\Services\Retrieval\RetrievalClient;
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
use App\Services\Spreadsheet\Relationships\DTOs\SpreadsheetRelationshipGraph;
use App\Services\Spreadsheet\Security\AnalyticsAstAuthorizer;
use App\Services\Spreadsheet\Security\Exceptions\UnauthorizedDataAccessException;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class KnowledgeAndExcelToolsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $ownerUser;
    private User $memberUser;
    private AgentContext $ownerContext;
    private AgentContext $memberContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Knowledge WS', 'slug' => 'knowledge-ws']);

        $this->ownerUser = User::create([
            'name'         => 'Owner User',
            'email'        => 'owner.kn@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);

        $this->memberUser = User::create([
            'name'         => 'Member User',
            'email'        => 'member.kn@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);

        $ownerScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->ownerUser->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [KnowledgeDocumentPermission::VIEW->value],
        );
        $this->ownerContext = AgentContext::fromServerControlledContext($ownerScc, user: $this->ownerUser);

        $memberScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->memberUser->id,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $this->memberContext = AgentContext::fromServerControlledContext($memberScc, user: $this->memberUser);
    }

    public function test_knowledge_intelligence_tool_contracts_and_faq_search(): void
    {
        $mockFaq = new FAQ([
            'id'            => 'faq-uuid-1',
            'document_type' => 'faq',
            'question'      => 'What is the refund policy?',
            'answer'        => 'Refunds are processed within 7 business days.',
        ]);

        $mockDocChunk = new FAQ([
            'id'            => 'doc_chunk_uuid_2',
            'document_type' => 'knowledge_document',
            'question'      => 'Internal Enterprise Manual',
            'answer'        => 'Confidential internal workflow procedures.',
        ]);

        $mockRetrieval = Mockery::mock(RetrievalClient::class);
        $mockRetrieval->shouldReceive('search')
            ->once()
            ->with('refund policy', $this->workspace->id, 5)
            ->andReturn(new Collection([$mockFaq, $mockDocChunk]));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockRetrieval);
        $this->assertSame('knowledge_intelligence', $tool->name());
        $this->assertTrue($tool->isReadOnly());

        // 1. Owner execution: sees both FAQ and document chunk
        $ownerResult = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'refund policy']));
        });

        $this->assertTrue($ownerResult->success);
        $this->assertSame(2, $ownerResult->data['match_count']);
        $this->assertSame('faq', $ownerResult->data['evidence_items'][0]['source_type']);
        $this->assertSame('knowledge_document', $ownerResult->data['evidence_items'][1]['source_type']);

        // 2. Member execution without document.view: document chunk is omitted
        $mockRetrievalMember = Mockery::mock(RetrievalClient::class);
        $mockRetrievalMember->shouldReceive('search')
            ->once()
            ->with('refund policy', $this->workspace->id, 5)
            ->andReturn(new Collection([$mockFaq, $mockDocChunk]));

        $toolMember = new KnowledgeIntelligenceTool(retrievalClient: $mockRetrievalMember);

        $memberResult = AgentContext::withContext($this->memberContext, function () use ($toolMember) {
            return $toolMember->handle(new Request(['query' => 'refund policy']));
        });

        $this->assertTrue($memberResult->success);
        $this->assertSame(1, $memberResult->data['match_count']);
        $this->assertSame('faq', $memberResult->data['evidence_items'][0]['source_type']);
    }

    public function test_legacy_excel_analytics_graceful_fallback_when_no_sheet(): void
    {
        $tool = new LegacyExcelAnalyticsTool();
        $this->assertSame('legacy_excel_analytics', $tool->name());
        $this->assertTrue($tool->isReadOnly());

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['question' => 'What was the highest sale?']));
        });

        $this->assertTrue($result->success);
        $this->assertSame('no_spreadsheet_found', $result->data['status']);
        $this->assertSame([], $result->data['rows']);
    }

    public function test_legacy_excel_analytics_executes_contract3_pipeline(): void
    {
        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Sales 2026 Spreadsheet',
            'original_filename' => 'sales_2026.xlsx',
            'file_path'         => 'spreadsheets/test.xlsx',
            'content_hash'      => hash('sha256', 'sales_2026'),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
        ]);

        $job = SpreadsheetImportJob::create([
            'workspace_id'       => $this->workspace->id,
            'document_id'        => $doc->id,
            'status'             => 'completed',
            'generation_id'      => 'gen-1234',
            'source_descriptors' => [
                ['source_column_id' => 'col_1', 'semantic_role' => 'revenue', 'sensitivity' => 'PUBLIC'],
            ],
        ]);

        $mockPlan = new SemanticQueryPlan(
            domain: 'sales',
            measures: [['name' => 'amount', 'aggregation' => 'sum', 'alias' => 'total_revenue']],
        );

        $mockArtifact = new PreparedQueryArtifact(
            contractVersion: PreparedQueryArtifact::CONTRACT_VERSION,
            requestId: 'req-123',
            workspaceId: $this->workspace->id,
            datasetId: (string) $job->id,
            generationId: 'gen-1234',
            queryFingerprint: 'fp-123',
            schemaFingerprint: 'sch-123',
            sqlTemplate: 'SELECT SUM(amount) AS total_revenue FROM Sheet1',
            params: [],
        );

        $mockGrant = new ExecutionAuthorizationGrant(
            grantId: 'grant-777',
            workspaceId: $this->workspace->id,
            queryFingerprint: 'fp-123',
            generationId: 'gen-1234',
            authorizedAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            expiresAt: (new \DateTimeImmutable('+60 seconds'))->format(\DateTimeInterface::ATOM),
            nonce: 'nonce-1',
            signature: 'sig-test',
        );

        $mockResponse = new AnalyticsExecutionResponse(
            success: true,
            contractVersion: AnalyticsExecutionResponse::CONTRACT_VERSION,
            requestId: 'req-123',
            workspaceId: $this->workspace->id,
            datasetId: (string) $job->id,
            generationId: 'gen-1234',
            rows: [['total_revenue' => 45000.00]],
            columns: ['total_revenue'],
            rowCount: 1,
            report: '',
            latencyMs: 12.5,
        );

        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('planCandidateQuery')
            ->once()
            ->andReturn($mockPlan);
        $mockAnalytics->shouldReceive('prepareVirtualQuery')
            ->once()
            ->andReturn($mockArtifact);
        $mockAnalytics->shouldReceive('executeVirtualQuery')
            ->once()
            ->with($mockArtifact, $mockGrant)
            ->andReturn($mockResponse);

        $mockAuthorizer = Mockery::mock(AnalyticsAstAuthorizer::class);
        $mockAuthorizer->shouldReceive('authorizePreparedQuery')
            ->once()
            ->andReturn($mockGrant);

        $mockRelService = Mockery::mock(CrossSheetRelationshipService::class);
        $mockRelService->shouldReceive('loadGraph')
            ->once()
            ->andReturn(new SpreadsheetRelationshipGraph(
                workspaceId: $this->workspace->id,
                documentId: $doc->id,
                jobId: (string) $job->id,
                generationId: 'gen-1234'
            ));

        $tool = new LegacyExcelAnalyticsTool(
            analyticsClient: $mockAnalytics,
            astAuthorizer: $mockAuthorizer,
            relationshipService: $mockRelService,
        );

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['question' => 'Total revenue this year?']));
        });

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('executed', $result->data['status']);
        $this->assertSame(1, $result->data['row_count']);
        $this->assertSame([['total_revenue' => 45000.00]], $result->data['rows']);
    }

    public function test_legacy_excel_analytics_rethrows_security_violation(): void
    {
        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Confidential Salaries',
            'original_filename' => 'confidential_salaries.xlsx',
            'file_path'         => 'spreadsheets/salaries.xlsx',
            'content_hash'      => hash('sha256', 'confidential_salaries'),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
        ]);

        $job = SpreadsheetImportJob::create([
            'workspace_id'  => $this->workspace->id,
            'document_id'   => $doc->id,
            'status'        => 'completed',
            'generation_id' => 'gen-999',
        ]);

        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('planCandidateQuery')->andReturn(new SemanticQueryPlan('payroll', []));
        $mockArtifact = new PreparedQueryArtifact(
            contractVersion: PreparedQueryArtifact::CONTRACT_VERSION,
            requestId: 'req-999',
            workspaceId: $this->workspace->id,
            datasetId: (string) $job->id,
            generationId: 'gen-999',
            queryFingerprint: 'fp-999',
            schemaFingerprint: 'sch-999',
            sqlTemplate: 'SELECT salary FROM t',
        );
        $mockAnalytics->shouldReceive('prepareVirtualQuery')->andReturn($mockArtifact);

        $mockAuthorizer = Mockery::mock(AnalyticsAstAuthorizer::class);
        $mockAuthorizer->shouldReceive('authorizePreparedQuery')
            ->andThrow(new UnauthorizedDataAccessException('Access denied to column salary'));

        $mockRelService = Mockery::mock(CrossSheetRelationshipService::class);
        $mockRelService->shouldReceive('loadGraph')
            ->once()
            ->andReturn(new SpreadsheetRelationshipGraph(
                workspaceId: $this->workspace->id,
                documentId: $doc->id,
                jobId: (string) $job->id,
                generationId: 'gen-999'
            ));

        $tool = new LegacyExcelAnalyticsTool(
            analyticsClient: $mockAnalytics,
            astAuthorizer: $mockAuthorizer,
            relationshipService: $mockRelService,
        );

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage('Access denied to spreadsheet column or relationship');

        AgentContext::withContext($this->memberContext, function () use ($tool) {
            $tool->handle(new Request(['question' => 'Show me salaries']));
        });
    }
}
