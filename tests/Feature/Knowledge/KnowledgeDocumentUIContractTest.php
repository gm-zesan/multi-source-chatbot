<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Knowledge\KnowledgeDocumentService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class KnowledgeDocumentUIContractTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected User $adminUser;
    protected User $viewerUser;
    protected KnowledgeDocumentService $documentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->workspace = Workspace::create([
            'name' => 'UI Contract Workspace',
            'slug' => 'ui-contract-workspace',
        ]);

        $this->adminUser = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->adminUser->assignRole(RoleEnum::ADMIN->value);

        $viewerRole = Role::create([
            'name' => 'viewer-role',
            'guard_name' => 'web',
        ]);
        $this->viewerUser = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->viewerUser->assignRole($viewerRole);

        $this->documentService = app(KnowledgeDocumentService::class);
    }

    public function test_datatables_represents_all_lifecycle_states_correctly(): void
    {
        // 1. PENDING_INGESTION
        $pendingDoc = KnowledgeDocument::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Pending Sheet',
            'original_filename' => 'pending.csv',
            'file_path' => 'documents/pending.csv',
            'mime_type' => 'text/csv',
            'file_size' => 1024,
            'content_hash' => hash('sha256', 'pending_test'),
            'status' => 'pending',
            'lifecycle_status' => DocumentLifecycleStatus::PENDING_INGESTION->value,
        ]);

        // 2. INDEXING
        $indexingDoc = KnowledgeDocument::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Indexing Sheet',
            'original_filename' => 'indexing.csv',
            'file_path' => 'documents/indexing.csv',
            'mime_type' => 'text/csv',
            'file_size' => 2048,
            'content_hash' => hash('sha256', 'indexing_test'),
            'status' => 'indexing',
            'lifecycle_status' => DocumentLifecycleStatus::INDEXING->value,
        ]);

        // 3. ACTIVE
        $activeDoc = KnowledgeDocument::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Active Sheet',
            'original_filename' => 'active.csv',
            'file_path' => 'documents/active.csv',
            'mime_type' => 'text/csv',
            'file_size' => 4096,
            'content_hash' => hash('sha256', 'active_test'),
            'status' => 'active',
            'lifecycle_status' => DocumentLifecycleStatus::ACTIVE->value,
            'total_rows' => 150,
        ]);

        // 4. FAILED
        $failedDoc = KnowledgeDocument::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Failed Sheet',
            'original_filename' => 'failed.csv',
            'file_path' => 'documents/failed.csv',
            'mime_type' => 'text/csv',
            'file_size' => 5120,
            'content_hash' => hash('sha256', 'failed_test'),
            'status' => 'failed',
            'error_message' => 'Processing failed: invalid format.',
            'lifecycle_status' => DocumentLifecycleStatus::FAILED->value,
        ]);

        $this->actingAs($this->adminUser);

        $request = Request::create('/dashboard/documents', 'GET', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]);

        $response = $this->documentService->getDataTables($request, $this->workspace->id, $this->adminUser);
        $data = $response->getData(true)['data'];

        $this->assertCount(4, $data);

        $rowsByTitle = collect($data)->keyBy('title');

        // Verify Pending
        $pendingRow = $rowsByTitle->get('Pending Sheet');
        $this->assertStringContainsString('Pending Ingestion', $pendingRow['status_badge']);
        $this->assertTrue($pendingRow['action-btn']['is_pending']);
        $this->assertFalse($pendingRow['action-btn']['is_active']);

        // Verify Indexing
        $indexingRow = $rowsByTitle->get('Indexing Sheet');
        $this->assertStringContainsString('Indexing', $indexingRow['status_badge']);
        $this->assertTrue($indexingRow['action-btn']['is_indexing']);
        $this->assertFalse($indexingRow['action-btn']['is_active']);

        // Verify Active
        $activeRow = $rowsByTitle->get('Active Sheet');
        $this->assertStringContainsString('Active', $activeRow['status_badge']);
        $this->assertTrue($activeRow['action-btn']['is_active']);
        $this->assertFalse($activeRow['action-btn']['is_indexing']);

        // Verify Failed
        $failedRow = $rowsByTitle->get('Failed Sheet');
        $this->assertStringContainsString('Failed', $failedRow['status_badge']);
        $this->assertTrue($failedRow['action-btn']['has_failed']);
        $this->assertSame('Processing failed: invalid format.', $failedRow['action-btn']['error']);
    }

    public function test_authorization_flags_enforced_in_datatables_payload(): void
    {
        $doc = KnowledgeDocument::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Protected Sheet',
            'original_filename' => 'protected.csv',
            'file_path' => 'documents/protected.csv',
            'mime_type' => 'text/csv',
            'file_size' => 1024,
            'content_hash' => hash('sha256', 'protected_test'),
            'status' => 'failed',
            'lifecycle_status' => DocumentLifecycleStatus::FAILED->value,
        ]);

        $request = Request::create('/dashboard/documents', 'GET', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]);

        // 1. Admin with full permissions
        $this->actingAs($this->adminUser);
        $adminResp = $this->documentService->getDataTables($request);
        $adminRow = $adminResp->getData(true)['data'][0];
        $this->assertTrue($adminRow['action-btn']['can_update']);
        $this->assertTrue($adminRow['action-btn']['can_delete']);

        // 2. Viewer without permissions
        $this->actingAs($this->viewerUser);
        $viewerResp = $this->documentService->getDataTables($request);
        $viewerRow = $viewerResp->getData(true)['data'][0];
        $this->assertFalse($viewerRow['action-btn']['can_update']);
        $this->assertFalse($viewerRow['action-btn']['can_delete']);
    }

    public function test_blade_index_view_renders_cleanly(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/dashboard/documents');
        $response->assertRedirect(route('knowledge-base.index', ['tab' => 'data_sources']));

        $hubResponse = $this->get(route('knowledge-base.index', ['tab' => 'data_sources']));
        $hubResponse->assertStatus(200);
        $hubResponse->assertSee('Knowledge Base');
        $hubResponse->assertSee('documentDetailsModal');
        $hubResponse->assertSee('modalDocPurpose');
        $hubResponse->assertSee('modalAskSimulatorBtn');
    }

    public function test_document_soft_delete_and_restore_workflow(): void
    {
        $doc = KnowledgeDocument::create([
            'workspace_id'     => $this->workspace->id,
            'title'            => 'Deletable Sheet',
            'original_filename'=> 'sheet.xlsx',
            'file_path'        => 'documents/sheet.xlsx',
            'mime_type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'        => 2048,
            'content_hash'     => hash('sha256', 'del_test'),
            'status'           => 'active',
            'lifecycle_status' => DocumentLifecycleStatus::ACTIVE->value,
        ]);

        $this->actingAs($this->adminUser);

        // Delete
        $delResp = $this->delete(route('documents.destroy', $doc->id));
        $delResp->assertRedirect(route('documents.index'));
        $this->assertSoftDeleted('knowledge_documents', ['id' => $doc->id]);

        // Restore
        $restoreResp = $this->post(route('documents.restore', $doc->id));
        $restoreResp->assertRedirect(route('documents.index'));
        $this->assertNotSoftDeleted('knowledge_documents', ['id' => $doc->id]);
    }

    public function test_blade_create_view_renders_purpose_selection_cards(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/dashboard/documents/create');
        $response->assertStatus(200);
        $response->assertSee('Upload Analytics Data Source', false);
        $response->assertSee('data_analytics');
        $response->assertSee('uploadDocumentForm');
    }

    public function test_purpose_update_endpoint_switches_document_purpose(): void
    {
        $doc = KnowledgeDocument::create([
            'workspace_id'     => $this->workspace->id,
            'title'            => 'Switchable Doc',
            'original_filename'=> 'knowledge.csv',
            'file_path'        => 'documents/knowledge.csv',
            'mime_type'        => 'text/csv',
            'file_size'        => 2048,
            'content_hash'     => hash('sha256', 'switch_test'),
            'lifecycle_status' => DocumentLifecycleStatus::ACTIVE->value,
            'purpose'          => \App\Enums\IngestionPurpose::KNOWLEDGE_BASE,
            'is_active'        => true,
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->postJson(route('documents.update-purpose', $doc->id), [
            'purpose' => \App\Enums\IngestionPurpose::DATA_ANALYTICS->value,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'purpose' => 'data_analytics',
            ]);

        $this->assertSame(
            \App\Enums\IngestionPurpose::DATA_ANALYTICS,
            $doc->fresh()->getIngestionPurpose()
        );
    }

    public function test_store_creates_direct_text_knowledge_document(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('documents.store'), [
            'purpose' => \App\Enums\IngestionPurpose::KNOWLEDGE_BASE->value,
            'title'   => 'Store Return & Refund Policy',
            'content' => 'Customers can return any unworn clothing item within 7 days with original receipt for a full refund.',
        ]);

        $response->assertRedirect(route('documents.index'));
        $this->assertDatabaseHas('knowledge_documents', [
            'workspace_id'     => $this->workspace->id,
            'title'            => 'Store Return & Refund Policy',
            'mime_type'        => 'text/plain',
            'purpose'          => 'knowledge_base',
            'lifecycle_status' => DocumentLifecycleStatus::ACTIVE->value,
            'is_active'        => 1,
        ]);
    }
}
