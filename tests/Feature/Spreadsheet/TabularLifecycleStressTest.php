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
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
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

/**
 * Real Staging Tabular Data Lifecycle Stress-Test Suite.
 *
 * Stress-tests Excel/CSV ingestion, cross-sheet relationships, schema drift,
 * missing values, duplicate records, update/deletion lifecycle, and partial ingestion fencing.
 */
class TabularLifecycleStressTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private SpreadsheetInspectorService $inspector;
    private SpreadsheetIngestionService $ingestion;
    private CrossSheetRelationshipService $relationshipService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

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

        $adminRole = Role::findOrCreate(RoleEnum::ADMIN->value, 'web');
        $adminRole->syncPermissions([
            KnowledgeDocumentPermission::VIEW->value,
            KnowledgeDocumentPermission::CREATE->value,
            KnowledgeDocumentPermission::UPDATE->value,
            KnowledgeDocumentPermission::DELETE->value,
        ]);

        $this->workspace = Workspace::create(['name' => 'Stress Test Merchant', 'slug' => 'stress-merchant', 'is_active' => true]);
        $this->owner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Audit Principal',
            'email'        => 'principal@stress-audit.com',
        ]);
        $this->owner->assignRole($adminRole);

        $this->inspector = app(SpreadsheetInspectorService::class);
        $this->ingestion = app(SpreadsheetIngestionService::class);
        $this->relationshipService = app(CrossSheetRelationshipService::class);
    }

    /**
     * Test 1: Ingestion of Dirty CSV with missing values, sparse columns, trailing spaces, and blank rows.
     */
    public function test_dirty_csv_ingestion_with_missing_values_and_sparse_rows(): void
    {
        $dirtyCsv = implode("\n", [
            '"SKU","Product Name","Category","Price","Stock","Note"',
            '"SKU-001","Linen Shirt","Apparel","1500","25","Active"',
            '"SKU-002","Silk Scarf","","850.50","",""',            // Missing Category and Stock
            ',,,,,',                                                 // Entirely blank row
            '"SKU-003","Winter Jacket","Outerwear","4500","10",""',  // Missing Note
            '"SKU-004","","Footwear","","5","Clearance"',            // Missing Name and Price
            '   ',                                                   // Whitespace row
            '"SKU-005","Leather Belt","Accessories","1200","50","Imported"',
        ]);

        $docPath = "documents/{$this->workspace->id}/dirty_catalog.csv";
        Storage::disk('local')->put($docPath, $dirtyCsv);

        $document = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Dirty Catalog Stress Test',
            'original_filename' => 'dirty_catalog.csv',
            'file_path'         => $docPath,
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($dirtyCsv),
            'content_hash'      => hash('sha256', $dirtyCsv),
            'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $mapping = [
            'SKU'          => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
            'Product Name' => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            'Category'     => SpreadsheetInspectorService::CATEGORY_CATEGORY,
            'Price'        => SpreadsheetInspectorService::CATEGORY_AMOUNT,
            'Stock'        => SpreadsheetInspectorService::CATEGORY_QUANTITY,
            'Note'         => SpreadsheetInspectorService::CATEGORY_CUSTOM_ATTRIBUTE,
        ];

        $job = $this->ingestion->createImportJob($document, 'Default', 0, $mapping, 'skip', $this->owner->id);
        $res = $this->ingestion->processChunk($job, 50);

        $this->assertTrue($res['is_finished']);
        $this->assertEquals('completed', $res['status']);

        // Verify that exactly 5 valid data rows were processed (empty lines skipped)
        $records = StructuredRecord::where('document_id', $document->id)->get();
        $this->assertCount(5, $records);

        // Verify row with missing category and stock handled gracefully
        $rec2 = $records->firstWhere('record_identifier', 'SKU-002');
        $this->assertNotNull($rec2);
        $this->assertEquals(850.50, (float) $rec2->canonical_amount);
        $this->assertNull($rec2->canonical_quantity);
        $this->assertEquals('', $rec2->canonical_category ?? '');

        // Verify row with missing price and name
        $rec4 = $records->firstWhere('record_identifier', 'SKU-004');
        $this->assertNotNull($rec4);
        $this->assertNull($rec4->canonical_amount);
        $this->assertEquals(5, $rec4->canonical_quantity);
        $this->assertEquals('Footwear', $rec4->canonical_category);
    }

    /**
     * Test 2: Duplicate records and conflict mode behavior ('skip' vs 'overwrite').
     */
    public function test_duplicate_record_conflict_resolution_skip_and_overwrite(): void
    {
        $csvWithDups = implode("\n", [
            '"SKU","Item","Price","Stock"',
            '"ITEM-A","Original Item A","100","10"',
            '"ITEM-B","Item B","200","20"',
            '"ITEM-A","Duplicate Item A Updated","150","30"', // Duplicate ITEM-A
        ]);

        $docPath = "documents/{$this->workspace->id}/dups.csv";
        Storage::disk('local')->put($docPath, $csvWithDups);

        $document = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Duplicate Resolution Test',
            'original_filename' => 'dups.csv',
            'file_path'         => $docPath,
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvWithDups),
            'content_hash'      => hash('sha256', $csvWithDups),
            'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $mapping = [
            'SKU'   => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
            'Item'  => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            'Price' => SpreadsheetInspectorService::CATEGORY_AMOUNT,
            'Stock' => SpreadsheetInspectorService::CATEGORY_QUANTITY,
        ];

        // 1. Conflict Mode: OVERWRITE
        $jobOverwrite = $this->ingestion->createImportJob($document, 'Default', 0, $mapping, 'overwrite', $this->owner->id);
        $this->ingestion->processChunk($jobOverwrite, 50);

        $records = StructuredRecord::where('import_job_id', $jobOverwrite->id)->get();
        $this->assertCount(2, $records);
        $itemA = $records->firstWhere('record_identifier', 'ITEM-A');
        $this->assertEquals(150.0, (float) $itemA->canonical_amount, 'Overwrite mode must update ITEM-A price to 150');
        $this->assertEquals('Duplicate Item A Updated', $itemA->canonical_entity_name);

        // 2. Conflict Mode: SKIP (on separate document to test intra-file duplicate skip cleanly)
        $docPathSkip = "documents/{$this->workspace->id}/dups_skip.csv";
        Storage::disk('local')->put($docPathSkip, $csvWithDups);

        $documentSkip = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Duplicate Skip Test',
            'original_filename' => 'dups_skip.csv',
            'file_path'         => $docPathSkip,
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvWithDups),
            'content_hash'      => hash('sha256', $csvWithDups . '_skip'),
            'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $jobSkip = $this->ingestion->createImportJob($documentSkip, 'Default', 0, $mapping, 'skip', $this->owner->id);
        $this->ingestion->processChunk($jobSkip, 50);

        $recordsSkip = StructuredRecord::where('import_job_id', $jobSkip->id)->get();
        $this->assertCount(2, $recordsSkip);
        $itemASkip = $recordsSkip->firstWhere('record_identifier', 'ITEM-A');
        $this->assertEquals(100.0, (float) $itemASkip->canonical_amount, 'Skip mode must preserve first version price of 100');
        $this->assertEquals('Original Item A', $itemASkip->canonical_entity_name);
    }

    /**
     * Test 3: Schema drift and re-ingestion versioning lifecycle.
     */
    public function test_schema_drift_reimport_advances_generation_and_updates_columns(): void
    {
        // Version 1: 3 columns
        $v1Csv = implode("\n", [
            '"SKU","Name","Price"',
            '"P-101","Kurti Red","1200"',
            '"P-102","Kurti Blue","1300"',
        ]);

        $docPath = "documents/{$this->workspace->id}/products_drift.csv";
        Storage::disk('local')->put($docPath, $v1Csv);

        $document = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Products Drift Test',
            'original_filename' => 'products_drift.csv',
            'file_path'         => $docPath,
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($v1Csv),
            'content_hash'      => hash('sha256', $v1Csv),
            'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $mappingV1 = [
            'SKU'   => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
            'Name'  => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            'Price' => SpreadsheetInspectorService::CATEGORY_AMOUNT,
        ];

        $jobV1 = $this->ingestion->createImportJob($document, 'Default', 0, $mappingV1, 'overwrite', $this->owner->id);
        $this->ingestion->processChunk($jobV1, 50);
        $this->assertCount(2, StructuredRecord::where('import_job_id', $jobV1->id)->get());

        // Version 2: Schema drift! Added 2 new columns: "Category" and "Discount"
        $v2Csv = implode("\n", [
            '"SKU","Name","Price","Category","Discount"',
            '"P-101","Kurti Red 2026","1400","Women Apparel","10%"',
            '"P-102","Kurti Blue 2026","1500","Women Apparel","15%"',
            '"P-103","Kurti Green","1600","Women Apparel","0%"',
        ]);
        Storage::disk('local')->put($docPath, $v2Csv);
        $document->update([
            'file_size'    => strlen($v2Csv),
            'content_hash' => hash('sha256', $v2Csv),
        ]);

        $mappingV2 = [
            'SKU'      => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
            'Name'     => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            'Price'    => SpreadsheetInspectorService::CATEGORY_AMOUNT,
            'Category' => SpreadsheetInspectorService::CATEGORY_CATEGORY,
            'Discount' => SpreadsheetInspectorService::CATEGORY_CUSTOM_ATTRIBUTE,
        ];

        $jobV2 = $this->ingestion->createImportJob($document, 'Default', 0, $mappingV2, 'overwrite', $this->owner->id);
        $this->assertNotEquals($jobV1->generation_id, $jobV2->generation_id);

        $this->ingestion->processChunk($jobV2, 50);

        // Verify V2 records
        $v2Records = StructuredRecord::where('import_job_id', $jobV2->id)->get();
        $this->assertCount(3, $v2Records);

        $p101 = $v2Records->firstWhere('record_identifier', 'P-101');
        $this->assertEquals('Kurti Red 2026', $p101->canonical_entity_name);
        $this->assertEquals(1400.0, (float) $p101->canonical_amount);
        $this->assertEquals('Women Apparel', $p101->canonical_category);
    }

    /**
     * Test 4: Soft-deletion and purge lifecycle.
     */
    public function test_document_deletion_removes_structured_records_from_active_retrieval(): void
    {
        $csv = implode("\n", [
            '"SKU","Name","Price"',
            '"DEL-01","Item To Delete","999"',
        ]);

        $docPath = "documents/{$this->workspace->id}/to_delete.csv";
        Storage::disk('local')->put($docPath, $csv);

        $document = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Deletable Doc',
            'original_filename' => 'to_delete.csv',
            'file_path'         => $docPath,
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csv),
            'content_hash'      => hash('sha256', $csv),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $mapping = [
            'SKU'   => SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
            'Name'  => SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            'Price' => SpreadsheetInspectorService::CATEGORY_AMOUNT,
        ];
        $job = $this->ingestion->createImportJob($document, 'Default', 0, $mapping, 'skip', $this->owner->id);
        $this->ingestion->processChunk($job, 50);

        $this->assertCount(1, StructuredRecord::where('document_id', $document->id)->get());

        // Soft delete document
        $document->delete();

        // Active query scoping excludes soft-deleted documents
        $activeRecords = StructuredRecord::whereHas('document', function ($q) {
            $q->whereNull('deleted_at');
        })->where('workspace_id', $this->workspace->id)->get();

        $this->assertCount(0, $activeRecords, 'Soft-deleted documents must not appear in active queries');
    }

    /**
     * Test 5: Multi-sheet cross-sheet relationship discovery (Foreign Key & Cardinality).
     */
    public function test_multi_sheet_cross_sheet_relationship_discovery(): void
    {
        $spreadsheet = new Spreadsheet();

        // Sheet 1: Products (Parent)
        $sheetProducts = $spreadsheet->getActiveSheet();
        $sheetProducts->setTitle('Products');
        $sheetProducts->fromArray([
            ['sku', 'product_name', 'unit_price'],
            ['PROD-10', 'Cotton Panjabi', 2200],
            ['PROD-20', 'Silk Scarf', 850],
            ['PROD-30', 'Linen Shirt', 1500],
            ['PROD-40', 'Leather Belt', 1200],
        ]);

        // Sheet 2: Orders (Child with Many-to-One FK referencing Products.sku)
        $sheetOrders = $spreadsheet->createSheet();
        $sheetOrders->setTitle('Orders');
        $sheetOrders->fromArray([
            ['order_id', 'product_sku', 'quantity', 'total_amount'],
            ['ORD-1001', 'PROD-10', 2, 4400],
            ['ORD-1002', 'PROD-10', 1, 2200],
            ['ORD-1003', 'PROD-20', 3, 2550],
            ['ORD-1004', 'PROD-30', 1, 1500],
            ['ORD-1005', 'PROD-40', 2, 2400],
        ]);

        $filePath = "documents/{$this->workspace->id}/multi_sheet_store.xlsx";
        $writer = new Xlsx($spreadsheet);
        $fullPath = Storage::disk('local')->path($filePath);
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        $writer->save($fullPath);

        $document = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Multi Sheet Store',
            'original_filename' => 'multi_sheet_store.xlsx',
            'file_path'         => $filePath,
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => filesize($fullPath),
            'content_hash'      => hash_file('sha256', $fullPath),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $job = $this->ingestion->createImportJob($document, 'Products', 0, [], 'skip', $this->owner->id);

        $graph = $this->relationshipService->discoverWorkbookFile($job, $fullPath);

        $this->assertNotNull($graph);
        $edges = $graph->edges;
        $this->assertNotEmpty($edges, 'Should discover relationship edges between Orders and Products');

        // Look for the edge between Orders.product_sku and Products.sku
        $fkEdge = null;
        foreach ($edges as $edge) {
            if ($edge->sourceSheet === 'Orders' && $edge->targetSheet === 'Products') {
                $fkEdge = $edge;
                break;
            }
        }

        $this->assertNotNull($fkEdge, 'Expected FK edge from Orders to Products');
        $this->assertEquals('Orders', $fkEdge->sourceSheet);
        $this->assertEquals('Products', $fkEdge->targetSheet);
        $this->assertContains('product_sku', $fkEdge->sourceHeaders);
        $this->assertContains('sku', $fkEdge->targetHeaders);
        $this->assertGreaterThanOrEqual(0.85, $fkEdge->inclusionRatio);
    }

    /**
     * Test 6: Generation fencing aborts stale concurrent background jobs.
     */
    public function test_generation_fencing_aborts_stale_worker(): void
    {
        $rows = ['"ID","Name","Price"'];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = "\"ID-{$i}\",\"Product {$i}\",\"100.00\"";
        }
        $csvContent = implode("\n", $rows);
        $filePath = "documents/{$this->workspace->id}/fencing_test.csv";
        Storage::disk('local')->put($filePath, $csvContent);

        $document = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
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

        // Simulate a job re-generation or concurrent cancel
        $newGeneration = (string) Str::uuid();
        $job->update(['generation_id' => $newGeneration]);

        // Attempt to execute old queue worker with old fencing token
        $staleWorkerJob = new ProcessSpreadsheetImportJob($job, $oldGeneration, 4);
        $staleWorkerJob->handle($this->ingestion);

        // Stale worker must NOT advance rows
        $this->assertEquals(4, $job->fresh()->checkpoint_row);
        $this->assertEquals(4, StructuredRecord::where('import_job_id', $job->id)->count());
    }
}
