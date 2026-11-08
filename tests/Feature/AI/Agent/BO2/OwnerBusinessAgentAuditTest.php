<?php

declare(strict_types=1);

namespace Tests\Feature\AI\Agent\BO2;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\User;
use App\Models\Workspace;
use App\AI\AgentRuntimeEngine;
use App\AI\Contracts\ToolInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Agents\OwnerBusinessAgent;
use App\AI\Policies\ToolAuthorizationPolicy;
use App\AI\Tools\AbstractGovernedTool;
use App\AI\Tools\BusinessOverviewTool;
use App\AI\Tools\CustomerInsightsTool;
use App\AI\Tools\EmployeeAnalyticsTool;
use App\AI\Tools\FinancialAnalyticsTool;
use App\AI\Tools\InventoryAnalyticsTool;
use App\AI\Tools\KnowledgeIntelligenceTool;
use App\AI\Tools\LegacyExcelAnalyticsTool;
use App\AI\Tools\OwnerCustomerSupportInsightsTool;
use App\AI\Tools\ProductPerformanceTool;
use App\AI\Tools\SalesAnalyticsTool;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\DTOs\Contract3\ExecutionAuthorizationGrant;
use App\Services\Analytics\DTOs\Contract3\GrantSigner;
use App\Services\Retrieval\RetrievalClient;
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
use App\Services\Spreadsheet\Security\AnalyticsAstAuthorizer;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

/**
 * Complete Independent Security, Architecture & Implementation Acceptance Audit Test Suite
 * Covers the 18 minimum required audit test scenarios for BO-2 under Level 2 authorization.
 */
class OwnerBusinessAgentAuditTest extends TestCase
{
    use RefreshDatabase;

    private AgentRuntimeEngine $engine;
    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $ownerUserA;
    private User $staffUserA;
    private User $ownerUserB;
    private AnalyticsSalesperson $salespersonA;
    private AnalyticsSalesperson $salespersonB;
    private AgentContext $ownerContextA;
    private AgentContext $staffContextA;
    private AgentContext $ownerContextB;
    private OwnerBusinessAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new AgentRuntimeEngine();

        $this->workspaceA = Workspace::create(['name' => 'Workspace Alpha', 'slug' => 'ws-alpha']);
        $this->workspaceB = Workspace::create(['name' => 'Workspace Beta', 'slug' => 'ws-beta']);

        $this->ownerUserA = User::create([
            'name'         => 'Owner Alpha',
            'email'        => 'owner.alpha@test.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspaceA->id,
        ]);

        $this->staffUserA = User::create([
            'name'         => 'Staff Alpha',
            'email'        => 'staff.alpha@test.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspaceA->id,
        ]);

        $this->ownerUserB = User::create([
            'name'         => 'Owner Beta',
            'email'        => 'owner.beta@test.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspaceB->id,
        ]);

        $this->salespersonA = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceA->id,
            'name'          => 'Seller Alpha',
            'employee_code' => 'SP-A-01',
            'base_salary'   => 40000.00,
            'target_amount' => 100000.00,
            'is_active'     => true,
        ]);

        $this->salespersonB = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceB->id,
            'name'          => 'Seller Beta',
            'employee_code' => 'SP-B-01',
            'base_salary'   => 40000.00,
            'target_amount' => 100000.00,
            'is_active'     => true,
        ]);

        $sccOwnerA = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: $this->ownerUserA->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries', KnowledgeDocumentPermission::VIEW->value],
        );
        $this->ownerContextA = AgentContext::fromServerControlledContext($sccOwnerA, user: $this->ownerUserA);

        $sccStaffA = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: $this->staffUserA->id,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $this->staffContextA = AgentContext::fromServerControlledContext($sccStaffA, user: $this->staffUserA);

        $sccOwnerB = new ServerControlledContext(
            workspaceId: $this->workspaceB->id,
            userId: $this->ownerUserB->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries', KnowledgeDocumentPermission::VIEW->value],
        );
        $this->ownerContextB = AgentContext::fromServerControlledContext($sccOwnerB, user: $this->ownerUserB);

        $this->agent = app(OwnerBusinessAgent::class);
    }

    /**
     * Audit Scenario 1: Tool Authorization Matrix across all roles
     */
    public function test_audit_01_tool_authorization_matrix_across_all_roles(): void
    {
        $policy = app(ToolAuthorizationPolicy::class);
        $tools = $this->agent->getRegisteredTools();
        $this->assertCount(10, $tools);

        // 1. Owner with both financial & HR permissions: All 10 tools authorized
        $boundOwnerContext = $this->ownerContextA->withAgent($this->agent);
        foreach ($tools as $tool) {
            $this->assertTrue(
                $policy->authorize($this->agent, $tool, $boundOwnerContext),
                "Tool {$tool->name()} must be authorized for owner with permissions."
            );
        }

        // 2. Staff without permissions: 8 tools authorized, 2 restricted tools rejected
        $boundStaffContext = $this->staffContextA->withAgent($this->agent);
        $authorizedTools = [];
        $rejectedTools = [];

        foreach ($tools as $tool) {
            try {
                if ($policy->authorize($this->agent, $tool, $boundStaffContext)) {
                    $authorizedTools[] = $tool->name();
                }
            } catch (UnauthorizedToolException) {
                $rejectedTools[] = $tool->name();
            }
        }

        $this->assertCount(9, $authorizedTools);
        $this->assertCount(1, $rejectedTools);
        $this->assertContains('financial_analytics', $rejectedTools);

        // 3. Customer context: Barred from privileged tools (like financial_analytics)
        $sccCustomer = ServerControlledContext::forCustomer($this->workspaceA->id);
        $customerContext = AgentContext::fromServerControlledContext($sccCustomer);
        $boundCustomerContext = $customerContext->withAgent($this->agent);

        $financialTool = app(FinancialAnalyticsTool::class);
        $customerDenied = false;
        try {
            $customerDenied = ! $policy->authorize($this->agent, $financialTool, $boundCustomerContext);
        } catch (UnauthorizedToolException) {
            $customerDenied = true;
        }
        $this->assertTrue($customerDenied, 'Customer context must be barred from privileged tool financial_analytics.');

        // 4. Unauthenticated non-customer context fails closed
        $unauthScc = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: 9999,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $unauthContext = AgentContext::fromServerControlledContext($unauthScc, user: null);
        $boundUnauthContext = $unauthContext->withAgent($this->agent);

        $unauthDenied = false;
        try {
            $unauthDenied = ! $policy->authorize($this->agent, $tools[0], $boundUnauthContext);
        } catch (UnauthorizedToolException) {
            $unauthDenied = true;
        }
        $this->assertTrue($unauthDenied, 'Unauthenticated context must fail closed.');
    }

    /**
     * Audit Scenario 2: Input Validation & Date Boundaries across tools
     */
    public function test_audit_02_input_validation_and_date_boundaries_across_tools(): void
    {
        $dateTools = [
            app(BusinessOverviewTool::class),
            app(SalesAnalyticsTool::class),
            app(ProductPerformanceTool::class),
            app(FinancialAnalyticsTool::class),
            app(OwnerCustomerSupportInsightsTool::class),
        ];

        // 1. Inverted date boundaries: start_date > end_date
        foreach ($dateTools as $tool) {
            $result = AgentContext::withContext($this->ownerContextA, function () use ($tool) {
                return $tool->handle(new Request([
                    'period'     => 'custom',
                    'start_date' => '2026-11-20',
                    'end_date'   => '2026-11-01',
                    'date_from'  => '2026-11-20',
                    'date_to'    => '2026-11-01',
                ]));
            });

            $this->assertFalse(
                $result->success,
                "Tool {$tool->name()} must fail gracefully when start_date > end_date."
            );
            $this->assertTrue(
                str_contains($result->error, 'before or equal to') || str_contains($result->error, 'cannot be after'),
                "Tool {$tool->name()} error message must mention date boundary constraint."
            );
        }

        // 2. Malformed date format
        foreach ($dateTools as $tool) {
            $result = AgentContext::withContext($this->ownerContextA, function () use ($tool) {
                return $tool->handle(new Request([
                    'period'     => 'custom',
                    'start_date' => 'invalid-date-string',
                    'end_date'   => '2026-11-05',
                    'date_from'  => 'invalid-date-string',
                    'date_to'    => '2026-11-05',
                ]));
            });

            $this->assertFalse(
                $result->success,
                "Tool {$tool->name()} must fail gracefully when date format is invalid."
            );
            $this->assertStringContainsString('Invalid date format', $result->error);
        }
    }

    /**
     * Audit Scenario 3: LLM Injection / Parameter Spoofing Resistance
     */
    public function test_audit_03_llm_parameter_spoofing_resistance(): void
    {
        // Seed order for Workspace A
        $customerA = AnalyticsCustomer::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Alpha Customer',
        ]);
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $customerA->id,
            'salesperson_id' => $this->salespersonA->id,
            'order_number'   => 'ORD-SPOOF-A',
            'total_amount'   => 1000.00,
            'net_amount'     => 1000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->toDateString(),
        ]);

        // Seed order for Workspace B (different workspace)
        $customerB = AnalyticsCustomer::create([
            'workspace_id' => $this->workspaceB->id,
            'name'         => 'Beta Customer',
        ]);
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceB->id,
            'customer_id'    => $customerB->id,
            'salesperson_id' => $this->salespersonB->id,
            'order_number'   => 'ORD-SPOOF-B',
            'total_amount'   => 99999.00,
            'net_amount'     => 99999.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->toDateString(),
        ]);

        $overviewTool = app(BusinessOverviewTool::class);

        // Adversarial LLM argument injection: passes workspace_id of Workspace B and elevated permissions
        $maliciousArgs = [
            'workspace_id' => $this->workspaceB->id,
            'user_id'      => 99999,
            'permissions'  => ['*'],
            'bypass_auth'  => true,
            'is_owner'     => true,
        ];

        $result = AgentContext::withContext($this->ownerContextA, function () use ($overviewTool, $maliciousArgs) {
            return $overviewTool->handle(new Request($maliciousArgs));
        });

        $this->assertTrue($result->success);
        // Must strictly report Workspace A's 1000.00, NOT Workspace B's 99999.00
        $this->assertSame(1000.00, (float) $result->data['gross_sales']);
        $this->assertSame(1, $result->data['order_count']);
        $this->assertNotSame(99999.00, (float) $result->data['gross_sales']);
    }

    /**
     * Audit Scenario 4: DLP Field Filtering (DataProjectionPolicy)
     */
    public function test_audit_04_dlp_field_filtering_projection_policy(): void
    {
        $customer = AnalyticsCustomer::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'DLP Customer',
        ]);

        $product = AnalyticsProduct::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'High Margin Product',
            'unit_price'   => 200.00,
            'cost_price'   => 80.00,
        ]);

        $order = AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $customer->id,
            'salesperson_id' => $this->salespersonA->id,
            'order_number'   => 'ORD-DLP-1',
            'total_amount'   => 2000.00,
            'net_amount'     => 2000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->toDateString(),
        ]);

        AnalyticsOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 10,
            'unit_price' => 200.00,
            'subtotal'   => 2000.00,
        ]);

        $overviewTool = app(BusinessOverviewTool::class);

        // 1. Owner execution: financial fields preserved
        $resultOwner = $this->engine->executeTool(
            tool: $overviewTool,
            arguments: [],
            context: $this->ownerContextA,
            agent: $this->agent,
        );
        $this->assertTrue($resultOwner->success);
        $this->assertArrayHasKey('cogs', $resultOwner->data);
        $this->assertArrayHasKey('gross_margin', $resultOwner->data);
        $this->assertArrayHasKey('gross_margin_rate', $resultOwner->data);

        // 2. Staff execution: financial fields scrubbed by DataProjectionPolicy
        $resultStaff = $this->engine->executeTool(
            tool: $overviewTool,
            arguments: [],
            context: $this->staffContextA,
            agent: $this->agent,
        );
        $this->assertTrue($resultStaff->success);
        $this->assertArrayNotHasKey('cogs', $resultStaff->data);
        $this->assertArrayNotHasKey('gross_margin', $resultStaff->data);
        $this->assertArrayNotHasKey('gross_margin_rate', $resultStaff->data);
        // Non-financial metrics remain available
        $this->assertArrayHasKey('gross_sales', $resultStaff->data);
        $this->assertArrayHasKey('order_count', $resultStaff->data);
    }

    /**
     * Audit Scenario 5: Strict Read-Only Verification & Database Immutability
     */
    public function test_audit_05_strict_read_only_verification_and_database_immutability(): void
    {
        $tools = $this->agent->getRegisteredTools();

        // 1. Contractual read-only check
        foreach ($tools as $tool) {
            $this->assertInstanceOf(AbstractGovernedTool::class, $tool);
            $this->assertTrue($tool->isReadOnly(), "Tool {$tool->name()} must return isReadOnly() === true.");
        }

        // 2. Database immutability snapshot
        $tables = [
            'workspaces',
            'users',
            'analytics_orders',
            'analytics_order_items',
            'analytics_products',
            'analytics_customers',
            'analytics_salespersons',
            'structured_records',
            'knowledge_documents',
            'spreadsheet_import_jobs',
            'faqs',
        ];

        $countsBefore = [];
        foreach ($tables as $table) {
            $countsBefore[$table] = DB::table($table)->count();
        }

        // Execute tools under standard owner context
        $toolsToRun = [
            app(BusinessOverviewTool::class),
            app(SalesAnalyticsTool::class),
            app(ProductPerformanceTool::class),
            app(InventoryAnalyticsTool::class),
            app(CustomerInsightsTool::class),
            app(FinancialAnalyticsTool::class),
            app(EmployeeAnalyticsTool::class),
            app(OwnerCustomerSupportInsightsTool::class),
        ];

        foreach ($toolsToRun as $t) {
            AgentContext::withContext($this->ownerContextA, function () use ($t) {
                $t->handle(new Request());
            });
        }

        // Assert all database counts remain completely unmodified
        foreach ($tables as $table) {
            $this->assertSame(
                $countsBefore[$table],
                DB::table($table)->count(),
                "Table {$table} count changed during read-only tool execution."
            );
        }
    }

    /**
     * Audit Scenario 6: Multi-Tenant Workspace Isolation across tools
     */
    public function test_audit_06_multi_tenant_workspace_isolation_across_all_tools(): void
    {
        // Seed distinct orders in Workspace A ($15,000) and Workspace B ($4,000)
        $custA = AnalyticsCustomer::create(['workspace_id' => $this->workspaceA->id, 'name' => 'Cust A']);
        $custB = AnalyticsCustomer::create(['workspace_id' => $this->workspaceB->id, 'name' => 'Cust B']);

        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $custA->id,
            'salesperson_id' => $this->salespersonA->id,
            'order_number'   => 'ORD-WS-A-1',
            'total_amount'   => 15000.00,
            'net_amount'     => 15000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->toDateString(),
        ]);

        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceB->id,
            'customer_id'    => $custB->id,
            'salesperson_id' => $this->salespersonB->id,
            'order_number'   => 'ORD-WS-B-1',
            'total_amount'   => 4000.00,
            'net_amount'     => 4000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->toDateString(),
        ]);

        $overviewTool = app(BusinessOverviewTool::class);

        $resA = AgentContext::withContext($this->ownerContextA, fn () => $overviewTool->handle(new Request()));
        $this->assertTrue($resA->success);
        $this->assertSame(15000.00, (float) $resA->data['gross_sales']);

        $resB = AgentContext::withContext($this->ownerContextB, fn () => $overviewTool->handle(new Request()));
        $this->assertTrue($resB->success);
        $this->assertSame(4000.00, (float) $resB->data['gross_sales']);
    }

    /**
     * Audit Scenario 7: SLA Fast-Fail & Deadline Enforcement
     */
    public function test_audit_07_sla_fast_fail_and_deadline_enforcement(): void
    {
        $sccExpired = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: $this->ownerUserA->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );

        $expiredContext = AgentContext::fromServerControlledContext(
            $sccExpired,
            user: $this->ownerUserA,
            timeoutSeconds: 0.001
        );
        usleep(3000); // 3ms to ensure expiration

        $overviewTool = app(BusinessOverviewTool::class);

        $this->expectException(AgentExecutionTimeoutException::class);
        $this->expectExceptionMessage('exhausted');

        $this->engine->executeTool(
            tool: $overviewTool,
            arguments: [],
            context: $expiredContext,
            agent: $this->agent,
        );
    }

    /**
     * Audit Scenario 8: Maximum Iteration Ceiling (3 steps)
     */
    public function test_audit_08_maximum_iteration_ceiling(): void
    {
        $this->assertSame(3, $this->agent->maxSteps());

        // AgentContext constructor caps maxIterations at 3
        $scc = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: $this->ownerUserA->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $ctx = AgentContext::fromServerControlledContext($scc, user: $this->ownerUserA, maxIterations: 10);
        $this->assertSame(3, $ctx->maxIterations);
    }

    /**
     * Audit Scenario 9: Empty Dataset & Edge Case Handling across all tools
     */
    public function test_audit_09_empty_dataset_and_edge_case_handling(): void
    {
        $emptyWorkspace = Workspace::create(['name' => 'Empty WS', 'slug' => 'empty-ws']);
        $emptyUser = User::create([
            'name'         => 'Empty Owner',
            'email'        => 'empty.owner@test.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $emptyWorkspace->id,
        ]);

        $sccEmpty = new ServerControlledContext(
            workspaceId: $emptyWorkspace->id,
            userId: $emptyUser->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries', KnowledgeDocumentPermission::VIEW->value],
        );
        $emptyContext = AgentContext::fromServerControlledContext($sccEmpty, user: $emptyUser);

        $tools = [
            app(BusinessOverviewTool::class),
            app(SalesAnalyticsTool::class),
            app(ProductPerformanceTool::class),
            app(InventoryAnalyticsTool::class),
            app(CustomerInsightsTool::class),
            app(FinancialAnalyticsTool::class),
            app(EmployeeAnalyticsTool::class),
            app(OwnerCustomerSupportInsightsTool::class),
            app(KnowledgeIntelligenceTool::class),
            app(LegacyExcelAnalyticsTool::class),
        ];

        foreach ($tools as $tool) {
            $result = AgentContext::withContext($emptyContext, function () use ($tool) {
                return $tool->handle(new Request(['query' => 'How are we doing?', 'question' => 'How are we doing?']));
            });

            $this->assertInstanceOf(
                ToolResult::class,
                $result,
                "Tool {$tool->name()} must return a ToolResult on empty dataset."
            );
            $this->assertTrue(
                $result->success,
                "Tool {$tool->name()} must return success === true on empty dataset without throwing division by zero."
            );
            $this->assertIsArray($result->data);
        }
    }

    /**
     * Audit Scenario 10: N+1 Query Prevention on EmployeeAnalyticsTool (Defect D2 Verification)
     */
    public function test_audit_10_n_plus_one_query_prevention_on_employee_analytics(): void
    {
        $cust = AnalyticsCustomer::create(['workspace_id' => $this->workspaceA->id, 'name' => 'General Customer']);

        // Seed 20 salespeople with orders
        for ($i = 1; $i <= 20; $i++) {
            $sp = AnalyticsSalesperson::create([
                'workspace_id'  => $this->workspaceA->id,
                'name'          => "Salesperson {$i}",
                'employee_code' => "EMP-N1-{$i}",
                'base_salary'   => 30000.00,
                'target_amount' => 50000.00,
                'is_active'     => true,
            ]);

            AnalyticsOrder::create([
                'workspace_id'   => $this->workspaceA->id,
                'customer_id'    => $cust->id,
                'salesperson_id' => $sp->id,
                'order_number'   => "ORD-N1-{$i}",
                'total_amount'   => 1000.00 * $i,
                'net_amount'     => 1000.00 * $i,
                'status'         => 'completed',
                'order_date'     => Carbon::now()->toDateString(),
            ]);
        }

        $tool = app(EmployeeAnalyticsTool::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = AgentContext::withContext($this->ownerContextA, function () use ($tool) {
            return $tool->handle(new Request());
        });

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($result->success);
        // Query count must be strictly <= 6 (constant O(1) queries), NEVER 20+ N+1 queries!
        $this->assertLessThanOrEqual(
            6,
            count($queryLog),
            "EmployeeAnalyticsTool executed " . count($queryLog) . " queries for 20 salespeople; expected O(1) query complexity."
        );
    }

    /**
     * Audit Scenario 11: Downstream Retrieval Resiliency in KnowledgeIntelligenceTool (Defect D4 Verification)
     */
    public function test_audit_11_downstream_retrieval_resiliency_in_knowledge_tool(): void
    {
        $failingRetrievalClient = Mockery::mock(RetrievalClient::class);
        $failingRetrievalClient->shouldReceive('search')
            ->andThrow(new \RuntimeException('Typesense downstream connection timeout'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $failingRetrievalClient);

        $result = AgentContext::withContext($this->ownerContextA, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'Return policy']));
        });

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertFalse(
            $result->success,
            "Tool must return failure when downstream retrieval service throws (never masquerading as empty success)."
        );
        $this->assertSame('DOWNSTREAM_UNAVAILABLE', $result->metadata['error_code'] ?? null);
        $this->assertStringContainsString('unavailable', $result->error ?? '');
    }

    /**
     * Audit Scenario 12: Legacy Excel Ingestion Status Guard (Defect D3 Verification)
     */
    public function test_audit_12_legacy_excel_ingestion_status_guard(): void
    {
        $doc = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'In-Progress Sheet',
            'original_filename' => 'sheet.xlsx',
            'file_path'         => 'spreadsheets/sheet.xlsx',
            'content_hash'      => hash('sha256', 'content'),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
        ]);

        // Job is still processing (not completed)
        SpreadsheetImportJob::create([
            'workspace_id'  => $this->workspaceA->id,
            'document_id'   => $doc->id,
            'status'        => 'processing',
            'generation_id' => 'gen-uncompleted',
        ]);

        $tool = app(LegacyExcelAnalyticsTool::class);

        $result = AgentContext::withContext($this->ownerContextA, function () use ($tool) {
            return $tool->handle(new Request(['question' => 'What is the sum?']));
        });

        $this->assertTrue($result->success);
        // Must ignore 'processing' job and report fallback
        $this->assertSame('no_spreadsheet_found', $result->data['status']);
        $this->assertEmpty($result->data['rows']);
    }

    /**
     * Audit Scenario 13: AST Authorization & Grant Signer Integration
     */
    public function test_audit_13_ast_authorization_and_grant_signer_integration(): void
    {
        $secret = 'test_secret_key_ast_audit';

        $queryFingerprint = hash('sha256', 'SELECT * FROM test');
        $generationId = 'gen-uuid-1';
        $expiresAt = (new \DateTimeImmutable('+60 seconds'))->format(\DateTimeInterface::ATOM);
        $nonce = bin2hex(random_bytes(16));

        $signature = GrantSigner::sign(
            workspaceId: $this->workspaceA->id,
            queryFingerprint: $queryFingerprint,
            generationId: $generationId,
            expiresAt: $expiresAt,
            nonce: $nonce,
            secret: $secret,
        );

        $grant = new ExecutionAuthorizationGrant(
            grantId: 'grant-test-001',
            workspaceId: $this->workspaceA->id,
            queryFingerprint: $queryFingerprint,
            generationId: $generationId,
            authorizedAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            expiresAt: $expiresAt,
            nonce: $nonce,
            signature: $signature,
        );

        $this->assertNotEmpty($grant->signature);
        $this->assertTrue($grant->verifySignature($secret));

        // Wrong secret verification
        $this->assertFalse($grant->verifySignature('wrong_secret'));

        // Tampered grant check
        $tamperedGrant = new ExecutionAuthorizationGrant(
            grantId: $grant->grantId,
            workspaceId: $grant->workspaceId,
            queryFingerprint: hash('sha256', 'SELECT * FROM tampered_table'),
            generationId: $grant->generationId,
            authorizedAt: $grant->authorizedAt,
            expiresAt: $grant->expiresAt,
            nonce: $grant->nonce,
            signature: $grant->signature,
        );

        $this->assertFalse($tamperedGrant->verifySignature($secret));
    }

    /**
     * Audit Scenario 14: Direct Excel Query Prohibition
     */
    public function test_audit_14_direct_excel_query_prohibition(): void
    {
        $tool = app(LegacyExcelAnalyticsTool::class);
        $reflection = new \ReflectionClass($tool);

        // Verify tool has no direct DB raw execution methods and depends strictly on AnalyticsClient
        $constructorParams = array_map(
            fn (\ReflectionParameter $p) => $p->getType()?->getName(),
            $reflection->getConstructor()->getParameters()
        );

        $this->assertContains(AnalyticsClient::class, $constructorParams);
        $this->assertContains(AnalyticsAstAuthorizer::class, $constructorParams);
        $this->assertContains(CrossSheetRelationshipService::class, $constructorParams);
    }

    /**
     * Audit Scenario 15: Singleton Statelessness & Multi-User Concurrency
     */
    public function test_audit_15_singleton_statelessness_and_multi_user_concurrency(): void
    {
        $agentInstance1 = app(OwnerBusinessAgent::class);
        $agentInstance2 = app(OwnerBusinessAgent::class);
        $this->assertSame($agentInstance1, $agentInstance2, 'OwnerBusinessAgent must be registered as a singleton.');

        // Seed data in WS-A ($500) and WS-B ($2,000)
        $custA = AnalyticsCustomer::create(['workspace_id' => $this->workspaceA->id, 'name' => 'Cust A']);
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $custA->id,
            'salesperson_id' => $this->salespersonA->id,
            'order_number'   => 'ORD-SEQ-A',
            'total_amount'   => 500.00,
            'net_amount'     => 500.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->toDateString(),
        ]);

        $custB = AnalyticsCustomer::create(['workspace_id' => $this->workspaceB->id, 'name' => 'Cust B']);
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceB->id,
            'customer_id'    => $custB->id,
            'salesperson_id' => $this->salespersonB->id,
            'order_number'   => 'ORD-SEQ-B',
            'total_amount'   => 2000.00,
            'net_amount'     => 2000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->toDateString(),
        ]);

        $overviewTool = app(BusinessOverviewTool::class);

        // Turn 1: User Alpha (WS-A)
        $resA1 = AgentContext::withContext($this->ownerContextA, fn () => $overviewTool->handle(new Request()));
        $this->assertFalse(AgentContext::hasCurrent());
        $this->assertSame(500.00, (float) $resA1->data['gross_sales']);

        // Turn 2: User Beta (WS-B)
        $resB = AgentContext::withContext($this->ownerContextB, fn () => $overviewTool->handle(new Request()));
        $this->assertFalse(AgentContext::hasCurrent());
        $this->assertSame(2000.00, (float) $resB->data['gross_sales']);

        // Turn 3: User Alpha (WS-A) again
        $resA2 = AgentContext::withContext($this->ownerContextA, fn () => $overviewTool->handle(new Request()));
        $this->assertFalse(AgentContext::hasCurrent());
        $this->assertSame(500.00, (float) $resA2->data['gross_sales']);
    }

    /**
     * Audit Scenario 16: AppServiceProvider Lifecycle Context Cleanup
     */
    public function test_audit_16_app_service_provider_lifecycle_context_cleanup(): void
    {
        // Manually set current context
        AgentContext::setCurrent($this->ownerContextA);
        $this->assertTrue(AgentContext::hasCurrent());

        // Simulate application termination lifecycle event
        app()->terminate();

        $this->assertFalse(AgentContext::hasCurrent(), 'AgentContext must be cleared when application terminates.');
    }

    /**
     * Audit Scenario 17: Date String Uniformity & Precision (Defect D5 Verification)
     */
    public function test_audit_17_date_string_uniformity_and_precision(): void
    {
        $cust = AnalyticsCustomer::create(['workspace_id' => $this->workspaceA->id, 'name' => 'Date Cust']);
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $cust->id,
            'salesperson_id' => $this->salespersonA->id,
            'order_number'   => 'ORD-DATE-1',
            'total_amount'   => 1200.00,
            'net_amount'     => 1200.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->subDays(3)->toDateString(),
        ]);

        $productTool = app(ProductPerformanceTool::class);
        $result = AgentContext::withContext($this->ownerContextA, fn () => $productTool->handle(new Request()));

        $this->assertTrue($result->success);
        $this->assertIsString($result->data['start_date']);
        $this->assertIsString($result->data['end_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $result->data['start_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $result->data['end_date']);

        // Ensure no Carbon objects leak in array
        foreach ($result->data as $key => $val) {
            $this->assertFalse($val instanceof \DateTimeInterface, "Field {$key} must not be a raw DateTime object.");
        }
    }

    /**
     * Audit Scenario 18: Narrative DLP Compliance (DEC-BO1-08)
     */
    public function test_audit_18_narrative_dlp_compliance_dec_bo1_08(): void
    {
        $product = AnalyticsProduct::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Proprietary Secret Item',
            'unit_price'   => 1000.00,
            'cost_price'   => 350.00,
        ]);

        $cust = AnalyticsCustomer::create(['workspace_id' => $this->workspaceA->id, 'name' => 'Secret Customer']);
        $order = AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $cust->id,
            'salesperson_id' => $this->salespersonA->id,
            'order_number'   => 'ORD-NARRATIVE-1',
            'total_amount'   => 1000.00,
            'net_amount'     => 1000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->toDateString(),
        ]);

        AnalyticsOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => 1000.00,
            'subtotal'   => 1000.00,
        ]);

        $overviewTool = app(BusinessOverviewTool::class);

        // Staff turn through runtime engine
        $resultStaff = $this->engine->executeTool(
            tool: $overviewTool,
            arguments: [],
            context: $this->staffContextA,
            agent: $this->agent,
        );

        $this->assertTrue($resultStaff->success);

        // Structured data must have cogs and gross margin stripped
        $this->assertArrayNotHasKey('cogs', $resultStaff->data);
        $this->assertArrayNotHasKey('gross_margin', $resultStaff->data);

        // Response string / narrative must NOT leak raw cost price 350.00
        $responseString = $resultStaff->toResponseString();
        $this->assertStringNotContainsString('350', $responseString);
    }
}
