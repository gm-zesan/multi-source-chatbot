<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\AgentRuntimeEngine;
use App\AI\Agents\OwnerBusinessAgent;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
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
use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-End verification of realistic business owner questions against BO-2 concrete tools.
 */
class OwnerBusinessRealisticQuestionsE2ETest extends TestCase
{
    use RefreshDatabase;

    private AgentRuntimeEngine $engine;
    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $ownerUserA;
    private User $staffUserA;
    private AgentContext $ownerContextA;
    private AgentContext $staffContextA;
    private AgentContext $ownerContextB;
    private OwnerBusinessAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new AgentRuntimeEngine();
        $this->workspaceA = Workspace::create(['name' => 'Mega Retail A', 'slug' => 'mega-retail-a']);
        $this->workspaceB = Workspace::create(['name' => 'Competitor B', 'slug' => 'competitor-b']);

        $this->ownerUserA = User::create([
            'name'         => 'Mahmud Owner',
            'email'        => 'owner.mahmud@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspaceA->id,
            'role'         => 'owner',
        ]);

        $this->staffUserA = User::create([
            'name'         => 'Rahim Staff',
            'email'        => 'staff.rahim@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspaceA->id,
            'role'         => 'staff',
        ]);

        $sccOwnerA = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: $this->ownerUserA->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries'],
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
            userId: $this->ownerUserA->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $this->ownerContextB = AgentContext::fromServerControlledContext($sccOwnerB, user: $this->ownerUserA);

        $this->agent = app(OwnerBusinessAgent::class);

        $this->seedCommercialDatasets();
    }

    private function seedCommercialDatasets(): void
    {
        $today = Carbon::now()->toDateString();

        // 1. Customers
        $cust1 = AnalyticsCustomer::create([
            'workspace_id'    => $this->workspaceA->id,
            'name'            => 'Shakil Ahmed',
            'email'           => 'shakil@example.com',
            'phone'           => '01811223344',
            'orders_count'    => 5,
            'total_spend'     => 150000.0,
            'current_balance' => 25000.0, // Outstanding
        ]);

        $cust2 = AnalyticsCustomer::create([
            'workspace_id'    => $this->workspaceA->id,
            'name'            => 'Anika Rahman',
            'email'           => 'anika@example.com',
            'phone'           => '01911223344',
            'orders_count'    => 1,
            'total_spend'     => 12000.0,
            'current_balance' => 0.0,
        ]);

        // Customer in Workspace B (for cross-tenant checks)
        AnalyticsCustomer::create([
            'workspace_id'    => $this->workspaceB->id,
            'name'            => 'Competitor Customer',
            'email'           => 'comp@example.com',
            'phone'           => '01311223344',
            'orders_count'    => 10,
            'total_spend'     => 500000.0,
            'current_balance' => 80000.0,
        ]);

        // 2. Products
        $p1 = AnalyticsProduct::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Smart Laptop Pro 15',
            'sku'          => 'SKU-LAP-001',
            'category'     => 'Electronics',
            'unit_price'   => 85000.0,
            'cost_price'   => 65000.0,
            'is_active'    => true,
        ]);

        $p2 = AnalyticsProduct::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Wireless Noise-Cancelling Earbuds',
            'sku'          => 'SKU-EAR-002',
            'category'     => 'Accessories',
            'unit_price'   => 5000.0,
            'cost_price'   => 3000.0,
            'is_active'    => true,
        ]);

        // 3. Salespeople
        $sp1 = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceA->id,
            'name'          => 'Tanvir Islam',
            'employee_code' => 'EMP-TNV-01',
            'email'         => 'tanvir@example.com',
            'phone'         => '01711002233',
        ]);

        $sp2 = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceA->id,
            'name'          => 'Nasrin Sultana',
            'employee_code' => 'EMP-NSR-02',
            'email'         => 'nasrin@example.com',
            'phone'         => '01711004455',
        ]);

        // 4. Orders Today
        $ord1 = AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $cust1->id,
            'salesperson_id' => $sp1->id,
            'order_number'   => 'ORD-TODAY-01',
            'total_amount'   => 85000.0,
            'discount'       => 0.0,
            'net_amount'     => 85000.0,
            'status'         => 'completed',
            'order_date'     => $today,
        ]);

        AnalyticsOrderItem::create([
            'order_id'   => $ord1->id,
            'product_id' => $p1->id,
            'quantity'   => 1,
            'unit_price' => 85000.0,
            'subtotal'   => 85000.0,
        ]);

        $ord2 = AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $cust2->id,
            'salesperson_id' => $sp2->id,
            'order_number'   => 'ORD-TODAY-02',
            'total_amount'   => 15000.0,
            'discount'       => 0.0,
            'net_amount'     => 15000.0,
            'status'         => 'completed',
            'order_date'     => $today,
        ]);

        AnalyticsOrderItem::create([
            'order_id'   => $ord2->id,
            'product_id' => $p2->id,
            'quantity'   => 3,
            'unit_price' => 5000.0,
            'subtotal'   => 15000.0,
        ]);
    }

    /**
     * Question 1: "আজকের বিক্রি কত?" (Today's total sales and revenue pulse)
     */
    public function test_question_1_todays_sales_and_revenue_pulse(): void
    {
        $tool = app(SalesAnalyticsTool::class);

        $result = $this->engine->executeTool(
            tool: $tool,
            arguments: [
                'start_date' => Carbon::now()->toDateString(),
                'end_date'   => Carbon::now()->toDateString(),
            ],
            context: $this->ownerContextA->withAgent($this->agent),
        );

        $this->assertTrue($result->success);
        $this->assertSame('sales_analytics', $result->metadata['tool_name']);
        $this->assertEquals(100000.0, $result->data['total_revenue']);
        $this->assertEquals(2, $result->data['total_orders']);
        $this->assertStringContainsString('100000', $result->toResponseString());
    }

    /**
     * Question 2: "কোন product সবচেয়ে বেশি বিক্রি হয়েছে?" (Top performing products)
     */
    public function test_question_2_top_performing_products(): void
    {
        $tool = app(ProductPerformanceTool::class);

        $result = $this->engine->executeTool(
            tool: $tool,
            arguments: ['limit' => 5],
            context: $this->ownerContextA->withAgent($this->agent),
        );

        $this->assertTrue($result->success);
        $this->assertSame('product_performance', $result->metadata['tool_name']);
        $this->assertNotEmpty($result->data['products']);
        $this->assertSame('Smart Laptop Pro 15', $result->data['products'][0]['name']);
    }

    /**
     * Question 3: "কোন employee সবচেয়ে বেশি collection করেছে?" (Top salesperson performance)
     */
    public function test_question_3_top_salesperson_performance(): void
    {
        $tool = app(EmployeeAnalyticsTool::class);

        $result = $this->engine->executeTool(
            tool: $tool,
            arguments: ['limit' => 5],
            context: $this->ownerContextA->withAgent($this->agent),
        );

        $this->assertTrue($result->success);
        $this->assertSame('employee_analytics', $result->metadata['tool_name']);
        $this->assertNotEmpty($result->data['staff_members']);
        // Tanvir achieved 85,000, Nasrin achieved 15,000
        $this->assertSame('Tanvir Islam', $result->data['staff_members'][0]['name']);
        $this->assertEquals(85000.0, $result->data['staff_members'][0]['sales_achieved']);
    }

    /**
     * Question 4: "কোন customer-এর outstanding বা বাকি বেশি?" (Customer insights & cohorts)
     */
    public function test_question_4_customer_outstanding_and_cohorts_zero_pii(): void
    {
        $tool = app(CustomerInsightsTool::class);

        $result = $this->engine->executeTool(
            tool: $tool,
            arguments: [],
            context: $this->ownerContextA->withAgent($this->agent),
        );

        $this->assertTrue($result->success);
        $this->assertSame('customer_insights', $result->metadata['tool_name']);
        $this->assertArrayHasKey('top_cohorts', $result->data);
        $this->assertEquals(2, $result->data['total_registered_customers']);
        // Strict Zero-PII check: Output string must not contain phone or email
        $outputStr = $result->toResponseString();
        $this->assertStringNotContainsString('01811223344', $outputStr);
        $this->assertStringNotContainsString('shakil@example.com', $outputStr);
    }

    /**
     * Question 5: "Cash-in, cash-out এবং profit-এর অবস্থা কী?" (Executive P&L and margins)
     */
    public function test_question_5_cash_in_and_profit_margins(): void
    {
        $tool = app(FinancialAnalyticsTool::class);

        // 1. Authorized Owner receives profit margins
        $ownerResult = $this->engine->executeTool(
            tool: $tool,
            arguments: [],
            context: $this->ownerContextA->withAgent($this->agent),
        );

        $this->assertTrue($ownerResult->success);
        $this->assertSame('financial_analytics', $ownerResult->metadata['tool_name']);
        $this->assertEquals(100000.0, $ownerResult->data['net_revenue']);
        $this->assertEquals(26000.0, $ownerResult->data['gross_profit']);
        $this->assertEquals(74000.0, $ownerResult->data['cogs']);

        // 2. Staff without analytics.view_margins is denied execution
        $this->expectException(\App\AI\Exceptions\UnauthorizedToolException::class);
        $this->engine->executeTool(
            tool: $tool,
            arguments: [],
            context: $this->staffContextA->withAgent($this->agent),
        );
    }

    /**
     * Question 6: "বিভিন্ন Excel/document source-এর মধ্যে সম্পর্ক কী?" (Legacy Excel Contract 3.0 virtual gateway)
     */
    public function test_question_6_spreadsheet_analytics_and_relationships_via_contract_3(): void
    {
        $tool = app(LegacyExcelAnalyticsTool::class);

        // When no completed spreadsheet job exists, returns safe graceful fallback
        $result = $this->engine->executeTool(
            tool: $tool,
            arguments: ['question' => 'What are the top expense categories?'],
            context: $this->ownerContextA->withAgent($this->agent),
        );

        $this->assertTrue($result->success);
        $this->assertSame('legacy_excel_analytics', $result->metadata['tool_name']);
        $this->assertStringContainsString('No active spreadsheet datasets found', $result->toResponseString());
    }

    /**
     * Question 7: "কোম্পানির পলিসি বা ডেলিভারি নিয়ম কী?" (Knowledge intelligence search)
     */
    public function test_question_7_knowledge_intelligence_company_policy_search(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/api/v1/search*' => Http::response([
                'success' => true,
                'results' => [
                    [
                        'id' => 'faq_101',
                        'question' => 'What is the return and refund policy?',
                        'answer' => 'Customers can return items within 7 days of delivery with receipt.',
                        'score' => 0.94,
                    ],
                ],
            ], 200),
        ]);

        $tool = app(KnowledgeIntelligenceTool::class);

        $result = $this->engine->executeTool(
            tool: $tool,
            arguments: ['query' => 'What is the refund policy?'],
            context: $this->ownerContextA->withAgent($this->agent),
        );

        $this->assertTrue($result->success);
        $this->assertSame('knowledge_intelligence', $result->metadata['tool_name']);
        $this->assertEquals(1, $result->data['match_count']);
        $this->assertStringContainsString('7 days of delivery', $result->toResponseString());
    }

    /**
     * Cross-tenant isolation verification across tools.
     */
    public function test_cross_tenant_isolation_strictly_enforced(): void
    {
        $tool = app(SalesAnalyticsTool::class);

        // Workspace B has zero orders
        $resultB = $this->engine->executeTool(
            tool: $tool,
            arguments: [],
            context: $this->ownerContextB->withAgent($this->agent),
        );

        $this->assertTrue($resultB->success);
        $this->assertEquals(0.0, $resultB->data['total_revenue']);
        $this->assertEquals(0, $resultB->data['total_orders']);
    }
}
