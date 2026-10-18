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
use Illuminate\Support\Facades\Http;
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
    protected array $tempFilesToDelete = [];

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

        $this->analyticsHandler = null;

        // Dynamic mock for Python analytics service
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($this->analyticsHandler !== null) {
                $handlerResult = ($this->analyticsHandler)($request);
                if ($handlerResult !== null) {
                    return $handlerResult;
                }
            }

            if (str_contains($request->url(), '/analytics/excel/upload')) {
                return Http::response([
                    'success'        => true,
                    'source_id'      => 'test_src_123',
                    'file_id'        => 'test_src_123',
                    'sheets'         => ['Sheet1'],
                    'sheets_count'   => 1,
                    'total_rows'     => 10,
                    'tables'         => ['Sheet1' => ['columns' => ['id', 'name']]],
                    'schema_summary' => 'Test Schema',
                ], 200);
            }

            if (str_contains($request->url(), '/analytics/sources/')) {
                return Http::response([
                    'success' => true,
                    'message' => 'Source deleted successfully.',
                ], 200);
            }

            return Http::response(['success' => true], 200);
        });
    }

    protected ?\Closure $analyticsHandler = null;

    protected function tearDown(): void
    {
        foreach ($this->tempFilesToDelete as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->tempFilesToDelete = [];

        parent::tearDown();
    }

    protected function createFakeXlsx(string $filename = 'test.xlsx'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_') . '.xlsx';
        $this->tempFilesToDelete[] = $tempPath;

        $zip = new \ZipArchive();
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"></workbook>');
        $zip->close();

        return new UploadedFile(
            $tempPath,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /**
     * 1. Authorized admin uploads a valid CSV file.
     */
    public function test_authorized_admin_uploads_valid_csv_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('policies.csv', "id,policy_name,amount\n1,Return Policy,100", 'text/csv');

        $response = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'title'    => 'Company Policies',
            'document' => $file,
        ]);

        $response->assertRedirect(route('documents.index'));
        $response->assertSessionHas('success', 'Document uploaded and registered successfully.');

        $this->assertDatabaseHas('knowledge_documents', [
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Company Policies',
            'original_filename' => 'policies.csv',
            'source_id'         => 'test_src_123',
            'sheets_count'      => 1,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE->value,
            'is_active'         => true,
        ]);
    }

    /**
     * 2. Allowed file types (xlsx and csv) are accepted.
     */
    public function test_allowed_file_types_xlsx_and_csv_are_accepted(): void
    {
        // 1. CSV
        $csvFile = UploadedFile::fake()->createWithContent('data.csv', "id,name\n1,Item", 'text/csv');
        $resCsv = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $csvFile,
        ]);
        $resCsv->assertRedirect(route('documents.index'));
        $resCsv->assertSessionHas('success');

        // 2. XLSX
        $xlsxFile = $this->createFakeXlsx('inventory.xlsx');
        $resXlsx = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $xlsxFile,
        ]);
        $resXlsx->assertRedirect(route('documents.index'));
        $resXlsx->assertSessionHas('success');

        $this->assertEquals(2, KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->count());
    }

    /**
     * 3. Unsupported file extensions (txt, pdf, docx, exe, sh) are rejected.
     */
    public function test_unsupported_extensions_are_rejected(): void
    {
        $unsupported = [
            'notes.txt'   => ['content' => 'Plain text content', 'mime' => 'text/plain'],
            'manual.pdf'  => ['content' => '%PDF-1.4 sample', 'mime' => 'application/pdf'],
            'report.docx' => ['content' => 'Fake docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'script.sh'   => ['content' => '#!/bin/bash', 'mime' => 'application/x-sh'],
            'bad.exe'     => ['content' => 'MZ binary', 'mime' => 'application/x-msdownload'],
        ];

        foreach ($unsupported as $filename => $spec) {
            $file = UploadedFile::fake()->createWithContent($filename, $spec['content'], $spec['mime']);

            $response = $this->actingAs($this->adminUserA)
                ->from(route('documents.create'))
                ->post(route('documents.store'), [
                    'document' => $file,
                ]);

            $response->assertRedirect(route('documents.create'));
            $response->assertSessionHasErrors(['document']);
        }

        $this->assertEquals(0, KnowledgeDocument::count());
    }

    /**
     * 4. Files exceeding 20 MB are rejected.
     */
    public function test_files_exceeding_20mb_are_rejected(): void
    {
        // 20 MB is 20480 KB. 20481 KB exceeds limit.
        $largeFile = UploadedFile::fake()->create('large.csv', 20481, 'text/csv');

        $response = $this->actingAs($this->adminUserA)
            ->from(route('documents.create'))
            ->post(route('documents.store'), [
                'document' => $largeFile,
            ]);

        $response->assertSessionHasErrors(['document']);
        $this->assertEquals(0, KnowledgeDocument::count());
    }

    /**
     * 5. Stored files use generated UUID filenames on private storage.
     */
    public function test_stored_files_use_generated_uuid_filenames_on_private_storage(): void
    {
        $originalFilename = 'confidential_sales.csv';
        $file = UploadedFile::fake()->createWithContent($originalFilename, "id,revenue\n1,5000", 'text/csv');

        $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file,
        ]);

        $doc = KnowledgeDocument::where('original_filename', $originalFilename)->first();
        $this->assertNotNull($doc);

        $storedBasename = basename($doc->file_path);
        $this->assertNotEquals($originalFilename, $storedBasename);

        $expectedPrefix = 'documents/' . $this->workspaceA->id . '/';
        $this->assertStringStartsWith($expectedPrefix, $doc->file_path);

        $this->assertTrue(Storage::disk('local')->exists($doc->file_path));
        $this->assertFalse(Storage::disk('public')->exists($doc->file_path));
    }

    /**
     * 6. Duplicate content is rejected within the same workspace.
     */
    public function test_duplicate_content_is_rejected_within_same_workspace(): void
    {
        $sameContent = "sku,qty\nA1,50";
        $file1 = UploadedFile::fake()->createWithContent('stock_v1.csv', $sameContent, 'text/csv');
        $file2 = UploadedFile::fake()->createWithContent('stock_v2.csv', $sameContent, 'text/csv');

        // First upload succeeds
        $res1 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file1,
        ]);
        $res1->assertRedirect(route('documents.index'));
        $res1->assertSessionHas('success');
        $this->assertEquals(1, KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->count());

        $firstDoc = KnowledgeDocument::first();
        $firstPath = $firstDoc->file_path;

        // Second upload with identical content in same workspace fails
        $res2 = $this->actingAs($this->adminUserA)
            ->from(route('documents.create'))
            ->post(route('documents.store'), [
                'document' => $file2,
            ]);
        $res2->assertSessionHasErrors(['document']);

        $this->assertEquals(1, KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->count());
        $this->assertTrue(Storage::disk('local')->exists($firstPath));
    }

    /**
     * 7. Identical content is permitted in different workspaces.
     */
    public function test_identical_content_is_permitted_in_different_workspaces(): void
    {
        $commonContent = "product,price\nWidget,20";
        $fileA = UploadedFile::fake()->createWithContent('items_a.csv', $commonContent, 'text/csv');
        $fileB = UploadedFile::fake()->createWithContent('items_b.csv', $commonContent, 'text/csv');

        $resA = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $fileA,
        ]);
        $resA->assertRedirect(route('documents.index'));

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
            'original_filename' => 'guide_b.csv',
            'file_path'         => 'documents/' . $this->workspaceB->id . '/b.csv',
            'mime_type'         => 'text/csv',
            'file_size'         => 1024,
            'content_hash'      => hash('sha256', 'workspace B private content'),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
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
     * 9. Unauthenticated or unauthorized users are rejected.
     */
    public function test_unauthenticated_or_unauthorized_users_are_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('info.csv', "a,b\n1,2", 'text/csv');

        $this->get(route('documents.index'))->assertRedirect(route('login'));
        $this->post(route('documents.store'), ['document' => $file])->assertRedirect(route('login'));

        $this->actingAs($this->unauthorizedUser)->get(route('documents.index'))->assertForbidden();
        $this->actingAs($this->unauthorizedUser)->post(route('documents.store'), ['document' => $file])->assertForbidden();
    }

    /**
     * 10. Database failure cleans up uploaded file immediately.
     */
    public function test_database_failure_cleans_up_uploaded_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('crash.csv', "col\nval", 'text/csv');

        KnowledgeDocument::saving(function () {
            throw new \RuntimeException('Simulated database failure');
        });

        try {
            $this->actingAs($this->adminUserA)->post(route('documents.store'), [
                'document' => $file,
            ]);
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated database failure', $e->getMessage());
        }

        $this->assertEquals(0, KnowledgeDocument::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    /**
     * 11. Analytics service failure sets FAILED status and flashes error to user.
     */
    public function test_analytics_service_failure_sets_failed_status_and_flashes_error(): void
    {
        $this->analyticsHandler = function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/analytics/excel/upload')) {
                return Http::response([
                    'success' => false,
                    'message' => 'Malformed spreadsheet columns detected',
                ], 422);
            }
            return null;
        };

        $file = UploadedFile::fake()->createWithContent('broken.csv', "broken,data\n1", 'text/csv');

        $response = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file,
        ]);

        $response->assertRedirect(route('documents.index'));
        $response->assertSessionHas('error');

        $doc = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame(DocumentLifecycleStatus::FAILED, $doc->lifecycle_status);
        $this->assertFalse($doc->is_active);
        $this->assertStringContainsString('Malformed spreadsheet columns', (string) $doc->error_message);
    }

    /**
     * 12. Retry reprocesses a failed document and activates it.
     */
    public function test_retry_reprocesses_failed_document_and_activates_it(): void
    {
        $callCount = 0;
        $this->analyticsHandler = function (\Illuminate\Http\Client\Request $request) use (&$callCount) {
            if (str_contains($request->url(), '/analytics/excel/upload')) {
                $callCount++;
                if ($callCount === 1) {
                    return Http::response(['success' => false, 'message' => 'Service busy'], 503);
                }
                return Http::response([
                    'success'      => true,
                    'source_id'    => 'src_retried_456',
                    'sheets'       => ['Orders'],
                    'sheets_count' => 1,
                    'total_rows'   => 15,
                ], 200);
            }
            return null;
        };

        $file = UploadedFile::fake()->createWithContent('orders.csv', "order_id,amount\n101,50", 'text/csv');
        $this->actingAs($this->adminUserA)->post(route('documents.store'), ['document' => $file]);

        $doc = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertSame(DocumentLifecycleStatus::FAILED, $doc->lifecycle_status);

        // 2. Retry succeeds
        $retryResponse = $this->actingAs($this->adminUserA)->post(route('documents.retry', $doc->id));
        $retryResponse->assertRedirect(route('documents.index'));
        $retryResponse->assertSessionHas('success');

        $doc->refresh();
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertTrue($doc->is_active);
        $this->assertSame('src_retried_456', $doc->source_id);
        $this->assertNull($doc->error_message);
    }

    /**
     * 13. Multi-sheet workbook records sheet count and metadata.
     */
    public function test_multi_sheet_workbook_records_sheet_count_and_metadata(): void
    {
        $this->analyticsHandler = function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/analytics/excel/upload')) {
                return Http::response([
                    'success'        => true,
                    'source_id'      => 'src_multi_789',
                    'sheets'         => ['orders', 'customers', 'products'],
                    'sheets_count'   => 3,
                    'total_rows'     => 1500,
                    'tables'         => [
                        'orders'    => ['columns' => ['id', 'total']],
                        'customers' => ['columns' => ['id', 'email']],
                        'products'  => ['columns' => ['id', 'sku']],
                    ],
                    'schema_summary' => '3 tables parsed',
                ], 200);
            }
            return null;
        };

        $xlsx = $this->createFakeXlsx('enterprise_data.xlsx');
        $response = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $xlsx,
        ]);
        $response->assertRedirect(route('documents.index'));

        $doc = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame('src_multi_789', $doc->source_id);
        $this->assertSame(3, $doc->sheets_count);
        $this->assertSame(1500, $doc->chunk_count);
        $this->assertIsArray($doc->metadata);
        $this->assertSame(['orders', 'customers', 'products'], $doc->metadata['sheets']);
    }

    /**
     * 14. Document deletion triggers remote source deletion when provenance is confirmed.
     */
    public function test_document_deletion_invokes_analytics_delete(): void
    {
        $this->analyticsHandler = function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/analytics/sources/src_delete_999')) {
                return Http::response([
                    'success' => true,
                    'message' => 'Source deleted successfully',
                ], 200);
            }
            return null;
        };

        $doc = KnowledgeDocument::create([
            'id'                  => (string) Str::uuid(),
            'workspace_id'        => $this->workspaceA->id,
            'title'               => 'To Delete',
            'original_filename'   => 'del.csv',
            'file_path'           => 'documents/' . $this->workspaceA->id . '/del.csv',
            'mime_type'           => 'text/csv',
            'file_size'           => 100,
            'content_hash'        => hash('sha256', 'delete test'),
            'active_content_hash' => hash('sha256', 'delete test'),
            'source_id'           => 'src_delete_999',
            'lifecycle_status'    => DocumentLifecycleStatus::ACTIVE,
            'is_active'           => true,
            'created_by'          => $this->adminUserA->id,
        ]);

        $res = $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $doc));
        $res->assertRedirect(route('documents.index'));
        $res->assertSessionHas('success');

        $this->assertNotNull($doc->fresh()->deleted_at);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/analytics/sources/src_delete_999')
                && $request->method() === 'DELETE';
        });
    }

    /**
     * 15. Document deletion reports warning if remote analytics cleanup fails.
     */
    public function test_document_deletion_reports_warning_if_analytics_cleanup_fails(): void
    {
        $this->analyticsHandler = function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/analytics/sources/src_failing_cleanup')) {
                return Http::response([
                    'success' => false,
                    'message' => 'Connection timed out',
                ], 500);
            }
            return null;
        };

        $doc = KnowledgeDocument::create([
            'id'                  => (string) Str::uuid(),
            'workspace_id'        => $this->workspaceA->id,
            'title'               => 'Fail Remote Cleanup',
            'original_filename'   => 'fail.csv',
            'file_path'           => 'documents/' . $this->workspaceA->id . '/fail.csv',
            'mime_type'           => 'text/csv',
            'file_size'           => 100,
            'content_hash'        => hash('sha256', 'fail remote cleanup'),
            'active_content_hash' => hash('sha256', 'fail remote cleanup'),
            'source_id'           => 'src_failing_cleanup',
            'lifecycle_status'    => DocumentLifecycleStatus::ACTIVE,
            'is_active'           => true,
            'created_by'          => $this->adminUserA->id,
        ]);

        $res = $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $doc));
        $res->assertRedirect(route('documents.index'));
        $res->assertSessionHas('error');

        $docFresh = $doc->fresh();
        $this->assertNotNull($docFresh->deleted_at);
        $this->assertTrue($docFresh->metadata['cleanup_pending']);
        $this->assertStringContainsString('Connection timed out', (string) $docFresh->error_message);
    }

    /**
     * 16. Document without source_id skips remote deletion safely.
     */
    public function test_document_without_source_id_skips_remote_deletion_safely(): void
    {
        Http::fake();

        $doc = KnowledgeDocument::create([
            'id'                  => (string) Str::uuid(),
            'workspace_id'        => $this->workspaceA->id,
            'title'               => 'No Source ID',
            'original_filename'   => 'nosource.csv',
            'file_path'           => 'documents/' . $this->workspaceA->id . '/nosource.csv',
            'mime_type'           => 'text/csv',
            'file_size'           => 100,
            'content_hash'        => hash('sha256', 'nosource'),
            'active_content_hash' => hash('sha256', 'nosource'),
            'source_id'           => null,
            'lifecycle_status'    => DocumentLifecycleStatus::FAILED,
            'is_active'           => false,
            'created_by'          => $this->adminUserA->id,
        ]);

        $res = $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $doc));
        $res->assertRedirect(route('documents.index'));
        $res->assertSessionHas('success');

        $this->assertNotNull($doc->fresh()->deleted_at);
        Http::assertNothingSent();
    }

    /**
     * 17. Soft-deleted document permits re-upload of identical content.
     */
    public function test_soft_deleted_document_permits_reupload_of_identical_content(): void
    {
        $content = "id,name\n1,Widget";
        $file1 = UploadedFile::fake()->createWithContent('items_v1.csv', $content, 'text/csv');

        $res1 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file1,
        ]);
        $res1->assertRedirect(route('documents.index'));

        $doc1 = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertNotNull($doc1);

        $delRes = $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $doc1));
        $delRes->assertRedirect(route('documents.index'));
        $this->assertNotNull($doc1->fresh()->deleted_at);
        $this->assertNull($doc1->fresh()->active_content_hash);

        // Re-upload identical content succeeds as a new document
        $file2 = UploadedFile::fake()->createWithContent('items_v2.csv', $content, 'text/csv');
        $res2 = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file2,
        ]);
        $res2->assertRedirect(route('documents.index'));

        $allDocs = KnowledgeDocument::withTrashed()->where('workspace_id', $this->workspaceA->id)->get();
        $this->assertCount(2, $allDocs);

        $activeDocs = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->get();
        $this->assertCount(1, $activeDocs);
    }

    /**
     * 18. Empty zero-byte file is rejected immediately by validation.
     */
    public function test_empty_zero_byte_file_is_rejected(): void
    {
        $emptyFile = UploadedFile::fake()->createWithContent('empty.csv', '', 'text/csv');

        $response = $this->actingAs($this->adminUserA)
            ->from(route('documents.create'))
            ->post(route('documents.store'), [
                'document' => $emptyFile,
            ]);

        $response->assertRedirect(route('documents.create'));
        $response->assertSessionHasErrors(['document']);
        $this->assertEquals(0, KnowledgeDocument::count());
    }

    /**
     * 19. Document UUID is consistently used as source_id across lifecycle.
     */
    public function test_document_uuid_is_consistently_used_as_source_id(): void
    {
        $file = UploadedFile::fake()->createWithContent('catalog.csv', "sku,price\nC1,99", 'text/csv');

        $sentSourceId = null;
        $this->analyticsHandler = function (\Illuminate\Http\Client\Request $request) use (&$sentSourceId) {
            if (str_contains($request->url(), '/analytics/excel/upload')) {
                // Read multipart parameters
                foreach ($request->data() as $part) {
                    if (($part['name'] ?? null) === 'source_id') {
                        $sentSourceId = $part['contents'] ?? null;
                    }
                }
                return Http::response([
                    'success'        => true,
                    'source_id'      => $sentSourceId,
                    'file_id'        => $sentSourceId,
                    'sheets'         => ['Sheet1'],
                    'sheets_count'   => 1,
                    'total_rows'     => 1,
                    'tables'         => ['Sheet1' => ['columns' => ['sku', 'price']]],
                    'schema_summary' => 'Catalog Schema',
                ], 200);
            }
            return null;
        };

        $res = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $file,
        ]);
        $res->assertRedirect(route('documents.index'));

        $doc = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame($doc->id, $doc->source_id);
        $this->assertSame($doc->id, $sentSourceId);
    }

    /**
     * 20. Repeated deletion request is idempotent and succeeds without warning if remote returns 404.
     */
    public function test_repeated_deletion_is_idempotent_and_safe_with_404(): void
    {
        $this->analyticsHandler = function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/analytics/sources/already_gone_source')) {
                return Http::response([
                    'detail' => 'Source already_gone_source not found in workspace',
                ], 404);
            }
            return null;
        };

        $doc = KnowledgeDocument::create([
            'id'                  => (string) Str::uuid(),
            'workspace_id'        => $this->workspaceA->id,
            'title'               => 'Already Gone Remotely',
            'original_filename'   => 'gone.csv',
            'file_path'           => 'documents/' . $this->workspaceA->id . '/gone.csv',
            'mime_type'           => 'text/csv',
            'file_size'           => 100,
            'content_hash'        => hash('sha256', 'already gone'),
            'active_content_hash' => hash('sha256', 'already gone'),
            'source_id'           => 'already_gone_source',
            'lifecycle_status'    => DocumentLifecycleStatus::ACTIVE,
            'is_active'           => true,
            'created_by'          => $this->adminUserA->id,
        ]);

        $res = $this->actingAs($this->adminUserA)->delete(route('documents.destroy', $doc));
        $res->assertRedirect(route('documents.index'));
        $res->assertSessionHas('success'); // Treated as success because 404 means already removed
        $this->assertNotNull($doc->fresh()->deleted_at);
    }

    /**
     * 21. Reconcile remote cleanup successfully clears pending cleanup state.
     */
    public function test_reconcile_remote_cleanup_recovers_pending_cleanup_state(): void
    {
        $callCount = 0;
        $this->analyticsHandler = function (\Illuminate\Http\Client\Request $request) use (&$callCount) {
            if (str_contains($request->url(), '/analytics/sources/reconcile_src')) {
                $callCount++;
                if ($callCount === 1) {
                    return Http::response(['message' => 'Internal Timeout'], 500);
                }
                return Http::response(['success' => true, 'message' => 'Deleted successfully'], 200);
            }
            return null;
        };

        $doc = KnowledgeDocument::create([
            'id'                  => (string) Str::uuid(),
            'workspace_id'        => $this->workspaceA->id,
            'title'               => 'Reconcile Document',
            'original_filename'   => 'reconcile.csv',
            'file_path'           => 'documents/' . $this->workspaceA->id . '/reconcile.csv',
            'mime_type'           => 'text/csv',
            'file_size'           => 100,
            'content_hash'        => hash('sha256', 'reconcile doc'),
            'active_content_hash' => hash('sha256', 'reconcile doc'),
            'source_id'           => 'reconcile_src',
            'lifecycle_status'    => DocumentLifecycleStatus::ACTIVE,
            'is_active'           => true,
            'created_by'          => $this->adminUserA->id,
        ]);

        // 1. Initial delete fails on remote call
        $service = app(\App\Services\Knowledge\KnowledgeDocumentService::class);
        $res = $service->delete($doc);
        $this->assertNotNull($res['cleanup_warning']);

        $docFresh = $doc->fresh();
        $this->assertTrue($docFresh->metadata['cleanup_pending']);

        // 2. Reconcile succeeds on retry
        $reconcileRes = $service->reconcileRemoteCleanup($docFresh);
        $this->assertTrue($reconcileRes['success']);

        $docReconciled = $doc->fresh();
        $this->assertArrayNotHasKey('cleanup_pending', $docReconciled->metadata ?? []);
        $this->assertNull($docReconciled->error_message);
    }

    /**
     * 22. Partial multi-sheet failure records successfully ingested sheets and failed sheets metadata.
     */
    public function test_partial_multi_sheet_failure_tracks_failed_sheets_metadata(): void
    {
        $this->analyticsHandler = function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/analytics/excel/upload')) {
                return Http::response([
                    'success'        => true,
                    'source_id'      => 'partial_multi_src',
                    'sheets'         => ['orders', 'inventory'],
                    'sheets_count'   => 2,
                    'failed_sheets'  => [
                        ['sheet' => 'corrupted_sheet', 'error' => 'Missing expected dimension headers'],
                    ],
                    'total_rows'     => 500,
                    'tables'         => [
                        'orders'    => ['columns' => ['id', 'amount']],
                        'inventory' => ['columns' => ['sku', 'quantity']],
                    ],
                    'schema_summary' => '2 valid tables parsed',
                ], 200);
            }
            return null;
        };

        $xlsx = $this->createFakeXlsx('partial_sheets.xlsx');
        $response = $this->actingAs($this->adminUserA)->post(route('documents.store'), [
            'document' => $xlsx,
        ]);
        $response->assertRedirect(route('documents.index'));

        $doc = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame(2, $doc->sheets_count);
        $this->assertCount(1, $doc->metadata['failed_sheets']);
        $this->assertSame('corrupted_sheet', $doc->metadata['failed_sheets'][0]['sheet']);
    }
}
