<?php

declare(strict_types=1);

namespace Tests\Feature\Spreadsheet;

use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\AI\Orchestration\CustomerSupportService;
use App\AI\Orchestration\OwnerBusinessAssistant;
use App\Services\Spreadsheet\DTOs\SourceColumnDescriptor;
use App\Services\Spreadsheet\Relationships\CandidateKeyProfiler;
use App\Services\Spreadsheet\Relationships\CardinalityInferenceEngine;
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
use App\Services\Spreadsheet\Relationships\DTOs\CandidateKeyDescriptor;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;
use App\Services\Spreadsheet\Relationships\DTOs\SpreadsheetRelationshipGraph;
use App\Services\Spreadsheet\Relationships\InclusionDependencyAnalyzer;
use App\Services\Spreadsheet\Security\Exceptions\TenantScopeViolationException;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrossSheetRelationshipDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $ownerA;
    protected User $ownerB;
    protected KnowledgeDocument $documentA;
    protected SpreadsheetImportJob $jobA;
    protected CrossSheetRelationshipService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->workspaceA = Workspace::create(['name' => 'Acme Corp', 'slug' => 'acme-corp']);
        $this->workspaceB = Workspace::create(['name' => 'Beta Global', 'slug' => 'beta-global']);

        $this->ownerA = User::factory()->create(['workspace_id' => $this->workspaceA->id]);
        $this->ownerA->assignRole(RoleEnum::ADMIN->value);
        $this->ownerA->givePermissionTo(KnowledgeDocumentPermission::VIEW->value);
        $this->ownerA->givePermissionTo(KnowledgeDocumentPermission::UPDATE->value);

        $this->ownerB = User::factory()->create(['workspace_id' => $this->workspaceB->id]);
        $this->ownerB->assignRole(RoleEnum::ADMIN->value);
        $this->ownerB->givePermissionTo(KnowledgeDocumentPermission::VIEW->value);
        $this->ownerB->givePermissionTo(KnowledgeDocumentPermission::UPDATE->value);

        $this->documentA = KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Enterprise Multi-Sheet Workbook',
            'original_filename' => 'enterprise_data.xlsx',
            'file_path'         => 'documents/test.xlsx',
            'content_hash'      => hash('sha256', 'mock_enterprise_workbook'),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'purpose'           => \App\Enums\IngestionPurpose::DATA_ANALYTICS,
        ]);

        $this->jobA = SpreadsheetImportJob::create([
            'workspace_id'     => $this->workspaceA->id,
            'document_id'      => $this->documentA->id,
            'status'           => 'completed',
            'conflict_mode'    => 'skip',
            'generation_id'    => (string) Str::uuid(),
            'schema_version'   => '2.3.0',
            'header_row_index' => 0,
            'total_rows'       => 100,
            'processed_rows'   => 100,
            'staged_rows'      => 100,
            'failed_rows'      => 0,
            'checkpoint_row'   => 100,
            'detected_schema'  => [],
        ]);

        $this->service = new CrossSheetRelationshipService();
    }

    /**
     * 1. Multi-Sheet E-Commerce: Customers <-> Orders <-> Order_Items <-> Products + Junction Detection.
     */
    public function test_e_commerce_multi_sheet_relationship_discovery_and_junction_table_detection(): void
    {
        $multiSheetData = [
            'Customers' => [
                'entity_type' => 'customer',
                'descriptors' => [
                    'col_000' => ['original_header' => 'customer_id', 'physical_type' => 'integer', 'semantic_role' => 'identifier'],
                    'col_001' => ['original_header' => 'customer_name', 'physical_type' => 'string', 'semantic_role' => 'entity_name'],
                ],
                'rows' => [
                    ['col_000' => 101, 'col_001' => 'Alice'],
                    ['col_000' => 102, 'col_001' => 'Bob'],
                    ['col_000' => 103, 'col_001' => 'Charlie'],
                ],
            ],
            'Orders' => [
                'entity_type' => 'order',
                'descriptors' => [
                    'col_000' => ['original_header' => 'order_id', 'physical_type' => 'string', 'semantic_role' => 'identifier'],
                    'col_001' => ['original_header' => 'customer_id', 'physical_type' => 'integer', 'semantic_role' => 'identifier'],
                    'col_002' => ['original_header' => 'order_date', 'physical_type' => 'date', 'semantic_role' => 'date_time'],
                ],
                'rows' => [
                    ['col_000' => 'ORD-001', 'col_001' => 101, 'col_002' => '2026-10-01'],
                    ['col_000' => 'ORD-002', 'col_001' => 101, 'col_002' => '2026-10-02'],
                    ['col_000' => 'ORD-003', 'col_001' => 102, 'col_002' => '2026-10-03'],
                ],
            ],
            'Products' => [
                'entity_type' => 'product',
                'descriptors' => [
                    'col_000' => ['original_header' => 'sku_code', 'physical_type' => 'string', 'semantic_role' => 'identifier'],
                    'col_001' => ['original_header' => 'product_name', 'physical_type' => 'string', 'semantic_role' => 'entity_name'],
                ],
                'rows' => [
                    ['col_000' => 'PROD-A', 'col_001' => 'Wireless Mouse'],
                    ['col_000' => 'PROD-B', 'col_001' => 'Mechanical Keyboard'],
                ],
            ],
            'Order_Items' => [
                'entity_type' => 'order_item',
                'descriptors' => [
                    'col_000' => ['original_header' => 'order_id', 'physical_type' => 'string', 'semantic_role' => 'identifier'],
                    'col_001' => ['original_header' => 'product_sku', 'physical_type' => 'string', 'semantic_role' => 'identifier'],
                    'col_002' => ['original_header' => 'quantity', 'physical_type' => 'integer', 'semantic_role' => 'quantity'],
                ],
                'rows' => [
                    ['col_000' => 'ORD-001', 'col_001' => 'PROD-A', 'col_002' => 2],
                    ['col_000' => 'ORD-001', 'col_001' => 'PROD-B', 'col_002' => 1],
                    ['col_000' => 'ORD-002', 'col_001' => 'PROD-A', 'col_002' => 5],
                ],
            ],
        ];

        $graph = $this->service->discoverRelationships($this->jobA, $multiSheetData);

        $this->assertInstanceOf(SpreadsheetRelationshipGraph::class, $graph);
        $this->assertEquals('2.3.0', $graph->graphVersion);
        $this->assertCount(4, $graph->nodes);

        // Verify Orders -> Customers (1:N)
        $ordersToCustomers = null;
        foreach ($graph->edges as $edge) {
            if ($edge->sourceSheet === 'Orders' && $edge->targetSheet === 'Customers') {
                $ordersToCustomers = $edge;
                break;
            }
        }
        $this->assertNotNull($ordersToCustomers);
        $this->assertEquals(DiscoveredRelationshipEdge::CARDINALITY_ONE_TO_MANY, $ordersToCustomers->cardinality);
        $this->assertEquals(1.0, $ordersToCustomers->inclusionRatio);
        $this->assertGreaterThanOrEqual(0.85, $ordersToCustomers->confidence);

        // Verify Order_Items is identified as N:M Junction Table
        $junctionEdges = array_filter($graph->edges, fn($e) => $e->sourceSheet === 'Order_Items');
        $this->assertNotEmpty($junctionEdges);
        foreach ($junctionEdges as $jEdge) {
            $this->assertTrue($jEdge->isJunctionTable);
            $this->assertEquals(DiscoveredRelationshipEdge::CARDINALITY_MANY_TO_MANY, $jEdge->cardinality);
        }
    }

    /**
     * 2. Healthcare Multi-Sheet: Doctors <-> Patients <-> Appointments without hardcoded terms.
     */
    public function test_healthcare_clinic_multi_sheet_relationship_discovery(): void
    {
        $multiSheetData = [
            'Provider_Roster' => [
                'entity_type' => 'doctor',
                'descriptors' => [
                    'col_000' => ['original_header' => 'doc_ref_no', 'physical_type' => 'string', 'semantic_role' => 'identifier'],
                    'col_001' => ['original_header' => 'specialist_name', 'physical_type' => 'string', 'semantic_role' => 'entity_name'],
                ],
                'rows' => [
                    ['col_000' => 'DOC-901', 'col_001' => 'Dr. Samantha Carter'],
                    ['col_000' => 'DOC-902', 'col_001' => 'Dr. Daniel Jackson'],
                ],
            ],
            'Clinical_Appointments' => [
                'entity_type' => 'appointment',
                'descriptors' => [
                    'col_000' => ['original_header' => 'appt_id', 'physical_type' => 'integer', 'semantic_role' => 'identifier'],
                    'col_001' => ['original_header' => 'attending_doc_ref', 'physical_type' => 'string', 'semantic_role' => 'identifier'],
                    'col_002' => ['original_header' => 'consult_fee', 'physical_type' => 'decimal', 'semantic_role' => 'amount'],
                ],
                'rows' => [
                    ['col_000' => 1, 'col_001' => 'DOC-901', 'col_002' => 2000.00],
                    ['col_000' => 2, 'col_001' => 'DOC-901', 'col_002' => 1500.00],
                    ['col_000' => 3, 'col_001' => 'DOC-902', 'col_002' => 2500.00],
                ],
            ],
        ];

        $graph = $this->service->discoverRelationships($this->jobA, $multiSheetData);

        $this->assertNotEmpty($graph->edges);
        $edges = array_values($graph->edges);
        $apptToDoc = $edges[0];
        $this->assertEquals('Clinical_Appointments', $apptToDoc->sourceSheet);
        $this->assertEquals('Provider_Roster', $apptToDoc->targetSheet);
        $this->assertEquals(1.0, $apptToDoc->inclusionRatio);
        $this->assertEquals(DiscoveredRelationshipEdge::CARDINALITY_ONE_TO_MANY, $apptToDoc->cardinality);
    }

    /**
     * 3. Hospitality & NGO Multi-Sheet Discovery.
     */
    public function test_hospitality_and_ngo_donation_multi_sheet_discovery(): void
    {
        // NGO Donation fixture
        $ngoData = [
            'Campaigns' => [
                'entity_type' => 'campaign',
                'descriptors' => [
                    'col_000' => ['original_header' => 'campaign_code', 'physical_type' => 'string', 'semantic_role' => 'identifier'],
                    'col_001' => ['original_header' => 'target_fund', 'physical_type' => 'decimal', 'semantic_role' => 'amount'],
                ],
                'rows' => [
                    ['col_000' => 'CMP-WINTER', 'col_001' => 500000.00],
                    ['col_000' => 'CMP-FLOOD', 'col_001' => 1000000.00],
                ],
            ],
            'Donation_Ledger' => [
                'entity_type' => 'donation',
                'descriptors' => [
                    'col_000' => ['original_header' => 'receipt_no', 'physical_type' => 'integer', 'semantic_role' => 'identifier'],
                    'col_001' => ['original_header' => 'campaign_ref', 'physical_type' => 'string', 'semantic_role' => 'identifier'],
                    'col_002' => ['original_header' => 'donated_amount', 'physical_type' => 'decimal', 'semantic_role' => 'amount'],
                ],
                'rows' => [
                    ['col_000' => 501, 'col_001' => 'CMP-WINTER', 'col_002' => 5000.00],
                    ['col_000' => 502, 'col_001' => 'CMP-WINTER', 'col_002' => 2000.00],
                    ['col_000' => 503, 'col_001' => 'CMP-FLOOD', 'col_002' => 15000.00],
                ],
            ],
        ];

        $graph = $this->service->discoverRelationships($this->jobA, $ngoData);
        $this->assertNotEmpty($graph->edges);
        $edges = array_values($graph->edges);
        $edge = $edges[0];
        $this->assertEquals('Donation_Ledger', $edge->sourceSheet);
        $this->assertEquals('Campaigns', $edge->targetSheet);
        $this->assertEquals(1.0, $edge->inclusionRatio);
    }

    /**
     * 4. Unknown Abstract Business Domain Discovery (Pure Mathematics).
     */
    public function test_unknown_domain_multi_sheet_discovery(): void
    {
        $unknownData = [
            'TableAlpha' => [
                'descriptors' => [
                    'col_x' => ['original_header' => 'alpha_key_x', 'physical_type' => 'string'],
                    'col_y' => ['original_header' => 'metric_val', 'physical_type' => 'decimal'],
                ],
                'rows' => [
                    ['col_x' => 'TX-001', 'col_y' => 10.5],
                    ['col_x' => 'TX-002', 'col_y' => 20.5],
                    ['col_x' => 'TX-003', 'col_y' => 30.5],
                ],
            ],
            'TableBeta' => [
                'descriptors' => [
                    'col_a' => ['original_header' => 'beta_row_id', 'physical_type' => 'integer'],
                    'col_b' => ['original_header' => 'pointer_to_alpha', 'physical_type' => 'string'],
                ],
                'rows' => [
                    ['col_a' => 1, 'col_b' => 'TX-001'],
                    ['col_a' => 2, 'col_b' => 'TX-002'],
                    ['col_a' => 3, 'col_b' => 'TX-001'],
                ],
            ],
        ];

        $graph = $this->service->discoverRelationships($this->jobA, $unknownData);
        $this->assertNotEmpty($graph->edges);
        $edges = array_values($graph->edges);
        $edge = $edges[0];
        $this->assertEquals('TableBeta', $edge->sourceSheet);
        $this->assertEquals('TableAlpha', $edge->targetSheet);
        $this->assertEquals(1.0, $edge->inclusionRatio);
    }

    /**
     * 5. Mathematical Candidate Key Profiler: Null Ratio, Uniqueness, Empty Strings & Composite Keys.
     */
    public function test_candidate_key_profiler_mathematical_constraints_and_composite_keys(): void
    {
        $profiler = new CandidateKeyProfiler();

        // Single Key with nulls fails
        $rowsWithNulls = [
            ['col_000' => 'ID-1', 'col_001' => 'A'],
            ['col_000' => null,   'col_001' => 'B'],
            ['col_000' => 'ID-3', 'col_001' => 'C'],
        ];
        $profile = $profiler->profileSheet('Sheet1', $rowsWithNulls);
        $this->assertFalse($profile['col_000']->isCandidateKey);
        $this->assertGreaterThan(0.0, $profile['col_000']->nullRatio);

        // Empty strings treated as nulls for primary key viability
        $rowsWithEmptyStrings = [
            ['col_000' => 'ID-1'],
            ['col_000' => '   '],
            ['col_000' => 'ID-3'],
        ];
        $profileEmpty = $profiler->profileSheet('Sheet1', $rowsWithEmptyStrings);
        $this->assertFalse($profileEmpty['col_000']->isCandidateKey);

        // Composite Key Detection: Col A alone is not unique, Col B alone is not unique, but (A, B) is unique
        $compositeRows = [
            ['dept_id' => 'ENG', 'emp_num' => 1, 'role' => 'dev'],
            ['dept_id' => 'ENG', 'emp_num' => 2, 'role' => 'lead'],
            ['dept_id' => 'HR',  'emp_num' => 1, 'role' => 'lead'],
            ['dept_id' => 'HR',  'emp_num' => 2, 'role' => 'dev'],
        ];
        $profileComposite = $profiler->profileSheet('OrgSheet', $compositeRows);
        $this->assertFalse($profileComposite['dept_id']->isCandidateKey);
        $this->assertFalse($profileComposite['emp_num']->isCandidateKey);
        $this->assertFalse($profileComposite['role']->isCandidateKey);

        // Composite pair exists and is valid
        $this->assertTrue(isset($profileComposite['composite_dept_id_emp_num']));
        $compKey = $profileComposite['composite_dept_id_emp_num'];
        $this->assertTrue($compKey->isComposite);
        $this->assertTrue($compKey->isCandidateKey);
        $this->assertEquals(1.0, $compKey->uniquenessRatio);
        $this->assertEquals(0.0, $compKey->nullRatio);

        // Empty dataset handling without division by zero
        $emptyProfile = $profiler->profileSheet('EmptySheet', []);
        $this->assertEmpty($emptyProfile);
    }

    /**
     * 6. Inclusion Dependency (IND) Exact Thresholds, Bangla Numerals & Comparison Normalization.
     */
    public function test_inclusion_dependency_exact_thresholds_and_normalization(): void
    {
        $analyzer = new InclusionDependencyAnalyzer();

        // 1. IND = 1.0 (Exact Containment)
        $result = $analyzer->analyzeInclusion([1, 2, 3], [1, 2, 3, 4, 5]);
        $this->assertEquals(1.0, $result['inclusion_ratio']);
        $this->assertTrue($result['is_valid_ind']);
        $this->assertEquals(0, $result['orphan_count']);

        // 2. IND >= 0.85 (e.g. 85% match passes threshold)
        // 20 items: 17 match, 3 orphans -> 17/20 = 0.85
        $fk20 = range(1, 20);
        $pk17 = range(1, 17);
        $result85 = $analyzer->analyzeInclusion($fk20, $pk17);
        $this->assertEquals(0.85, $result85['inclusion_ratio']);
        $this->assertTrue($result85['is_valid_ind']);
        $this->assertEquals(3, $result85['orphan_count']);

        // 3. IND < 0.85 (e.g. 80% match fails threshold)
        // 10 items: 8 match, 2 orphans -> 8/10 = 0.80
        $result80 = $analyzer->analyzeInclusion(range(1, 10), range(1, 8));
        $this->assertEquals(0.80, $result80['inclusion_ratio']);
        $this->assertFalse($result80['is_valid_ind']);
        $this->assertEquals(2, $result80['orphan_count']);

        // 4. Bengali Numerals to Western Comparison Normalization without changing source storage
        $fkBangla = ['১০১', '১০২', '১০৩'];
        $pkWestern = [101, 102, 103, 104];
        $resultBangla = $analyzer->analyzeInclusion($fkBangla, $pkWestern);
        $this->assertEquals(1.0, $resultBangla['inclusion_ratio']);
        $this->assertTrue($resultBangla['is_valid_ind']);
    }

    /**
     * 7. Relationship Lifecycle: Confirmation, Rejection, and State Machine.
     */
    public function test_relationship_lifecycle_and_owner_confirmation_and_rejection(): void
    {
        $multiSheetData = [
            'Departments' => [
                'descriptors' => ['col_000' => ['original_header' => 'dept_id', 'physical_type' => 'integer']],
                'rows' => [['col_000' => 10], ['col_000' => 20]],
            ],
            'Staff' => [
                'descriptors' => [
                    'col_000' => ['original_header' => 'staff_id', 'physical_type' => 'integer'],
                    'col_001' => ['original_header' => 'dept_id', 'physical_type' => 'integer'],
                ],
                'rows' => [
                    ['col_000' => 1, 'col_001' => 10],
                    ['col_000' => 2, 'col_001' => 20],
                ],
            ],
        ];

        $graph = $this->service->discoverRelationships($this->jobA, $multiSheetData);
        $edgeId = array_key_first($graph->edges);
        $this->assertNotNull($edgeId);

        // 1. Initial State
        $edge = $graph->edges[$edgeId];
        $this->assertEquals(DiscoveredRelationshipEdge::STATE_DISCOVERED, $edge->state);

        // 2. Owner Confirms Candidate
        $confirmed = $this->service->confirmRelationship($this->jobA, $edgeId, (int) $this->ownerA->id, (int) $this->workspaceA->id);
        $this->assertEquals(DiscoveredRelationshipEdge::STATE_CONFIRMED, $confirmed->state);
        $this->assertEquals((int) $this->ownerA->id, $confirmed->confirmedBy);
        $this->assertNotNull($confirmed->confirmedAt);

        // 3. Reload graph and assert state is persisted in detected_schema
        $this->jobA->refresh();
        $reloaded = $this->service->loadGraph($this->jobA);
        $this->assertEquals(DiscoveredRelationshipEdge::STATE_CONFIRMED, $reloaded->edges[$edgeId]->state);

        // 4. Owner Rejects Candidate
        $rejected = $this->service->rejectRelationship(
            $this->jobA,
            $edgeId,
            (int) $this->ownerA->id,
            (int) $this->workspaceA->id,
            'Coincidental overlap, not a real business relationship'
        );
        $this->assertEquals(DiscoveredRelationshipEdge::STATE_REJECTED, $rejected->state);
        $this->assertEquals('Coincidental overlap, not a real business relationship', $rejected->rejectionReason);

        // 5. Re-discovery retains previously rejected decision
        $rediscovered = $this->service->evaluateDrift($this->jobA, $multiSheetData);
        $this->assertEquals(DiscoveredRelationshipEdge::STATE_REJECTED, $rediscovered->edges[$edgeId]->state);
    }

    /**
     * 8. Re-Import Drift Invalidation: Stale and Invalidated Relationships.
     */
    public function test_re_import_drift_invalidates_stale_relationships(): void
    {
        $initialData = [
            'Users' => [
                'descriptors' => ['col_000' => ['original_header' => 'user_id', 'physical_type' => 'integer']],
                'rows' => [['col_000' => 1], ['col_000' => 2], ['col_000' => 3]],
            ],
            'Logs' => [
                'descriptors' => [
                    'col_000' => ['original_header' => 'log_id', 'physical_type' => 'integer'],
                    'col_001' => ['original_header' => 'user_id', 'physical_type' => 'integer'],
                ],
                'rows' => [['col_000' => 101, 'col_001' => 1], ['col_000' => 102, 'col_001' => 2]],
            ],
        ];

        $graph = $this->service->discoverRelationships($this->jobA, $initialData);
        $edgeId = array_key_first($graph->edges);

        // Owner confirms it
        $this->service->confirmRelationship($this->jobA, $edgeId, (int) $this->ownerA->id, (int) $this->workspaceA->id);

        // New re-imported data has drift: Logs contains IDs 999, 888 that do NOT exist in Users (IND drops to 0.0)
        $driftedData = [
            'Users' => [
                'descriptors' => ['col_000' => ['original_header' => 'user_id', 'physical_type' => 'integer']],
                'rows' => [['col_000' => 1], ['col_000' => 2], ['col_000' => 3]],
            ],
            'Logs' => [
                'descriptors' => [
                    'col_000' => ['original_header' => 'log_id', 'physical_type' => 'integer'],
                    'col_001' => ['original_header' => 'user_id', 'physical_type' => 'integer'],
                ],
                'rows' => [['col_000' => 101, 'col_001' => 999], ['col_000' => 102, 'col_001' => 888]],
            ],
        ];

        $driftGraph = $this->service->evaluateDrift($this->jobA, $driftedData);
        $this->assertEquals(DiscoveredRelationshipEdge::STATE_STALE, $driftGraph->edges[$edgeId]->state);
    }

    /**
     * 9. Generation Fencing & Stale Worker Rejection.
     */
    public function test_generation_fencing_prevents_stale_job_overwrites(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stale generation fencing token');

        $this->service->discoverRelationships(
            $this->jobA,
            ['S1' => ['rows' => []], 'S2' => ['rows' => []]],
            'wrong-stale-uuid-fencing-token'
        );
    }

    /**
     * 10. Tenant Isolation on Relationship API Endpoints.
     */
    public function test_tenant_isolation_on_relationship_endpoints(): void
    {
        // Owner B attempts to confirm relationship on Document A / Job A (belongs to Workspace A)
        $this->expectException(TenantScopeViolationException::class);

        $this->service->confirmRelationship(
            $this->jobA,
            'some_edge_id',
            (int) $this->ownerB->id,
            (int) $this->workspaceB->id // Mismatched workspace
        );
    }

    /**
     * 11. Owner-First Simulator Routing vs Customer Support Pipeline Isolation.
     */
    public function test_owner_simulator_queries_route_to_owner_business_assistant_without_customer_denials(): void
    {
        // 1. Seed Structured Records for Workspace A (Product Catalog with Purchase Cost & Selling Price)
        StructuredRecord::create([
            'workspace_id'      => $this->workspaceA->id,
            'document_id'       => $this->documentA->id,
            'import_job_id'     => $this->jobA->id,
            'sheet_name'        => 'Products',
            'row_index'         => 1,
            'record_identifier' => 'ITEM-001',
            'mapped_attributes' => [
                'col_000' => ['source_column_id' => 'col_000', 'header' => 'Product Name', 'semantic_role' => 'entity_name', 'sensitivity' => 'public'],
                'col_001' => ['source_column_id' => 'col_001', 'header' => 'Selling Price', 'semantic_role' => 'selling_price', 'sensitivity' => 'public'],
                'col_002' => ['source_column_id' => 'col_002', 'header' => 'Purchase Cost', 'semantic_role' => 'purchase_cost', 'sensitivity' => 'restricted'],
            ],
            'semantic_projections' => [
                'entity_name'   => 'Gaming Console',
                'selling_price' => '45000',
                'purchase_cost' => '32000',
            ],
            'source_values' => ['col_000' => 'Gaming Console', 'col_001' => '45000', 'col_002' => '32000'],
            'raw_values'    => ['Gaming Console', '45000', '32000'],
            'is_public'     => true,
        ]);

        // Establish channel & conversation
        $channel = Channel::firstOrCreate(['slug' => 'simulator'], ['name' => 'Simulator', 'driver' => 'web']);
        $account = ChannelAccount::firstOrCreate(
            ['workspace_id' => $this->workspaceA->id, 'external_id' => 'sim_test_acc'],
            ['name' => 'Simulator Account', 'channel_id' => $channel->id, 'access_token' => 'sim_tok', 'is_active' => true]
        );

        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'sim_owner_conv_001',
            'status'             => 'active',
            'customer_name'      => 'Owner User',
            'last_direction'     => 'inbound',
            'metadata'           => ['is_simulator' => true, 'is_customer' => false, 'role' => 'owner'],
        ]);

        $ownerAssistant = app(OwnerBusinessAssistant::class);
        $customerService = app(CustomerSupportService::class);

        // Query asking for internal business metrics (sales, profit margin)
        $query = 'আমার মোট বিক্রয় এবং পণ্যের লাভ বা মার্জিন কত?';

        // 2. Owner Pipeline Execution -> Must NOT receive Customer Denial Message
        $ownerResult = $ownerAssistant->handleOwnerQuery($query, $this->workspaceA->id, $conversation, $this->ownerA);
        $this->assertTrue($ownerResult['is_owner_pipeline']);
        $this->assertEquals('analytics', $ownerResult['route']);
        $this->assertStringNotContainsString(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $ownerResult['reply']);
        $this->assertStringContainsString('ব্যবসায়িক অ্যানালিটিক্স ইনসাইট', $ownerResult['reply']);

        // 3. Customer Channel Execution -> MUST receive Customer Denial Message
        $customerChannel = Channel::firstOrCreate(['slug' => 'web_widget'], ['name' => 'Customer Widget', 'driver' => 'web']);
        $custAccount = ChannelAccount::firstOrCreate(
            ['workspace_id' => $this->workspaceA->id, 'external_id' => 'cust_acc_001'],
            ['name' => 'Public Widget', 'channel_id' => $customerChannel->id, 'access_token' => 'cust_tok', 'is_active' => true]
        );
        $custConversation = Conversation::create([
            'channel_account_id' => $custAccount->id,
            'external_user_id'   => 'public_customer_001',
            'status'             => 'active',
            'customer_name'      => 'Public Customer',
            'last_direction'     => 'inbound',
            'metadata'           => ['is_simulator' => false, 'is_customer' => true],
        ]);

        $customerResult = $customerService->handleQuery($query, $this->workspaceA->id, $custConversation);
        $this->assertEquals(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $customerResult['reply']);
        $this->assertEquals('analytics_denied', $customerResult['route']);
    }

    /**
     * 12. Data Integrity: Original source_values and raw_values remain 100% immutable.
     */
    public function test_source_values_and_raw_values_remain_lossless_and_immutable(): void
    {
        $rawValuesBefore = ['Val1', '120.50', '2026-10-15'];
        $sourceValuesBefore = ['col_000' => 'Val1', 'col_001' => '120.50', 'col_002' => '2026-10-15'];

        $record = StructuredRecord::create([
            'workspace_id'      => $this->workspaceA->id,
            'document_id'       => $this->documentA->id,
            'import_job_id'     => $this->jobA->id,
            'sheet_name'        => 'ImmutableSheet',
            'row_index'         => 1,
            'source_values'     => $sourceValuesBefore,
            'raw_values'        => $rawValuesBefore,
            'is_public'         => true,
        ]);

        $multiSheetData = [
            'ImmutableSheet' => [
                'descriptors' => ['col_000' => ['original_header' => 'id', 'physical_type' => 'string']],
                'rows' => [['col_000' => 'Val1']],
            ],
            'RefSheet' => [
                'descriptors' => ['col_x' => ['original_header' => 'fk_id', 'physical_type' => 'string']],
                'rows' => [['col_x' => 'Val1']],
            ],
        ];

        // Run relationship discovery and confirmation
        $graph = $this->service->discoverRelationships($this->jobA, $multiSheetData);
        $edgeId = array_key_first($graph->edges);
        if ($edgeId) {
            $this->service->confirmRelationship($this->jobA, $edgeId, (int) $this->ownerA->id, (int) $this->workspaceA->id);
        }

        // Verify record is strictly unmodified
        $record->refresh();
        $this->assertEquals($sourceValuesBefore, $record->source_values);
        $this->assertEquals($rawValuesBefore, $record->raw_values);
    }

    /**
     * 13. Audit B: Real Multi-Sheet XLSX parsing and discovery using PhpSpreadsheet.
     */
    public function test_real_multi_sheet_xlsx_parsing_and_discovery_with_phpspreadsheet(): void
    {
        // 1. Construct real 3-sheet XLSX workbook
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // Sheet 1: Departments
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Departments');
        $sheet1->fromArray([
            ['dept_code', 'department_name'],
            ['D1', 'Engineering'],
            ['D2', 'Finance'],
            ['D3', 'Operations'],
        ]);

        // Sheet 2: Employees
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Employees');
        $sheet2->fromArray([
            ['employee_id', 'department_code', 'full_name'],
            ['E101', 'D1', 'Alice Johnson'],
            ['E102', 'D1', 'Bob Smith'],
            ['E103', 'D2', 'Charlie Brown'],
            ['E104', 'D3', 'Diana Prince'],
        ]);

        // Sheet 3: Projects
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Projects');
        $sheet3->fromArray([
            ['project_id', 'owner_emp_id', 'budget'],
            ['PRJ-1', 'E101', 50000.00],
            ['PRJ-2', 'E101', 75000.00],
            ['PRJ-3', 'E102', 30000.00],
        ]);

        $tempPath = sys_get_temp_dir() . '/audit_test_multisheet_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempPath);

        $this->assertFileExists($tempPath);

        try {
            // 2. Run discoverWorkbookFile against real XLSX binary
            $graph = $this->service->discoverWorkbookFile($this->jobA, $tempPath);

            $this->assertInstanceOf(SpreadsheetRelationshipGraph::class, $graph);
            $this->assertCount(3, $graph->nodes);
            $this->assertArrayHasKey('Departments', $graph->nodes);
            $this->assertArrayHasKey('Employees', $graph->nodes);
            $this->assertArrayHasKey('Projects', $graph->nodes);

            // Verify Employees -> Departments relationship was discovered
            $empToDept = null;
            foreach ($graph->edges as $edge) {
                if ($edge->sourceSheet === 'Employees' && $edge->targetSheet === 'Departments') {
                    $empToDept = $edge;
                    break;
                }
            }
            $this->assertNotNull($empToDept, "Employees -> Departments relationship was not discovered from real XLSX");
            $this->assertEquals(1.0, $empToDept->inclusionRatio);
            $this->assertEquals(DiscoveredRelationshipEdge::CARDINALITY_ONE_TO_MANY, $empToDept->cardinality);
            $this->assertFalse($empToDept->isJunctionTable);

            // Verify Projects -> Employees relationship was discovered
            $prjToEmp = null;
            foreach ($graph->edges as $edge) {
                if ($edge->sourceSheet === 'Projects' && $edge->targetSheet === 'Employees') {
                    $prjToEmp = $edge;
                    break;
                }
            }
            $this->assertNotNull($prjToEmp, "Projects -> Employees relationship was not discovered from real XLSX");
            $this->assertEquals(1.0, $prjToEmp->inclusionRatio);
            $this->assertEquals(DiscoveredRelationshipEdge::CARDINALITY_ONE_TO_MANY, $prjToEmp->cardinality);
            $this->assertFalse($prjToEmp->isJunctionTable);

        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * 14. Audit B: Discover Relationships API Endpoint execution.
     */
    public function test_api_endpoint_discover_relationships_from_uploaded_document(): void
    {
        // 1. Create real 2-sheet spreadsheet on disk for documentA
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Courses');
        $sheet1->fromArray([
            ['course_id', 'title'],
            ['CS101', 'Intro to CS'],
            ['CS102', 'Data Structures'],
        ]);

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Enrollments');
        $sheet2->fromArray([
            ['enrollment_id', 'course_id', 'student_name'],
            [1, 'CS101', 'Alice'],
            [2, 'CS101', 'Bob'],
            [3, 'CS102', 'Charlie'],
        ]);

        $storagePath = \Illuminate\Support\Facades\Storage::disk('local')->path($this->documentA->file_path);
        @mkdir(dirname($storagePath), 0755, true);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($storagePath);

        // 2. Owner A requests discovery
        $response = $this->actingAs($this->ownerA)
            ->postJson(route('documents.spreadsheet.relationships.discover', ['document' => $this->documentA->id]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'document_id' => $this->documentA->id,
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['graph']['edges']);

        // 3. Owner B (different tenant) attempts to trigger discovery on Document A -> 403 Forbidden
        $crossTenantResponse = $this->actingAs($this->ownerB)
            ->postJson(route('documents.spreadsheet.relationships.discover', ['document' => $this->documentA->id]));

        $crossTenantResponse->assertStatus(403);
    }
}
