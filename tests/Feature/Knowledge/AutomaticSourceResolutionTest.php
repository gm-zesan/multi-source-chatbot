<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\AI\Tools\BusinessAnalyticsTool;
use App\AI\Tools\ExcelAnalyticsTool;
use App\Enums\DocumentLifecycleStatus;
use App\Enums\IngestionPurpose;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Knowledge\KnowledgeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AutomaticSourceResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Workspace $otherWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->workspace = Workspace::create([
            'name' => 'Auto Resolve WS',
            'slug' => 'auto-resolve-ws-' . Str::random(6),
        ]);

        $this->otherWorkspace = Workspace::create([
            'name' => 'Other WS',
            'slug' => 'other-ws-' . Str::random(6),
        ]);

        $this->mockRouterRoute();
    }

    private function mockRouterRoute(RouteType $route = RouteType::ANALYTICS): void
    {
        $mockRouter = Mockery::mock(HybridRouter::class);
        $mockRouter->shouldReceive('route')
            ->andReturn(new RoutingResult(
                route: $route,
                confidence: 0.95,
                intent: 'business_analytics',
                signals: [],
                entities: [],
                routerLatencyMs: 10.0,
                isFallback: false,
                securityStatus: 'allowed'
            ));

        $this->app->instance(HybridRouter::class, $mockRouter);
    }

    private function createDoc(array $attributes = []): KnowledgeDocument
    {
        return KnowledgeDocument::create(array_merge([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Test Spreadsheet',
            'original_filename' => 'spreadsheet.xlsx',
            'file_path'         => 'documents/test.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 2048,
            'content_hash'      => hash('sha256', (string) Str::uuid()),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
            'metadata'          => [
                'ingestion_purpose' => IngestionPurpose::DATA_ANALYTICS->value,
            ],
        ], $attributes));
    }

    private function createConversation(Workspace $workspace, array $metadata = []): Conversation
    {
        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        $channelAccount = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Widget ' . Str::random(4),
            'external_id'  => 'web_' . Str::random(8),
            'access_token' => 'token_' . Str::random(8),
            'is_active'    => true,
        ]);

        return Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'user_' . Str::random(6),
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'metadata'           => $metadata,
        ]);
    }

    public function test_process_analytics_registration_preserves_purpose_and_stores_searchable_catalog(): void
    {
        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldReceive('uploadSpreadsheet')
            ->once()
            ->andReturn([
                'success'          => true,
                'source_id'        => 'py_source_abc123',
                'sheets'           => ['Payroll2024'],
                'sheets_count'     => 1,
                'total_rows'       => 120,
                'schema_summary'   => 'Columns: employee_id, salary, department',
                'metadata_catalog' => [
                    'dataset_summary'     => 'Employee monthly salary dataset',
                    'searchable_keywords' => ['salary', 'বেতন', 'payroll', 'কর্মচারী'],
                ],
            ]);

        $filePath = 'documents/payroll.xlsx';
        Storage::disk('local')->put($filePath, 'fake-excel-content');

        $doc = $this->createDoc([
            'title'             => 'Payroll 2024',
            'original_filename' => 'payroll.xlsx',
            'file_path'         => $filePath,
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'is_active'         => false,
        ]);

        $service = new KnowledgeDocumentService(
            analyticsClient: $mockClient,
        );

        $service->processAnalyticsRegistration($doc);

        $doc->refresh();
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertTrue($doc->is_active);
        $this->assertSame('py_source_abc123', $doc->source_id);
        $this->assertSame(IngestionPurpose::DATA_ANALYTICS, $doc->getIngestionPurpose());
        $this->assertNotNull($doc->metadata['searchable_catalog'] ?? null);
        $this->assertContains('বেতন', $doc->metadata['searchable_catalog']['searchable_keywords']);
    }

    public function test_customer_support_service_auto_resolves_single_active_generic_file(): void
    {
        $this->createDoc([
            'source_id'         => 'payroll_src_single',
            'title'             => 'Employee Payroll',
            'original_filename' => 'payroll_march.xlsx',
        ]);

        $conversation = $this->createConversation($this->workspace);

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        $mockAnalyticsClient->shouldReceive('resolveSource')
            ->once()
            ->with('কার বেতন সবচেয়ে বেশি?', $this->workspace->id)
            ->andReturn([
                'success'   => true,
                'status'    => 'resolved',
                'source_id' => 'payroll_src_single',
                'filename'  => 'payroll_march.xlsx',
            ]);

        $mockExcelTool = Mockery::mock(ExcelAnalyticsTool::class);
        $mockExcelTool->shouldReceive('execute')
            ->once()
            ->with(
                'কার বেতন সবচেয়ে বেশি?',
                $this->workspace->id,
                'payroll_src_single',
                Mockery::any()
            )
            ->andReturn([
                'success' => true,
                'report'  => 'সবচেয়ে বেশি বেতন পাচ্ছেন হাবিব সাহেব (৮৫,০০০ টাকা)।',
            ]);

        $service = app(CustomerSupportService::class, [
            'analyticsClient'   => $mockAnalyticsClient,
            'excelAnalyticsTool' => $mockExcelTool,
        ]);

        $reply = $service->handleQuery(
            query: 'কার বেতন সবচেয়ে বেশি?',
            workspaceId: $this->workspace->id,
            conversation: $conversation,
        );

        $this->assertStringContainsString('হাবিব সাহেব', $reply['reply']);
        $conversation->refresh();
        $this->assertSame('payroll_src_single', $conversation->metadata['active_file_id']);
    }

    public function test_customer_support_service_respects_manual_file_override(): void
    {
        $this->createDoc([
            'source_id'         => 'sales_2023_src',
            'title'             => 'Sales 2023',
            'original_filename' => 'sales_2023.xlsx',
        ]);

        $this->createDoc([
            'source_id'         => 'sales_2024_src',
            'title'             => 'Sales 2024',
            'original_filename' => 'sales_2024.xlsx',
        ]);

        // Conversation already has active_file_id set manually
        $conversation = $this->createConversation($this->workspace, [
            'active_file_id' => 'sales_2023_src',
        ]);

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        // resolveSource should NOT be called because active_file_id is explicitly set!
        $mockAnalyticsClient->shouldNotReceive('resolveSource');

        $mockExcelTool = Mockery::mock(ExcelAnalyticsTool::class);
        $mockExcelTool->shouldReceive('execute')
            ->once()
            ->with(
                'Total revenue koto?',
                $this->workspace->id,
                'sales_2023_src',
                Mockery::any()
            )
            ->andReturn([
                'success' => true,
                'report'  => '2023 Total Revenue is 5,000,000 BDT.',
            ]);

        $service = app(CustomerSupportService::class, [
            'analyticsClient'   => $mockAnalyticsClient,
            'excelAnalyticsTool' => $mockExcelTool,
        ]);

        $reply = $service->handleQuery(
            query: 'Total revenue koto?',
            workspaceId: $this->workspace->id,
            conversation: $conversation,
        );

        $this->assertStringContainsString('2023 Total Revenue', $reply['reply']);
    }

    public function test_ambiguous_datasets_return_clarification_message(): void
    {
        $this->createDoc([
            'source_id'         => 'sales_23',
            'title'             => 'Sales 2023',
            'original_filename' => 'sales_2023.xlsx',
        ]);

        $this->createDoc([
            'source_id'         => 'sales_24',
            'title'             => 'Sales 2024',
            'original_filename' => 'sales_2024.xlsx',
        ]);

        $conversation = $this->createConversation($this->workspace);

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        $mockAnalyticsClient->shouldReceive('resolveSource')
            ->once()
            ->andReturn([
                'success'               => false,
                'status'                => 'ambiguous',
                'clarification_message' => '🤔 একাধিক ফাইলে সেলসের তথ্য রয়েছে। অনুগ্রহ করে নির্দিষ্ট ফাইল সিলেক্ট করুন।',
                'clarification_options' => ['sales_2023.xlsx থেকে উত্তর দাও', 'sales_2024.xlsx থেকে উত্তর দাও'],
            ]);

        $service = app(CustomerSupportService::class, [
            'analyticsClient' => $mockAnalyticsClient,
        ]);

        $reply = $service->handleQuery(
            query: 'Total sales koto?',
            workspaceId: $this->workspace->id,
            conversation: $conversation,
        );

        $this->assertStringContainsString('একাধিক ফাইলে সেলসের তথ্য রয়েছে', $reply['reply']);
    }

    public function test_knowledge_base_never_enters_generic_analytics_auto_resolver(): void
    {
        // Document has KNOWLEDGE_BASE purpose
        $this->createDoc([
            'source_id'         => 'catalog_src_99',
            'title'             => 'Catalog Products',
            'original_filename' => 'products.xlsx',
            'metadata'          => [
                'ingestion_purpose' => IngestionPurpose::KNOWLEDGE_BASE->value,
            ],
        ]);

        $conversation = $this->createConversation($this->workspace);

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        // resolveSource must NEVER be called because there are 0 active generic analytics documents!
        $mockAnalyticsClient->shouldNotReceive('resolveSource');

        $service = app(CustomerSupportService::class, [
            'analyticsClient' => $mockAnalyticsClient,
        ]);

        // A business analytics query should route to Rule 5 (Production DB) without touching generic analytics resolver
        $reply = $service->handleQuery(
            query: 'What is our total product count?',
            workspaceId: $this->workspace->id,
            conversation: $conversation,
        );

        $this->assertNotNull($reply['reply']);
    }

    public function test_inactive_or_failed_documents_are_ignored_by_auto_resolver(): void
    {
        // Document is FAILED
        $this->createDoc([
            'source_id'         => 'failed_src',
            'title'             => 'Failed Doc',
            'original_filename' => 'failed.xlsx',
            'lifecycle_status'  => DocumentLifecycleStatus::FAILED,
            'is_active'         => false,
        ]);

        $conversation = $this->createConversation($this->workspace);

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        // resolveSource must NOT be called since no active document exists!
        $mockAnalyticsClient->shouldNotReceive('resolveSource');

        $service = app(CustomerSupportService::class, [
            'analyticsClient' => $mockAnalyticsClient,
        ]);

        $reply = $service->handleQuery(
            query: 'Show me data',
            workspaceId: $this->workspace->id,
            conversation: $conversation,
        );

        $this->assertNotNull($reply['reply']);
    }

    public function test_tenant_isolation_rejects_cross_workspace_resolution(): void
    {
        // Document belongs to otherWorkspace
        $this->createDoc([
            'workspace_id'      => $this->otherWorkspace->id,
            'source_id'         => 'other_src_777',
            'title'             => 'Confidential Other WS',
            'original_filename' => 'other_payroll.xlsx',
        ]);

        // Current user is in workspace
        $conversation = $this->createConversation($this->workspace);

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        // Since workspace 1 has no documents, resolveSource is not called
        $mockAnalyticsClient->shouldNotReceive('resolveSource');

        $service = app(CustomerSupportService::class, [
            'analyticsClient' => $mockAnalyticsClient,
        ]);

        $reply = $service->handleQuery(
            query: 'কার বেতন সবচেয়ে বেশি?',
            workspaceId: $this->workspace->id,
            conversation: $conversation,
        );

        $this->assertNotNull($reply['reply']);
        $conversation->refresh();
        $this->assertNull($conversation->metadata['active_file_id'] ?? null);
    }

    public function test_spreadsheet_intent_query_with_no_matching_spreadsheet_does_not_fall_through_to_mysql(): void
    {
        // 0 active generic analytics documents in workspace
        $conversation = $this->createConversation($this->workspace);

        $mockBusinessTool = Mockery::mock(BusinessAnalyticsTool::class);
        // CRITICAL AUDIT CHECK: BusinessAnalyticsTool (Production MySQL) MUST NOT be executed for spreadsheet-intent queries!
        $mockBusinessTool->shouldNotReceive('execute');

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        $mockAnalyticsClient->shouldNotReceive('resolveSource');

        $service = app(CustomerSupportService::class, [
            'analyticsClient'       => $mockAnalyticsClient,
            'businessAnalyticsTool' => $mockBusinessTool,
        ]);

        $reply = $service->handleQuery(
            query: 'বেতন শিটে কর্মীদের বেতন কত?',
            workspaceId: $this->workspace->id,
            conversation: $conversation,
        );

        $this->assertStringContainsString('কোনো সক্রিয় স্প্রেডশিট বা ডেটাসেট পাওয়া যায়নি', $reply['reply']);
    }

    public function test_identical_filenames_across_workspaces_enforces_authoritative_isolation(): void
    {
        // Workspace 1 has sales.xlsx
        $docWs1 = $this->createDoc([
            'workspace_id'      => $this->workspace->id,
            'source_id'         => 'sales_ws1_id',
            'title'             => 'Sales 2024',
            'original_filename' => 'sales.xlsx',
        ]);

        // Workspace 2 also has sales.xlsx with identical name
        $docWs2 = $this->createDoc([
            'workspace_id'      => $this->otherWorkspace->id,
            'source_id'         => 'sales_ws2_id',
            'title'             => 'Sales 2024',
            'original_filename' => 'sales.xlsx',
        ]);

        $conversation = $this->createConversation($this->workspace);

        $mockAnalyticsClient = Mockery::mock(AnalyticsClient::class);
        // Simulate a corrupted or adversarial resolver response returning Workspace 2's source_id
        $mockAnalyticsClient->shouldReceive('resolveSource')
            ->once()
            ->andReturn([
                'success'   => true,
                'status'    => 'resolved',
                'source_id' => 'sales_ws2_id',
                'filename'  => 'sales.xlsx',
            ]);

        $mockExcelTool = Mockery::mock(ExcelAnalyticsTool::class);
        // The ExcelAnalyticsTool MUST NEVER execute on the other workspace's source_id!
        $mockExcelTool->shouldNotReceive('execute');

        $service = app(CustomerSupportService::class, [
            'analyticsClient'    => $mockAnalyticsClient,
            'excelAnalyticsTool' => $mockExcelTool,
        ]);

        // Query in workspace 1 mentioning spreadsheet intent
        $reply = $service->handleQuery(
            query: 'sales.xlsx ফাইলে মোট বিক্রয় কত?',
            workspaceId: $this->workspace->id,
            conversation: $conversation,
        );

        // Authoritative validation rejected sales_ws2_id because it does not belong to workspace 1!
        $conversation->refresh();
        $this->assertNotEquals('sales_ws2_id', $conversation->metadata['active_file_id'] ?? null);
    }

    public function test_document_restore_re_registers_generic_analytics_source(): void
    {
        $user = User::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->actingAs($user);

        Storage::disk('local')->put('documents/restore_test.xlsx', 'dummy binary content');

        $doc = $this->createDoc([
            'source_id'         => 'restore_src_1',
            'file_path'         => 'documents/restore_test.xlsx',
            'original_filename' => 'restore_test.xlsx',
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
        ]);

        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldReceive('deleteSource')
            ->once()
            ->with($this->workspace->id, 'restore_src_1')
            ->andReturn(['success' => true]);

        $mockClient->shouldReceive('uploadSpreadsheet')
            ->once()
            ->andReturn([
                'success'          => true,
                'source_id'        => 'restore_src_1',
                'sheets'           => ['Sheet1'],
                'sheets_count'     => 1,
                'metadata_catalog' => ['dataset_summary' => 'Restored sheet'],
            ]);

        $service = new KnowledgeDocumentService($mockClient);

        // 1. Soft-delete document
        $deleteResult = $service->delete($doc);
        $this->assertTrue($deleteResult['success']);
        $doc->refresh();
        $this->assertTrue($doc->trashed());
        $this->assertFalse($doc->is_active);

        // 2. Restore document -> should re-register with Python and revive active status
        $restoredDoc = $service->restore($doc->id);
        $this->assertFalse($restoredDoc->trashed());
        $this->assertTrue($restoredDoc->is_active);
        $this->assertEquals(DocumentLifecycleStatus::ACTIVE, $restoredDoc->lifecycle_status);
    }
}
