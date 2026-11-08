<?php

declare(strict_types=1);

namespace Tests\Feature\AI\Agent\BO2;

use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsSalesperson;
use App\Models\User;
use App\Models\Workspace;
use App\AI\AgentRuntimeEngine;
use App\AI\Contracts\AgentInterface;
use App\AI\Contracts\ToolInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\AgentResponse;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\AI\Agents\OwnerBusinessAgent;
use App\AI\Policies\ToolAuthorizationPolicy;
use App\AI\Tools\AbstractGovernedTool;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerBusinessAgentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private AgentRuntimeEngine $engine;
    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $ownerUser;
    private User $staffUser;
    private AgentContext $ownerContextA;
    private AgentContext $staffContextA;
    private AgentContext $ownerContextB;
    private OwnerBusinessAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new AgentRuntimeEngine();
        $this->workspaceA = Workspace::create(['name' => 'Alpha WS', 'slug' => 'alpha-ws']);
        $this->workspaceB = Workspace::create(['name' => 'Beta WS', 'slug' => 'beta-ws']);

        $this->ownerUser = User::create([
            'name'         => 'Owner Alpha',
            'email'        => 'owner.alpha@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspaceA->id,
        ]);

        $this->staffUser = User::create([
            'name'         => 'Staff Alpha',
            'email'        => 'staff.alpha@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspaceA->id,
        ]);

        $sccOwnerA = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: $this->ownerUser->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries'],
        );
        $this->ownerContextA = AgentContext::fromServerControlledContext($sccOwnerA, user: $this->ownerUser);

        $sccStaffA = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: $this->staffUser->id,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [], // No financial or HR permissions
        );
        $this->staffContextA = AgentContext::fromServerControlledContext($sccStaffA, user: $this->staffUser);

        $sccOwnerB = new ServerControlledContext(
            workspaceId: $this->workspaceB->id,
            userId: $this->ownerUser->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $this->ownerContextB = AgentContext::fromServerControlledContext($sccOwnerB, user: $this->ownerUser);

        $this->agent = app(OwnerBusinessAgent::class);
    }

    public function test_owner_business_agent_metadata_and_contracts(): void
    {
        $this->assertInstanceOf(AgentInterface::class, $this->agent);
        $this->assertSame('owner_business_agent', $this->agent->getAgentId());
        $this->assertSame('Enterprise Business Intelligence Assistant', $this->agent->getRoleName());
        $this->assertSame(3, $this->agent->maxSteps());

        $tools = $this->agent->getRegisteredTools();
        $this->assertCount(10, $tools);

        $expectedToolNames = [
            'business_overview',
            'sales_analytics',
            'product_performance',
            'inventory_analytics',
            'customer_insights',
            'financial_analytics',
            'employee_analytics',
            'owner_support_insights',
            'knowledge_intelligence',
            'legacy_excel_analytics',
        ];

        $actualToolNames = array_map(fn (ToolInterface $t) => $t->name(), $tools);
        $this->assertSame($expectedToolNames, $actualToolNames);

        foreach ($tools as $tool) {
            $this->assertInstanceOf(AbstractGovernedTool::class, $tool);
            $this->assertTrue($tool->isReadOnly(), "Tool {$tool->name()} must be strictly read-only.");
        }

        $instructions = (string) $this->agent->instructions();
        $this->assertStringContainsString('Enterprise Business Intelligence Assistant', $instructions);
        $this->assertStringContainsString('business_overview', $instructions);
        $this->assertStringContainsString('legacy_excel_analytics', $instructions);
    }

    public function test_all_10_tools_are_whitelisted_on_agent_policy(): void
    {
        $policy = app(ToolAuthorizationPolicy::class);
        $boundContext = $this->ownerContextA->withAgent($this->agent);

        foreach ($this->agent->getRegisteredTools() as $tool) {
            $isAuthorized = $policy->authorize($this->agent, $tool, $boundContext);
            $this->assertTrue($isAuthorized, "Tool {$tool->name()} must be authorized for owner on owner_business_agent.");
        }
    }

    public function test_runtime_engine_runs_turn_with_owner_business_agent(): void
    {
        OwnerBusinessAgent::fake([
            'Enterprise Business Intelligence Assistant: Operational pulse healthy and revenue trending upward.',
        ]);

        $response = $this->engine->run(
            agent: $this->agent,
            userPrompt: 'Provide an overview of our sales and performance this month.',
            context: $this->ownerContextA,
        );

        $this->assertTrue($response->isSuccess());
        $this->assertSame(AgentResponse::STATUS_SUCCESS, $response->status);
        $this->assertStringContainsString('Enterprise Business Intelligence Assistant', $response->content);
        $this->assertSame($this->ownerContextA->correlationId, $response->telemetry['correlation_id']);
        $this->assertArrayHasKey('elapsed_ms', $response->telemetry);
        $this->assertFalse(AgentContext::hasCurrent());
    }

    public function test_multi_tool_invocation_under_runtime_engine_with_dlp_owner_vs_staff(): void
    {
        // Seed customer and salesperson
        $customer = AnalyticsCustomer::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Fatima Begum',
            'email'        => 'fatima@example.com',
            'phone'        => '01711223344',
        ]);

        $salesperson = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceA->id,
            'name'          => 'Zahid Hasan',
            'employee_code' => 'EMP-ZH-01',
            'email'         => 'zahid@example.com',
        ]);

        // Seed orders
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $customer->id,
            'salesperson_id' => $salesperson->id,
            'order_number'   => 'ORD-ALPHA-101',
            'total_amount'   => 5000.00,
            'net_amount'     => 5000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->subDays(2)->toDateString(),
        ]);

        $tools = [];
        foreach ($this->agent->getRegisteredTools() as $t) {
            $tools[$t->name()] = $t;
        }

        // 1. Execute business_overview for Owner
        $resultOverviewOwner = $this->engine->executeTool(
            tool: $tools['business_overview'],
            arguments: ['date_from' => Carbon::now()->subDays(7)->toDateString(), 'date_to' => Carbon::now()->toDateString()],
            context: $this->ownerContextA,
            agent: $this->agent,
        );

        $this->assertTrue($resultOverviewOwner->success);
        $this->assertSame(5000.00, (float) $resultOverviewOwner->data['gross_sales']);
        $this->assertSame(1, $resultOverviewOwner->data['order_count']);
        $this->assertArrayHasKey('cogs', $resultOverviewOwner->data);
        $this->assertArrayHasKey('gross_margin', $resultOverviewOwner->data);

        // 2. Execute business_overview for Staff (Non-financial DLP)
        $resultOverviewStaff = $this->engine->executeTool(
            tool: $tools['business_overview'],
            arguments: ['date_from' => Carbon::now()->subDays(7)->toDateString(), 'date_to' => Carbon::now()->toDateString()],
            context: $this->staffContextA,
            agent: $this->agent,
        );

        $this->assertTrue($resultOverviewStaff->success);
        $this->assertSame(5000.00, (float) $resultOverviewStaff->data['gross_sales']);
        $this->assertSame(1, $resultOverviewStaff->data['order_count']);
        // Sensitive financial fields MUST BE SCRUBBED by DataProjectionPolicy
        $this->assertArrayNotHasKey('cogs', $resultOverviewStaff->data);
        $this->assertArrayNotHasKey('gross_margin', $resultOverviewStaff->data);
        $this->assertArrayNotHasKey('gross_margin_rate', $resultOverviewStaff->data);

        // 3. Execute customer_insights for Staff
        $resultCustomerStaff = $this->engine->executeTool(
            tool: $tools['customer_insights'],
            arguments: [],
            context: $this->staffContextA,
            agent: $this->agent,
        );

        $this->assertTrue($resultCustomerStaff->success);
        $this->assertSame(1, $resultCustomerStaff->data['total_registered_customers']);
        $this->assertSame(5000.00, (float) $resultCustomerStaff->data['total_revenue']);
        // PII check: customer name, email, phone MUST NOT appear in customer_insights
        $json = json_encode($resultCustomerStaff->data);
        $this->assertStringNotContainsString('Fatima', $json);
        $this->assertStringNotContainsString('01711223344', $json);
        $this->assertStringNotContainsString('fatima@example.com', $json);
    }

    public function test_tenant_isolation_across_tools(): void
    {
        $salespersonA = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceA->id,
            'name'          => 'Sales Alpha',
            'employee_code' => 'EMP-A-01',
        ]);
        $salespersonB = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceB->id,
            'name'          => 'Sales Beta',
            'employee_code' => 'EMP-B-01',
        ]);

        $customerA = AnalyticsCustomer::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Customer Alpha',
        ]);
        $customerB = AnalyticsCustomer::create([
            'workspace_id' => $this->workspaceB->id,
            'name'         => 'Customer Beta',
        ]);

        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $customerA->id,
            'salesperson_id' => $salespersonA->id,
            'order_number'   => 'ORD-A-1',
            'total_amount'   => 12000.00,
            'net_amount'     => 12000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->subDays(1)->toDateString(),
        ]);

        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceB->id,
            'customer_id'    => $customerB->id,
            'salesperson_id' => $salespersonB->id,
            'order_number'   => 'ORD-B-1',
            'total_amount'   => 3500.00,
            'net_amount'     => 3500.00,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->subDays(1)->toDateString(),
        ]);

        $overviewTool = $this->agent->getRegisteredTools()[0]; // business_overview

        // Workspace A context
        $resA = $this->engine->executeTool(
            tool: $overviewTool,
            arguments: ['date_from' => Carbon::now()->subDays(7)->toDateString(), 'date_to' => Carbon::now()->toDateString()],
            context: $this->ownerContextA,
            agent: $this->agent,
        );

        $this->assertTrue($resA->success);
        $this->assertSame(12000.00, (float) $resA->data['gross_sales']);
        $this->assertSame(1, $resA->data['order_count']);

        // Workspace B context
        $resB = $this->engine->executeTool(
            tool: $overviewTool,
            arguments: ['date_from' => Carbon::now()->subDays(7)->toDateString(), 'date_to' => Carbon::now()->toDateString()],
            context: $this->ownerContextB,
            agent: $this->agent,
        );

        $this->assertTrue($resB->success);
        $this->assertSame(3500.00, (float) $resB->data['gross_sales']);
        $this->assertSame(1, $resB->data['order_count']);
    }

    public function test_sla_fast_fail_under_runtime_engine(): void
    {
        $overviewTool = $this->agent->getRegisteredTools()[0];

        // Expired context (timeout 1ms + sleep)
        $sccExpired = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: $this->ownerUser->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $expiredContext = AgentContext::fromServerControlledContext(
            $sccExpired,
            user: $this->ownerUser,
            timeoutSeconds: 0.001
        );
        usleep(3000); // 3ms

        // Engine run turn
        $turnResponse = $this->engine->run(
            agent: $this->agent,
            userPrompt: 'Need fast numbers',
            context: $expiredContext,
        );

        $this->assertTrue($turnResponse->isTimedOut());
        $this->assertSame(AgentResponse::STATUS_TIMEOUT, $turnResponse->status);

        // Direct tool invocation under expired context throws AgentExecutionTimeoutException
        $this->expectException(AgentExecutionTimeoutException::class);
        $this->expectExceptionMessage('exhausted');

        $this->engine->executeTool(
            tool: $overviewTool,
            arguments: [],
            context: $expiredContext,
            agent: $this->agent,
        );
    }
}
