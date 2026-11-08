<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Agent\Tools;

use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsPayment;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\User;
use App\Models\Workspace;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\AI\Tools\BusinessOverviewTool;
use App\AI\Tools\ProductPerformanceTool;
use App\AI\Tools\SalesAnalyticsTool;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class P0ToolsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $ownerUser;
    private User $staffUser;
    private AgentContext $ownerContext;
    private AgentContext $staffContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Test WS', 'slug' => 'test-ws']);

        $this->ownerUser = User::create([
            'name'         => 'Owner User',
            'email'        => 'owner@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);

        $this->staffUser = User::create([
            'name'         => 'Staff User',
            'email'        => 'staff@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);

        $ownerScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->ownerUser->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $this->ownerContext = AgentContext::fromServerControlledContext($ownerScc, user: $this->ownerUser);

        $staffScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->staffUser->id,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $this->staffContext = AgentContext::fromServerControlledContext($staffScc, user: $this->staffUser);

        $this->seedSampleCommerceData();
    }

    private function seedSampleCommerceData(): void
    {
        $salesperson = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Rahim Seller',
            'employee_code' => 'EMP-001',
            'target_amount' => 50000.00,
            'is_active'     => true,
        ]);

        $customer = AnalyticsCustomer::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Karim Customer',
            'phone'        => '01700000000',
            'email'        => 'karim@example.com',
            'is_active'    => true,
        ]);

        $productA = AnalyticsProduct::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Product Alpha',
            'sku'          => 'SKU-A',
            'category'     => 'Electronics',
            'unit_price'   => 100.00,
            'cost_price'   => 60.00,
            'is_active'    => true,
        ]);

        $productB = AnalyticsProduct::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Product Beta',
            'sku'          => 'SKU-B',
            'category'     => 'Electronics',
            'unit_price'   => 200.00,
            'cost_price'   => 110.00,
            'is_active'    => true,
        ]);

        $order = AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $customer->id,
            'salesperson_id' => $salesperson->id,
            'order_number'   => 'ORD-1001',
            'total_amount'   => 500.00,
            'discount'       => 50.00,
            'net_amount'     => 450.00,
            'status'         => 'completed',
            'order_date'     => Carbon::today()->toDateString(),
        ]);

        AnalyticsOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $productA->id,
            'quantity'   => 2,
            'unit_price' => 100.00,
            'subtotal'   => 200.00,
        ]);

        AnalyticsOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $productB->id,
            'quantity'   => 1,
            'unit_price' => 200.00,
            'subtotal'   => 200.00,
        ]);

        AnalyticsPayment::create([
            'workspace_id'    => $this->workspace->id,
            'order_id'        => $order->id,
            'customer_id'     => $customer->id,
            'salesperson_id'  => $salesperson->id,
            'amount'          => 300.00,
            'payment_method'  => 'bkash',
            'transaction_ref' => 'TRX-12345',
            'collected_at'    => Carbon::today(),
        ]);
    }

    public function test_business_overview_tool_contracts_and_execution_for_owner(): void
    {
        $tool = new BusinessOverviewTool();

        $this->assertSame('business_overview', $tool->name());
        $this->assertSame('business_overview', $tool->getName());
        $this->assertTrue($tool->isReadOnly());
        $this->assertSame([], $tool->getRequiredPermissions());

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['period' => 'today']));
        });

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame(450.00, $result->data['gross_sales']);
        $this->assertSame(1, $result->data['order_count']);
        $this->assertSame(300.00, $result->data['cash_collected']);
        $this->assertSame(150.00, $result->data['outstanding_dues']);
        $this->assertSame(1, $result->data['active_customers']);

        // Owner sees financial metrics
        $this->assertArrayHasKey('cogs', $result->data);
        $this->assertSame(230.00, $result->data['cogs']); // 2*60 + 1*110 = 230
        $this->assertSame(220.00, $result->data['gross_margin']); // 450 - 230 = 220
    }

    public function test_business_overview_tool_strips_margins_for_staff(): void
    {
        $tool = new BusinessOverviewTool();

        $result = AgentContext::withContext($this->staffContext, function () use ($tool) {
            return $tool->handle(new Request(['period' => 'today']));
        });

        $this->assertTrue($result->success);
        $this->assertSame(450.00, $result->data['gross_sales']);
        $this->assertSame(300.00, $result->data['cash_collected']);

        // Unauthorized staff must NOT see financial cost/margins
        $this->assertArrayNotHasKey('cogs', $result->data);
        $this->assertArrayNotHasKey('gross_margin', $result->data);
        $this->assertArrayNotHasKey('gross_margin_rate', $result->data);
    }

    public function test_sales_analytics_tool_aggregation_by_salesperson(): void
    {
        $tool = new SalesAnalyticsTool();

        $this->assertSame('sales_analytics', $tool->name());
        $this->assertTrue($tool->isReadOnly());

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request([
                'group_by'   => 'salesperson',
                'start_date' => Carbon::today()->subDays(5)->toDateString(),
                'end_date'   => Carbon::today()->toDateString(),
            ]));
        });

        $this->assertTrue($result->success);
        $this->assertSame('salesperson', $result->data['group_by']);
        $this->assertSame(450.00, $result->data['total_revenue']);
        $this->assertCount(1, $result->data['groups']);
        $this->assertSame('Rahim Seller', $result->data['groups'][0]['salesperson_name']);
        $this->assertSame('EMP-001', $result->data['groups'][0]['employee_code']);
    }

    public function test_sales_analytics_tool_strips_category_margins_for_staff(): void
    {
        $tool = new SalesAnalyticsTool();

        $result = AgentContext::withContext($this->staffContext, function () use ($tool) {
            return $tool->handle(new Request(['group_by' => 'category']));
        });

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data['groups']);
        $this->assertSame('Electronics', $result->data['groups'][0]['category']);
        $this->assertSame(400.00, $result->data['groups'][0]['revenue']);

        // Non-privileged staff must not see cogs or gross_margin in category groups
        $this->assertArrayNotHasKey('cogs', $result->data['groups'][0]);
        $this->assertArrayNotHasKey('gross_margin', $result->data['groups'][0]);
    }

    public function test_product_performance_tool_ranking_and_dlp(): void
    {
        $tool = new ProductPerformanceTool();

        $this->assertSame('product_performance', $tool->name());
        $this->assertTrue($tool->isReadOnly());

        // 1. Owner execution: sees all metrics including margins
        $ownerResult = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['sort_by' => 'units_sold', 'direction' => 'desc']));
        });

        $this->assertTrue($ownerResult->success);
        $this->assertCount(2, $ownerResult->data['products']);
        $this->assertSame('Product Alpha', $ownerResult->data['products'][0]['name']);
        $this->assertSame(2, $ownerResult->data['products'][0]['units_sold']);
        $this->assertSame(60.00, $ownerResult->data['products'][0]['cost_price']);
        $this->assertSame(120.00, $ownerResult->data['products'][0]['cogs']);
        $this->assertSame(80.00, $ownerResult->data['products'][0]['gross_margin']);

        // 2. Staff execution: cost_price and margins stripped
        $staffResult = AgentContext::withContext($this->staffContext, function () use ($tool) {
            return $tool->handle(new Request(['sort_by' => 'units_sold', 'direction' => 'desc']));
        });

        $this->assertTrue($staffResult->success);
        $this->assertCount(2, $staffResult->data['products']);
        $this->assertSame('Product Alpha', $staffResult->data['products'][0]['name']);
        $this->assertSame(2, $staffResult->data['products'][0]['units_sold']);
        $this->assertArrayNotHasKey('cost_price', $staffResult->data['products'][0]);
        $this->assertArrayNotHasKey('cogs', $staffResult->data['products'][0]);
        $this->assertArrayNotHasKey('gross_margin', $staffResult->data['products'][0]);
    }

    public function test_sla_deadline_fast_fail(): void
    {
        $tool = new BusinessOverviewTool();

        // Context with tiny timeout so remaining is < 0.5s
        $expiredScc = ServerControlledContext::forUser(
            user: $this->ownerUser,
            workspaceId: $this->workspace->id,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
        );
        $expiredContext = AgentContext::fromServerControlledContext($expiredScc, timeoutSeconds: 0.1, user: $this->ownerUser);

        // Sleep 150ms to exceed timeout
        usleep(150000);

        $this->expectException(AgentExecutionTimeoutException::class);
        $tool->execute([], $expiredContext);
    }
}
