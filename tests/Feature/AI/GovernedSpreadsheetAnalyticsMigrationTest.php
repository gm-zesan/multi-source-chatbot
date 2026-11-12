<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DeterministicExecutionController;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\DTOs\ToolCall;
use App\AI\Decision\FinalResponseGenerator;
use App\AI\Decision\ToolRegistry;
use App\AI\Decision\UnifiedDecisionEngine;
use App\AI\Decision\UnifiedOrchestrator;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Formatters\LegacyExcelAnalyticsReportFormatter;
use App\AI\LLM\LLMClient;
use App\AI\LLM\LLMRequest;
use App\AI\LLM\LLMResponse;
use App\AI\Orchestration\CustomerSupportService;
use App\AI\Tools\LegacyExcelAnalyticsTool;
use App\Enums\DocumentLifecycleStatus;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\Message;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\DTOs\Contract3\AnalyticsExecutionResponse;
use App\Services\Analytics\DTOs\Contract3\ExecutionAuthorizationGrant;
use App\Services\Analytics\DTOs\Contract3\PreparedQueryArtifact;
use App\Services\Analytics\DTOs\Contract3\SemanticQueryPlan;
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;
use App\Services\Spreadsheet\Relationships\DTOs\SpreadsheetRelationshipGraph;
use App\Services\Spreadsheet\Security\AnalyticsAstAuthorizer;
use App\Services\Spreadsheet\Security\Exceptions\UnauthorizedDataAccessException;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Governed Spreadsheet Analytics Migration & Contract 3.0 Verification Suite.
 *
 * Verifies Phase A Migration:
 * - Governed tool execution through DeterministicExecutionController
 * - Zero legacy assistant / router bypass
 * - Report parity via LegacyExcelAnalyticsReportFormatter
 * - Zero 2nd LLM call for deterministic analytics
 * - Strict Contract 3.0 security & RBAC isolation
 * - Fail-closed safe failure handling without legacy customer-support fallback
 */
class GovernedSpreadsheetAnalyticsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Workspace $otherWorkspace;
    private User $owner;
    private User $manager;
    private User $staff;
    private KnowledgeDocument $document;
    private SpreadsheetImportJob $job;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->workspace = Workspace::create([
            'name' => 'Governed Analytics Store',
            'slug' => 'governed-store',
            'is_active' => true,
        ]);

        $this->otherWorkspace = Workspace::create([
            'name' => 'Foreign Store',
            'slug' => 'foreign-store',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Store Owner',
            'email' => 'owner@governed.store',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->owner->assignRole('owner');

        $this->manager = User::factory()->create([
            'name' => 'Store Manager',
            'email' => 'manager@governed.store',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->manager->assignRole('manager');

        $this->staff = User::factory()->create([
            'name' => 'Store Staff',
            'email' => 'staff@governed.store',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->staff->assignRole('staff');

        $this->document = KnowledgeDocument::create([
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

        $this->job = SpreadsheetImportJob::create([
            'workspace_id'       => $this->workspace->id,
            'document_id'        => $this->document->id,
            'status'             => 'completed',
            'generation_id'      => 'gen_gov_2026',
            'source_descriptors' => [
                ['source_column_id' => 'col_amt', 'name' => 'amount', 'semantic_role' => 'revenue', 'sensitivity' => 'PUBLIC'],
            ],
        ]);

        StructuredRecord::create([
            'workspace_id'  => $this->workspace->id,
            'document_id'   => (string) $this->document->id,
            'import_job_id' => $this->job->id,
            'sheet_name'    => 'Sales',
            'row_index'     => 1,
            'payload'       => ['Product' => 'Enterprise Plan', 'Sales' => 125000],
            'record_hash'   => hash('sha256', 'rec_1'),
            'generation_id' => $this->job->generation_id,
        ]);

        $channel = Channel::create(['name' => 'Simulator', 'slug' => 'simulator', 'driver' => 'web']);
        $channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Sim Account',
            'external_id'  => 'sim_gov_001',
            'access_token' => 'tok_sim',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'owner_sim_01',
            'customer_name'      => 'Store Owner',
            'last_direction'     => 'inbound',
            'metadata'           => ['is_simulator' => true],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION A: Tool Selection, Governed Execution & Bypass Removal
    // ─────────────────────────────────────────────────────────────────────────

    public function test_orchestrator_executes_governed_tool_pipeline_without_owner_assistant_bypass(): void
    {
        // Mock Contract 3.0 execution components
        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('planCandidateQuery')->once()->andReturn(new SemanticQueryPlan('sales', []));
        $mockAnalytics->shouldReceive('prepareVirtualQuery')->once()->andReturn(
            new PreparedQueryArtifact(
                contractVersion: '3.0.0',
                requestId: 'req-101',
                workspaceId: $this->workspace->id,
                datasetId: (string) $this->job->id,
                generationId: $this->job->generation_id,
                queryFingerprint: 'fp-101',
                schemaFingerprint: 'sch-101',
                sqlTemplate: 'SELECT SUM(amount) AS total_sales FROM Sales',
                params: []
            )
        );
        $mockAnalytics->shouldReceive('executeVirtualQuery')->once()->andReturn(
            new AnalyticsExecutionResponse(
                success: true,
                contractVersion: '3.0.0',
                requestId: 'req-101',
                workspaceId: $this->workspace->id,
                datasetId: (string) $this->job->id,
                generationId: $this->job->generation_id,
                rows: [['total_sales' => 125000.00]],
                columns: ['total_sales'],
                rowCount: 1,
                report: 'আজকের মোট বিক্রি ১,২৫,০০০ টাকা।',
                latencyMs: 8.5
            )
        );
        $this->app->instance(AnalyticsClient::class, $mockAnalytics);

        $mockAuthorizer = Mockery::mock(AnalyticsAstAuthorizer::class);
        $mockAuthorizer->shouldReceive('authorizePreparedQuery')->once()->andReturn(
            new ExecutionAuthorizationGrant(
                grantId: 'grant-101',
                workspaceId: $this->workspace->id,
                queryFingerprint: 'fp-101',
                generationId: $this->job->generation_id,
                authorizedAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                expiresAt: (new \DateTimeImmutable('+60 seconds'))->format(\DateTimeInterface::ATOM),
                nonce: 'nonce-101',
                signature: 'sig-101'
            )
        );
        $this->app->instance(AnalyticsAstAuthorizer::class, $mockAuthorizer);

        $orchestrator = app(UnifiedOrchestrator::class);
        $result = $orchestrator->process(
            query: 'স্প্রেডশিট অনুযায়ী আজকের মোট বিক্রি কত?',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false
        );

        // Verify response contract
        $this->assertSame('analytics', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertStringContainsString('📊 **ব্যবসায়িক অ্যানালিটিক্স ইনসাইট** (sales_2026.xlsx)', $result['reply']);
        $this->assertStringContainsString('আজকের মোট বিক্রি ১,২৫,০০০ টাকা।', $result['reply']);
        $this->assertSame('3.0.0', $result['contract_version'] ?? null);
        $this->assertSame('fp-101', $result['query_fingerprint'] ?? null);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION B: Bengali Report Parity & Metadata
    // ─────────────────────────────────────────────────────────────────────────

    public function test_report_formatter_produces_exact_parity_with_metadata_and_edges(): void
    {
        $formatter = new LegacyExcelAnalyticsReportFormatter();

        $edge = new DiscoveredRelationshipEdge(
            id: 'edge_sales_cust',
            sourceSheet: 'Sales',
            sourceColumnIds: ['customer_id'],
            sourceHeaders: ['customer_id'],
            targetSheet: 'Customers',
            targetColumnIds: ['id'],
            targetHeaders: ['id'],
            cardinality: '1:N',
            isJunctionTable: false,
            inclusionRatio: 1.0,
            headerSimilarity: 0.95,
            entityAlignment: 0.9,
            typeCompatibility: 1.0,
            confidence: 0.98,
            state: 'CONFIRMED'
        );

        $response = new AnalyticsExecutionResponse(
            success: true,
            contractVersion: '3.0.0',
            requestId: 'req-202',
            workspaceId: $this->workspace->id,
            datasetId: (string) $this->job->id,
            generationId: $this->job->generation_id,
            rows: [],
            columns: [],
            rowCount: 0,
            report: 'মোট প্রসেসকৃত ট্রানজ্যাকশন সফলভাবে লোড হয়েছে।',
            latencyMs: 12.0
        );

        $output = $formatter->formatExecution(
            query: 'লাভ ও মার্জিন বিশ্লেষণ দিন',
            filename: 'sales_2026.xlsx',
            recordCount: 1540,
            activeEdges: [$edge],
            executionResponse: $response
        );

        $this->assertStringContainsString('📊 **ব্যবসায়িক অ্যানালিটিক্স ইনসাইট** (sales_2026.xlsx)', $output);
        $this->assertStringContainsString('• মোট প্রসেসকৃত রেকর্ড: 1,540 টি।', $output);
        $this->assertStringContainsString('• সক্রিয় ক্রস-শিট সম্পর্ক (1 টি):', $output);
        $this->assertStringContainsString('Sales ↔ Customers (1:N, কনফিডেন্স: 98%)', $output);
        $this->assertStringContainsString('• মুনাফা ও মার্জিন বিশ্লেষণ: আপনার ডেটাসেট অনুযায়ী অনুমোদিত গ্রস মার্জিন ও ক্রয়মূল্য হিসাব যাচাই করা হয়েছে।', $output);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION C: Pass-Through & Zero 2nd LLM Call Accounting
    // ─────────────────────────────────────────────────────────────────────────

    public function test_deterministic_spreadsheet_report_bypasses_second_llm_synthesis(): void
    {
        // Mock LLMClient so we can count exact calls
        $mockLLM = Mockery::mock(LLMClient::class);

        // Call 1: Semantic Decision Engine creates structured plan
        $mockLLM->shouldReceive('generate')
            ->once()
            ->andReturn(
                new LLMResponse(
                    content: json_encode([
                        'schema_version' => '1.0',
                        'intent'         => 'ANALYTICS',
                        'response_mode'  => 'TOOL_REQUIRED',
                        'tool_calls'     => [
                            [
                                'tool'      => 'legacy_excel_analytics',
                                'operation' => 'query_spreadsheet',
                                'arguments' => ['question' => 'Excel অনুযায়ী আজকের সেলস কত?'],
                            ],
                        ],
                    ]),
                    provider: 'deepseek',
                    model: 'deepseek-chat',
                    toolCalls: null,
                    usage: [
                        'prompt_tokens'     => 150,
                        'completion_tokens' => 50,
                        'total_tokens'      => 200,
                    ]
                )
            );

        // Bind mock LLM
        $this->app->instance(LLMClient::class, $mockLLM);

        // Mock AnalyticsClient execution
        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('planCandidateQuery')->once()->andReturn(new SemanticQueryPlan('sales', []));
        $mockAnalytics->shouldReceive('prepareVirtualQuery')->once()->andReturn(
            new PreparedQueryArtifact('3.0.0', 'req-303', $this->workspace->id, (string) $this->job->id, $this->job->generation_id, 'fp-303', 'sch-303', 'SELECT 1', [])
        );
        $mockAnalytics->shouldReceive('executeVirtualQuery')->once()->andReturn(
            new AnalyticsExecutionResponse(true, '3.0.0', 'req-303', $this->workspace->id, (string) $this->job->id, $this->job->generation_id, [['sales' => 50000]], ['sales'], 1, 'আজকের সেলস ৫০,০০০ টাকা।', 5.0)
        );
        $this->app->instance(AnalyticsClient::class, $mockAnalytics);

        $mockAuthorizer = Mockery::mock(AnalyticsAstAuthorizer::class);
        $mockAuthorizer->shouldReceive('authorizePreparedQuery')->once()->andReturn(
            new ExecutionAuthorizationGrant('g-303', $this->workspace->id, 'fp-303', $this->job->generation_id, '2026-11-12T00:00:00Z', '2026-11-12T00:01:00Z', 'n-303', 's-303')
        );
        $this->app->instance(AnalyticsAstAuthorizer::class, $mockAuthorizer);

        $orchestrator = app(UnifiedOrchestrator::class);
        $result = $orchestrator->process(
            query: 'Excel অনুযায়ী আজকের সেলস কত?',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false
        );

        // Exactly 1 LLM call occurred (for decision planning). Zero 2nd LLM call for synthesis!
        $this->assertSame('analytics', $result['route']);
        $this->assertStringContainsString('আজকের সেলস ৫০,০০০ টাকা।', $result['reply']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION D: Failure Handling & Zero Legacy Fallback
    // ─────────────────────────────────────────────────────────────────────────

    public function test_missing_spreadsheet_returns_safe_message_without_legacy_fallback(): void
    {
        // Delete all spreadsheet jobs in workspace
        SpreadsheetImportJob::where('workspace_id', $this->workspace->id)->delete();

        $orchestrator = app(UnifiedOrchestrator::class);
        $result = $orchestrator->process(
            query: 'আমার এক্সেল ডেটাসেট অনুযায়ী বিক্রি কত?',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false
        );

        $this->assertSame('analytics', $result['route']);
        $this->assertStringContainsString('বর্তমানে আপনার ওয়ার্কস্পেসে সক্রিয় কোনো স্প্রেডশিট ডেটাসেট নেই', $result['reply']);
    }

    public function test_downstream_timeout_fails_safely_without_customer_support_service_fallback(): void
    {
        // Delete structured records to test the hard failure path when no offline fallback records exist
        StructuredRecord::where('workspace_id', $this->workspace->id)->delete();

        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('planCandidateQuery')->andThrow(new \RuntimeException('Connection timed out after 5000ms'));
        $this->app->instance(AnalyticsClient::class, $mockAnalytics);

        $cssSpy = Mockery::spy(CustomerSupportService::class);
        $this->app->instance(CustomerSupportService::class, $cssSpy);

        $orchestrator = app(UnifiedOrchestrator::class);
        $result = $orchestrator->process(
            query: 'এক্সেল শিট অনুযায়ী হিসাব দিন',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false
        );

        // Guarantee CustomerSupportService::handleQuery was NEVER called as fallback
        $cssSpy->shouldNotHaveReceived('handleQuery');

        $this->assertSame('analytics', $result['route']);
        $this->assertFalse($result['answered']);
        $this->assertStringContainsString('আপনার কোয়েরি প্রসেস করতে সাময়িক সমস্যা হয়েছে', $result['reply']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION E: Security & RBAC Boundaries
    // ─────────────────────────────────────────────────────────────────────────

    public function test_customer_channel_cannot_execute_legacy_excel_analytics(): void
    {
        $tool = app(LegacyExcelAnalyticsTool::class);

        $customerContext = AgentContext::forCustomer(
            workspaceId: $this->workspace->id,
            channel: 'whatsapp',
            conversation: $this->conversation
        );

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage('Customer channels are strictly forbidden from executing spreadsheet analytics.');

        $tool->execute(['question' => 'Show me sales'], $customerContext);
    }

    public function test_cross_workspace_context_is_rejected_by_ast_authorizer(): void
    {
        $tool = app(LegacyExcelAnalyticsTool::class);

        // Context belongs to other workspace
        $ownerScc = new ServerControlledContext(
            workspaceId: $this->otherWorkspace->id,
            userId: $this->owner->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $foreignContext = AgentContext::fromServerControlledContext($ownerScc, user: $this->owner);

        // Job belongs to workspace A
        $result = $tool->execute([
            'question'   => 'Total sales?',
            'dataset_id' => (string) $this->job->id,
        ], $foreignContext);

        // Scoped job query strictly filters by context workspace_id, so it safely reports no spreadsheet
        $this->assertSame('no_spreadsheet_found', $result->data['status']);
        $this->assertStringContainsString('বর্তমানে আপনার ওয়ার্কস্পেসে সক্রিয় কোনো স্প্রেডশিট ডেটাসেট নেই', $result->data['report']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION F: Gate B2.2 Provenance & Freshness Hardening Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_offline_fallback_excludes_records_from_other_documents_in_same_workspace(): void
    {
        // Document B in the same workspace with 50 foreign records
        $docB = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Unrelated Inventory Spreadsheet',
            'original_filename' => 'inventory_other.xlsx',
            'file_path'         => 'spreadsheets/inv.xlsx',
            'content_hash'      => hash('sha256', 'inv_hash'),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
        ]);

        for ($i = 0; $i < 5; $i++) {
            StructuredRecord::create([
                'workspace_id'  => $this->workspace->id,
                'document_id'   => (string) $docB->id,
                'sheet_name'    => 'Inventory',
                'row_index'     => $i + 1,
                'payload'       => ['Item' => "Item {$i}"],
                'record_hash'   => hash('sha256', "inv_rec_{$i}"),
                'generation_id' => 'gen_other_doc',
            ]);
        }

        // Mock downstream gateway failure to trigger offline fallback
        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('planCandidateQuery')->andThrow(new \RuntimeException('Gateway connection refused'));
        $this->app->instance(AnalyticsClient::class, $mockAnalytics);

        $orchestrator = app(UnifiedOrchestrator::class);
        $result = $orchestrator->process(
            query: 'বিক্রয় রিপোর্ট দিন',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false
        );

        $this->assertSame('analytics', $result['route']);
        $this->assertTrue($result['answered']);

        // Must count EXACTLY 1 record from document A (from setUp), NOT 1 + 5 = 6 records from document B!
        $this->assertStringContainsString('• মোট প্রসেসকৃত রেকর্ড: 1 টি।', $result['reply']);
        $this->assertStringNotContainsString('6 টি', $result['reply']);

        // Verify offline disclosure notice is present
        $this->assertStringContainsString('ℹ️ লাইভ ভার্চুয়াল অ্যানালিটিক্স গেটওয়ে সাময়িকভাবে অনুপলব্ধ থাকায় সংরক্ষিত স্ট্রাকচার্ড ডেটাসেটের (sales_2026.xlsx) ভিত্তিতে এই প্রতিবেদন প্রস্তুত করা হয়েছে।', $result['reply']);
    }

    public function test_successful_live_execution_does_not_include_offline_disclosure(): void
    {
        $mockAnalytics = Mockery::mock(AnalyticsClient::class);
        $mockAnalytics->shouldReceive('planCandidateQuery')->once()->andReturn(new SemanticQueryPlan('sales', []));
        $mockAnalytics->shouldReceive('prepareVirtualQuery')->once()->andReturn(
            new PreparedQueryArtifact('3.0.0', 'req-live', $this->workspace->id, (string) $this->job->id, $this->job->generation_id, 'fp-live', 'sch-live', 'SELECT 1', [])
        );
        $mockAnalytics->shouldReceive('executeVirtualQuery')->once()->andReturn(
            new AnalyticsExecutionResponse(true, '3.0.0', 'req-live', $this->workspace->id, (string) $this->job->id, $this->job->generation_id, [['amt' => 100]], ['amt'], 1, 'লাইভ সফল রিপোর্ট', 5.0)
        );
        $this->app->instance(AnalyticsClient::class, $mockAnalytics);

        $mockAuthorizer = Mockery::mock(AnalyticsAstAuthorizer::class);
        $mockAuthorizer->shouldReceive('authorizePreparedQuery')->once()->andReturn(
            new ExecutionAuthorizationGrant('g-live', $this->workspace->id, 'fp-live', $this->job->generation_id, '2026-11-12T00:00:00Z', '2026-11-12T00:01:00Z', 'n-live', 's-live')
        );
        $this->app->instance(AnalyticsAstAuthorizer::class, $mockAuthorizer);

        $orchestrator = app(UnifiedOrchestrator::class);
        $result = $orchestrator->process(
            query: 'বিক্রয় কত?',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false
        );

        $this->assertStringContainsString('লাইভ সফল রিপোর্ট', $result['reply']);
        // Guarantee disclosure does NOT appear for live execution
        $this->assertStringNotContainsString('লাইভ ভার্চুয়াল অ্যানালিটিক্স গেটওয়ে সাময়িকভাবে অনুপলব্ধ', $result['reply']);
    }
}
