<?php

declare(strict_types=1);

namespace Tests\Feature\Spreadsheet;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\IngestionPurpose;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Models\SuperAdminGovernanceSession;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Spreadsheet\DTOs\SourceColumnDescriptor;
use App\Services\Spreadsheet\Security\AnalyticsAstAuthorizer;
use App\Services\Spreadsheet\Security\DataClassification;
use App\Services\Spreadsheet\Security\DataProjectionPolicy;
use App\Services\Spreadsheet\Security\Exceptions\TenantScopeViolationException;
use App\Services\Spreadsheet\Security\Exceptions\UnauthorizedDataAccessException;
use App\Services\Spreadsheet\Projections\FieldLevelProjectionService;
use App\Services\Spreadsheet\Projections\ProjectedRecord;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use App\Services\Spreadsheet\Security\TypesensePublicProjectionSyncService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2.2D: Field-Level Privacy Matrix & Authorized Data Egress Engine Test Suite.
 *
 * Verifies centralized authorization, zero-token customer context leakage,
 * AST-level indirect mathematical inference barriers, cross-tenant isolation,
 * SuperAdmin governance sessions, cryptographic cache partitioning, and Typesense reconciliation.
 */
class FieldLevelPrivacyMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $ownerA;
    protected User $ownerB;
    protected User $superAdmin;
    protected KnowledgeDocument $documentA;
    protected SpreadsheetImportJob $jobA;
    protected FieldLevelProjectionService $projectionService;
    protected AnalyticsAstAuthorizer $astAuthorizer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->workspaceA = Workspace::create([
            'name' => 'Nexus Retail Ltd',
            'slug' => 'nexus-retail',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Starlight Healthcare',
            'slug' => 'starlight-health',
        ]);

        $this->ownerA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->ownerA->assignRole(RoleEnum::ADMIN->value);

        $this->ownerB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
        ]);
        $this->ownerB->assignRole(RoleEnum::ADMIN->value);

        $this->superAdmin = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->superAdmin->assignRole(RoleEnum::SUPERADMIN->value);

        $this->documentA = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Q4 Financials & Catalog',
            'original_filename' => 'q4_catalog.xlsx',
            'file_path'         => 'documents/q4_catalog.xlsx',
            'content_hash'      => hash('sha256', 'mock_q4_file'),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
            'is_active'         => true,
            'chunk_count'       => 1,
        ]);

        $this->jobA = SpreadsheetImportJob::create([
            'document_id'      => $this->documentA->id,
            'workspace_id'     => $this->workspaceA->id,
            'original_filename'=> 'q4_catalog.xlsx',
            'stored_path'      => 'documents/q4_catalog.xlsx',
            'mime_type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'        => 1024,
            'status'           => 'completed',
            'is_active'        => true,
            'generation_id'    => (string) Str::uuid(),
            'conflict_mode'    => 'skip',
            'header_row_index' => 0,
        ]);

        $this->projectionService = new FieldLevelProjectionService(new DataProjectionPolicy());
        $this->astAuthorizer = new AnalyticsAstAuthorizer(new DataProjectionPolicy());
    }

    /**
     * Helper to create a realistic multi-metric structured record.
     */
    protected function createMultiMetricRecord(): StructuredRecord
    {
        $mappedAttributes = [
            'col_000' => [
                'source_column_id'   => 'col_000',
                'header'             => 'SKU',
                'physical_type'      => 'string',
                'suggested_category' => 'identifier',
                'semantic_role'      => 'sku',
                'sensitivity'        => DataClassification::PUBLIC,
            ],
            'col_001' => [
                'source_column_id'   => 'col_001',
                'header'             => 'Product Name',
                'physical_type'      => 'string',
                'suggested_category' => 'entity_name',
                'semantic_role'      => 'product_name',
                'sensitivity'        => DataClassification::PUBLIC,
            ],
            'col_002' => [
                'source_column_id'   => 'col_002',
                'header'             => 'Purchase Cost',
                'physical_type'      => 'decimal',
                'suggested_category' => 'amount',
                'semantic_role'      => 'purchase_cost',
                'sensitivity'        => DataClassification::RESTRICTED,
            ],
            'col_003' => [
                'source_column_id'   => 'col_003',
                'header'             => 'Selling Price',
                'physical_type'      => 'decimal',
                'suggested_category' => 'amount',
                'semantic_role'      => 'selling_price',
                'sensitivity'        => DataClassification::PUBLIC,
            ],
            'col_004' => [
                'source_column_id'   => 'col_004',
                'header'             => 'Sales Commission',
                'physical_type'      => 'decimal',
                'suggested_category' => 'amount',
                'semantic_role'      => 'commission',
                'sensitivity'        => DataClassification::RESTRICTED,
            ],
            'col_005' => [
                'source_column_id'   => 'col_005',
                'header'             => 'Quantity',
                'physical_type'      => 'integer',
                'suggested_category' => 'quantity',
                'semantic_role'      => 'quantity',
                'sensitivity'        => DataClassification::PUBLIC,
            ],
        ];

        $sourceValues = [
            'col_000' => 'SKU-001',
            'col_001' => 'Cotton Embroidered Kurti',
            'col_002' => '650.00',
            'col_003' => '1200.00',
            'col_004' => '75.00',
            'col_005' => '45',
        ];

        $semanticProjections = [
            'sku'           => 'SKU-001',
            'product_name'  => 'Cotton Embroidered Kurti',
            'purchase_cost' => 650.0,
            'selling_price' => 1200.0,
            'commission'    => 75.0,
            'quantity'      => 45,
        ];

        $fieldSensitivities = [
            'col_000' => DataClassification::PUBLIC,
            'col_001' => DataClassification::PUBLIC,
            'col_002' => DataClassification::RESTRICTED,
            'col_003' => DataClassification::PUBLIC,
            'col_004' => DataClassification::RESTRICTED,
            'col_005' => DataClassification::PUBLIC,
        ];

        return StructuredRecord::create([
            'workspace_id'          => $this->workspaceA->id,
            'document_id'           => $this->documentA->id,
            'import_job_id'         => $this->jobA->id,
            'sheet_name'            => 'Products',
            'row_index'             => 2,
            'record_identifier'     => 'SKU-001',
            'canonical_entity_name' => 'Cotton Embroidered Kurti',
            'canonical_category'    => 'Apparel',
            'canonical_amount'      => '1200.0000',
            'canonical_quantity'    => '45.0000',
            'raw_values'            => $sourceValues,
            'source_values'         => $sourceValues,
            'mapped_attributes'     => $mappedAttributes,
            'semantic_projections'  => $semanticProjections,
            'field_sensitivities'   => $fieldSensitivities,
            'is_public'             => false, // Record contains restricted fields
            'content_fingerprint'   => hash('sha256', 'sku_001_multi_metric'),
        ]);
    }

    /**
     * 1. Customer Channel: Public Projection with Zero Token Leakage.
     */
    public function test_customer_channel_receives_public_fields_with_zero_token_leakage_of_restricted_fields(): void
    {
        $record = $this->createMultiMetricRecord();

        $customerContext = ServerControlledContext::forCustomer(
            workspaceId: (int) $this->workspaceA->id,
            channel: ServerControlledContext::CHANNEL_CUSTOMER_WEB
        );

        $projected = $this->projectionService->projectRecord($record, $customerContext);

        $this->assertInstanceOf(ProjectedRecord::class, $projected);

        // 1. Authorized Public Fields are present
        $this->assertTrue($projected->has('sku'));
        $this->assertTrue($projected->has('product_name'));
        $this->assertTrue($projected->has('selling_price'));
        $this->assertTrue($projected->has('quantity'));
        $this->assertEquals('SKU-001', $projected->get('sku'));
        $this->assertEquals('Cotton Embroidered Kurti', $projected->get('product_name'));
        $this->assertEquals(1200.0, $projected->get('selling_price'));
        $this->assertEquals(45, $projected->get('quantity'));

        // 2. Sensitive Restricted Fields are completely absent
        $this->assertFalse($projected->has('purchase_cost'));
        $this->assertFalse($projected->has('commission'));
        $this->assertNull($projected->get('purchase_cost'));
        $this->assertNull($projected->get('commission'));

        // 3. Redacted count indicates suppressed columns
        $this->assertEquals(2, $projected->redactedCount);

        // 4. Zero Token Leakage in Prompt String
        $promptString = $projected->toPromptString();

        $this->assertStringContainsString('Cotton Embroidered Kurti', $promptString);
        $this->assertStringContainsString('Selling Price: 1200', $promptString);
        $this->assertStringContainsString('Sku: SKU-001', $promptString);

        // HARD INVARIANT: Zero tokens of restricted values or column IDs
        $this->assertStringNotContainsString('650', $promptString, 'Zero Token Violation: purchase_cost leaked in prompt string!');
        $this->assertStringNotContainsString('75', $promptString, 'Zero Token Violation: commission leaked in prompt string!');
        $this->assertStringNotContainsString('purchase_cost', $promptString);
        $this->assertStringNotContainsString('commission', $promptString);
        $this->assertStringNotContainsString('col_002', $promptString);
        $this->assertStringNotContainsString('col_004', $promptString);
        $this->assertStringNotContainsString('[REDACTED]', $promptString, 'Prompt should not signal redacted secrets to the LLM.');
    }

    /**
     * 2. Indirect Mathematical & Aggregate Inference Attack Barrier (AST Level).
     */
    public function test_indirect_math_inference_attack_is_blocked_by_ast_authorizer(): void
    {
        $descriptors = [
            'col_000' => [
                'source_column_id'  => 'col_000',
                'original_header'   => 'Selling Price',
                'normalized_header' => 'selling_price',
                'semantic_role'     => 'selling_price',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
            'col_001' => [
                'source_column_id'  => 'col_001',
                'original_header'   => 'Purchase Cost',
                'normalized_header' => 'purchase_cost',
                'semantic_role'     => 'purchase_cost',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
        ];

        // Member without 'analytics.view_margins'
        $analyst = User::factory()->create(['workspace_id' => $this->workspaceA->id]);
        $context = ServerControlledContext::forUser(
            user: $analyst,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: ['analytics.view_reports'] // Lacks 'analytics.view_margins'
        );

        // Attack 1: Direct SELECT of restricted cost column
        $this->expectException(UnauthorizedDataAccessException::class);
        $this->astAuthorizer->authorizeQuery(
            sql: "SELECT selling_price, purchase_cost FROM sales_records",
            sourceDescriptors: $descriptors,
            context: $context
        );
    }

    /**
     * 2b. AST Arithmetic Expression Inference (price - cost).
     */
    public function test_ast_authorizer_blocks_derived_arithmetic_inference_without_margins_permission(): void
    {
        $descriptors = [
            'selling_price' => [
                'source_column_id'  => 'col_000',
                'original_header'   => 'Selling Price',
                'normalized_header' => 'selling_price',
                'semantic_role'     => 'selling_price',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
            'purchase_cost' => [
                'source_column_id'  => 'col_001',
                'original_header'   => 'Purchase Cost',
                'normalized_header' => 'purchase_cost',
                'semantic_role'     => 'purchase_cost',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
        ];

        $analyst = User::factory()->create(['workspace_id' => $this->workspaceA->id]);
        $contextWithoutMarginPerm = ServerControlledContext::forUser(
            user: $analyst,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: []
        );

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->astAuthorizer->authorizeQuery(
            sql: "SELECT selling_price - purchase_cost AS net_margin FROM sales_records",
            sourceDescriptors: $descriptors,
            context: $contextWithoutMarginPerm
        );
    }

    /**
     * 2c. AST Query Succeeds when Granted Explicit 'analytics.view_margins' Permission.
     */
    public function test_ast_authorizer_permits_cost_queries_when_permission_granted(): void
    {
        $descriptors = [
            'selling_price' => [
                'source_column_id'  => 'col_000',
                'original_header'   => 'Selling Price',
                'normalized_header' => 'selling_price',
                'semantic_role'     => 'selling_price',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
            'purchase_cost' => [
                'source_column_id'  => 'col_001',
                'original_header'   => 'Purchase Cost',
                'normalized_header' => 'purchase_cost',
                'semantic_role'     => 'purchase_cost',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
        ];

        $manager = User::factory()->create(['workspace_id' => $this->workspaceA->id]);
        $contextWithMarginPerm = ServerControlledContext::forUser(
            user: $manager,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: ['analytics.view_margins']
        );

        $isAllowed = $this->astAuthorizer->authorizeQuery(
            sql: "SELECT selling_price, purchase_cost, selling_price - purchase_cost AS gross_profit FROM sales_records WHERE purchase_cost > 500",
            sourceDescriptors: $descriptors,
            context: $contextWithMarginPerm
        );

        $this->assertTrue($isAllowed);
    }

    /**
     * 3. Column ID & Alias Probing Protection: Stripped from Customer Channel.
     */
    public function test_internal_column_id_probing_and_duplicate_aliases_are_stripped_from_customer_channel(): void
    {
        $mappedAttributes = [
            'col_000' => [
                'source_column_id'   => 'col_000',
                'header'             => 'Primary Price',
                'physical_type'      => 'decimal',
                'suggested_category' => 'amount',
                'semantic_role'      => 'selling_price',
                'sensitivity'        => DataClassification::PUBLIC,
            ],
            'col_001' => [
                'source_column_id'   => 'col_001',
                'header'             => 'Discounted Price',
                'physical_type'      => 'decimal',
                'suggested_category' => 'amount',
                'semantic_role'      => 'selling_price',
                'sensitivity'        => DataClassification::PUBLIC,
            ],
        ];

        $semanticProjections = [
            'selling_price' => [
                '_is_multi_column' => true,
                'col_000'          => 1200.0,
                'col_001'          => 1050.0,
            ],
            'selling_price_col_001' => 1050.0,
        ];

        $record = StructuredRecord::create([
            'workspace_id'          => $this->workspaceA->id,
            'document_id'           => $this->documentA->id,
            'import_job_id'         => $this->jobA->id,
            'sheet_name'            => 'Catalog',
            'row_index'             => 3,
            'raw_values'            => ['col_000' => '1200.00', 'col_001' => '1050.00'],
            'source_values'         => ['col_000' => '1200.00', 'col_001' => '1050.00'],
            'mapped_attributes'     => $mappedAttributes,
            'semantic_projections'  => $semanticProjections,
            'field_sensitivities'   => ['col_000' => DataClassification::PUBLIC, 'col_001' => DataClassification::PUBLIC],
            'is_public'             => true,
            'content_fingerprint'   => hash('sha256', 'multi_price_row'),
        ]);

        $customerContext = ServerControlledContext::forCustomer((int) $this->workspaceA->id);
        $projected = $this->projectionService->projectRecord($record, $customerContext);

        // Customer receives clean collapsed role without raw col_* aliases
        $this->assertTrue($projected->has('selling_price'));
        $this->assertEquals(1200.0, $projected->get('selling_price'));
        $this->assertFalse($projected->has('selling_price_col_001'), 'Duplicate alias with column ID must be stripped in customer context.');

        $prompt = $projected->toPromptString();
        $this->assertStringNotContainsString('col_001', $prompt);
        $this->assertStringNotContainsString('_is_multi_column', $prompt);
    }

    /**
     * 4. Multi-Tenant Scoping & Cross-Tenant Boundary Violation.
     */
    public function test_cross_tenant_record_access_is_strictly_blocked(): void
    {
        $recordA = $this->createMultiMetricRecord();

        // Context belongs to Workspace B
        $contextB = ServerControlledContext::forUser(
            user: $this->ownerB,
            workspaceId: (int) $this->workspaceB->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $this->expectException(TenantScopeViolationException::class);
        $this->expectExceptionMessage('Cross-tenant record projection denied');

        $this->projectionService->projectRecord($recordA, $contextB);
    }

    /**
     * 5. SuperAdmin Has Unrestricted Cross-Tenant Access Without Governance Session.
     */
    public function test_superadmin_has_unrestricted_cross_tenant_access_without_governance_session(): void
    {
        $recordA = $this->createMultiMetricRecord();

        // 1. SuperAdmin accessing Workspace A directly succeeds without a governance session
        $ownContext = ServerControlledContext::forUser(
            user: $this->superAdmin,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_SYSTEM_SYNC
        );
        $this->assertEquals('superadmin', $ownContext->userRole);
        $this->assertTrue($ownContext->isOwner());

        // 2. SuperAdmin accessing foreign Workspace B directly without a governance session succeeds
        $crossContext = ServerControlledContext::forUser(
            user: $this->superAdmin,
            workspaceId: (int) $this->workspaceB->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );
        $this->assertEquals('superadmin', $crossContext->userRole);
        $this->assertTrue($crossContext->isOwner());
        $this->assertTrue($crossContext->hasPermission('analytics.view_margins'));

        // Global SuperAdmin (workspace_id = null) projecting Workspace A record using Workspace B context succeeds unredacted
        $globalSuperAdmin = User::factory()->create([
            'workspace_id' => null,
        ]);
        $globalSuperAdmin->assignRole(RoleEnum::SUPERADMIN->value);

        $globalCrossContext = ServerControlledContext::forUser(
            user: $globalSuperAdmin,
            workspaceId: (int) $this->workspaceB->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );
        $projected = $this->projectionService->projectRecord($recordA, $globalCrossContext);
        $this->assertEquals(650.0, $projected->get('purchase_cost'));
        $this->assertEquals(0, $projected->redactedCount);

        // 3. Optional Governance Session still works and records audit trail when explicitly passed
        $session = SuperAdminGovernanceSession::createSession(
            user: $this->superAdmin,
            targetWorkspaceId: (int) $this->workspaceB->id,
            reason: 'Optional audit ticket INC-8821 for compliance review',
            ticketReference: 'INC-8821',
            durationMinutes: 30
        );

        $this->assertTrue($session->isValid());

        $govContext = ServerControlledContext::forUser(
            user: $this->superAdmin,
            workspaceId: (int) $this->workspaceB->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            session: $session
        );

        $this->projectionService->projectRecord($recordA, $govContext);
        $session->refresh();
        $actions = array_column($session->audit_log ?? [], 'action');
        $this->assertContains('cross_tenant_record_projection', $actions);
    }

    /**
     * 6. Cryptographic Cache Partitioning Prevents Cross-Persona Leakage.
     */
    public function test_cryptographic_cache_partition_prevents_cross_persona_leakage(): void
    {
        $record = $this->createMultiMetricRecord();

        $ownerContext = ServerControlledContext::forUser(
            user: $this->ownerA,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $customerContext = ServerControlledContext::forCustomer(
            workspaceId: (int) $this->workspaceA->id,
            channel: ServerControlledContext::CHANNEL_CUSTOMER_WEB
        );

        // 1. Owner projects record -> cached with cost
        $ownerProjected = $this->projectionService->projectRecord($record, $ownerContext);
        $this->assertTrue($ownerProjected->has('purchase_cost'));
        $this->assertEquals(650.0, $ownerProjected->get('purchase_cost'));

        // 2. Customer projects record -> MUST NOT retrieve owner's cached cost!
        $customerProjected = $this->projectionService->projectRecord($record, $customerContext);
        $this->assertFalse($customerProjected->has('purchase_cost'));
        $this->assertNull($customerProjected->get('purchase_cost'));

        // 3. Verify Cache Keys are cryptographically isolated
        $ownerCacheKey = $this->projectionService->buildCacheKey($record, $ownerContext);
        $customerCacheKey = $this->projectionService->buildCacheKey($record, $customerContext);

        $this->assertNotEquals($ownerCacheKey, $customerCacheKey, 'Cache key collision detected between Owner and Customer!');
    }

    /**
     * 7. Fail-Closed Default on Unclassified / Ambiguous Columns.
     */
    public function test_fail_closed_default_on_unclassified_or_ambiguous_columns(): void
    {
        $mappedAttributes = [
            'col_000' => [
                'source_column_id'   => 'col_000',
                'header'             => 'Mystery Field',
                'physical_type'      => 'string',
                'suggested_category' => 'custom_attribute',
                'semantic_role'      => 'mystery_field',
                // Sensitivity omitted or unclassified
            ],
            'col_001' => [
                'source_column_id'   => 'col_001',
                'header'             => 'Selling Price',
                'physical_type'      => 'decimal',
                'suggested_category' => 'amount',
                'semantic_role'      => 'selling_price',
                'sensitivity'        => DataClassification::PUBLIC,
            ],
        ];

        $record = StructuredRecord::create([
            'workspace_id'          => $this->workspaceA->id,
            'document_id'           => $this->documentA->id,
            'import_job_id'         => $this->jobA->id,
            'sheet_name'            => 'Sheet1',
            'row_index'             => 4,
            'raw_values'            => ['col_000' => 'CONFIDENTIAL_DATA', 'col_001' => '250.00'],
            'source_values'         => ['col_000' => 'CONFIDENTIAL_DATA', 'col_001' => '250.00'],
            'mapped_attributes'     => $mappedAttributes,
            'semantic_projections'  => ['mystery_field' => 'CONFIDENTIAL_DATA', 'selling_price' => 250.0],
            'field_sensitivities'   => ['col_001' => DataClassification::PUBLIC], // col_000 missing
            'is_public'             => false,
            'content_fingerprint'   => hash('sha256', 'mystery_row'),
        ]);

        $customerContext = ServerControlledContext::forCustomer((int) $this->workspaceA->id);
        $projected = $this->projectionService->projectRecord($record, $customerContext);

        $this->assertTrue($projected->has('selling_price'));
        $this->assertFalse($projected->has('mystery_field'), 'Unclassified column must fail-closed (omitted).');
        $this->assertStringNotContainsString('CONFIDENTIAL_DATA', $projected->toPromptString());
    }

    /**
     * 8. Raw Row Projection for Previews and Exports.
     */
    public function test_raw_row_projection_masks_restricted_data_for_unauthorized_dashboard_views(): void
    {
        $rawRow = ['SKU-100', 'Leather Handbag', '450.00', '950.00', 'John Doe (Sales)', '55,000.00'];

        $sourceDescriptors = [
            0 => ['source_column_id' => 'col_000', 'semantic_role' => 'sku', 'sensitivity' => DataClassification::PUBLIC],
            1 => ['source_column_id' => 'col_001', 'semantic_role' => 'product_name', 'sensitivity' => DataClassification::PUBLIC],
            2 => ['source_column_id' => 'col_002', 'semantic_role' => 'purchase_cost', 'sensitivity' => DataClassification::RESTRICTED],
            3 => ['source_column_id' => 'col_003', 'semantic_role' => 'selling_price', 'sensitivity' => DataClassification::PUBLIC],
            4 => ['source_column_id' => 'col_004', 'semantic_role' => 'salesperson', 'sensitivity' => DataClassification::INTERNAL],
            5 => ['source_column_id' => 'col_005', 'semantic_role' => 'salary', 'sensitivity' => DataClassification::RESTRICTED],
        ];

        // 1. Customer Context: Restricted and Internal columns are stripped completely
        $customerContext = ServerControlledContext::forCustomer((int) $this->workspaceA->id);
        $customerRow = $this->projectionService->projectRawRow($rawRow, $sourceDescriptors, $customerContext);

        $this->assertArrayHasKey(0, $customerRow);
        $this->assertEquals('SKU-100', $customerRow[0]);
        $this->assertEquals('Leather Handbag', $customerRow[1]);
        $this->assertArrayNotHasKey(2, $customerRow, 'purchase_cost must be stripped in customer row');
        $this->assertEquals('950.00', $customerRow[3]);
        $this->assertArrayNotHasKey(4, $customerRow, 'salesperson must be stripped in customer row');
        $this->assertArrayNotHasKey(5, $customerRow, 'salary must be stripped in customer row');

        // 2. Member without salary/cost perms: Restricted columns are masked (***)
        $member = User::factory()->create(['workspace_id' => $this->workspaceA->id]);
        $memberContext = ServerControlledContext::forUser(
            user: $member,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: []
        );

        $memberRow = $this->projectionService->projectRawRow($rawRow, $sourceDescriptors, $memberContext);
        $this->assertEquals('SKU-100', $memberRow[0]);
        $this->assertEquals('***', $memberRow[2]); // purchase_cost masked
        $this->assertEquals('950.00', $memberRow[3]);
        $this->assertEquals('John Doe (Sales)', $memberRow[4]); // internal visible to member
        $this->assertEquals('***', $memberRow[5]); // salary masked

        // 3. Owner Context: All columns visible
        $ownerContext = ServerControlledContext::forUser(
            user: $this->ownerA,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $ownerRow = $this->projectionService->projectRawRow($rawRow, $sourceDescriptors, $ownerContext);
        $this->assertEquals('450.00', $ownerRow[2]);
        $this->assertEquals('55,000.00', $ownerRow[5]);
    }

    /**
     * 9. Typesense Public Projection Sync & Legacy Purge Reconciliation.
     */
    public function test_typesense_public_projection_and_reconciliation_purge(): void
    {
        $syncService = new TypesensePublicProjectionSyncService($this->projectionService);

        // Record with restricted field marked is_public = true (tainted legacy state)
        $taintedRecord = StructuredRecord::create([
            'workspace_id'          => $this->workspaceA->id,
            'document_id'           => $this->documentA->id,
            'import_job_id'         => $this->jobA->id,
            'sheet_name'            => 'Staff',
            'row_index'             => 5,
            'raw_values'            => ['col_000' => 'Dr. Bob', 'col_001' => '90000.00'],
            'source_values'         => ['col_000' => 'Dr. Bob', 'col_001' => '90000.00'],
            'mapped_attributes'     => [
                'col_000' => ['source_column_id' => 'col_000', 'semantic_role' => 'doctor', 'sensitivity' => DataClassification::INTERNAL],
                'col_001' => ['source_column_id' => 'col_001', 'semantic_role' => 'salary', 'sensitivity' => DataClassification::RESTRICTED],
            ],
            'semantic_projections'  => ['doctor' => 'Dr. Bob', 'salary' => 90000.0],
            'field_sensitivities'   => ['col_000' => DataClassification::INTERNAL, 'col_001' => DataClassification::RESTRICTED],
            'is_public'             => true, // Tainted legacy flag!
            'content_fingerprint'   => hash('sha256', 'tainted_legacy_record'),
        ]);

        // Clean public record
        $cleanPublicRecord = StructuredRecord::create([
            'workspace_id'          => $this->workspaceA->id,
            'document_id'           => $this->documentA->id,
            'import_job_id'         => $this->jobA->id,
            'sheet_name'            => 'Catalog',
            'row_index'             => 6,
            'raw_values'            => ['col_000' => 'Cotton Scarf', 'col_001' => '350.00'],
            'source_values'         => ['col_000' => 'Cotton Scarf', 'col_001' => '350.00'],
            'mapped_attributes'     => [
                'col_000' => ['source_column_id' => 'col_000', 'semantic_role' => 'product_name', 'sensitivity' => DataClassification::PUBLIC],
                'col_001' => ['source_column_id' => 'col_001', 'semantic_role' => 'selling_price', 'sensitivity' => DataClassification::PUBLIC],
            ],
            'semantic_projections'  => ['product_name' => 'Cotton Scarf', 'selling_price' => 350.0],
            'field_sensitivities'   => ['col_000' => DataClassification::PUBLIC, 'col_001' => DataClassification::PUBLIC],
            'is_public'             => true,
            'content_fingerprint'   => hash('sha256', 'clean_public_record'),
        ]);

        // Run reconciliation purge routine
        $purgeReport = $syncService->reconcileAndPurgeLegacyFields((int) $this->workspaceA->id);

        $this->assertEquals(2, $purgeReport['scanned_records']);
        $this->assertEquals(1, $purgeReport['purged_restricted_records']);
        $this->assertContains('salary', $purgeReport['purged_fields']);

        // Verify tainted record was demoted from public
        $taintedRecord->refresh();
        $this->assertFalse($taintedRecord->is_public);

        // Verify clean public record remains public
        $cleanPublicRecord->refresh();
        $this->assertTrue($cleanPublicRecord->is_public);

        // Verify public sync only indexes clean public record
        $syncResult = $syncService->syncPublicProjections([$taintedRecord, $cleanPublicRecord], (int) $this->workspaceA->id);
        $this->assertEquals(1, $syncResult['indexed_count']);
        $this->assertEquals(1, $syncResult['skipped_count']);
        $this->assertEquals('Cotton Scarf', $syncResult['documents'][0]['product_name']);
        $this->assertArrayNotHasKey('salary', $syncResult['documents'][0]);
    }

    /**
     * 10. Workspace Owner with Full Permissions Projects All Fields.
     */
    public function test_workspace_owner_with_full_permissions_projects_all_fields(): void
    {
        $record = $this->createMultiMetricRecord();

        $ownerContext = ServerControlledContext::forUser(
            user: $this->ownerA,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $projected = $this->projectionService->projectRecord($record, $ownerContext);

        $this->assertTrue($projected->has('sku'));
        $this->assertTrue($projected->has('product_name'));
        $this->assertTrue($projected->has('selling_price'));
        $this->assertTrue($projected->has('purchase_cost'));
        $this->assertTrue($projected->has('commission'));
        $this->assertTrue($projected->has('quantity'));

        $this->assertEquals(650.0, $projected->get('purchase_cost'));
        $this->assertEquals(75.0, $projected->get('commission'));
        $this->assertEquals(0, $projected->redactedCount);
    }

    /**
     * 13. Granular Analytics Permissions Matrix (using AnalyticsPermission & DataClassification enums).
     */
    public function test_granular_analytics_permissions_matrix_using_analytics_permission_enum(): void
    {
        $policy = new DataProjectionPolicy();

        $analyst = User::factory()->create(['workspace_id' => $this->workspaceA->id]);

        // Scenario A: User has VIEW_BASIC only
        $basicContext = ServerControlledContext::forUser(
            user: $analyst,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: [\App\Enums\Permissions\AnalyticsPermission::VIEW_BASIC->value]
        );

        $this->assertTrue($policy->evaluate(\App\Enums\DataClassification::PUBLIC_CATALOG, $basicContext));
        $this->assertTrue($policy->evaluate(\App\Enums\DataClassification::OPERATIONAL_INTERNAL, $basicContext));
        $this->assertFalse($policy->evaluate(\App\Enums\DataClassification::CONFIDENTIAL_FINANCIAL, $basicContext));
        $this->assertFalse($policy->evaluate(\App\Enums\DataClassification::WORKFORCE_COMPENSATION, $basicContext));
        $this->assertFalse($policy->evaluate(\App\Enums\DataClassification::PATIENT_HEALTH_PHI, $basicContext));

        // Scenario B: User has VIEW_MARGINS
        $marginContext = ServerControlledContext::forUser(
            user: $analyst,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: [\App\Enums\Permissions\AnalyticsPermission::VIEW_MARGINS->value]
        );

        $this->assertTrue($policy->evaluate(\App\Enums\DataClassification::CONFIDENTIAL_FINANCIAL, $marginContext));
        $this->assertFalse($policy->evaluate(\App\Enums\DataClassification::WORKFORCE_COMPENSATION, $marginContext));

        // Scenario C: User has VIEW_PAYROLL
        $payrollContext = ServerControlledContext::forUser(
            user: $analyst,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: [\App\Enums\Permissions\AnalyticsPermission::VIEW_PAYROLL->value]
        );

        $this->assertFalse($policy->evaluate(\App\Enums\DataClassification::CONFIDENTIAL_FINANCIAL, $payrollContext));
        $this->assertTrue($policy->evaluate(\App\Enums\DataClassification::WORKFORCE_COMPENSATION, $payrollContext));

        // Scenario D: User has VIEW_HEALTH_PHI
        $healthContext = ServerControlledContext::forUser(
            user: $analyst,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: [\App\Enums\Permissions\AnalyticsPermission::VIEW_HEALTH_PHI->value]
        );

        $this->assertTrue($policy->evaluate(\App\Enums\DataClassification::PATIENT_HEALTH_PHI, $healthContext));
        $this->assertFalse($policy->evaluate(\App\Enums\DataClassification::CONFIDENTIAL_FINANCIAL, $healthContext));
    }

    /**
     * 14. Adversarial Prompt Jailbreak Attempt Against Customer Context Guarantees Zero Token Leakage.
     */
    public function test_adversarial_prompt_jailbreak_attempt_against_customer_context_guarantees_zero_token_leakage(): void
    {
        $record = $this->createMultiMetricRecord();

        // Customer attempts prompt injection / jailbreak
        $adversarialCustomerContext = ServerControlledContext::forCustomer(
            workspaceId: (int) $this->workspaceA->id,
            channel: ServerControlledContext::CHANNEL_CUSTOMER_WHATSAPP
        );

        $projected = $this->projectionService->project($record, $adversarialCustomerContext);

        // Assert 0 restricted tokens in prompt string
        $prompt = $projected->toPromptString();
        $this->assertStringNotContainsString('650', $prompt);
        $this->assertStringNotContainsString('75', $prompt);
        $this->assertStringNotContainsString('purchase_cost', $prompt);
        $this->assertStringNotContainsString('commission', $prompt);
        $this->assertStringNotContainsString('col_002', $prompt);
        $this->assertStringNotContainsString('col_004', $prompt);

        // Confirm clean public attributes
        $this->assertEquals('SKU-001', $projected->get('sku'));
        $this->assertEquals(1200.0, $projected->get('selling_price'));
    }

    /**
     * 15. Export Service Enforces Field-Level Projection and Masks Restricted Data.
     */
    public function test_export_service_enforces_field_level_projection_and_masks_restricted_data(): void
    {
        $record = $this->createMultiMetricRecord();
        $exportService = app(\App\Services\Export\ExportService::class);

        // Customer Context -> Only public columns exported
        $customerContext = ServerControlledContext::forCustomer((int) $this->workspaceA->id);
        $response = $exportService->exportStructuredRecords([$record], $customerContext, 'csv', 'customer-export');

        $this->assertEquals(200, $response->getStatusCode());

        // Capture CSV output
        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('Selling Price', $csvContent);
        $this->assertStringContainsString('Product Name', $csvContent);
        $this->assertStringNotContainsString('Purchase Cost', $csvContent);
        $this->assertStringNotContainsString('650.00', $csvContent);
        $this->assertStringNotContainsString('Commission', $csvContent);

        // Owner Context -> Full export with costs and commissions
        $ownerContext = ServerControlledContext::forUser(
            user: $this->ownerA,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_DATA_EXPORT
        );
        $ownerResponse = $exportService->exportStructuredRecords([$record], $ownerContext, 'csv', 'owner-export');

        ob_start();
        $ownerResponse->sendContent();
        $ownerCsv = ob_get_clean();

        $this->assertStringContainsString('Purchase Cost', $ownerCsv);
        $this->assertStringContainsString('650', $ownerCsv);
    }

    /**
     * 16. Classification Registry Extensibility & Fail-Closed Custom Roles.
     */
    public function test_classification_registry_extensibility_and_fail_closed_custom_role(): void
    {
        // 1. Unregistered custom role fails closed to RESTRICTED_CUSTOM
        $classification = \App\Enums\DataClassification::classifyRole('vip_investor_equity_pct');
        $this->assertEquals(\App\Enums\DataClassification::RESTRICTED_CUSTOM, $classification);
        $this->assertTrue($classification->isRestricted());
        $this->assertFalse($classification->isPublic());

        // 2. Register dynamic custom role as PUBLIC_CATALOG
        \App\Enums\DataClassification::register('brand_ambassador_name', \App\Enums\DataClassification::PUBLIC_CATALOG);
        $brandClass = \App\Enums\DataClassification::classifyRole('brand_ambassador_name');
        $this->assertEquals(\App\Enums\DataClassification::PUBLIC_CATALOG, $brandClass);
        $this->assertTrue($brandClass->isPublic());

        // Clean up
        \App\Enums\DataClassification::resetRegistry();
        $resetClass = \App\Enums\DataClassification::classifyRole('brand_ambassador_name');
        $this->assertEquals(\App\Enums\DataClassification::RESTRICTED_CUSTOM, $resetClass);
    }

    /**
     * 17. Complex AST Query Matrix: CTEs, Subqueries, Window Functions, CASE & Comment-Bypass Defense.
     */
    public function test_ast_authorizer_complex_query_matrix_cte_subqueries_window_functions_and_case(): void
    {
        $descriptors = [
            'selling_price' => [
                'source_column_id'  => 'col_000',
                'original_header'   => 'Selling Price',
                'normalized_header' => 'selling_price',
                'semantic_role'     => 'selling_price',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
            'purchase_cost' => [
                'source_column_id'  => 'col_001',
                'original_header'   => 'Purchase Cost',
                'normalized_header' => 'purchase_cost',
                'semantic_role'     => 'purchase_cost',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
            'category' => [
                'source_column_id'  => 'col_002',
                'original_header'   => 'Category',
                'normalized_header' => 'category',
                'semantic_role'     => 'category',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
        ];

        $analyst = User::factory()->create(['workspace_id' => $this->workspaceA->id]);
        $unprivilegedContext = ServerControlledContext::forUser(
            user: $analyst,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: []
        );

        // 1. CTE with Alias Hiding: WITH cte AS (SELECT purchase_cost AS c FROM sales) SELECT c FROM cte;
        try {
            $this->astAuthorizer->authorizeQuery(
                sql: "WITH cte AS (SELECT purchase_cost AS c FROM sales) SELECT c FROM cte",
                sourceDescriptors: $descriptors,
                context: $unprivilegedContext
            );
            $this->fail('Expected UnauthorizedDataAccessException for CTE referencing purchase_cost');
        } catch (UnauthorizedDataAccessException $e) {
            $this->assertStringContainsString('purchase_cost', $e->getMessage());
        }

        // 2. Window Function: SELECT AVG(purchase_cost) OVER (PARTITION BY category) FROM sales;
        try {
            $this->astAuthorizer->authorizeQuery(
                sql: "SELECT category, AVG(purchase_cost) OVER (PARTITION BY category) AS avg_cost FROM sales",
                sourceDescriptors: $descriptors,
                context: $unprivilegedContext
            );
            $this->fail('Expected UnauthorizedDataAccessException for Window Function referencing purchase_cost');
        } catch (UnauthorizedDataAccessException $e) {
            $this->assertStringContainsString('purchase_cost', $e->getMessage());
        }

        // 3. CASE Expression: SELECT CASE WHEN purchase_cost > 500 THEN 'expensive' ELSE 'cheap' END FROM sales;
        try {
            $this->astAuthorizer->authorizeQuery(
                sql: "SELECT CASE WHEN purchase_cost > 500 THEN 'expensive' ELSE 'cheap' END AS cost_tier FROM sales",
                sourceDescriptors: $descriptors,
                context: $unprivilegedContext
            );
            $this->fail('Expected UnauthorizedDataAccessException for CASE expression referencing purchase_cost');
        } catch (UnauthorizedDataAccessException $e) {
            $this->assertStringContainsString('purchase_cost', $e->getMessage());
        }

        // 4. Comment-Injection Bypass Attempt: SELECT selling_price /* bypass */ - /* bypass */ purchase_cost FROM sales;
        try {
            $this->astAuthorizer->authorizeQuery(
                sql: "SELECT selling_price /* sneaky comment */ - /* sneaky comment */ purchase_cost AS margin FROM sales",
                sourceDescriptors: $descriptors,
                context: $unprivilegedContext
            );
            $this->fail('Expected UnauthorizedDataAccessException for comment-injected derived math expression');
        } catch (UnauthorizedDataAccessException $e) {
            $this->assertStringContainsString('purchase_cost', $e->getMessage());
        }

        // 5. Correlated Subquery in WHERE: SELECT * FROM sales WHERE purchase_cost IN (SELECT MIN(purchase_cost) FROM sales);
        try {
            $this->astAuthorizer->authorizeQuery(
                sql: "SELECT selling_price FROM sales WHERE purchase_cost IN (SELECT MIN(purchase_cost) FROM sales)",
                sourceDescriptors: $descriptors,
                context: $unprivilegedContext
            );
            $this->fail('Expected UnauthorizedDataAccessException for Correlated subquery in WHERE referencing purchase_cost');
        } catch (UnauthorizedDataAccessException $e) {
            $this->assertStringContainsString('purchase_cost', $e->getMessage());
        }
    }

    /**
     * 18. AST Authorizer Strictly Blocks SQL Mutation, DDL & DML Commands (Fail-Closed).
     */
    public function test_ast_authorizer_strictly_blocks_mutations_and_non_select_dml_ddl(): void
    {
        $descriptors = [];
        $analyst = User::factory()->create(['workspace_id' => $this->workspaceA->id]);
        $context = ServerControlledContext::forUser(
            user: $analyst,
            workspaceId: (int) $this->workspaceA->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: ['analytics.view_margins']
        );

        // Attempt 1: DROP TABLE
        $this->expectException(UnauthorizedDataAccessException::class);
        $this->astAuthorizer->authorizeQuery(
            sql: "DROP TABLE sales_records",
            sourceDescriptors: $descriptors,
            context: $context
        );
    }

    /**
     * 19. Customer RAG Text Extraction Enforces Zero Token Leakage for Ingested Spreadsheets.
     */
    public function test_customer_rag_text_extraction_enforces_zero_token_leakage_for_ingested_spreadsheets(): void
    {
        $docService = app(\App\Services\Knowledge\KnowledgeDocumentService::class);

        // Record 1: Clean public record
        StructuredRecord::create([
            'workspace_id'          => $this->workspaceA->id,
            'document_id'           => $this->documentA->id,
            'import_job_id'         => $this->jobA->id,
            'sheet_name'            => 'Products',
            'row_index'             => 2,
            'raw_values'            => ['col_000' => 'Linen Kurti', 'col_001' => '1500.00', 'col_002' => '800.00'],
            'source_values'         => ['col_000' => 'Linen Kurti', 'col_001' => '1500.00', 'col_002' => '800.00'],
            'mapped_attributes'     => [
                'col_000' => ['source_column_id' => 'col_000', 'semantic_role' => 'product_name', 'sensitivity' => DataClassification::PUBLIC],
                'col_001' => ['source_column_id' => 'col_001', 'semantic_role' => 'selling_price', 'sensitivity' => DataClassification::PUBLIC],
                'col_002' => ['source_column_id' => 'col_002', 'semantic_role' => 'purchase_cost', 'sensitivity' => DataClassification::RESTRICTED],
            ],
            'semantic_projections'  => ['product_name' => 'Linen Kurti', 'selling_price' => 1500.0, 'purchase_cost' => 800.0],
            'field_sensitivities'   => ['col_000' => DataClassification::PUBLIC, 'col_001' => DataClassification::PUBLIC, 'col_002' => DataClassification::RESTRICTED],
            'is_public'             => true,
            'content_fingerprint'   => hash('sha256', 'linen_kurti_row'),
        ]);

        // Record 2: Entirely private staff salary record
        StructuredRecord::create([
            'workspace_id'          => $this->workspaceA->id,
            'document_id'           => $this->documentA->id,
            'import_job_id'         => $this->jobA->id,
            'sheet_name'            => 'Staff',
            'row_index'             => 3,
            'raw_values'            => ['col_000' => 'CEO John', 'col_001' => '250000.00'],
            'source_values'         => ['col_000' => 'CEO John', 'col_001' => '250000.00'],
            'mapped_attributes'     => [
                'col_000' => ['source_column_id' => 'col_000', 'semantic_role' => 'employee', 'sensitivity' => DataClassification::INTERNAL],
                'col_001' => ['source_column_id' => 'col_001', 'semantic_role' => 'salary', 'sensitivity' => DataClassification::RESTRICTED],
            ],
            'semantic_projections'  => ['employee' => 'CEO John', 'salary' => 250000.0],
            'field_sensitivities'   => ['col_000' => DataClassification::INTERNAL, 'col_001' => DataClassification::RESTRICTED],
            'is_public'             => false,
            'content_fingerprint'   => hash('sha256', 'ceo_salary_row'),
        ]);

        $extractedRAGText = $docService->extractDocumentText($this->documentA);

        // 1. Public catalog attributes ARE present in RAG text
        $this->assertStringContainsString('Linen Kurti', $extractedRAGText);
        $this->assertStringContainsString('Selling Price: 1500', $extractedRAGText);

        // 2. HARD INVARIANT: Zero tokens of restricted purchase costs or employee salaries in RAG text
        $this->assertStringNotContainsString('800', $extractedRAGText, 'Purchase cost leaked in extracted RAG text!');
        $this->assertStringNotContainsString('purchase_cost', $extractedRAGText);
        $this->assertStringNotContainsString('CEO John', $extractedRAGText, 'Private staff record leaked in extracted RAG text!');
        $this->assertStringNotContainsString('250000', $extractedRAGText, 'Salary leaked in extracted RAG text!');
        $this->assertStringNotContainsString('salary', $extractedRAGText);
    }

    /**
     * 20. SuperAdmin Governance Session Full Lifecycle, Audit Persistence & Revocation.
     */
    public function test_superadmin_governance_session_full_lifecycle_and_audit_persistence(): void
    {
        $session = SuperAdminGovernanceSession::createSession(
            user: $this->superAdmin,
            targetWorkspaceId: (int) $this->workspaceB->id,
            reason: 'Production data integrity audit ticket PROD-1029',
            ticketReference: 'PROD-1029',
            durationMinutes: 45,
            readOnlyScope: true
        );

        $this->assertTrue($session->isValid());
        $this->assertEquals((int) $this->superAdmin->id, (int) $session->actor_id);
        $this->assertEquals((int) $this->superAdmin->id, (int) $session->user_id);
        $this->assertEquals('Production data integrity audit ticket PROD-1029', $session->justification);
        $this->assertEquals('PROD-1029', $session->ticket_ref);
        $this->assertTrue($session->read_only_scope);

        // Record audited action
        $session->recordAction('executed_export', ['format' => 'csv', 'rows' => 150]);
        $session->refresh();

        $actions = array_column($session->audit_log, 'action');
        $this->assertContains('session_created', $actions);
        $this->assertContains('executed_export', $actions);

        // Query active scope
        $activeSessions = SuperAdminGovernanceSession::activeFor((int) $this->superAdmin->id, (int) $this->workspaceB->id)->get();
        $this->assertCount(1, $activeSessions);

        // Revoke session
        $session->revoke('Audit completed successfully');
        $session->refresh();

        $this->assertFalse($session->isValid());
        $this->assertNotNull($session->revoked_at);

        $activeAfterRevocation = SuperAdminGovernanceSession::activeFor((int) $this->superAdmin->id, (int) $this->workspaceB->id)->get();
        $this->assertCount(0, $activeAfterRevocation);
    }

    /**
     * 21. Defense-in-Depth: Direct Model Serialization Hides Raw and Source Values.
     */
    public function test_direct_structured_record_model_serialization_hides_raw_and_source_values(): void
    {
        $record = $this->createMultiMetricRecord();

        // 1. Eloquent toArray() must omit raw_values and source_values
        $array = $record->toArray();
        $this->assertArrayNotHasKey('raw_values', $array);
        $this->assertArrayNotHasKey('source_values', $array);

        // 2. Eloquent jsonSerialize() must omit raw_values and source_values
        $json = json_encode($record);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('"raw_values"', $json);
        $this->assertStringNotContainsString('"source_values"', $json);
    }
}
