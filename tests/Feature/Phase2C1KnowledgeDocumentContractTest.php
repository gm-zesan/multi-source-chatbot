<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Knowledge\KnowledgeDocumentService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2C1KnowledgeDocumentContractTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $adminA;
    protected User $adminB;
    protected KnowledgeDocumentService $service;

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

        $this->adminA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->adminA->assignRole(RoleEnum::ADMIN->value);

        $this->adminB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
        ]);
        $this->adminB->assignRole(RoleEnum::ADMIN->value);

        $this->service = app(KnowledgeDocumentService::class);
    }

    protected array $tempFilesToDelete = [];

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

    protected function createFakeXlsx(string $filename = 'test.xlsx', bool $validStructure = true): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_test_') . '.xlsx';
        $this->tempFilesToDelete[] = $tempPath;

        $zip = new \ZipArchive();
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($validStructure) {
            $zip->addFromString(
                '[Content_Types].xml',
                '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>'
            );
            $zip->addFromString(
                'xl/workbook.xml',
                '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"></workbook>'
            );
        } else {
            $zip->addFromString('random.txt', 'plain text without openxml spreadsheet structure');
        }

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
     * Test 1: Upload valid XLSX document succeeds and registers with analytics engine.
     */
    public function test_valid_xlsx_upload_is_accepted(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/analytics/excel/upload' => \Illuminate\Support\Facades\Http::response([
                'success'      => true,
                'source_id'    => 'src_inv_123',
                'sheets'       => ['Sheet1'],
                'sheets_count' => 1,
                'total_rows'   => 42,
            ], 200),
        ]);

        $xlsx = $this->createFakeXlsx('inventory.xlsx', true);

        $response = $this->actingAs($this->adminA)
            ->post(route('documents.store'), [
                'document' => $xlsx,
                'title'    => 'Q3 Inventory Report',
            ]);

        $response->assertRedirect(route('documents.index'));
        $response->assertSessionHas('success');

        $doc = KnowledgeDocument::where('workspace_id', $this->workspaceA->id)
            ->where('title', 'Q3 Inventory Report')
            ->first();

        $this->assertNotNull($doc);
        $this->assertSame('inventory.xlsx', $doc->original_filename);
        $this->assertSame(DocumentLifecycleStatus::ACTIVE, $doc->lifecycle_status);
        $this->assertTrue($doc->is_active);
        $this->assertSame('src_inv_123', $doc->source_id);
        $this->assertSame(1, $doc->sheets_count);
        $this->assertSame(42, $doc->chunk_count);
    }

    /**
     * Test 2: Upload PDF document is rejected (Phase 1 contract allows only XLSX and CSV).
     */
    public function test_unsupported_pdf_upload_is_rejected(): void
    {
        $pdf = UploadedFile::fake()->create('handbook.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->adminA)
            ->from(route('documents.create'))
            ->post(route('documents.store'), [
                'document' => $pdf,
                'title'    => 'Employee Handbook',
            ]);

        $response->assertRedirect(route('documents.create'));
        $response->assertSessionHasErrors([
            'document' => 'Only .xlsx and .csv files are supported.',
        ]);

        $this->assertDatabaseMissing('knowledge_documents', [
            'workspace_id'      => $this->workspaceA->id,
            'original_filename' => 'handbook.pdf',
        ]);
    }

    /**
     * Boundary Test 1: Malformed non-ZIP bytes uploaded as XLSX are rejected.
     */
    public function test_xlsx_malformed_non_zip_bytes_rejected(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'bad_zip_') . '.xlsx';
        $this->tempFilesToDelete[] = $tempPath;
        file_put_contents($tempPath, 'CORRUPTED_NON_ZIP_RAW_BINARY_DATA_HEADER');

        $uploaded = new UploadedFile(
            $tempPath,
            'bad.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->adminA)
            ->from(route('documents.index'))
            ->post(route('documents.store'), [
                'document' => $uploaded,
                'title'    => 'Corrupted Bytes',
            ]);

        $response->assertSessionHasErrors(['document' => 'The uploaded XLSX file is not a valid ZIP package.']);
        $this->assertDatabaseMissing('knowledge_documents', ['original_filename' => 'bad.xlsx']);
    }

    /**
     * Boundary Test 2: Valid ZIP missing required OpenXML structure is rejected.
     */
    public function test_xlsx_missing_openxml_structure_rejected(): void
    {
        // 2a: Missing xl/workbook.xml
        $tempPathA = tempnam(sys_get_temp_dir(), 'no_wb_') . '.xlsx';
        $this->tempFilesToDelete[] = $tempPathA;
        $zipA = new \ZipArchive();
        $zipA->open($tempPathA, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zipA->addFromString('[Content_Types].xml', '<Types/>');
        $zipA->close();

        $uploadedA = new UploadedFile(
            $tempPathA,
            'missing_workbook.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $responseA = $this->actingAs($this->adminA)
            ->from(route('documents.index'))
            ->post(route('documents.store'), ['document' => $uploadedA]);

        $responseA->assertSessionHasErrors([
            'document' => 'The uploaded XLSX file lacks required spreadsheet structure (missing xl/workbook.xml or [Content_Types].xml).',
        ]);

        // 2b: Missing [Content_Types].xml
        $tempPathB = tempnam(sys_get_temp_dir(), 'no_ct_') . '.xlsx';
        $this->tempFilesToDelete[] = $tempPathB;
        $zipB = new \ZipArchive();
        $zipB->open($tempPathB, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zipB->addFromString('xl/workbook.xml', '<workbook/>');
        $zipB->close();

        $uploadedB = new UploadedFile(
            $tempPathB,
            'missing_content_types.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $responseB = $this->actingAs($this->adminA)
            ->from(route('documents.index'))
            ->post(route('documents.store'), ['document' => $uploadedB]);

        $responseB->assertSessionHasErrors([
            'document' => 'The uploaded XLSX file lacks required spreadsheet structure (missing xl/workbook.xml or [Content_Types].xml).',
        ]);
    }

    /**
     * Boundary Test 3: ZIP content disguised with mismatched extensions (.txt, .pdf, .csv) is rejected.
     */
    public function test_zip_disguised_with_mismatched_extension_rejected(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'disguised_') . '.bin';
        $this->tempFilesToDelete[] = $tempPath;
        $zip = new \ZipArchive();
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('payload.bin', 'hidden zip payload');
        $zip->close();

        $extensionsToTest = [
            'csv' => 'The detected file type is not supported or the file content is spoofed.',
            'txt' => 'Only .xlsx and .csv files are supported.',
            'pdf' => 'Only .xlsx and .csv files are supported.',
        ];

        foreach ($extensionsToTest as $ext => $expectedError) {
            $uploaded = new UploadedFile(
                $tempPath,
                "disguised.{$ext}",
                'application/zip',
                null,
                true
            );

            $response = $this->actingAs($this->adminA)
                ->from(route('documents.index'))
                ->post(route('documents.store'), ['document' => $uploaded]);

            $response->assertSessionHasErrors([
                'document' => $expectedError,
            ]);
        }
    }

    /**
     * Boundary Test 4: Archive containing 1,001 entries is rejected without disk bloat (< 100 KB).
     */
    public function test_xlsx_exceeding_max_file_count_rejected(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'z1001_') . '.xlsx';
        $this->tempFilesToDelete[] = $tempPath;

        $zip = new \ZipArchive();
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        for ($i = 0; $i < 999; $i++) {
            $zip->addFromString("f_{$i}.txt", '');
        }
        $zip->close();

        $this->assertLessThan(150 * 1024, filesize($tempPath), 'Fixture must remain below 150 KB on disk.');

        $uploaded = new UploadedFile(
            $tempPath,
            'over_1000_files.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->adminA)
            ->from(route('documents.index'))
            ->post(route('documents.store'), ['document' => $uploaded]);

        $response->assertSessionHasErrors([
            'document' => 'The XLSX package contains too many internal files.',
        ]);
    }

    /**
     * Boundary Test 5: Archive whose entry metadata reports > 50 MB uncompressed content (< 120 KB on disk).
     */
    public function test_xlsx_exceeding_decompressed_size_rejected(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'z50mb_') . '.xlsx';
        $this->tempFilesToDelete[] = $tempPath;

        $zip = new \ZipArchive();
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('xl/workbook.xml', '<workbook/>');

        // 525 entries of 100,000 bytes = 52.5 MB uncompressed.
        // Each entry <= 102,400 bytes so ratio check (>100:1 on >100KB) does not trigger first.
        $chunk = str_repeat('A', 100000);
        for ($i = 0; $i < 525; $i++) {
            $zip->addFromString("e_{$i}.bin", $chunk);
        }
        $zip->close();

        $this->assertLessThan(150 * 1024, filesize($tempPath), 'Fixture must remain below 150 KB on disk.');

        $uploaded = new UploadedFile(
            $tempPath,
            'over_50mb.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->adminA)
            ->from(route('documents.index'))
            ->post(route('documents.store'), ['document' => $uploaded]);

        $response->assertSessionHasErrors([
            'document' => 'The XLSX decompressed size exceeds the 50 MB safety limit.',
        ]);
    }

    /**
     * Boundary Test 6: Compression ratio > 100:1 for an entry exceeding 100 KB uncompressed (< 2 KB on disk).
     */
    public function test_xlsx_exceeding_compression_ratio_rejected(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'zratio_') . '.xlsx';
        $this->tempFilesToDelete[] = $tempPath;

        $zip = new \ZipArchive();
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        // 200 KB of zeros: uncompressed 204,800 bytes (>102,400), compressed ~220 bytes (ratio > 900:1)
        $zip->addFromString('bomb.bin', str_repeat('0', 200 * 1024));
        $zip->close();

        $this->assertLessThan(5 * 1024, filesize($tempPath), 'Fixture must remain below 5 KB on disk.');

        $uploaded = new UploadedFile(
            $tempPath,
            'ratio_bomb.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->adminA)
            ->from(route('documents.index'))
            ->post(route('documents.store'), ['document' => $uploaded]);

        $response->assertSessionHasErrors([
            'document' => 'The XLSX file exceeds safe compression ratio limits.',
        ]);
    }
}

