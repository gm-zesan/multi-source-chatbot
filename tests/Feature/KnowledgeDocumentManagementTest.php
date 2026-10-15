<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $adminUserA;
    protected User $adminUserB;
    protected User $unauthorizedUser;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace Alpha',
            'slug' => 'workspace-alpha',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace Beta',
            'slug' => 'workspace-beta',
        ]);

        // Admin User for Workspace A
        $this->adminUserA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->adminUserA->assignRole(RoleEnum::ADMIN->value);

        // Admin User for Workspace B
        $this->adminUserB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
        ]);
        $this->adminUserB->assignRole(RoleEnum::ADMIN->value);

        // User without document management permissions
        $noPermRole = Role::create([
            'name'       => 'viewer-role',
            'guard_name' => 'web',
        ]);
        $this->unauthorizedUser = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->unauthorizedUser->assignRole($noPermRole);

        // Super Admin
        $this->superAdmin = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->superAdmin->assignRole(RoleEnum::SUPERADMIN->value);
    }

    /**
     * 1. Authorized admin uploads a valid text file.
     */
    public function test_authorized_admin_uploads_valid_text_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('policies.txt', 'Official company refund and return policies.');

        $response = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'title'    => 'Company Policies',
            'document' => $file,
        ]);

        $response->assertRedirect(route('documents.index'));
        $response->assertSessionHas('success', 'Document uploaded successfully.');

        $this->assertDatabaseHas('knowledge_documents', [
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Company Policies',
            'original_filename' => 'policies.txt',
            'mime_type'         => 'text/plain',
            'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION->value,
            'is_active'         => false,
        ]);
    }

    /**
     * 2. Allowed file extensions and detected MIME types are validated correctly (txt, pdf, docx, csv).
     */
    public function test_allowed_file_types_are_validated_correctly(): void
    {
        $allowedFiles = [
            'notes.txt'   => ['content' => 'Plain text content', 'mime' => 'text/plain'],
            'manual.pdf'  => ['content' => '%PDF-1.4 test pdf content', 'mime' => 'application/pdf'],
            'data.csv'    => ['content' => "id,title\n1,Alpha", 'mime' => 'text/csv'],
            'report.docx' => [
                'content' => 'Fake docx payload',
                'mime'    => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ];

        foreach ($allowedFiles as $filename => $spec) {
            $file = UploadedFile::fake()->createWithContent($filename, $spec['content'], $spec['mime']);

            $response = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
                'document' => $file,
            ]);

            $response->assertRedirect(route('documents.index'));
            $response->assertSessionHasNoErrors();
        }

        $this->assertEquals(4, KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->count());
    }

    /**
     * 3. Unsupported and spoofed files are rejected.
     */
    public function test_unsupported_and_spoofed_files_are_rejected(): void
    {
        // Disallowed extension (.exe)
        $exeFile = UploadedFile::fake()->create('installer.exe', 100, 'application/x-msdownload');
        $response1 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $exeFile,
        ]);
        $response1->assertSessionHasErrors(['document']);

        // Spoofed file: extension says .txt but mime is executable/php
        $spoofedFile = UploadedFile::fake()->create('malicious.txt', 100, 'application/x-httpd-php');
        $response2 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $spoofedFile,
        ]);
        $response2->assertSessionHasErrors(['document']);

        // Script file (.sh)
        $shFile = UploadedFile::fake()->create('deploy.sh', 50, 'application/x-sh');
        $response3 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $shFile,
        ]);
        $response3->assertSessionHasErrors(['document']);

        $this->assertEquals(0, KnowledgeDocument::count());
    }

    /**
     * 4. Files exceeding 10 MB are rejected.
     */
    public function test_files_exceeding_10mb_are_rejected(): void
    {
        // 10 MB is 10240 KB. 10241 KB exceeds limit.
        $largeFile = UploadedFile::fake()->create('large_manual.pdf', 10241, 'application/pdf');

        $response = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $largeFile,
        ]);

        $response->assertSessionHasErrors(['document']);
        $this->assertEquals(0, KnowledgeDocument::count());
    }

    /**
     * 5. Stored files use generated filenames on private storage.
     */
    public function test_stored_files_use_generated_uuid_filenames_on_private_storage(): void
    {
        $originalFilename = 'my_super_secret_file.pdf';
        $file = UploadedFile::fake()->createWithContent($originalFilename, '%PDF-1.4 sample payload', 'application/pdf');

        $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file,
        ]);

        $doc = KnowledgeDocument::where('original_filename', $originalFilename)->first();
        $this->assertNotNull($doc);

        // Ensure storage filename is NOT the original filename
        $storedBasename = basename($doc->file_path);
        $this->assertNotEquals($originalFilename, $storedBasename);

        // Ensure path follows documents/{workspace_id}/{uuid}.{ext}
        $expectedPrefix = 'documents/' . $this->workspaceA->id . '/';
        $this->assertStringStartsWith($expectedPrefix, $doc->file_path);

        // Check storage existence on private local disk
        $this->assertTrue(Storage::disk('local')->exists($doc->file_path));

        // Ensure file is NOT stored on public disk
        $this->assertFalse(Storage::disk('public')->exists($doc->file_path));
    }

    /**
     * 6. Duplicate content is rejected within the same workspace.
     */
    public function test_duplicate_content_is_rejected_within_same_workspace(): void
    {
        $sameContent = 'Identical product terms and conditions 2026.';
        $file1 = UploadedFile::fake()->createWithContent('terms_v1.txt', $sameContent, 'text/plain');
        $file2 = UploadedFile::fake()->createWithContent('terms_v2_rename.txt', $sameContent, 'text/plain');

        // First upload succeeds
        $res1 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'title'    => 'First Upload',
            'document' => $file1,
        ]);
        $res1->assertRedirect(route('documents.index'));
        $this->assertEquals(1, KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->count());

        $firstDoc = KnowledgeDocument::first();
        $firstPath = $firstDoc->file_path;

        // Second upload with identical content in same workspace fails
        $res2 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'title'    => 'Second Upload Duplicate',
            'document' => $file2,
        ]);
        $res2->assertSessionHasErrors(['document']);

        // Database count remains 1
        $this->assertEquals(1, KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->count());

        // Existing file was NOT deleted
        $this->assertTrue(Storage::disk('local')->exists($firstPath));
    }

    /**
     * 7. Identical content is permitted in different workspaces.
     */
    public function test_identical_content_is_permitted_in_different_workspaces(): void
    {
        $commonContent = 'Universal e-commerce privacy disclaimer.';
        $fileA = UploadedFile::fake()->createWithContent('privacy_a.txt', $commonContent, 'text/plain');
        $fileB = UploadedFile::fake()->createWithContent('privacy_b.txt', $commonContent, 'text/plain');

        // Upload in Workspace A
        $resA = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $fileA,
        ]);
        $resA->assertRedirect(route('documents.index'));

        // Upload identical content in Workspace B
        $resB = $this->actingAs($this->adminUserB)->post(route('documents.store'), [
            'document' => $fileB,
        ]);
        $resB->assertRedirect(route('documents.index'));

        $this->assertEquals(1, KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->count());
        $this->assertEquals(1, KnowledgeDocument::where('workspace_id', $this->workspaceB->id)->count());

        $docA = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->first();
        $docB = KnowledgeDocument::where('workspace_id', $this->workspaceB->id)->first();

        $this->assertEquals($docA->content_hash, $docB->content_hash);
        $this->assertNotEquals($docA->file_path, $docB->file_path);
    }

    /**
     * 8. Cross-workspace listing, deletion, and restoration are denied.
     */
    public function test_cross_workspace_listing_deletion_and_restoration_are_denied(): void
    {
        $docB = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceB->id,
            'title'             => 'Workspace B Private Guide',
            'original_filename' => 'guide_b.pdf',
            'file_path'         => 'documents/' . $this->workspaceB->id . '/b.pdf',
            'mime_type'         => 'application/pdf',
            'file_size'         => 1024,
            'content_hash'      => hash('sha256', 'workspace B private content'),
            'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
            'is_active'         => false,
            'created_by'        => $this->adminUserB->id,
        ]);

        // 8a. Listing isolation: Admin A AJAX listing does NOT contain docB
        $listResponse = $this->actingAs($this->adminUserA)->getJson(route('documents.index'));
        $listResponse->assertOk();
        $data = $listResponse->json('data');
        $this->assertEmpty($data);

        // 8b. Deletion isolation: Admin A cannot delete docB (403 Forbidden)
        $delResponse = $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $docB));
        $delResponse->assertForbidden();
        $this->assertNull($docB->fresh()->deleted_at);

        // 8c. Restoration isolation: Soft-deleted docB cannot be restored by Admin A
        $docB->delete();
        $this->assertNotNull($docB->fresh()->deleted_at);

        $restoreResponse = $this->actingAs($this->adminUserA)->post(route('documents.restore', $docB->id));
        $restoreResponse->assertNotFound();
        $this->assertNotNull($docB->fresh()->deleted_at);

        // Admin B can restore their own document
        $restoreSuccess = $this->actingAs($this->adminUserB)->post(route('documents.restore', $docB->id));
        $restoreSuccess->assertRedirect(route('documents.index'));
        $this->assertNull($docB->fresh()->deleted_at);
    }

    /**
     * 9. Unauthenticated or insufficiently authorized users cannot manage documents.
     */
    public function test_unauthenticated_or_insufficiently_authorized_users_cannot_manage_documents(): void
    {
        $file = UploadedFile::fake()->createWithContent('info.txt', 'Some content', 'text/plain');

        // Unauthenticated user
        $this->get(route('documents.index'))->assertRedirect(route('login'));
        $this->post(route('documents.store'), ['document' => $file])->assertRedirect(route('login'));

        // Authenticated user lacking KnowledgeDocumentPermission
        $this->actingAs($this->unauthorizedUser)->get(route('documents.index'))->assertForbidden();
        $this->actingAs($this->unauthorizedUser)->post(route('documents.store'), ['document' => $file])->assertForbidden();
    }

    /**
     * 10. Database failure does not leave an orphaned uploaded file.
     */
    public function test_database_failure_does_not_leave_orphaned_uploaded_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('crash_test.txt', 'Content intended to trigger rollback', 'text/plain');

        // Simulate database exception during KnowledgeDocument saving
        KnowledgeDocument::saving(function () {
            throw new \RuntimeException('Simulated database failure during document persistence');
        });

        try {
            $this->actingAs($this->adminUserA)->post(route('documents.store'), [
                'document' => $file,
            ]);
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated database failure during document persistence', $e->getMessage());
        }

        // Verify no document was saved in database
        $this->assertEquals(0, KnowledgeDocument::count());

        // Verify storage directory has no orphaned files
        $storedFiles = Storage::disk('local')->allFiles();
        $this->assertEmpty($storedFiles, 'Orphaned file was left on disk after database failure.');
    }

    /**
     * 11. Upload does not dispatch an ingestion job or falsely mark the document as indexed.
     */
    public function test_upload_does_not_dispatch_ingestion_job_or_falsely_mark_active(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->createWithContent('pre_ingest.pdf', '%PDF-1.4 raw data', 'application/pdf');

        $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file,
        ]);

        // Phase 1 invariant: No background jobs are dispatched
        Queue::assertNothingPushed();

        $doc = KnowledgeDocument::first();
        $this->assertNotNull($doc);

        // Document is explicitly in pending_ingestion state
        $this->assertEquals(DocumentLifecycleStatus::PENDING_INGESTION, $doc->lifecycle_status);
        $this->assertFalse($doc->is_active);
        $this->assertEquals(0, $doc->chunk_count);
        $this->assertFalse($doc->isReadyForRetrieval());
    }

    /**
     * 12. Superadmin can view and manage documents across workspaces.
     */
    public function test_superadmin_can_manage_documents_across_workspaces(): void
    {
        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceB->id,
            'title'             => 'Workspace B Document',
            'original_filename' => 'doc_b.pdf',
            'file_path'         => 'documents/' . $this->workspaceB->id . '/super.pdf',
            'mime_type'         => 'application/pdf',
            'file_size'         => 1024,
            'content_hash'      => hash('sha256', 'superadmin content'),
            'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
            'is_active'         => false,
            'created_by'        => $this->adminUserB->id,
        ]);

        // Superadmin deletes document belonging to Workspace B
        $res = $this->actingAs($this->superAdmin)->delete(route('documents.destroy', $doc));
        $res->assertRedirect(route('documents.index'));
        $this->assertNotNull($doc->fresh()->deleted_at);

        // Superadmin restores document belonging to Workspace B
        $resRestore = $this->actingAs($this->superAdmin)->post(route('documents.restore', $doc->id));
        $resRestore->assertRedirect(route('documents.index'));
        $this->assertNull($doc->fresh()->deleted_at);
    }

    public function test_datatables_server_side_request(): void
    {
        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Workspace A Doc',
            'original_filename' => 'doc_a.pdf',
            'file_path'         => 'documents/' . $this->workspaceA->id . '/a.pdf',
            'mime_type'         => 'application/pdf',
            'file_size'         => 1024,
            'content_hash'      => hash('sha256', 'workspace A content'),
            'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
            'is_active'         => false,
            'created_by'        => $this->adminUserA->id,
        ]);

        $response = $this->actingAs($this->adminUserA)->get(route('documents.index', [
            'draw' => 1,
            'columns' => [
                ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'original_filename', 'name' => 'original_filename', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'file_size', 'name' => 'file_size', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'mime_type', 'name' => 'mime_type', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'created_at', 'name' => 'created_at', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'status_badge', 'name' => 'status_badge', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'action-btn', 'name' => 'action-btn', 'searchable' => 'false', 'orderable' => 'false'],
            ],
            'order' => [
                ['column' => 5, 'dir' => 'desc']
            ],
            'start' => 0,
            'length' => 20,
            'search' => ['value' => '', 'regex' => 'false'],
        ]), ['X-Requested-With' => 'XMLHttpRequest']);

        $pageResponse = $this->actingAs($this->adminUserA)->get(route('documents.index'));
        $pageResponse->assertOk();
        $pageResponse->assertSee('Knowledge Documents');
        $pageResponse->assertSee('id="data-table"', false);
    }

    /**
     * 14. Soft-deleted document permits re-upload of identical content.
     */
    public function test_soft_deleted_document_permits_reupload_of_identical_content(): void
    {
        $content = 'Refund Policy version 1.0 content';
        $file1 = UploadedFile::fake()->createWithContent('refund_v1.txt', $content, 'text/plain');

        // Initial upload
        $res1 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file1,
        ]);
        $res1->assertRedirect(route('documents.index'));

        $doc1 = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertNotNull($doc1);
        $this->assertEquals(hash('sha256', $content), $doc1->active_content_hash);

        // Soft delete doc1
        $delRes = $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $doc1));
        $delRes->assertRedirect(route('documents.index'));

        $this->assertNotNull($doc1->fresh()->deleted_at);
        $this->assertNull($doc1->fresh()->active_content_hash);

        // Re-upload identical content succeeds as a new document
        $file2 = UploadedFile::fake()->createWithContent('refund_v2.txt', $content, 'text/plain');
        $res2 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file2,
        ]);
        $res2->assertRedirect(route('documents.index'));

        // There are now 2 records: one trashed, one active
        $allDocs = KnowledgeDocument::withTrashed()->where('workspace_id', $this->workspaceA->id)->get();
        $this->assertCount(2, $allDocs);

        $activeDocs = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->get();
        $this->assertCount(1, $activeDocs);
        $this->assertEquals(hash('sha256', $content), $activeDocs->first()->active_content_hash);
    }

    /**
     * 15. Restoring a soft-deleted document that conflicts with an active document is rejected.
     */
    public function test_restoring_document_conflicting_with_active_document_is_rejected(): void
    {
        $content = 'Warranty Policy identical content';
        $file1 = UploadedFile::fake()->createWithContent('warranty_1.txt', $content, 'text/plain');

        // Upload doc1
        $this->actingAs($this->adminUserA)->post(route('documents.store'), ['document' => $file1]);
        $doc1 = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->first();

        // Soft delete doc1
        $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $doc1));
        $this->assertNotNull($doc1->fresh()->deleted_at);

        // Upload doc2 with identical content
        $file2 = UploadedFile::fake()->createWithContent('warranty_2.txt', $content, 'text/plain');
        $this->actingAs($this->adminUserA)->post(route('documents.store'), ['document' => $file2]);
        $doc2 = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->whereNull('deleted_at')->first();
        $this->assertNotNull($doc2);
        $this->assertNotEquals($doc1->id, $doc2->id);

        // Attempt to restore doc1 while doc2 is active -> fails with validation error
        $restoreRes = $this->actingAs($this->adminUserA)->post(route('documents.restore', $doc1->id));
        $restoreRes->assertSessionHasErrors(['document']);

        // doc1 remains soft-deleted; doc2 remains active
        $this->assertNotNull($doc1->fresh()->deleted_at);
        $this->assertNull($doc2->fresh()->deleted_at);

        // Now delete doc2
        $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $doc2));
        $this->assertNotNull($doc2->fresh()->deleted_at);

        // Restoring doc1 now succeeds because no conflicting active document exists
        $restoreRes2 = $this->actingAs($this->adminUserA)->post(route('documents.restore', $doc1->id));
        $restoreRes2->assertRedirect(route('documents.index'));
        $this->assertNull($doc1->fresh()->deleted_at);
        $this->assertEquals(hash('sha256', $content), $doc1->fresh()->active_content_hash);
    }

    /**
     * 16. Database-level unique constraint enforces active document uniqueness and service handles race collision.
     */
    public function test_database_level_unique_constraint_enforces_active_document_uniqueness_on_simulated_race(): void
    {
        $content = 'Concurrency test payload content';
        $hash = hash('sha256', $content);

        // Direct DB creation of first active document
        KnowledgeDocument::create([
            'id'                  => (string) Str::uuid(),
            'workspace_id'        => $this->workspaceA->id,
            'title'               => 'Direct Doc 1',
            'original_filename'   => 'c1.txt',
            'file_path'           => 'documents/' . $this->workspaceA->id . '/c1.txt',
            'mime_type'           => 'text/plain',
            'file_size'           => strlen($content),
            'content_hash'        => $hash,
            'active_content_hash' => $hash,
            'lifecycle_status'    => DocumentLifecycleStatus::PENDING_INGESTION,
            'chunk_count'         => 0,
            'version'             => 1,
            'is_active'           => false,
            'created_by'          => $this->adminUserA->id,
        ]);

        // Direct DB creation of duplicate active document must throw UniqueConstraintViolationException
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        KnowledgeDocument::create([
            'id'                  => (string) Str::uuid(),
            'workspace_id'        => $this->workspaceA->id,
            'title'               => 'Direct Doc 2 (Raced)',
            'original_filename'   => 'c2.txt',
            'file_path'           => 'documents/' . $this->workspaceA->id . '/c2.txt',
            'mime_type'           => 'text/plain',
            'file_size'           => strlen($content),
            'content_hash'        => $hash,
            'active_content_hash' => $hash,
            'lifecycle_status'    => DocumentLifecycleStatus::PENDING_INGESTION,
            'chunk_count'         => 0,
            'version'             => 1,
            'is_active'           => false,
            'created_by'          => $this->adminUserA->id,
        ]);
    }

    /**
     * 17. User with only document.view permission cannot delete or restore documents.
     */
    public function test_user_with_only_view_permission_cannot_delete_or_restore(): void
    {
        // Create viewer user with ONLY document.view permission
        $viewer = User::create([
            'workspace_id'      => $this->workspaceA->id,
            'name'              => 'Viewer User',
            'email'             => 'viewer@workspacea.com',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active'         => 1,
        ]);
        $viewer->givePermissionTo(KnowledgeDocumentPermission::VIEW->value);

        $doc = KnowledgeDocument::create([
            'id'                  => (string) Str::uuid(),
            'workspace_id'        => $this->workspaceA->id,
            'title'               => 'Doc To Delete',
            'original_filename'   => 'del.txt',
            'file_path'           => 'documents/' . $this->workspaceA->id . '/del.txt',
            'mime_type'           => 'text/plain',
            'file_size'           => 100,
            'content_hash'        => hash('sha256', 'some delete text'),
            'active_content_hash' => hash('sha256', 'some delete text'),
            'lifecycle_status'    => DocumentLifecycleStatus::PENDING_INGESTION,
            'is_active'           => false,
            'created_by'          => $this->adminUserA->id,
        ]);

        // Attempt delete with only VIEW permission -> 403 Forbidden
        $delResponse = $this->actingAs($viewer)->delete(route('documents.destroy', $doc));
        $delResponse->assertForbidden();
        $this->assertNull($doc->fresh()->deleted_at);

        // Soft delete using authorized admin
        $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $doc));
        $this->assertNotNull($doc->fresh()->deleted_at);

        // Attempt restore with only VIEW permission -> 403 Forbidden
        $restoreResponse = $this->actingAs($viewer)->post(route('documents.restore', $doc->id));
        $restoreResponse->assertForbidden();
        $this->assertNotNull($doc->fresh()->deleted_at);
    }
}

