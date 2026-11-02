<?php

declare(strict_types=1);

namespace Tests\Feature\Spreadsheet;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\IngestionPurpose;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Spreadsheet\DTOs\SourceColumnDescriptor;
use App\Services\Spreadsheet\SpreadsheetIngestionService;
use App\Services\Spreadsheet\SpreadsheetInspectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DomainAgnosticDynamicSemanticsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private SpreadsheetInspectorService $inspector;
    private SpreadsheetIngestionService $ingestion;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->workspace = Workspace::create([
            'name'      => 'Acme Universal Enterprises',
            'slug'      => 'acme-universal',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email'        => 'owner@acme-universal.com',
        ]);

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

        $role = Role::findOrCreate(RoleEnum::ADMIN->value, 'web');
        $role->syncPermissions([
            KnowledgeDocumentPermission::VIEW->value,
            KnowledgeDocumentPermission::CREATE->value,
            KnowledgeDocumentPermission::UPDATE->value,
            KnowledgeDocumentPermission::DELETE->value,
        ]);
        $this->owner->assignRole($role);

        $this->inspector = app(SpreadsheetInspectorService::class);
        $this->ingestion = app(SpreadsheetIngestionService::class);
    }

    /**
     * Scenario 1: Retail / E-Commerce Multi-Metric Overwrite Defense.
     * Purchase Cost, Selling Price, Discount, and Commission must all be preserved independently without overwrite.
     */
    public function test_multi_metric_sales_preserves_all_financial_columns_without_overwrite(): void
    {
        $csvContent = implode("\n", [
            '"Date","SKU","Product Name","Purchase Cost","Selling Price","Discount","Commission","Quantity","Salesperson"',
            '"2026-10-01","SKU-001","Cotton Kurti","650.00","1000.00","100.00","50.00","5","Rahim"',
            '"2026-10-02","SKU-002","Silk Scarf","300.00","550.00","0.00","25.00","10","Karim"',
        ]);

        $filePath = 'multi_metric_sales.csv';
        Storage::disk('local')->put($filePath, $csvContent);
        $absolutePath = Storage::disk('local')->path($filePath);

        $inspection = $this->inspector->inspectWorkbook($absolutePath, 'sales.csv');
        $this->assertTrue($inspection['success']);

        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Sales Report Oct 2026',
            'original_filename' => 'sales.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $job = $this->ingestion->createImportJob($doc, 'Default', 0);
        $this->assertEquals('2.1.0', $job->schema_version);
        $this->assertNotEmpty($job->source_descriptors);

        $result = $this->ingestion->processChunk($job, 10);
        $this->assertTrue($result['is_finished']);

        $records = StructuredRecord::where('import_job_id', $job->id)->orderBy('row_index')->get();
        $this->assertCount(2, $records);

        $rec1 = $records->first();

        // 1. Source Values verify independent raw storage
        $sourceValues = $rec1->source_values;
        $this->assertIsArray($sourceValues);
        $this->assertEquals('650.00', $sourceValues['col_003']); // Purchase Cost
        $this->assertEquals('1000.00', $sourceValues['col_004']); // Selling Price
        $this->assertEquals('100.00', $sourceValues['col_005']); // Discount
        $this->assertEquals('50.00', $sourceValues['col_006']); // Commission

        // 2. Mapped Attributes verify typed source descriptors
        $mappedAttrs = $rec1->mapped_attributes;
        $this->assertEquals('purchase_cost', $mappedAttrs['col_003']['semantic_role']);
        $this->assertEquals(650.0, $mappedAttrs['col_003']['value']);
        $this->assertEquals('internal', $mappedAttrs['col_003']['sensitivity']);

        $this->assertEquals('selling_price', $mappedAttrs['col_004']['semantic_role']);
        $this->assertEquals(1000.0, $mappedAttrs['col_004']['value']);
        $this->assertEquals('public', $mappedAttrs['col_004']['sensitivity']);

        $this->assertEquals('discount', $mappedAttrs['col_005']['semantic_role']);
        $this->assertEquals(100.0, $mappedAttrs['col_005']['value']);

        $this->assertEquals('commission', $mappedAttrs['col_006']['semantic_role']);
        $this->assertEquals(50.0, $mappedAttrs['col_006']['value']);
        $this->assertEquals('restricted', $mappedAttrs['col_006']['sensitivity']);

        // 3. Semantic Projections verify zero collision / zero overwrite across all 4 amounts
        $projections = $rec1->semantic_projections;
        $this->assertEquals(650.0, $projections['purchase_cost']);
        $this->assertEquals(1000.0, $projections['selling_price']);
        $this->assertEquals(100.0, $projections['discount']);
        $this->assertEquals(50.0, $projections['commission']);

        // 4. Field Sensitivities verify column-level security
        $sensitivities = $rec1->field_sensitivities;
        $this->assertEquals('internal', $sensitivities['col_003']);
        $this->assertEquals('public', $sensitivities['col_004']);
        $this->assertEquals('restricted', $sensitivities['col_006']);

        // 5. Fail-closed privacy: restricted commission + workforce salesperson keeps row private
        $this->assertFalse((bool) $rec1->is_public);
    }

    /**
     * Scenario 2: Medical Clinic / Healthcare Domain.
     * Doctor, Patient, Consultation Fee, Due, and Diagnosis Notes are correctly separated and privacy-protected.
     */
    public function test_medical_clinic_separates_doctor_patient_and_enforces_privacy(): void
    {
        $csvContent = implode("\n", [
            '"Appointment ID","Doctor Name","Patient Name","Visit Date","Consultation Fee","Diagnostic Fee","Paid Amount","Due Balance","Diagnosis Notes"',
            '"APT-991","Dr. Farhana","Nasima Begum","2026-11-05","1200.00","3500.00","4000.00","700.00","Type 2 Diabetes Routine Checkup"',
        ]);

        $filePath = 'medical_appointments.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Clinical Appointments Nov 2026',
            'original_filename' => 'appointments.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $job = $this->ingestion->createImportJob($doc, 'Default', 0);
        $this->ingestion->processChunk($job, 10);

        $rec = StructuredRecord::where('import_job_id', $job->id)->first();
        $this->assertNotNull($rec);

        $mappedAttrs = $rec->mapped_attributes;
        $projections = $rec->semantic_projections;

        // Verify Doctor vs Patient
        $this->assertEquals('doctor', $mappedAttrs['col_001']['semantic_role']);
        $this->assertEquals('internal', $mappedAttrs['col_001']['sensitivity']);
        $this->assertEquals('patient', $mappedAttrs['col_002']['semantic_role']);
        $this->assertEquals('restricted', $mappedAttrs['col_002']['sensitivity']);

        // Projections
        $this->assertEquals('Dr. Farhana', $projections['doctor']);
        $this->assertEquals('Nasima Begum', $projections['patient']);
        $this->assertEquals(1200.0, $projections['consultation_fee']);
        $this->assertEquals(700.0, $projections['due_balance']);

        // Clinical notes restricted
        $this->assertEquals('clinical_notes', $mappedAttrs['col_008']['semantic_role']);
        $this->assertEquals('restricted', $mappedAttrs['col_008']['sensitivity']);

        // Fail-closed privacy
        $this->assertFalse((bool) $rec->is_public);
    }

    /**
     * Scenario 3: Hospitality / Hotel Domain.
     * Check-in Date, Check-out Date, Room Charge, and Extra Fees.
     */
    public function test_hotel_domain_preserves_checkin_checkout_and_room_charges(): void
    {
        $csvContent = implode("\n", [
            '"Booking ID","Guest Name","Room Number","Check-in Date","Check-out Date","Room Charge","Extra Service Fee","Booking Status"',
            '"BKG-7701","John Doe","Suite 402","2026-12-20","2026-12-25","15000.00","2500.00","Confirmed"',
        ]);

        $filePath = 'hotel_bookings.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Hotel Bookings Dec 2026',
            'original_filename' => 'bookings.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $job = $this->ingestion->createImportJob($doc, 'Default', 0);
        $this->ingestion->processChunk($job, 10);

        $rec = StructuredRecord::where('import_job_id', $job->id)->first();
        $this->assertNotNull($rec);

        $projections = $rec->semantic_projections;
        $this->assertEquals('John Doe', $projections['guest']);
        $this->assertEquals('Suite 402', $projections['room_number']);
        $this->assertStringStartsWith('2026-12-20', $projections['check_in_date']);
        $this->assertStringStartsWith('2026-12-25', $projections['check_out_date']);
        $this->assertEquals(15000.0, $projections['booking_charge']);
        $this->assertEquals(2500.0, $projections['service_fee']);
    }

    /**
     * Scenario 4: NGO / Donation Domain.
     * Donor, Beneficiary, Pledge Amount, Received Amount.
     */
    public function test_ngo_donation_domain_preserves_donor_and_beneficiary(): void
    {
        $csvContent = implode("\n", [
            '"Donor ID","Donor Name","Beneficiary Name","Pledge Amount","Received Amount","Donation Date","Project Name"',
            '"DON-101","Anonymous Philanthropist","Village School Fund","500000.00","250000.00","2026-10-15","Winter Clothes Campaign"',
        ]);

        $filePath = 'ngo_donations.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Donations 2026',
            'original_filename' => 'donations.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $job = $this->ingestion->createImportJob($doc, 'Default', 0);
        $this->ingestion->processChunk($job, 10);

        $rec = StructuredRecord::where('import_job_id', $job->id)->first();
        $this->assertNotNull($rec);

        $mappedAttrs = $rec->mapped_attributes;
        $projections = $rec->semantic_projections;

        $this->assertEquals('donor', $mappedAttrs['col_001']['semantic_role']);
        $this->assertEquals('beneficiary', $mappedAttrs['col_002']['semantic_role']);
        $this->assertEquals('restricted', $mappedAttrs['col_002']['sensitivity']);

        $this->assertEquals('Anonymous Philanthropist', $projections['donor']);
        $this->assertEquals('Village School Fund', $projections['beneficiary']);
        $this->assertEquals(500000.0, $projections['donation_amount']);
    }

    /**
     * Scenario 5: Unknown / Future Business Domain.
     * Ingests arbitrary experimental laboratory data with zero domain errors.
     */
    public function test_unknown_future_business_domain_ingests_gracefully(): void
    {
        $csvContent = implode("\n", [
            '"Specimen Code","Incubation Chamber","Target Yield Pct","Operating Temp","Logged Timestamp"',
            '"SP-88","Chamber Alpha","98.5","37.2","2026-11-01 10:00:00"',
        ]);

        $filePath = 'lab_experiment.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Lab Experiment Alpha',
            'original_filename' => 'lab.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $job = $this->ingestion->createImportJob($doc, 'Default', 0);
        $this->ingestion->processChunk($job, 10);

        $rec = StructuredRecord::where('import_job_id', $job->id)->first();
        $this->assertNotNull($rec);

        $sourceVals = $rec->source_values;
        $mappedAttrs = $rec->mapped_attributes;

        $this->assertEquals('SP-88', $sourceVals['col_000']);
        $this->assertEquals('Chamber Alpha', $sourceVals['col_001']);
        $this->assertEquals(98.5, $mappedAttrs['col_002']['value']);
        $this->assertEquals(37.2, $mappedAttrs['col_003']['value']);
    }

    /**
     * Scenario 6: Duplicate Semantic Roles in Single Row.
     * When two columns map to the same semantic role (e.g. Lead Doctor and Assisting Doctor),
     * collision avoidance prevents overwrite.
     */
    public function test_duplicate_semantic_roles_do_not_collide_or_overwrite(): void
    {
        $headers = ['Lead Doctor', 'Assisting Doctor', 'Procedure Date'];
        $rowCells = ['Dr. Alice Smith', 'Dr. Bob Jones', '2026-11-02 12:00:00'];

        $descriptors = [
            'col_000' => [
                'source_column_id'   => 'col_000',
                'original_header'    => 'Lead Doctor',
                'physical_type'      => 'string',
                'suggested_category' => 'person_reference',
                'semantic_role'      => 'doctor',
                'sensitivity'        => 'internal',
            ],
            'col_001' => [
                'source_column_id'   => 'col_001',
                'original_header'    => 'Assisting Doctor',
                'physical_type'      => 'string',
                'suggested_category' => 'person_reference',
                'semantic_role'      => 'doctor',
                'sensitivity'        => 'internal',
            ],
            'col_002' => [
                'source_column_id'   => 'col_002',
                'original_header'    => 'Procedure Date',
                'physical_type'      => 'date',
                'suggested_category' => 'date_time',
                'semantic_role'      => 'procedure_date',
                'sensitivity'        => 'internal',
            ],
        ];

        $parsed = $this->ingestion->parseRow($headers, $rowCells, [], 2, false, $descriptors);

        // Neither doctor is lost!
        $projections = $parsed['semantic_projections'];
        $this->assertTrue(isset($projections['doctor']));
        $this->assertTrue(is_array($projections['doctor']));
        $this->assertEquals('Dr. Alice Smith', $projections['doctor']['col_000']);
        $this->assertEquals('Dr. Bob Jones', $projections['doctor']['col_001']);
        $this->assertEquals('Dr. Bob Jones', $projections['doctor_col_001']);
    }

    /**
     * Scenario 7: Security Barrier & Fail-Closed Sensitivity Validation.
     * Client cannot mark sensitive roles (salary, patient, purchase cost) as public.
     */
    public function test_confirm_mapping_rejects_marking_restricted_roles_as_public(): void
    {
        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Payroll Summary',
            'original_filename' => 'payroll.csv',
            'file_path'         => 'payroll.csv',
            'content_hash'      => 'dummy_hash',
            'mime_type'         => 'text/csv',
            'file_size'         => 100,
            'lifecycle_status'  => DocumentLifecycleStatus::REQUIRES_MAPPING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        SpreadsheetImportJob::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'document_id'       => $doc->id,
            'sheet_name'        => 'Default',
            'status'            => 'awaiting_mapping',
            'conflict_mode'     => 'skip',
            'generation_id'     => (string) Str::uuid(),
            'header_row_index'  => 0,
            'total_rows'        => 10,
            'processed_rows'    => 0,
            'staged_rows'       => 0,
            'failed_rows'       => 0,
            'checkpoint_row'    => 0,
            'detected_schema'   => [],
            'mapping_config'    => [],
            'validation_errors' => [],
            'created_by'        => $this->owner->id,
        ]);

        // Attempt to maliciously or mistakenly mark Salary as public via source_descriptors
        $payload = [
            'source_descriptors' => [
                'col_001' => [
                    'source_column_id' => 'col_001',
                    'semantic_role'    => 'salary',
                    'sensitivity'      => 'public', // FORBIDDEN!
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->postJson(route('documents.spreadsheet.confirm', $doc), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_descriptors']);
    }

    /**
     * Scenario 8: Extended Sensitivity Protection for Substrings and Unvetted Custom Roles.
     * Prevents custom roles like doctor_payment, staff_bonus, clinical_record, or arbitrary custom roles from being marked public.
     */
    public function test_sensitivity_rejects_sensitive_substrings_and_unvetted_custom_roles(): void
    {
        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Clinical Finance',
            'original_filename' => 'finance.csv',
            'file_path'         => 'finance.csv',
            'content_hash'      => 'dummy_hash_2',
            'mime_type'         => 'text/csv',
            'file_size'         => 100,
            'lifecycle_status'  => DocumentLifecycleStatus::REQUIRES_MAPPING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        SpreadsheetImportJob::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'document_id'       => $doc->id,
            'sheet_name'        => 'Default',
            'status'            => 'awaiting_mapping',
            'conflict_mode'     => 'skip',
            'generation_id'     => (string) Str::uuid(),
            'header_row_index'  => 0,
            'total_rows'        => 5,
            'processed_rows'    => 0,
            'staged_rows'       => 0,
            'failed_rows'       => 0,
            'checkpoint_row'    => 0,
            'detected_schema'   => [],
            'mapping_config'    => [],
            'validation_errors' => [],
            'created_by'        => $this->owner->id,
        ]);

        // 1. Substring doctor_payment
        $payload1 = [
            'source_descriptors' => [
                'col_001' => ['semantic_role' => 'doctor_payment', 'sensitivity' => 'public'],
            ],
        ];
        $res1 = $this->actingAs($this->owner)->postJson(route('documents.spreadsheet.confirm', $doc), $payload1);
        $res1->assertStatus(422);

        // 2. Substring staff_bonus
        $payload2 = [
            'source_descriptors' => [
                'col_001' => ['semantic_role' => 'staff_bonus', 'sensitivity' => 'public'],
            ],
        ];
        $res2 = $this->actingAs($this->owner)->postJson(route('documents.spreadsheet.confirm', $doc), $payload2);
        $res2->assertStatus(422);

        // 3. Substring internal_margin
        $payload3 = [
            'source_descriptors' => [
                'col_001' => ['semantic_role' => 'internal_margin', 'sensitivity' => 'public'],
            ],
        ];
        $res3 = $this->actingAs($this->owner)->postJson(route('documents.spreadsheet.confirm', $doc), $payload3);
        $res3->assertStatus(422);

        // 4. Unvetted custom field without security clearance
        $payload4 = [
            'source_descriptors' => [
                'col_001' => ['semantic_role' => 'secret_formula_yield', 'sensitivity' => 'public'],
            ],
        ];
        $res4 = $this->actingAs($this->owner)->postJson(route('documents.spreadsheet.confirm', $doc), $payload4);
        $res4->assertStatus(422);
    }

    /**
     * Scenario 9: Raw Values Lossless Preservation.
     * Leading zeroes, trailing decimal zeroes, Bengali numerals, empty strings, and negative values.
     */
    public function test_raw_values_lossless_preservation(): void
    {
        $headers = ['Postal Code', 'Precise Amount', 'Bengali Measure', 'Empty Notes', 'Negative Val'];
        $rowCells = ['00125', '000045.500', '১২৩৪.৫০', '', '-500'];

        $descriptors = [
            'col_000' => ['source_column_id' => 'col_000', 'original_header' => 'Postal Code', 'physical_type' => 'string', 'suggested_category' => 'identifier', 'semantic_role' => 'postal_code'],
            'col_001' => ['source_column_id' => 'col_001', 'original_header' => 'Precise Amount', 'physical_type' => 'decimal', 'suggested_category' => 'amount', 'semantic_role' => 'precise_amount'],
            'col_002' => ['source_column_id' => 'col_002', 'original_header' => 'Bengali Measure', 'physical_type' => 'decimal', 'suggested_category' => 'amount', 'semantic_role' => 'bengali_measure'],
            'col_003' => ['source_column_id' => 'col_003', 'original_header' => 'Empty Notes', 'physical_type' => 'string', 'suggested_category' => 'description', 'semantic_role' => 'notes'],
            'col_004' => ['source_column_id' => 'col_004', 'original_header' => 'Negative Val', 'physical_type' => 'decimal', 'suggested_category' => 'amount', 'semantic_role' => 'negative_val'],
        ];

        $parsed = $this->ingestion->parseRow($headers, $rowCells, [], 2, false, $descriptors);

        // Raw values MUST be strictly lossless strings
        $this->assertSame('00125', $parsed['source_values']['col_000']);
        $this->assertSame('000045.500', $parsed['source_values']['col_001']);
        $this->assertSame('১২৩৪.৫০', $parsed['source_values']['col_002']);
        $this->assertSame('', $parsed['source_values']['col_003']);
        $this->assertSame('-500', $parsed['source_values']['col_004']);

        // Typed values preserve numeric meaning
        $this->assertEquals(45.5, $parsed['mapped_attributes']['col_001']['value']);
        $this->assertEquals(1234.50, $parsed['mapped_attributes']['col_002']['value']);
        $this->assertNull($parsed['mapped_attributes']['col_003']['value']);
        $this->assertEquals(-500.0, $parsed['mapped_attributes']['col_004']['value']);
    }

    /**
     * Scenario 10: Three or More Duplicate Roles Preserved in Multi-Column Array & Direct Aliases.
     */
    public function test_three_duplicate_roles_preserved_in_sub_array_and_aliases(): void
    {
        $headers = ['Lead Doctor', 'Assisting Doctor', 'Visiting Doctor'];
        $rowCells = ['Dr. Alice', 'Dr. Bob', 'Dr. Charlie'];

        $descriptors = [
            'col_000' => ['source_column_id' => 'col_000', 'original_header' => 'Lead Doctor', 'physical_type' => 'string', 'suggested_category' => 'person_reference', 'semantic_role' => 'doctor', 'sensitivity' => 'internal'],
            'col_001' => ['source_column_id' => 'col_001', 'original_header' => 'Assisting Doctor', 'physical_type' => 'string', 'suggested_category' => 'person_reference', 'semantic_role' => 'doctor', 'sensitivity' => 'internal'],
            'col_002' => ['source_column_id' => 'col_002', 'original_header' => 'Visiting Doctor', 'physical_type' => 'string', 'suggested_category' => 'person_reference', 'semantic_role' => 'doctor', 'sensitivity' => 'internal'],
        ];

        $parsed = $this->ingestion->parseRow($headers, $rowCells, [], 2, false, $descriptors);

        // 1. Authoritative source_values has all 3
        $this->assertEquals('Dr. Alice', $parsed['source_values']['col_000']);
        $this->assertEquals('Dr. Bob', $parsed['source_values']['col_001']);
        $this->assertEquals('Dr. Charlie', $parsed['source_values']['col_002']);

        // 2. Authoritative mapped_attributes has all 3
        $this->assertEquals('Dr. Alice', $parsed['mapped_attributes']['col_000']['value']);
        $this->assertEquals('Dr. Bob', $parsed['mapped_attributes']['col_001']['value']);
        $this->assertEquals('Dr. Charlie', $parsed['mapped_attributes']['col_002']['value']);

        // 3. Derived semantic_projections sub-array has all 3
        $projections = $parsed['semantic_projections'];
        $this->assertTrue(isset($projections['doctor']['_is_multi_column']));
        $this->assertEquals('Dr. Alice', $projections['doctor']['col_000']);
        $this->assertEquals('Dr. Bob', $projections['doctor']['col_001']);
        $this->assertEquals('Dr. Charlie', $projections['doctor']['col_002']);

        // 4. Derived semantic_projections aliases
        $this->assertEquals('Dr. Alice', $projections['doctor_col_000']);
        $this->assertEquals('Dr. Bob', $projections['doctor_col_001']);
        $this->assertEquals('Dr. Charlie', $projections['doctor_col_002']);
    }

    /**
     * Scenario 11: Legacy Records Backward Compatibility.
     * Existing records with NULL source_values or semantic_projections gracefully fall back.
     */
    public function test_legacy_structured_records_backward_compatibility(): void
    {
        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Legacy Doc',
            'original_filename' => 'legacy.csv',
            'file_path'         => 'legacy.csv',
            'content_hash'      => 'dummy_legacy_hash',
            'mime_type'         => 'text/csv',
            'file_size'         => 100,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $legacyRecord = new StructuredRecord([
            'workspace_id'           => $this->workspace->id,
            'document_id'            => $doc->id,
            'sheet_name'             => 'Sheet1',
            'row_index'              => 5,
            'record_identifier'      => 'LEGACY-001',
            'canonical_entity_name'  => 'Legacy Product',
            'canonical_amount'       => 1500.00,
            'canonical_quantity'     => 10,
            'canonical_person'       => 'Old Salesperson',
            'raw_values'             => ['SKU' => 'LEGACY-001', 'Price' => '1500.00'],
            'source_values'          => null, // Pre-Phase 2.1 NULL
            'semantic_projections'   => null, // Pre-Phase 2.1 NULL
            'field_sensitivities'    => null, // Pre-Phase 2.1 NULL
            'mapped_attributes'      => null,
            'content_fingerprint'    => 'hash_legacy',
            'is_public'              => false,
        ]);
        $legacyRecord->save();

        $fresh = StructuredRecord::find($legacyRecord->id);
        $this->assertNotNull($fresh);

        // Fallbacks must work seamlessly
        $this->assertEquals(['SKU' => 'LEGACY-001', 'Price' => '1500.00'], $fresh->source_values);
        $this->assertEquals(1500.00, $fresh->semantic_projections['amount']);
        $this->assertEquals(10.0, $fresh->semantic_projections['quantity']);
        $this->assertEquals('Old Salesperson', $fresh->semantic_projections['person']);
        $this->assertEquals('Legacy Product', $fresh->semantic_projections['entity_name']);
        $this->assertEquals([], $fresh->field_sensitivities);
    }

    /**
     * Scenario 12: Shared Contract Version Symmetry & Python Compatibility Export.
     * Verify SourceColumnDescriptor exports compatible schema for Python virtual database.
     */
    public function test_source_column_descriptor_contract_symmetry(): void
    {
        $desc = new SourceColumnDescriptor(
            sourceColumnId: 'col_001',
            originalHeader: 'Purchase Cost',
            normalizedHeader: 'purchase_cost',
            sheetName: 'Sheet1',
            columnIndex: 1,
            physicalType: 'decimal',
            suggestedCategory: 'amount',
            semanticRole: 'purchase_cost',
            candidateRoles: [
                ['role' => 'unit_cost', 'confidence' => 0.85, 'entity' => 'product', 'sensitivity' => 'internal'],
            ],
            entityType: 'product',
            unit: 'BDT',
            sensitivity: 'internal',
            confidence: 0.95,
            evidence: ['Header pattern matched'],
            isAmbiguous: false,
            mappingSource: 'inferred',
            ownerConfirmed: false
        );

        $array = $desc->toArray();
        $this->assertEquals('2.1.0', $array['contract_version']);
        $this->assertEquals('col_001', $array['source_column_id']);
        $this->assertEquals('purchase_cost', $array['semantic_role']);
        $this->assertEquals('product', $array['entity_type']);
        $this->assertEquals('BDT', $array['unit']);
        $this->assertEquals('internal', $array['sensitivity']);

        // Roundtrip instantiation from array
        $reconstituted = SourceColumnDescriptor::fromArray($array);
        $this->assertEquals($desc->sourceColumnId, $reconstituted->sourceColumnId);
        $this->assertEquals($desc->semanticRole, $reconstituted->semanticRole);

        // Test Python descriptor export
        $pyDesc = $desc->toPythonDescriptor();
        $this->assertEquals('REAL', $pyDesc['physical_type']);
        $this->assertEquals('currency', $pyDesc['semantic_role']);
        $this->assertEquals('product', $pyDesc['entity_type']);
        $this->assertEquals('2.1.0', $pyDesc['contract_version']);

        // Execute live cross-language validation in Python if virtualenv available
        $pythonBin = '/Users/zesan/Desktop/Entrepreneurs_Automation/python-ai-service/venv/bin/python';
        if (file_exists($pythonBin)) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'contract_');
            file_put_contents($tmpFile, json_encode($pyDesc));

            $cmd = sprintf(
                '%s -c %s',
                escapeshellarg($pythonBin),
                escapeshellarg(sprintf('
import json, sys
sys.path.insert(0, "/Users/zesan/Desktop/Entrepreneurs_Automation/python-ai-service")
from app.analytics.semantic_schema import ColumnSemanticDescriptor, ColumnSemanticRole

with open("%s") as f:
    d = json.load(f)

desc = ColumnSemanticDescriptor(
    name=d["name"],
    physical_type=d["physical_type"],
    semantic_role=ColumnSemanticRole(d["semantic_role"]),
    entity_type=d.get("entity_type"),
    business_meaning=d.get("business_meaning"),
    unit=d.get("unit"),
    is_nullable=d.get("is_nullable", True),
    uniqueness_ratio=d.get("uniqueness_ratio", 0.0),
    evidence=d.get("evidence", [])
)
assert desc.name == "Purchase Cost"
assert desc.physical_type == "REAL"
print("OK")
', $tmpFile))
            );

            $output = shell_exec($cmd);
            @unlink($tmpFile);
            $this->assertStringContainsString('OK', (string) $output);
        }
    }

    /**
     * Scenario 13: Real XLSX Cell Formats Lossless Ingestion Verification.
     * Verifies that PhpSpreadsheet preserves leading zeroes in text cells, formatted numbers, and empty cells.
     */
    public function test_real_xlsx_cell_lossless_representation(): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CellFormats');

        $sheet->fromArray([
            ['Order Code', 'Product SKU', 'List Price', 'Discount Rate', 'Empty Cell']
        ], null, 'A1');

        // Explicitly set '00125' as explicit string to preserve leading zeroes
        $sheet->setCellValueExplicit('A2', 'ORD-00125', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B2', '000789', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C2', 1250.50);
        $sheet->setCellValue('D2', 0.15);
        $sheet->setCellValue('E2', null);

        $fixturePath = 'test_cell_formats.xlsx';
        $absPath = Storage::disk('local')->path($fixturePath);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($absPath);
        unset($spreadsheet, $sheet);

        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'XLSX Cell Formats Test',
            'original_filename' => 'formats.xlsx',
            'file_path'         => $fixturePath,
            'content_hash'      => hash_file('sha256', $absPath),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => Storage::disk('local')->size($fixturePath),
            'lifecycle_status'  => DocumentLifecycleStatus::INDEXING,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $job = $this->ingestion->createImportJob($doc, 'CellFormats', 0);
        $this->ingestion->processChunk($job, 10);

        $rec = StructuredRecord::where('import_job_id', $job->id)->first();
        $this->assertNotNull($rec);

        // Leading zeroes in text cells are 100% preserved in raw source values
        $this->assertSame('ORD-00125', $rec->source_values['col_000']);
        $this->assertSame('000789', $rec->source_values['col_001']);
        $this->assertEquals(1250.50, $rec->mapped_attributes['col_002']['value']);
        $this->assertSame('', $rec->source_values['col_004']);
    }
}
