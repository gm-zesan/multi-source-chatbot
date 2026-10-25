<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IngestionPurpose;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Knowledge\KnowledgeDocumentService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KnowledgeDocumentTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $tenantUserA;
    private User $tenantUserB;
    private User $userWithoutWorkspace;
    private User $superAdminWithoutWorkspace;
    private KnowledgeDocumentService $documentService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A',
            'slug' => 'workspace-a',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B',
            'slug' => 'workspace-b',
        ]);

        // Tenant user in Workspace A
        $this->tenantUserA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->tenantUserA->assignRole(RoleEnum::ADMIN->value);

        // Tenant user in Workspace B
        $this->tenantUserB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
        ]);
        $this->tenantUserB->assignRole(RoleEnum::ADMIN->value);

        // User with null workspace_id
        $this->userWithoutWorkspace = User::factory()->create([
            'workspace_id' => null,
        ]);
        $this->userWithoutWorkspace->assignRole(RoleEnum::ADMIN->value);

        // Superadmin without workspace_id
        $this->superAdminWithoutWorkspace = User::factory()->create([
            'workspace_id' => null,
        ]);
        $this->superAdminWithoutWorkspace->assignRole(RoleEnum::SUPERADMIN->value);

        $this->documentService = app(KnowledgeDocumentService::class);
    }

    /**
     * Scenario 1: A user with a valid authorized workspace can create a text
     * knowledge document in that workspace.
     */
    public function test_user_with_valid_authorized_workspace_creates_document_in_that_workspace(): void
    {
        $this->actingAs($this->tenantUserA);

        $doc = $this->documentService->storeText(
            title: 'Workspace A Policy',
            textContent: 'This policy strictly belongs to Workspace A.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $this->assertNotNull($doc);
        $this->assertEquals($this->workspaceA->id, $doc->workspace_id);
        $this->assertStringContainsString("documents/{$this->workspaceA->id}/", $doc->file_path);
        Storage::disk('local')->assertExists($doc->file_path);
    }

    /**
     * Scenario 2: A user with missing/null workspace context cannot silently create
     * a document in workspace 1 (must fail closed).
     */
    public function test_user_with_null_workspace_context_cannot_silently_create_document_in_workspace_1(): void
    {
        $this->actingAs($this->userWithoutWorkspace);

        $this->expectException(ValidationException::class);

        // Under current code, this does NOT throw, but silently writes to workspace 1!
        $doc = $this->documentService->storeText(
            title: 'Orphan Text',
            textContent: 'Content that should not go to workspace 1.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        // If it didn't throw, assert that it definitely was NOT created in workspace 1
        $this->assertNotEquals(1, $doc->workspace_id);
    }

    /**
     * Scenario 3: A superadmin without a selected/explicit workspace cannot silently create
     * a document in workspace 1 (must fail closed).
     */
    public function test_superadmin_without_selected_workspace_cannot_silently_create_document_in_workspace_1(): void
    {
        $this->actingAs($this->superAdminWithoutWorkspace);

        $this->expectException(ValidationException::class);

        // Under current code, this silently writes to workspace 1!
        $doc = $this->documentService->storeText(
            title: 'Superadmin Text',
            textContent: 'Superadmin content without target workspace.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE
        );

        $this->assertNotEquals(1, $doc->workspace_id);
    }

    /**
     * Scenario 4: Superadmin with explicit workspace selection can create the document
     * only in the authorized target workspace.
     */
    public function test_superadmin_with_explicit_workspace_creates_document_in_target_workspace(): void
    {
        $this->actingAs($this->superAdminWithoutWorkspace);

        $response = $this->post(route('documents.store'), [
            'purpose'      => IngestionPurpose::KNOWLEDGE_BASE->value,
            'title'        => 'Superadmin Targeted Document',
            'content'      => 'Targeted content explicitly designated for Workspace B.',
            'workspace_id' => $this->workspaceB->id,
        ]);

        $response->assertRedirect(route('documents.index'));

        $doc = KnowledgeDocument::where('title', 'Superadmin Targeted Document')->first();
        $this->assertNotNull($doc);
        $this->assertEquals($this->workspaceB->id, $doc->workspace_id);
        $this->assertStringContainsString("documents/{$this->workspaceB->id}/", $doc->file_path);
    }

    /**
     * Scenario 5: A tenant user cannot submit another workspace's ID and bypass authorization.
     */
    public function test_tenant_user_cannot_submit_another_workspace_id_and_bypass_authorization(): void
    {
        // User A belongs to Workspace A, attempts to upload/create text in Workspace B
        $this->actingAs($this->tenantUserA);

        $response = $this->post(route('documents.store'), [
            'purpose'      => IngestionPurpose::KNOWLEDGE_BASE->value,
            'title'        => 'Unauthorized Cross-Tenant Text',
            'content'      => 'Attempting to inject into Workspace B.',
            'workspace_id' => $this->workspaceB->id,
        ]);

        // Must fail closed with 403 Forbidden or 422 Validation Error
        $this->assertTrue(
            in_array($response->status(), [403, 422, 302], true),
            "Expected 403, 422 or redirect with errors, got {$response->status()}"
        );

        if ($response->status() === 302) {
            $response->assertSessionHasErrors(['workspace']);
        }

        // Verify document was NEVER created in Workspace B
        $docInB = KnowledgeDocument::where('workspace_id', $this->workspaceB->id)
            ->where('title', 'Unauthorized Cross-Tenant Text')
            ->first();
        $this->assertNull($docInB, 'Cross-tenant document was created in Workspace B!');
    }
}
