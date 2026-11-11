<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\IngestionPurpose;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\Role;
use App\Models\SpreadsheetImportJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Spreadsheet\Security\DataClassification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SuperAdminUnrestrictedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $globalSuperAdmin;
    protected User $workspaceSuperAdmin;
    protected User $otherSuperAdmin;
    protected User $adminA;
    protected User $analystA;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->workspaceA = Workspace::create([
            'name' => 'Entrepreneurs Automation',
            'slug' => 'entrepreneurs-automation',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Apex Solutions',
            'slug' => 'apex-solutions',
        ]);

        // Global SuperAdmin seeded with workspace_id = NULL (mirrors UserSeeder)
        $this->globalSuperAdmin = User::factory()->create([
            'workspace_id' => null,
        ]);
        $this->globalSuperAdmin->assignRole(RoleEnum::SUPERADMIN->value);

        // SuperAdmin assigned to Workspace A
        $this->workspaceSuperAdmin = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->workspaceSuperAdmin->assignRole(RoleEnum::SUPERADMIN->value);

        $this->otherSuperAdmin = User::factory()->create([
            'workspace_id' => null,
        ]);
        $this->otherSuperAdmin->assignRole(RoleEnum::SUPERADMIN->value);

        $this->adminA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->adminA->assignRole(RoleEnum::ADMIN->value);

        $analystRole = Role::create([
            'name'       => 'analyst',
            'guard_name' => 'web',
        ]);
        $analystRole->givePermissionTo(KnowledgeDocumentPermission::VIEW->value);

        $this->analystA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->analystA->assignRole($analystRole);
    }

    protected function createWorkspaceCsvDocument(Workspace $workspace, string $csvContent = "Product,Price,Quantity\nKurti,1200,15"): KnowledgeDocument
    {
        $filePath = "documents/{$workspace->id}/" . (string) Str::uuid() . '.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        return KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $workspace->id,
            'title'             => "CSV in {$workspace->name}",
            'original_filename' => 'catalog.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent . Str::uuid()),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
            'is_active'         => true,
        ]);
    }

    protected function createWorkspaceXlsxDocument(Workspace $workspace): KnowledgeDocument
    {
        $spreadsheet = new Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Products');
        $sheet1->fromArray([
            ['Product', 'Selling Price', 'Purchase Cost'],
            ['Linen Shirt', '1450', '700'],
        ]);

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Stock');
        $sheet2->fromArray([
            ['SKU', 'Qty'],
            ['SKU-10', '25'],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'sa_unrestricted_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $filePath = "documents/{$workspace->id}/" . (string) Str::uuid() . '.xlsx';
        Storage::disk('local')->put($filePath, (string) file_get_contents($tempPath));
        @unlink($tempPath);

        return KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $workspace->id,
            'title'             => "Workbook in {$workspace->name}",
            'original_filename' => 'workbook.xlsx',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $filePath . Str::uuid()),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => Storage::disk('local')->size($filePath),
            'sheets_count'      => 2,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
            'is_active'         => true,
        ]);
    }

    public function test_superadmin_previews_and_streams_all_formats_across_all_workspaces_without_verification(): void
    {
        $csvA = $this->createWorkspaceCsvDocument($this->workspaceA);
        $csvB = $this->createWorkspaceCsvDocument($this->workspaceB, "Item,Amount\nBlazer,3500");
        $xlsxB = $this->createWorkspaceXlsxDocument($this->workspaceB);

        $txtPath = "documents/{$this->workspaceB->id}/" . (string) Str::uuid() . '.txt';
        Storage::disk('local')->put($txtPath, 'Return policy: 7 days unworn.');
        $txtB = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceB->id,
            'title'             => 'Return Policy TXT',
            'original_filename' => 'policy.txt',
            'file_path'         => $txtPath,
            'content_hash'      => hash('sha256', 'Return policy: 7 days unworn.'),
            'mime_type'         => 'text/plain',
            'file_size'         => 31,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::KNOWLEDGE_BASE,
            'is_active'         => true,
        ]);

        foreach ([$this->globalSuperAdmin, $this->workspaceSuperAdmin] as $superAdmin) {
            // Workspace A CSV
            $this->actingAs($superAdmin)
                ->getJson(route('documents.preview', ['document' => $csvA->id]))
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('type', 'spreadsheet')
                ->assertJsonPath('headers', ['Product', 'Price', 'Quantity'])
                ->assertJsonPath('rows.0', ['Kurti', '1200', '15']);

            // Workspace B CSV
            $this->actingAs($superAdmin)
                ->getJson(route('documents.preview', ['document' => $csvB->id]))
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('rows.0', ['Blazer', '3500']);

            // Workspace B Multi-sheet XLSX (second sheet)
            $this->actingAs($superAdmin)
                ->getJson(route('documents.preview', ['document' => $xlsxB->id, 'sheet' => 'Stock']))
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('active_sheet', 'Stock')
                ->assertJsonPath('headers', ['SKU', 'Qty'])
                ->assertJsonPath('rows.0', ['SKU-10', '25']);

            // Workspace B TXT + raw file download stream
            $this->actingAs($superAdmin)
                ->getJson(route('documents.preview', ['document' => $txtB->id]))
                ->assertOk()
                ->assertJsonPath('type', 'text')
                ->assertJsonPath('content', 'Return policy: 7 days unworn.');

            $this->actingAs($superAdmin)
                ->get(route('documents.preview', ['document' => $csvB->id, 'stream' => 1]))
                ->assertOk();
        }
    }

    public function test_superadmin_can_inspect_map_delete_and_restore_cross_workspace_documents_without_restriction(): void
    {
        Http::fake([
            '*' => Http::response([
                'success'        => true,
                'source_id'      => 'mock_src_999',
                'sheets'         => ['Sheet1'],
                'sheets_count'   => 1,
                'total_rows'     => 1,
                'tables'         => ['Sheet1' => ['columns' => ['Product', 'Price', 'Quantity']]],
                'schema_summary' => 'Mock Schema',
            ], 200),
        ]);

        $docB = $this->createWorkspaceCsvDocument($this->workspaceB);

        // 1. Inspect & Suggestions
        $this->actingAs($this->globalSuperAdmin)
            ->getJson(route('documents.spreadsheet.inspect', ['document' => $docB->id]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($this->globalSuperAdmin)
            ->getJson(route('documents.spreadsheet.suggestions', ['document' => $docB->id]))
            ->assertOk()
            ->assertJsonPath('success', true);

        // 2. Delete & Restore across workspaces
        $this->actingAs($this->globalSuperAdmin)
            ->delete(route('documents.destroy', ['document' => $docB->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('knowledge_documents', ['id' => $docB->id]);

        $this->actingAs($this->globalSuperAdmin)
            ->post(route('documents.restore', ['id' => $docB->id]))
            ->assertRedirect();

        $this->assertNotSoftDeleted('knowledge_documents', ['id' => $docB->id]);
    }

    public function test_non_superadmin_cross_workspace_isolation_remains_strictly_enforced(): void
    {
        $docB = $this->createWorkspaceCsvDocument($this->workspaceB);

        $this->actingAs($this->adminA)
            ->getJson(route('documents.preview', ['document' => $docB->id]))
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->getJson(route('documents.spreadsheet.inspect', ['document' => $docB->id]))
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->getJson(route('documents.spreadsheet.suggestions', ['document' => $docB->id]))
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->delete(route('documents.destroy', ['document' => $docB->id]))
            ->assertForbidden();
    }

    public function test_col_keyed_source_descriptors_show_unmasked_for_superadmin_and_mask_restricted_for_analyst(): void
    {
        $xlsxB = $this->createWorkspaceXlsxDocument($this->workspaceB);
        $xlsxA = $this->createWorkspaceXlsxDocument($this->workspaceA);

        foreach ([$xlsxA, $xlsxB] as $doc) {
            SpreadsheetImportJob::create([
                'document_id'        => $doc->id,
                'workspace_id'       => $doc->workspace_id,
                'sheet_name'         => 'Products',
                'original_filename'  => 'workbook.xlsx',
                'stored_path'        => $doc->file_path,
                'mime_type'          => $doc->mime_type,
                'file_size'          => $doc->file_size,
                'status'             => 'completed',
                'is_active'          => true,
                'generation_id'      => (string) Str::uuid(),
                'conflict_mode'      => 'skip',
                'header_row_index'   => 0,
                'source_descriptors' => [
                    'col_000' => [
                        'source_column_id'   => 'col_000',
                        'column_index'       => 0,
                        'original_header'    => 'Product',
                        'suggested_category' => 'entity_name',
                        'semantic_role'      => 'product_name',
                        'sensitivity'        => DataClassification::PUBLIC,
                    ],
                    'col_001' => [
                        'source_column_id'   => 'col_001',
                        'column_index'       => 1,
                        'original_header'    => 'Selling Price',
                        'suggested_category' => 'amount',
                        'semantic_role'      => 'selling_price',
                        'sensitivity'        => DataClassification::PUBLIC,
                    ],
                    'col_002' => [
                        'source_column_id'   => 'col_002',
                        'column_index'       => 2,
                        'original_header'    => 'Purchase Cost',
                        'suggested_category' => 'amount',
                        'semantic_role'      => 'purchase_cost',
                        'sensitivity'        => DataClassification::RESTRICTED,
                    ],
                ],
            ]);
        }

        // Unprivileged same-workspace analyst still has restricted columns masked (***)
        $analystPreview = $this->actingAs($this->analystA)
            ->getJson(route('documents.preview', ['document' => $xlsxA->id, 'sheet' => 'Products']));

        $analystPreview->assertOk();
        $this->assertEquals(['Linen Shirt', '1450', '***'], $analystPreview->json('rows.0'));

        // Global SuperAdmin sees unmasked values immediately across any workspace without governance verification
        $superAdminPreview = $this->actingAs($this->globalSuperAdmin)
            ->getJson(route('documents.preview', ['document' => $xlsxB->id, 'sheet' => 'Products']));

        $superAdminPreview->assertOk();
        $this->assertEquals(['Linen Shirt', '1450', '700'], $superAdminPreview->json('rows.0'));
    }

    public function test_global_superadmin_can_upload_to_target_workspace_and_hub_has_no_governance_prompts(): void
    {
        Http::fake([
            '*' => Http::response([
                'success'        => true,
                'source_id'      => 'mock_src_001',
                'sheets'         => ['Sheet1'],
                'sheets_count'   => 1,
                'total_rows'     => 1,
                'tables'         => ['Sheet1' => ['columns' => ['item', 'price']]],
                'schema_summary' => 'Mock Schema',
            ], 200),
        ]);

        // Upload form renders workspace selector for global SuperAdmin
        $this->actingAs($this->globalSuperAdmin)
            ->get(route('documents.create'))
            ->assertOk()
            ->assertSee('Target Workspace', false)
            ->assertSee('Entrepreneurs Automation');

        // Global SuperAdmin can upload specifying workspace_id
        $file = UploadedFile::fake()->createWithContent('superadmin_upload.csv', "item,price\nCap,450", 'text/csv');
        $this->actingAs($this->globalSuperAdmin)
            ->post(route('documents.store'), [
                'workspace_id' => $this->workspaceB->id,
                'purpose'      => IngestionPurpose::DATA_ANALYTICS->value,
                'redirect_to'  => 'hub',
                'title'        => 'SuperAdmin Upload Test',
                'document'     => $file,
            ])
            ->assertRedirect(route('knowledge-base.index', ['tab' => 'data_sources']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('knowledge_documents', [
            'workspace_id' => $this->workspaceB->id,
            'title'        => 'SuperAdmin Upload Test',
        ]);

        // Knowledge Base hub renders cleanly without any governance session prompts/modals
        $this->actingAs($this->globalSuperAdmin)
            ->get(route('knowledge-base.index', ['tab' => 'data_sources']))
            ->assertOk()
            ->assertDontSee('superadminGovernanceBar')
            ->assertDontSee('previewGovernanceBox')
            ->assertDontSee('governanceSessionModal')
            ->assertDontSee('submitInlineGovernanceAuthorization');
    }

    public function test_superadmin_can_edit_other_superadmin_account(): void
    {
        $this->actingAs($this->globalSuperAdmin)
            ->get(route('users.edit', ['user' => $this->otherSuperAdmin->id]))
            ->assertOk();
    }
}

