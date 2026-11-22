<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KnowledgeDocument;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Spreadsheet\SpreadsheetInspectorService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceColumnMappingMemoryTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $userA;
    private KnowledgeDocument $docA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->workspaceA = Workspace::forceCreate([
            'id'        => 999001,
            'name'      => 'Dhaka Handloom',
            'slug'      => 'dhaka-handloom',
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::forceCreate([
            'id'        => 999002,
            'name'      => 'Other Store',
            'slug'      => 'other-store',
            'is_active' => true,
        ]);

        $role = Role::create([
            'name'         => 'handloom-merchant-admin',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $role->givePermissionTo(['document.view', 'document.update']);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->userA->assignRole($role);

        $this->docA = KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Catalog Upload',
            'original_filename' => 'catalog.xlsx',
            'file_path'         => 'documents/catalog.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'content_hash'      => hash('sha256', 'catalog_content'),
            'is_active'         => true,
            'lifecycle_status'  => 'active',
            'purpose'           => 'data_analytics',
        ]);
    }

    public function test_workspace_records_and_remembers_custom_column_aliases(): void
    {
        $this->workspaceA->recordColumnAliases([
            'দর'  => 'amount',
            'মাল' => 'entity_name',
        ]);

        $this->workspaceA->refresh();
        $aliases = $this->workspaceA->getColumnAliases();

        $this->assertArrayHasKey('দর', $aliases);
        $this->assertSame('amount', $aliases['দর']);
        $this->assertSame('entity_name', $aliases['মাল']);
    }

    public function test_subsequent_file_upload_automatically_maps_learned_alias_with_100_percent_confidence(): void
    {
        $this->workspaceA->recordColumnAliases([
            'দর' => SpreadsheetInspectorService::CATEGORY_AMOUNT,
        ]);

        $inspector = app(SpreadsheetInspectorService::class);
        $customAliases = $this->workspaceA->getColumnAliases();

        $profile = [
            'type'            => 'float',
            'null_count'      => 0,
            'distinct_count'  => 5,
            'min'             => 100,
            'max'             => 500,
            'currency_symbol' => '৳',
        ];

        // Obscure header "দর" should now be automatically mapped to AMOUNT with 100% confidence!
        $suggestion = $inspector->suggestMapping('দর', $profile, $customAliases);

        $this->assertSame(SpreadsheetInspectorService::CATEGORY_AMOUNT, $suggestion['category']);
        $this->assertSame(1.0, (float) $suggestion['confidence']);
        $this->assertFalse($suggestion['is_ambiguous']);
        $this->assertStringContainsString('Workspace-level learned custom header alias', $suggestion['evidence']);
    }

    public function test_workspace_alias_isolation(): void
    {
        $this->workspaceA->recordColumnAliases([
            'দর' => 'amount',
        ]);

        $this->assertArrayNotHasKey('দর', $this->workspaceB->getColumnAliases());
    }
}
