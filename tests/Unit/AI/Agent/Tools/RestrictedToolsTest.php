<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Agent\Tools;

use App\Enums\DocumentLifecycleStatus;
use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\KnowledgeDocument;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Tools\EmployeeAnalyticsTool;
use App\AI\Tools\FinancialAnalyticsTool;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class RestrictedToolsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $ownerUser;
    private User $memberUser;
    private AgentContext $ownerContext;
    private AgentContext $memberContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Restricted WS', 'slug' => 'restricted-ws']);

        $this->ownerUser = User::create([
            'name'         => 'Owner User',
            'email'        => 'owner.res@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);

        $this->memberUser = User::create([
            'name'         => 'Member User',
            'email'        => 'member.res@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);

        $ownerScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->ownerUser->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries'],
        );
        $this->ownerContext = AgentContext::fromServerControlledContext($ownerScc, user: $this->ownerUser);

        $memberScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->memberUser->id,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $this->memberContext = AgentContext::fromServerControlledContext($memberScc, user: $this->memberUser);

        $this->seedCommercialDataset();
    }

    private function seedCommercialDataset(): void
    {
        $salesperson = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Top Rep',
            'employee_code' => 'REP-777',
            'target_amount' => 10000.00,
            'is_active'     => true,
        ]);

        $customer = AnalyticsCustomer::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Regular Client',
            'is_active'    => true,
        ]);

        $product = AnalyticsProduct::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'High Margin Device',
            'sku'          => 'DEV-01',
            'unit_price'   => 500.00,
            'cost_price'   => 300.00,
            'is_active'    => true,
        ]);

        $order = AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $customer->id,
            'salesperson_id' => $salesperson->id,
            'order_number'   => 'ORD-RES-1',
            'total_amount'   => 1000.00,
            'discount'       => 100.00,
            'net_amount'     => 900.00,
            'status'         => 'completed',
            'order_date'     => Carbon::today()->toDateString(),
        ]);

        AnalyticsOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 2,
            'unit_price' => 500.00,
            'subtotal'   => 1000.00,
        ]);
    }

    public function test_financial_analytics_tool_requires_permission(): void
    {
        $tool = new FinancialAnalyticsTool();
        $this->assertSame('financial_analytics', $tool->name());
        $this->assertSame(['analytics.view_margins'], $tool->getRequiredPermissions());

        // Member without analytics.view_margins is rejected fail-closed
        $this->expectException(UnauthorizedToolException::class);

        AgentContext::withContext($this->memberContext, function () use ($tool) {
            $tool->handle(new Request());
        });
    }

    public function test_financial_analytics_tool_succeeds_for_authorized_owner(): void
    {
        $tool = new FinancialAnalyticsTool();

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request());
        });

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame(1000.00, $result->data['gross_sales']);
        $this->assertSame(100.00, $result->data['total_discount']);
        $this->assertSame(900.00, $result->data['net_revenue']);
        $this->assertSame(600.00, $result->data['cogs']); // 2 * 300 = 600
        $this->assertSame(300.00, $result->data['gross_profit']); // 900 - 600 = 300
        $this->assertSame(0.3333, $result->data['gross_margin_rate']);
        $this->assertNull($result->data['operating_expenses']);
        $this->assertNull($result->data['net_profit']);
    }

    public function test_employee_analytics_tool_granular_dlp_taxonomy(): void
    {
        $doc = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Payroll Records',
            'original_filename' => 'payroll.xlsx',
            'file_path'         => '/tmp/payroll.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'content_hash'      => 'hash_pay',
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
        ]);

        StructuredRecord::create([
            'workspace_id'          => $this->workspace->id,
            'document_id'           => $doc->id,
            'sheet_name'            => 'Payroll',
            'row_index'             => 1,
            'canonical_person'      => 'Top Rep',
            'canonical_category'    => 'Salary',
            'canonical_amount'      => 35000.00,
            'raw_values'            => ['Name' => 'Top Rep', 'Salary' => 35000],
        ]);

        $tool = new EmployeeAnalyticsTool();
        $this->assertSame('employee_analytics', $tool->name());
        $this->assertTrue($tool->isReadOnly());

        // 1. Staff with NO permissions: sees headcount & targets, but NO compensation
        $noPermResult = AgentContext::withContext($this->memberContext, function () use ($tool) {
            return $tool->handle(new Request());
        });

        $this->assertTrue($noPermResult->success);
        $this->assertSame(1, $noPermResult->data['total_headcount']);
        $this->assertSame('Top Rep', $noPermResult->data['staff_members'][0]['name']);
        $this->assertSame(10000.00, $noPermResult->data['staff_members'][0]['target_amount']);
        $this->assertSame(900.00, $noPermResult->data['staff_members'][0]['sales_achieved']);

        $this->assertArrayNotHasKey('base_salary', $noPermResult->data);
        $this->assertArrayNotHasKey('commission_expense', $noPermResult->data);
        $this->assertArrayNotHasKey('employee_cost', $noPermResult->data);
        $this->assertArrayNotHasKey('base_salary', $noPermResult->data['staff_members'][0]);
        $this->assertArrayNotHasKey('commission_expense', $noPermResult->data['staff_members'][0]);
        $this->assertArrayNotHasKey('employee_cost', $noPermResult->data['staff_members'][0]);

        // 2. Staff with ONLY hr.view_salaries: sees base_salary, but NOT commissions or dual employee_cost
        $hrScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->memberUser->id,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['hr.view_salaries'],
        );
        $hrContext = AgentContext::fromServerControlledContext($hrScc, user: $this->memberUser);

        $hrResult = AgentContext::withContext($hrContext, function () use ($tool) {
            return $tool->handle(new Request());
        });

        $this->assertTrue($hrResult->success);
        $this->assertArrayHasKey('base_salary', $hrResult->data);
        $this->assertSame(35000.00, $hrResult->data['base_salary']);
        $this->assertArrayNotHasKey('commission_expense', $hrResult->data);
        $this->assertArrayNotHasKey('employee_cost', $hrResult->data); // Conjunctive: blocked!

        // 3. Staff with ONLY analytics.view_margins: sees commission_expense, but NOT base_salary or employee_cost
        $finScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->memberUser->id,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $finContext = AgentContext::fromServerControlledContext($finScc, user: $this->memberUser);

        $finResult = AgentContext::withContext($finContext, function () use ($tool) {
            return $tool->handle(new Request());
        });

        $this->assertTrue($finResult->success);
        $this->assertArrayHasKey('commission_expense', $finResult->data);
        $this->assertSame(45.00, $finResult->data['commission_expense']); // 5% of 900
        $this->assertArrayNotHasKey('base_salary', $finResult->data);
        $this->assertArrayNotHasKey('employee_cost', $finResult->data); // Conjunctive: blocked!

        // 4. Staff with BOTH permissions: sees base_salary, commissions, AND dual employee_cost
        $bothScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->memberUser->id,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries'],
        );
        $bothContext = AgentContext::fromServerControlledContext($bothScc, user: $this->memberUser);

        $bothResult = AgentContext::withContext($bothContext, function () use ($tool) {
            return $tool->handle(new Request());
        });

        $this->assertTrue($bothResult->success);
        $this->assertArrayHasKey('base_salary', $bothResult->data);
        $this->assertArrayHasKey('commission_expense', $bothResult->data);
        $this->assertArrayHasKey('employee_cost', $bothResult->data); // Conjunctive: allowed!
        $this->assertSame(35045.00, $bothResult->data['employee_cost']);
    }
}

