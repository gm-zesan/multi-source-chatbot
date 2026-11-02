<?php

declare(strict_types=1);

namespace Tests\Feature\Spreadsheet;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\IngestionPurpose;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Jobs\ProcessSpreadsheetImportJob;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Knowledge\KnowledgeDocumentService;
use App\Services\Spreadsheet\SpreadsheetIngestionService;
use App\Services\Spreadsheet\SpreadsheetInspectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpreadsheetIngestionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $ownerA;
    private User $ownerB;
    private SpreadsheetInspectorService $inspector;
    private SpreadsheetIngestionService $ingestion;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Create Permissions
        foreach ([
            KnowledgeDocumentPermission::VIEW->value,
            KnowledgeDocumentPermission::CREATE->value,
            KnowledgeDocumentPermission::UPDATE->value,
            KnowledgeDocumentPermission::DELETE->value,
        ] as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['display_name' => ucwords(str_replace(['.', '_'], ' ', $permName)), 'module' => 'knowledge']
            );
        }

        // Create Roles
        $adminRole = Role::findOrCreate(RoleEnum::ADMIN->value, 'web');
        $adminRole->syncPermissions([
            KnowledgeDocumentPermission::VIEW->value,
            KnowledgeDocumentPermission::CREATE->value,
            KnowledgeDocumentPermission::UPDATE->value,
            KnowledgeDocumentPermission::DELETE->value,
        ]);

        $this->workspaceA = Workspace::create(['name' => 'Workspace A', 'slug' => 'workspace-a', 'is_active' => true]);
        $this->workspaceB = Workspace::create(['name' => 'Workspace B', 'slug' => 'workspace-b', 'is_active' => true]);

        $this->ownerA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Owner Alpha',
            'email'        => 'ownerA@example.com',
        ]);
        $this->ownerA->assignRole($adminRole);

        $this->ownerB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'name'         => 'Owner Beta',
            'email'        => 'ownerB@example.com',
        ]);
        $this->ownerB->assignRole($adminRole);

        $this->inspector = app(SpreadsheetInspectorService::class);
        $this->ingestion = app(SpreadsheetIngestionService::class);
    }

    /**
     * Test 1: Dynamic header detection skips banner title rows and identifies true headers.
     */
    public function test_dynamic_header_detection_skips_banner_rows_and_identifies_true_headers(): void
    {
        $csvContent = implode("\n", [
            '"XYZ Fashion & Retail Ltd - Monthly Sales Report Oct 2026",,,,,,',
            ',,,,,,',
            '"Date","SKU","Product Name","Category","Quantity","Amount","Salesperson"',
            '"2026-10-01","SKU-1001","Cotton Kurti Premium","Apparel","15","1850.50","Rahim"',
            '"2026-10-02","SKU-1002","Silk Scarf Blue","Apparel","8","750.00","Karim"',
            '"2026-10-03","SKU-1003","Linen Panjabi","Menswear","20","2400.00","Faruk"',
        ]);

        $filePath = 'test_banner_sales.csv';
        Storage::disk('local')->put($filePath, $csvContent);
        $absolutePath = Storage::disk('local')->path($filePath);

        $result = $this->inspector->inspectWorkbook($absolutePath, 'sales.csv');

        $this->assertTrue($result['success']);
        $inspection = $result['inspection'];

        // True header is at index 2 (row 3 of the file)
        $this->assertEquals(2, $inspection['header_row_index']);
        $this->assertFalse($inspection['is_ambiguous']);

        $columnNames = array_column($inspection['columns'], 'name');
        $this->assertContains('Date', $columnNames);
        $this->assertContains('SKU', $columnNames);
        $this->assertContains('Product Name', $columnNames);
        $this->assertContains('Amount', $columnNames);

        // Check suggested categories
        $skuCol = collect($inspection['columns'])->firstWhere('name', 'SKU');
        $this->assertEquals(SpreadsheetInspectorService::CATEGORY_IDENTIFIER, $skuCol['suggested_category']);

        $amountCol = collect($inspection['columns'])->firstWhere('name', 'Amount');
        $this->assertEquals(SpreadsheetInspectorService::CATEGORY_AMOUNT, $amountCol['suggested_category']);

        $personCol = collect($inspection['columns'])->firstWhere('name', 'Salesperson');
        $this->assertEquals(SpreadsheetInspectorService::CATEGORY_PERSON_REFERENCE, $personCol['suggested_category']);
    }

    /**
     * Test 2: Formula Injection Defense vs Legitimate Negative Numbers (Requirement 2).
     */
    public function test_formula_injection_defense_and_legitimate_negative_numbers(): void
    {
        // 1. Legitimate negative and positive numbers must NEVER be corrupted with single quotes
        $this->assertEquals('-500', $this->inspector->sanitizeCellForDisplay('-500'));
        $this->assertEquals('-5.25', $this->inspector->sanitizeCellForDisplay('-5.25'));
        $this->assertEquals('+500', $this->inspector->sanitizeCellForDisplay('+500'));
        $this->assertEquals('100.50', $this->inspector->sanitizeCellForDisplay('100.50'));

        // 2. Bengali numerals (positive and negative) must NEVER be corrupted
        $this->assertEquals('-৫০০', $this->inspector->sanitizeCellForDisplay('-৫০০'));
        $this->assertEquals('-৫.২৫', $this->inspector->sanitizeCellForDisplay('-৫.২৫'));
        $this->assertEquals('১২৫০', $this->inspector->sanitizeCellForDisplay('১২৫০'));

        // 3. Negative and positive currency formats must NEVER be corrupted
        $this->assertEquals('-$500', $this->inspector->sanitizeCellForDisplay('-$500'));
        $this->assertEquals('$-500', $this->inspector->sanitizeCellForDisplay('$-500'));
        $this->assertEquals('-৳২৫০', $this->inspector->sanitizeCellForDisplay('-৳২৫০'));
        $this->assertEquals('(500.00)', $this->inspector->sanitizeCellForDisplay('(500.00)'));
        $this->assertEquals('($500.25)', $this->inspector->sanitizeCellForDisplay('($500.25)'));

        // 4. Untrusted formula injections must be safely escaped with single quote
        $this->assertEquals("'=SUM(A1:A5)", $this->inspector->sanitizeCellForDisplay('=SUM(A1:A5)'));
        $this->assertEquals("'@SUM(A1:A5)", $this->inspector->sanitizeCellForDisplay('@SUM(A1:A5)'));
        $this->assertEquals("'=cmd|' /C calc'!A0", $this->inspector->sanitizeCellForDisplay("=cmd|' /C calc'!A0"));
        $this->assertEquals("'+10+20", $this->inspector->sanitizeCellForDisplay('+10+20'));
        $this->assertEquals("'-cmd|calc", $this->inspector->sanitizeCellForDisplay('-cmd|calc'));
        $this->assertEquals("'|calc.exe", $this->inspector->sanitizeCellForDisplay('|calc.exe'));

        // 5. Formula injections with leading whitespace, tabs, or newlines must be detected and escaped
        $this->assertEquals("'\t=cmd|' /C calc'!A0", $this->inspector->sanitizeCellForDisplay("\t=cmd|' /C calc'!A0"));
        $this->assertEquals("'   =1+1", $this->inspector->sanitizeCellForDisplay("   =1+1"));
        $this->assertEquals("'\r@malicious", $this->inspector->sanitizeCellForDisplay("\r@malicious"));

        // Normal strings remain untouched
        $this->assertEquals('Normal Product Name', $this->inspector->sanitizeCellForDisplay('Normal Product Name'));
    }

    /**
     * Test 3: Numerical and Decimal parsing precision across negative and Bengali formats.
     */
    public function test_typed_numeric_parsing_and_bengali_numerals(): void
    {
        // 1. Bengali digit to decimal conversion
        $this->assertEquals(1250.50, $this->ingestion->parseDecimal('১,২৫০.৫০ ৳'));
        $this->assertEquals(50000.0, $this->ingestion->parseDecimal('৫০,০০০ টাকা'));
        $this->assertEquals(750.25, $this->ingestion->parseDecimal('Tk. ৭৫০.২৫'));
        $this->assertEquals(1800.0, $this->ingestion->parseDecimal('$1,800.00'));

        // 2. Negative numbers & accounting formats
        $this->assertEquals(-500.0, $this->ingestion->parseDecimal('-500'));
        $this->assertEquals(-5.25, $this->ingestion->parseDecimal('-5.25'));
        $this->assertEquals(500.0, $this->ingestion->parseDecimal('+500'));
        $this->assertEquals(-500.0, $this->ingestion->parseDecimal('-৫০০'));
        $this->assertEquals(-5.25, $this->ingestion->parseDecimal('-৫.২৫'));
        $this->assertEquals(-500.0, $this->ingestion->parseDecimal('-$500'));
        $this->assertEquals(-500.0, $this->ingestion->parseDecimal('$-500'));
        $this->assertEquals(-250.0, $this->ingestion->parseDecimal('-৳২৫০'));
        $this->assertEquals(-500.0, $this->ingestion->parseDecimal('(500.00)'));
        $this->assertEquals(-500.25, $this->ingestion->parseDecimal('($500.25)'));

        // Invalid formats return null without crashing
        $this->assertNull($this->ingestion->parseDecimal('invalid text'));
        $this->assertNull($this->ingestion->parseDecimal(''));

        // 3. Bengali Date parsing
        $parsedDate = $this->ingestion->parseDateTime('২০২৬-১০-১৫');
        $this->assertNotNull($parsedDate);
        $this->assertStringStartsWith('2026-10-15', $parsedDate);
    }

    /**
     * Test 4: Verification of all 11 canonical mapping categories.
     */
    public function test_all_eleven_canonical_mapping_categories(): void
    {
        $headers = [
            'SKU_Code',
            'Item_Name',
            'Full_Details',
            'Txn_Date',
            'Sale_Price',
            'Stock_Qty',
            'Delivery_Status',
            'Dept_Category',
            'Agent_Name',
            'Support_Phone',
            'Internal_Warehouse',
        ];

        $row = [
            'SKU-9901',
            'Wireless Earbuds',
            'High fidelity noise cancelling bluetooth earbuds',
            '2026-10-25 14:30:00',
            '-150.50',
            '25',
            'Shipped',
            'Electronics',
            'Rahim Agent',
            '+8801711223344',
            'BIN-A14-NORTH',
        ];

        $mapping = [
            'SKU_Code'           => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
            'Item_Name'          => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            'Full_Details'       => SpreadsheetInspectorService::CATEGORY_DESCRIPTION,
            'Txn_Date'           => SpreadsheetInspectorService::CATEGORY_DATE_TIME,
            'Sale_Price'         => SpreadsheetInspectorService::CATEGORY_AMOUNT,
            'Stock_Qty'          => SpreadsheetInspectorService::CATEGORY_QUANTITY,
            'Delivery_Status'    => SpreadsheetInspectorService::CATEGORY_STATUS,
            'Dept_Category'      => SpreadsheetInspectorService::CATEGORY_CATEGORY,
            'Agent_Name'         => SpreadsheetInspectorService::CATEGORY_PERSON_REFERENCE,
            'Support_Phone'      => SpreadsheetInspectorService::CATEGORY_CONTACT_INFO,
            'Internal_Warehouse' => SpreadsheetInspectorService::CATEGORY_CUSTOM_ATTRIBUTE,
        ];

        $parsed = $this->ingestion->parseRow($headers, $row, $mapping, 2, false);

        $this->assertEquals('SKU-9901', $parsed['identifier']);
        $this->assertEquals('Wireless Earbuds', $parsed['entity_name']);
        $this->assertEquals('2026-10-25 14:30:00', $parsed['date']);
        $this->assertEquals(-150.50, $parsed['amount']);
        $this->assertEquals(25.0, $parsed['quantity']);
        $this->assertEquals('Shipped', $parsed['status']);
        $this->assertEquals('Electronics', $parsed['category']);
        $this->assertEquals('Rahim Agent', $parsed['person']);
        $this->assertEquals('+8801711223344', $parsed['contact']);
        $this->assertEquals('BIN-A14-NORTH', $parsed['custom_attributes']['Internal_Warehouse']);

        // Raw values preserve exact unmutated source values (negative price string preserved)
        $this->assertEquals('-150.50', $parsed['raw_values']['Sale_Price']);
        $this->assertEquals('SKU-9901', $parsed['raw_values']['SKU_Code']);
    }

    /**
     * Test 5: Ambiguous headers trigger REQUIRES_MAPPING lifecycle status and awaiting_mapping job.
     */
    public function test_ambiguous_headers_require_owner_confirmation(): void
    {
        // Unclear / generic column headers with low confidence
        $csvContent = implode("\n", [
            '"Field_Alpha","Field_Beta","Field_Gamma","Field_Delta"',
            '"123","ABC","XYZ","999"',
            '"456","DEF","UVW","888"',
        ]);

        $filePath = 'ambiguous.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $document = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Unclear Data',
            'original_filename' => 'ambiguous.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $docService = app(KnowledgeDocumentService::class);
        $docService->processAnalyticsRegistration($document);

        $document->refresh();

        // Must be marked as REQUIRES_MAPPING
        $this->assertEquals(DocumentLifecycleStatus::REQUIRES_MAPPING, $document->lifecycle_status);
        $this->assertFalse((bool) $document->is_active);

        $importJob = SpreadsheetImportJob::where('document_id', $document->id)->first();
        $this->assertNotNull($importJob);
        $this->assertEquals('awaiting_mapping', $importJob->status);
    }

    /**
     * Test 6: Intra-file duplicate identifiers and conflict resolution (skip, overwrite, append).
     */
    public function test_intra_file_duplicate_identifiers_and_conflict_resolution(): void
    {
        $csvContent = implode("\n", [
            '"SKU","Title","Amount"',
            '"ITEM-1","First Version","50.00"',
            '"ITEM-1","Duplicate Second Version","75.00"',
            '"ITEM-2","Unique Widget","30.00"',
        ]);
        $filePath = 'intra_conflict.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $mapping = [
            'SKU'    => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
            'Title'  => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            'Amount' => SpreadsheetInspectorService::CATEGORY_AMOUNT,
        ];

        // 1. Mode 'skip': First row wins, intra-file duplicate is skipped
        $docSkip = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Skip Conflict Doc',
            'original_filename' => 'intra_conflict.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent . '_skip'),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $jobSkip = $this->ingestion->createImportJob($docSkip, 'Default', 0, $mapping, 'skip');
        $this->ingestion->processChunk($jobSkip, 10);

        $this->assertEquals(2, StructuredRecord::where('import_job_id', $jobSkip->id)->count());
        $rec1 = StructuredRecord::where('import_job_id', $jobSkip->id)->where('record_identifier', 'ITEM-1')->first();
        $this->assertEquals('First Version', $rec1->canonical_entity_name);
        $this->assertEquals(50.00, (float) $rec1->canonical_amount);

        // 2. Mode 'overwrite': Last row wins, intra-file duplicate overwrites in place
        $docOverwrite = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Overwrite Conflict Doc',
            'original_filename' => 'intra_conflict.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent . '_overwrite'),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $jobOverwrite = $this->ingestion->createImportJob($docOverwrite, 'Default', 0, $mapping, 'overwrite');
        $this->ingestion->processChunk($jobOverwrite, 10);

        $this->assertEquals(2, StructuredRecord::where('import_job_id', $jobOverwrite->id)->count());
        $recOverwrite = StructuredRecord::where('import_job_id', $jobOverwrite->id)->where('record_identifier', 'ITEM-1')->first();
        $this->assertEquals('Duplicate Second Version', $recOverwrite->canonical_entity_name);
        $this->assertEquals(75.00, (float) $recOverwrite->canonical_amount);

        // 3. Mode 'append': Both duplicates are inserted
        $docAppend = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Append Conflict Doc',
            'original_filename' => 'intra_conflict.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent . '_append'),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $jobAppend = $this->ingestion->createImportJob($docAppend, 'Default', 0, $mapping, 'append');
        $this->ingestion->processChunk($jobAppend, 10);

        $this->assertEquals(3, StructuredRecord::where('import_job_id', $jobAppend->id)->count());
    }

    /**
     * Test 7: Public and Private Data Isolation (Requirement 4).
     */
    public function test_public_and_private_data_isolation_rules(): void
    {
        $headers = ['Emp_Name', 'Salary', 'Product_Title'];
        $row = ['Rahim Staff', '35000', 'Winter Jacket'];

        $mapping = [
            'Emp_Name'      => SpreadsheetInspectorService::CATEGORY_PERSON_REFERENCE,
            'Salary'        => SpreadsheetInspectorService::CATEGORY_AMOUNT,
            'Product_Title' => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
        ];

        // 1. By default, records MUST be private (is_public = false)
        $parsedDefault = $this->ingestion->parseRow($headers, $row, $mapping, 2, false);
        $this->assertFalse($parsedDefault['is_public']);

        // 2. Even if public exposure is requested, workforce records MUST remain strictly private
        $parsedWithWorkforce = $this->ingestion->parseRow($headers, $row, $mapping, 2, true);
        $this->assertFalse($parsedWithWorkforce['is_public']);

        // 3. Only an authorized public catalog without private workforce data may be public
        $catalogHeaders = ['Product_Title', 'Price'];
        $catalogRow = ['Winter Jacket Premium', '1250.00'];
        $catalogMapping = [
            'Product_Title' => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            'Price'         => SpreadsheetInspectorService::CATEGORY_AMOUNT,
        ];

        $parsedCatalogPrivate = $this->ingestion->parseRow($catalogHeaders, $catalogRow, $catalogMapping, 2, false);
        $this->assertFalse($parsedCatalogPrivate['is_public']);

        $parsedCatalogPublic = $this->ingestion->parseRow($catalogHeaders, $catalogRow, $catalogMapping, 2, true);
        $this->assertTrue($parsedCatalogPublic['is_public']);
    }

    /**
     * Test 8: Checkpointing and generation fencing aborts stale workers.
     */
    public function test_checkpointing_and_generation_fencing_aborts_stale_workers(): void
    {
        $rows = ['"ID","Name","Price"'];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = "\"ID-{$i}\",\"Product {$i}\",\"100.00\"";
        }
        $csvContent = implode("\n", $rows);
        $filePath = 'fencing_test.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $document = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Fencing Test',
            'original_filename' => 'fencing.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $mappingConfig = [
            'ID'    => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
            'Name'  => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            'Price' => SpreadsheetInspectorService::CATEGORY_AMOUNT,
        ];

        $job = $this->ingestion->createImportJob($document, 'Default', 0, $mappingConfig, 'skip');
        $oldGeneration = $job->generation_id;

        // Process chunk 1: 4 rows
        $res1 = $this->ingestion->processChunk($job, 4);
        $this->assertFalse($res1['is_finished']);
        $this->assertEquals(4, $res1['processed_in_chunk']);
        $this->assertEquals(4, $job->fresh()->checkpoint_row);
        $this->assertEquals(4, StructuredRecord::where('import_job_id', $job->id)->count());

        // Simulate a job retry or remapping that changes the generation token
        $newGeneration = (string) Str::uuid();
        $job->update(['generation_id' => $newGeneration]);

        // Attempt to execute old queue worker with old fencing token
        $staleWorkerJob = new ProcessSpreadsheetImportJob($job, $oldGeneration, 4);
        $staleWorkerJob->handle($this->ingestion);

        // Stale worker must NOT process rows
        $this->assertEquals(4, $job->fresh()->checkpoint_row);
        $this->assertEquals(4, StructuredRecord::where('import_job_id', $job->id)->count());

        // Legitimate worker with new generation token processes remaining rows
        $validWorkerJob = new ProcessSpreadsheetImportJob($job, $newGeneration, 10);
        $validWorkerJob->handle($this->ingestion);

        $this->assertEquals(10, $job->fresh()->processed_rows);
        $this->assertEquals('completed', $job->fresh()->status);
        $this->assertEquals(10, StructuredRecord::where('import_job_id', $job->id)->count());
    }

    /**
     * Test 9: Tenant isolation strictly enforced on spreadsheet REST endpoints.
     */
    public function test_tenant_isolation_on_spreadsheet_api_endpoints(): void
    {
        $csvContent = "\"SKU\",\"Name\"\n\"A1\",\"Alpha Widget\"";
        $filePath = 'doc_a.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $docA = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Workspace A Spreadsheet',
            'original_filename' => 'doc_a.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::REQUIRES_MAPPING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        // 1. Owner A can inspect their own document
        $responseA = $this->actingAs($this->ownerA)->getJson(route('documents.spreadsheet.inspect', $docA));
        $responseA->assertStatus(200);
        $responseA->assertJson(['success' => true]);

        // 2. Owner B is strictly forbidden from inspecting Document A (403)
        $responseB = $this->actingAs($this->ownerB)->getJson(route('documents.spreadsheet.inspect', $docA));
        $responseB->assertStatus(403);

        // 3. Owner B is strictly forbidden from confirming mapping for Document A (403)
        $confirmB = $this->actingAs($this->ownerB)->postJson(route('documents.spreadsheet.confirm', $docA), [
            'header_row_index' => 0,
            'mappings'         => ['SKU' => 'identifier', 'Name' => 'entity_name'],
        ]);
        $confirmB->assertStatus(403);

        // 4. Owner B is strictly forbidden from retrying or cancelling Document A (403)
        $retryB = $this->actingAs($this->ownerB)->postJson(route('documents.spreadsheet.retry', $docA));
        $retryB->assertStatus(403);

        $cancelB = $this->actingAs($this->ownerB)->postJson(route('documents.spreadsheet.cancel', $docA));
        $cancelB->assertStatus(403);
    }

    /**
     * Test 10: Corrupt, 0-byte, and spoofed file validation (Requirement 1).
     */
    public function test_corrupt_empty_and_spoofed_file_validation(): void
    {
        // 1. 0-byte file rejected
        $emptyPath = 'empty.xlsx';
        Storage::disk('local')->put($emptyPath, '');
        $absEmptyPath = Storage::disk('local')->path($emptyPath);
        $resEmpty = $this->inspector->inspectWorkbook($absEmptyPath, 'empty.xlsx');
        $this->assertFalse($resEmpty['success']);
        $this->assertStringContainsString('empty (0 bytes)', $resEmpty['error']);

        // 2. Disguised executable rejected by magic bytes
        $fakeXlsxPath = 'malicious.xlsx';
        Storage::disk('local')->put($fakeXlsxPath, "MZ\x90\x00\x03\x00\x00\x00NOT_ZIP");
        $absFakePath = Storage::disk('local')->path($fakeXlsxPath);
        $result = $this->inspector->inspectWorkbook($absFakePath, 'malicious.xlsx');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('MIME spoofing detected', $result['error']);

        // 3. Corrupt zip container rejected
        $corruptZipPath = 'corrupt.xlsx';
        Storage::disk('local')->put($corruptZipPath, "PK\x03\x04\x00\x00CORRUPT_DATA");
        $absCorruptPath = Storage::disk('local')->path($corruptZipPath);
        $resCorrupt = $this->inspector->inspectWorkbook($absCorruptPath, 'corrupt.xlsx');
        $this->assertFalse($resCorrupt['success']);
        $this->assertStringContainsString('corrupt', strtolower($resCorrupt['error']));
    }

    /**
     * Test 11: Real XLSX file parsing with PhpSpreadsheet.
     */
    public function test_real_xlsx_file_parsing_with_phpspreadsheet(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SalesData');

        $sheet->setCellValue('A1', 'SKU');
        $sheet->setCellValue('B1', 'Product');
        $sheet->setCellValue('C1', 'Price');
        $sheet->setCellValue('D1', 'Stock');

        $sheet->setCellValue('A2', 'XLSX-001');
        $sheet->setCellValue('B2', 'Premium Polo Shirt');
        $sheet->setCellValue('C2', '-850.50');
        $sheet->setCellValue('D2', '25');

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        $writer->save($tempPath);

        $storedPath = 'xlsx_test.xlsx';
        $fileBytes = file_get_contents($tempPath);
        Storage::disk('local')->put($storedPath, $fileBytes);
        unlink($tempPath);

        $absPath = Storage::disk('local')->path($storedPath);
        $inspectResult = $this->inspector->inspectWorkbook($absPath, 'products.xlsx');

        $this->assertTrue($inspectResult['success']);
        $this->assertEquals(['SalesData'], $inspectResult['sheets']);
        $this->assertEquals(1, $inspectResult['total_rows']);

        $document = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'XLSX Products',
            'original_filename' => 'products.xlsx',
            'file_path'         => $storedPath,
            'content_hash'      => hash('sha256', $fileBytes),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => Storage::disk('local')->size($storedPath),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $job = $this->ingestion->createImportJob(
            document: $document,
            sheetName: 'SalesData',
            headerRowIndex: 0,
            mappingConfig: [
                'SKU'     => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
                'Product' => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
                'Price'   => SpreadsheetInspectorService::CATEGORY_AMOUNT,
                'Stock'   => SpreadsheetInspectorService::CATEGORY_QUANTITY,
            ],
            conflictMode: 'skip'
        );

        $chunkResult = $this->ingestion->processChunk($job, 50);
        $this->assertTrue($chunkResult['is_finished']);
        $this->assertEquals(1, $chunkResult['processed_in_chunk']);

        $record = StructuredRecord::where('import_job_id', $job->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals('XLSX-001', $record->record_identifier);
        $this->assertEquals('Premium Polo Shirt', $record->canonical_entity_name);
        $this->assertEquals(-850.50, (float) $record->canonical_amount);
        $this->assertEquals(25.0, (float) $record->canonical_quantity);
    }

    /**
     * Test 12: Real XLS (legacy BIFF8 binary format) file parsing with PhpSpreadsheet.
     */
    public function test_real_xls_legacy_binary_file_parsing_with_phpspreadsheet(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('LegacySheet');

        $sheet->setCellValue('A1', 'Code');
        $sheet->setCellValue('B1', 'Product');
        $sheet->setCellValue('C1', 'Price');

        $sheet->setCellValue('A2', 'XLS-99');
        $sheet->setCellValue('B2', 'Vintage Radio');
        $sheet->setCellValue('C2', '450.00');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_xls_') . '.xls';
        $writer->save($tempPath);

        $storedPath = 'legacy_test.xls';
        $fileBytes = file_get_contents($tempPath);
        Storage::disk('local')->put($storedPath, $fileBytes);
        unlink($tempPath);

        $absPath = Storage::disk('local')->path($storedPath);
        $inspectResult = $this->inspector->inspectWorkbook($absPath, 'products.xls');

        $this->assertTrue($inspectResult['success']);
        $this->assertEquals(['LegacySheet'], $inspectResult['sheets']);
        $this->assertEquals(1, $inspectResult['total_rows']);

        $document = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'XLS Legacy Products',
            'original_filename' => 'products.xls',
            'file_path'         => $storedPath,
            'content_hash'      => hash('sha256', $fileBytes),
            'mime_type'         => 'application/vnd.ms-excel',
            'file_size'         => Storage::disk('local')->size($storedPath),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $job = $this->ingestion->createImportJob(
            document: $document,
            sheetName: 'LegacySheet',
            headerRowIndex: 0,
            mappingConfig: [
                'Code'    => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
                'Product' => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
                'Price'   => SpreadsheetInspectorService::CATEGORY_AMOUNT,
            ],
            conflictMode: 'skip'
        );

        $chunkResult = $this->ingestion->processChunk($job, 50);
        $this->assertTrue($chunkResult['is_finished']);
        $this->assertEquals(1, $chunkResult['processed_in_chunk']);

        $record = StructuredRecord::where('import_job_id', $job->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals('XLS-99', $record->record_identifier);
        $this->assertEquals('Vintage Radio', $record->canonical_entity_name);
        $this->assertEquals(450.00, (float) $record->canonical_amount);
    }
}
