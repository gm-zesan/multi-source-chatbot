<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Agent;

use App\Models\SuperAdminGovernanceSession;
use App\Models\User;
use App\AI\Contracts\AgentInterface;
use App\AI\Contracts\ToolInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\AgentResponse;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Policies\ToolAuthorizationPolicy;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;
use Stringable;
use Tests\TestCase;

class ToolAuthorizationPolicyTest extends TestCase
{
    private ToolAuthorizationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ToolAuthorizationPolicy();
    }

    public function test_authorize_rejects_tool_not_whitelisted_on_agent(): void
    {
        $allowedTool = $this->createMockTool('tool_alpha', true, []);
        $unauthorizedTool = $this->createMockTool('tool_beta', true, []);

        $agent = $this->createMockAgent('test_agent', [$allowedTool]);

        $scc = new ServerControlledContext(
            workspaceId: 1,
            userId: 10,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $user = new User(['id' => 10, 'workspace_id' => 1]);
        $user->id = 10;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Tool 'tool_beta' is not registered on agent 'test_agent'");

        $this->policy->authorize($agent, $unauthorizedTool, $context);
    }

    public function test_customer_channel_is_strictly_barred_from_mutating_tools(): void
    {
        $mutatingTool = $this->createMockTool('create_order_action', isReadOnly: false, permissions: []);
        $context = AgentContext::forCustomer(
            workspaceId: 10,
            channel: ServerControlledContext::CHANNEL_CUSTOMER_FACEBOOK,
        );

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Customer channels are not authorized to execute mutating tool 'create_order_action'");

        $this->policy->authorizeToolForContext($mutatingTool, $context);
    }

    public function test_customer_channel_is_strictly_barred_from_privileged_tools(): void
    {
        $privilegedTool = $this->createMockTool('view_financials', isReadOnly: true, permissions: ['analytics.view_margins']);
        $context = AgentContext::forCustomer(
            workspaceId: 10,
            channel: ServerControlledContext::CHANNEL_CUSTOMER_WEB,
        );

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Customer channels are not authorized to execute privileged tool 'view_financials'");

        $this->policy->authorizeToolForContext($privilegedTool, $context);
    }

    public function test_customer_channel_can_invoke_read_only_unprivileged_tool(): void
    {
        $faqTool = $this->createMockTool('faq_retrieval', isReadOnly: true, permissions: []);
        $context = AgentContext::forCustomer(
            workspaceId: 10,
            channel: ServerControlledContext::CHANNEL_CUSTOMER_WEB,
        );

        $authorized = $this->policy->authorizeToolForContext($faqTool, $context);
        $this->assertTrue($authorized);
    }

    public function test_unauthenticated_context_fails_closed_for_non_customer(): void
    {
        $tool = $this->createMockTool('analytics_tool', isReadOnly: true, permissions: []);
        $scc = new ServerControlledContext(
            workspaceId: 1,
            userId: null,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );

        $context = AgentContext::fromServerControlledContext($scc, user: null);

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Unauthenticated execution context rejected for tool 'analytics_tool'");

        $this->policy->authorizeToolForContext($tool, $context);
    }

    public function test_owner_is_authorized_for_analytics_view_margins(): void
    {
        $financialTool = $this->createMockTool('bi_margins', isReadOnly: true, permissions: ['analytics.view_margins']);

        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $user = new User(['id' => 1, 'workspace_id' => 5]);
        $user->id = 1;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $authorized = $this->policy->authorizeToolForContext($financialTool, $context);
        $this->assertTrue($authorized);
    }

    public function test_manager_without_permission_is_denied_analytics_view_margins(): void
    {
        $financialTool = $this->createMockTool('bi_margins', isReadOnly: true, permissions: ['analytics.view_margins']);

        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 2,
            userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [], // No margin permission
        );
        $user = new User(['id' => 2, 'workspace_id' => 5]);
        $user->id = 2;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Missing required permission 'analytics.view_margins' for tool 'bi_margins'");

        $this->policy->authorizeToolForContext($financialTool, $context);
    }

    public function test_manager_with_permission_is_authorized_for_analytics_view_margins(): void
    {
        $financialTool = $this->createMockTool('bi_margins', isReadOnly: true, permissions: ['analytics.view_margins']);

        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 2,
            userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $user = new User(['id' => 2, 'workspace_id' => 5]);
        $user->id = 2;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $authorized = $this->policy->authorizeToolForContext($financialTool, $context);
        $this->assertTrue($authorized);
    }

    public function test_sales_staff_is_denied_financial_tools(): void
    {
        $financialTool = $this->createMockTool('bi_margins', isReadOnly: true, permissions: ['analytics.view_margins']);

        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 3,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $user = new User(['id' => 3, 'workspace_id' => 5]);
        $user->id = 3;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Missing required permission 'analytics.view_margins'");

        $this->policy->authorizeToolForContext($financialTool, $context);
    }

    public function test_superadmin_without_valid_session_is_denied_margins(): void
    {
        $financialTool = $this->createMockTool('bi_margins', isReadOnly: true, permissions: ['analytics.view_margins']);

        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 99,
            userRole: 'superadmin',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            governanceSession: null,
        );
        $user = new User(['id' => 99, 'workspace_id' => 1]);
        $user->id = 99;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("SuperAdmin requires an active, unexpired GovernanceSession");

        $this->policy->authorizeToolForContext($financialTool, $context);
    }

    public function test_superadmin_with_valid_governance_session_is_authorized_for_margins(): void
    {
        $financialTool = $this->createMockTool('bi_margins', isReadOnly: true, permissions: ['analytics.view_margins']);

        $session = new SuperAdminGovernanceSession([
            'actor_id'            => 99,
            'user_id'             => 99,
            'target_workspace_id' => 5,
            'justification'       => 'Authorized security audit SEC-101',
            'read_only_scope'     => true,
            'started_at'          => Carbon::now(),
            'expires_at'          => Carbon::now()->addHour(),
            'revoked_at'          => null,
        ]);

        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 99,
            userRole: 'superadmin',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            governanceSession: $session,
        );
        $user = new User(['id' => 99, 'workspace_id' => 1]);
        $user->id = 99;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $authorized = $this->policy->authorizeToolForContext($financialTool, $context);
        $this->assertTrue($authorized);
    }

    public function test_superadmin_with_expired_governance_session_fails_closed(): void
    {
        $financialTool = $this->createMockTool('bi_margins', isReadOnly: true, permissions: ['analytics.view_margins']);

        $session = new SuperAdminGovernanceSession([
            'actor_id'            => 99,
            'user_id'             => 99,
            'target_workspace_id' => 5,
            'justification'       => 'Old audit session',
            'read_only_scope'     => true,
            'started_at'          => Carbon::now()->subHours(2),
            'expires_at'          => Carbon::now()->subMinute(), // Expired
            'revoked_at'          => null,
        ]);

        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 99,
            userRole: 'superadmin',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            governanceSession: $session,
        );
        $user = new User(['id' => 99, 'workspace_id' => 1]);
        $user->id = 99;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("SuperAdmin requires an active, unexpired GovernanceSession");

        $this->policy->authorizeToolForContext($financialTool, $context);
    }

    public function test_superadmin_with_wrong_principal_session_fails_closed(): void
    {
        $financialTool = $this->createMockTool('bi_margins', isReadOnly: true, permissions: ['analytics.view_margins']);

        $session = new SuperAdminGovernanceSession([
            'actor_id'            => 100, // Different user
            'user_id'             => 100,
            'target_workspace_id' => 5,
            'justification'       => 'Another admin session',
            'read_only_scope'     => true,
            'started_at'          => Carbon::now(),
            'expires_at'          => Carbon::now()->addHour(),
            'revoked_at'          => null,
        ]);

        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 99,
            userRole: 'superadmin',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            governanceSession: $session,
        );
        $user = new User(['id' => 99, 'workspace_id' => 1]);
        $user->id = 99;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("SuperAdmin GovernanceSession principal/workspace mismatch");

        $this->policy->authorizeToolForContext($financialTool, $context);
    }

    public function test_superadmin_with_read_only_session_denied_mutating_tool(): void
    {
        $mutatingTool = $this->createMockTool('cancel_order_tool', isReadOnly: false, permissions: []);

        $session = new SuperAdminGovernanceSession([
            'actor_id'            => 99,
            'user_id'             => 99,
            'target_workspace_id' => 5,
            'justification'       => 'Read-only audit session',
            'read_only_scope'     => true,
            'started_at'          => Carbon::now(),
            'expires_at'          => Carbon::now()->addHour(),
            'revoked_at'          => null,
        ]);

        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 99,
            userRole: 'superadmin',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            governanceSession: $session,
        );
        $user = new User(['id' => 99, 'workspace_id' => 1]);
        $user->id = 99;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("SuperAdmin GovernanceSession has read-only scope; mutating tool 'cancel_order_tool' is forbidden.");

        $this->policy->authorizeToolForContext($mutatingTool, $context);
    }

    // ─── Central Output Projection Tests (BO1-FIND-04) ───────────────────────

    public function test_project_tool_result_strips_cost_price_and_cogs_for_unauthorized_staff(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 3,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [], // No view_margins
        );
        $user = new User(['id' => 3, 'workspace_id' => 5]);
        $user->id = 3;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $rawResult = ToolResult::success([
            'product_name' => 'Premium Shirt',
            'selling_price' => 29.99,
            'cost_price'   => 12.50, // Sensitive
            'cogs'         => 12.50, // Sensitive
            'margin'       => 17.49, // Sensitive
        ]);

        $projected = $this->policy->projectToolResult($rawResult, $context);

        $this->assertTrue($projected->success);
        $this->assertArrayHasKey('product_name', $projected->data);
        $this->assertArrayHasKey('selling_price', $projected->data);
        $this->assertArrayNotHasKey('cost_price', $projected->data);
        $this->assertArrayNotHasKey('cogs', $projected->data);
        $this->assertArrayNotHasKey('margin', $projected->data);

        // Assert string and JSON representations are clean
        $this->assertStringNotContainsString('12.50', (string) $projected);
        $this->assertStringNotContainsString('cost_price', json_encode($projected));
    }

    public function test_project_tool_result_preserves_cost_price_for_authorized_owner(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $user = new User(['id' => 1, 'workspace_id' => 5]);
        $user->id = 1;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $rawResult = ToolResult::success([
            'product_name' => 'Premium Shirt',
            'selling_price' => 29.99,
            'cost_price'   => 12.50,
            'cogs'         => 12.50,
        ]);

        $projected = $this->policy->projectToolResult($rawResult, $context);

        $this->assertTrue($projected->success);
        $this->assertSame(12.50, $projected->data['cost_price']);
        $this->assertSame(12.50, $projected->data['cogs']);
    }

    public function test_project_tool_result_preserves_cost_price_for_manager_with_permission(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 2,
            userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $user = new User(['id' => 2, 'workspace_id' => 5]);
        $user->id = 2;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $rawResult = ToolResult::success([
            'product_name' => 'Premium Shirt',
            'cost_price'   => 12.50,
        ]);

        $projected = $this->policy->projectToolResult($rawResult, $context);

        $this->assertTrue($projected->success);
        $this->assertSame(12.50, $projected->data['cost_price']);
    }

    public function test_project_tool_result_scrubs_nested_records_and_arrays(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 3,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $user = new User(['id' => 3, 'workspace_id' => 5]);
        $user->id = 3;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $rawResult = ToolResult::success([
            'items' => [
                ['sku' => 'A1', 'price' => 50, 'cost_price' => 20],
                ['sku' => 'A2', 'price' => 80, 'cost_price' => 35],
            ],
            'summary' => [
                'total_items' => 2,
                'profit'      => 75,
            ],
        ]);

        $projected = $this->policy->projectToolResult($rawResult, $context);

        $this->assertSame('A1', $projected->data['items'][0]['sku']);
        $this->assertSame(50, $projected->data['items'][0]['price']);
        $this->assertArrayNotHasKey('cost_price', $projected->data['items'][0]);
        $this->assertArrayNotHasKey('cost_price', $projected->data['items'][1]);
        $this->assertSame(2, $projected->data['summary']['total_items']);
        $this->assertArrayNotHasKey('profit', $projected->data['summary']);
    }

    public function test_project_tool_result_redacts_sensitive_lines_in_report_text(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 3,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $user = new User(['id' => 3, 'workspace_id' => 5]);
        $user->id = 3;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $reportText = "Sales Report October\nTotal Revenue: $5000\nCost_Price: $2800\nProfit: $2200\nUnits Sold: 150";
        $rawResult = ToolResult::success(['report' => $reportText]);

        $projected = $this->policy->projectToolResult($rawResult, $context);

        $sanitizedReport = $projected->data['report'];
        $this->assertStringContainsString('Total Revenue: $5000', $sanitizedReport);
        $this->assertStringContainsString('Units Sold: 150', $sanitizedReport);
        $this->assertStringNotContainsString('Cost_Price: $2800', $sanitizedReport);
        $this->assertStringNotContainsString('Profit: $2200', $sanitizedReport);
        $this->assertStringContainsString('[REDACTED]', $sanitizedReport);
    }

    public function test_project_tool_result_sanitizes_metadata_keys(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $user = new User(['id' => 1, 'workspace_id' => 5]);
        $user->id = 1;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $rawResult = ToolResult::success(
            data: ['status' => 'ok'],
            metadata: [
                'execution_time_ms' => 45.2,
                'source'            => 'catalog_db',
                'secret_token'      => 'sensitive_api_token_value',
                'db_password'       => 'secret_db_pass',
            ]
        );

        $projected = $this->policy->projectToolResult($rawResult, $context);

        $this->assertSame(45.2, $projected->metadata['execution_time_ms']);
        $this->assertSame('catalog_db', $projected->metadata['source']);
        $this->assertArrayNotHasKey('secret_token', $projected->metadata);
        $this->assertArrayNotHasKey('db_password', $projected->metadata);
    }

    public function test_manager_with_margins_only_allows_financial_and_redacts_salaries_in_report(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 10,
            userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $user = new User(['id' => 10, 'workspace_id' => 5]);
        $user->id = 10;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $reportText = "Executive Summary\nRevenue: $10000\nGross Margin: 35%\nEmployee Salary: $4500\nMonthly Payroll: $45000\nTotal Profit: $3500";
        $rawResult = ToolResult::success([
            'gross_margin' => 0.35,
            'net_profit'   => 3500,
            'base_salary'  => 4500,
            'hourly_wage'  => 25.0,
            'report'       => $reportText,
        ]);

        $projected = $this->policy->projectToolResult($rawResult, $context);

        // Data arrays: financial allowed, HR stripped
        $this->assertSame(0.35, $projected->data['gross_margin']);
        $this->assertSame(3500, $projected->data['net_profit']);
        $this->assertArrayNotHasKey('base_salary', $projected->data);
        $this->assertArrayNotHasKey('hourly_wage', $projected->data);

        // Report text: margins preserved, salaries redacted
        $sanitized = $projected->data['report'];
        $this->assertStringContainsString('Revenue: $10000', $sanitized);
        $this->assertStringContainsString('Gross Margin: 35%', $sanitized);
        $this->assertStringContainsString('Total Profit: $3500', $sanitized);
        $this->assertStringNotContainsString('Employee Salary: $4500', $sanitized);
        $this->assertStringNotContainsString('Monthly Payroll: $45000', $sanitized);
        $this->assertStringContainsString('[REDACTED]', $sanitized);
    }

    public function test_manager_with_hr_only_allows_salaries_and_redacts_margins_in_report(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 11,
            userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['hr.view_salaries'],
        );
        $user = new User(['id' => 11, 'workspace_id' => 5]);
        $user->id = 11;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $reportText = "Executive Summary\nRevenue: $10000\nGross Margin: 35%\nEmployee Salary: $4500\nMonthly Payroll: $45000\nTotal Profit: $3500";
        $rawResult = ToolResult::success([
            'gross_margin' => 0.35,
            'net_profit'   => 3500,
            'base_salary'  => 4500,
            'hourly_wage'  => 25.0,
            'report'       => $reportText,
        ]);

        $projected = $this->policy->projectToolResult($rawResult, $context);

        // Data arrays: HR allowed, financial stripped
        $this->assertArrayNotHasKey('gross_margin', $projected->data);
        $this->assertArrayNotHasKey('net_profit', $projected->data);
        $this->assertSame(4500, $projected->data['base_salary']);
        $this->assertSame(25.0, $projected->data['hourly_wage']);

        // Report text: salaries preserved, margins redacted
        $sanitized = $projected->data['report'];
        $this->assertStringContainsString('Revenue: $10000', $sanitized);
        $this->assertStringContainsString('Employee Salary: $4500', $sanitized);
        $this->assertStringContainsString('Monthly Payroll: $45000', $sanitized);
        $this->assertStringNotContainsString('Gross Margin: 35%', $sanitized);
        $this->assertStringNotContainsString('Total Profit: $3500', $sanitized);
        $this->assertStringContainsString('[REDACTED]', $sanitized);
    }

    public function test_manager_with_both_permissions_allows_both_categories(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 12,
            userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries'],
        );
        $user = new User(['id' => 12, 'workspace_id' => 5]);
        $user->id = 12;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $reportText = "Revenue: $10000\nGross Margin: 35%\nStaff Salary: $4500\n";
        $rawResult = ToolResult::success([
            'gross_margin' => 0.35,
            'base_salary'  => 4500,
            'report'       => $reportText,
        ]);

        $projected = $this->policy->projectToolResult($rawResult, $context);

        $this->assertSame(0.35, $projected->data['gross_margin']);
        $this->assertSame(4500, $projected->data['base_salary']);
        $this->assertSame($reportText, $projected->data['report']);
    }

    public function test_sales_staff_with_no_permissions_redacts_both_categories_in_report_and_data(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 13,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $user = new User(['id' => 13, 'workspace_id' => 5]);
        $user->id = 13;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $reportText = "Revenue: $10000\nGross Margin: 35%\nStaff Salary: $4500\n";
        $rawResult = ToolResult::success([
            'gross_margin' => 0.35,
            'base_salary'  => 4500,
            'report'       => $reportText,
        ]);

        $projected = $this->policy->projectToolResult($rawResult, $context);

        $this->assertArrayNotHasKey('gross_margin', $projected->data);
        $this->assertArrayNotHasKey('base_salary', $projected->data);
        $this->assertStringNotContainsString('Gross Margin: 35%', $projected->data['report']);
        $this->assertStringNotContainsString('Staff Salary: $4500', $projected->data['report']);
        $this->assertStringContainsString('Revenue: $10000', $projected->data['report']);
    }

    public function test_compound_and_variant_case_keys_are_classified_correctly(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 14,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $user = new User(['id' => 14, 'workspace_id' => 5]);
        $user->id = 14;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $compoundFinancial = ['gross_margin', 'net_margin', 'gross_profit', 'net_profit', 'grossMargin', 'netMargin', 'item_cost_price'];
        foreach ($compoundFinancial as $key) {
            $this->assertTrue($this->policy->isFieldRestricted($key, $context), "Expected {$key} to be restricted");
        }

        $compoundHr = ['base_salary', 'hourly_wage', 'baseSalary', 'hourlyWage', 'hourly-wage', 'employee_compensation'];
        foreach ($compoundHr as $key) {
            $this->assertTrue($this->policy->isFieldRestricted($key, $context), "Expected {$key} to be restricted");
        }

        // Unrelated fields must not be falsely restricted
        $unrelated = ['product_name', 'sku', 'category', 'description', 'created_at'];
        foreach ($unrelated as $key) {
            $this->assertFalse($this->policy->isFieldRestricted($key, $context), "Expected {$key} NOT to be restricted");
        }
    }

    public function test_extended_vocabulary_financial_and_hr_fields_are_classified_and_restricted(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 15,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $user = new User(['id' => 15, 'workspace_id' => 5]);
        $user->id = 15;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $financialVocabulary = [
            'cost_of_goods',
            'cost_of_goods_sold',
            'wholesale_price',
            'wholesale-price',
            'margin_rate',
            'expense_cost',
            'procurement_cost',
        ];
        foreach ($financialVocabulary as $key) {
            $this->assertSame(
                ToolAuthorizationPolicy::SENSITIVITY_FINANCIAL,
                $this->policy->classifyField($key),
                "Expected {$key} to be classified as FINANCIAL"
            );
            $this->assertTrue(
                $this->policy->isFieldRestricted($key, $context),
                "Expected {$key} to be restricted for unprivileged staff"
            );
        }

        $hrVocabulary = [
            'hourly_rate',
            'wages',
            'overtime_pay',
            'take_home_pay',
            'worker_pay',
            'payroll_tax',
            'salary_expense',
        ];
        foreach ($hrVocabulary as $key) {
            $this->assertSame(
                ToolAuthorizationPolicy::SENSITIVITY_HR,
                $this->policy->classifyField($key),
                "Expected {$key} to be classified as HR"
            );
            $this->assertTrue(
                $this->policy->isFieldRestricted($key, $context),
                "Expected {$key} to be restricted for unprivileged staff"
            );
        }
    }

    public function test_false_positive_fields_are_not_restricted(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 16,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $user = new User(['id' => 16, 'workspace_id' => 5]);
        $user->id = 16;
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $falsePositives = [
            'non_profit_status',
            'profitability_score',
            'loyalty_points',
            'customer_bonus_points',
            'revenue',
            'order_count',
            'product_count',
            'sales_count',
        ];

        foreach ($falsePositives as $key) {
            $this->assertSame(
                ToolAuthorizationPolicy::SENSITIVITY_PUBLIC,
                $this->policy->classifyField($key),
                "Expected {$key} to be classified as PUBLIC"
            );
            $this->assertFalse(
                $this->policy->isFieldRestricted($key, $context),
                "Expected {$key} NOT to be restricted"
            );
        }
    }

    public function test_conjunctive_dual_permission_enforcement_for_employee_cost(): void
    {
        $dualFields = ['employee_cost', 'labor_cost', 'staff_expense', 'worker_cost'];

        foreach ($dualFields as $field) {
            $this->assertSame(
                ToolAuthorizationPolicy::SENSITIVITY_FINANCIAL_AND_HR,
                $this->policy->classifyField($field),
                "Expected {$field} to be classified as FINANCIAL_AND_HR"
            );
        }

        $user = new User(['id' => 20, 'workspace_id' => 5]);
        $user->id = 20;

        // 1. Manager with NO permissions -> restricted
        $sccNone = new ServerControlledContext(
            workspaceId: 5, userId: 20, userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $ctxNone = AgentContext::fromServerControlledContext($sccNone, user: $user);
        foreach ($dualFields as $field) {
            $this->assertTrue($this->policy->isFieldRestricted($field, $ctxNone));
        }

        // 2. Manager with ONLY analytics.view_margins -> restricted (must not leak workforce cost)
        $sccMarginsOnly = new ServerControlledContext(
            workspaceId: 5, userId: 20, userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $ctxMarginsOnly = AgentContext::fromServerControlledContext($sccMarginsOnly, user: $user);
        foreach ($dualFields as $field) {
            $this->assertTrue(
                $this->policy->isFieldRestricted($field, $ctxMarginsOnly),
                "Expected {$field} to be restricted when only analytics.view_margins is granted"
            );
        }

        // 3. Manager with ONLY hr.view_salaries -> restricted (must not leak financial cost)
        $sccHrOnly = new ServerControlledContext(
            workspaceId: 5, userId: 20, userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['hr.view_salaries'],
        );
        $ctxHrOnly = AgentContext::fromServerControlledContext($sccHrOnly, user: $user);
        foreach ($dualFields as $field) {
            $this->assertTrue(
                $this->policy->isFieldRestricted($field, $ctxHrOnly),
                "Expected {$field} to be restricted when only hr.view_salaries is granted"
            );
        }

        // 4. Manager with BOTH permissions -> ALLOWED
        $sccBoth = new ServerControlledContext(
            workspaceId: 5, userId: 20, userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries'],
        );
        $ctxBoth = AgentContext::fromServerControlledContext($sccBoth, user: $user);
        foreach ($dualFields as $field) {
            $this->assertFalse(
                $this->policy->isFieldRestricted($field, $ctxBoth),
                "Expected {$field} to be allowed when BOTH permissions are granted"
            );
        }

        // 5. Workspace Owner -> ALLOWED
        $sccOwner = new ServerControlledContext(
            workspaceId: 5, userId: 1, userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $ownerUser = new User(['id' => 1, 'workspace_id' => 5]);
        $ownerUser->id = 1;
        $ctxOwner = AgentContext::fromServerControlledContext($sccOwner, user: $ownerUser);
        foreach ($dualFields as $field) {
            $this->assertFalse($this->policy->isFieldRestricted($field, $ctxOwner));
        }
    }

    public function test_normalizer_handles_mixed_case_kebab_case_and_dot_notation(): void
    {
        $user = new User(['id' => 30, 'workspace_id' => 5]);
        $user->id = 30;
        $scc = new ServerControlledContext(
            workspaceId: 5, userId: 30, userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $context = AgentContext::fromServerControlledContext($scc, user: $user);

        $testCases = [
            'cOsT_pRiCe'          => 'cost_price',
            'product.cost_price'  => 'product_cost_price',
            'staff.employee_cost' => 'staff_employee_cost',
            'COST_PRICE'          => 'cost_price',
            'Wholesale-Price'     => 'wholesale_price',
            'hourly-rate'         => 'hourly_rate',
            'GrossMarginRate'     => 'gross_margin_rate',
        ];

        foreach ($testCases as $input => $expectedNormalized) {
            $normalized = $this->policy->normalizeFieldName($input);
            $this->assertSame($expectedNormalized, $normalized, "Failed normalizing '{$input}'");
            $this->assertTrue(
                $this->policy->isFieldRestricted($input, $context),
                "Expected '{$input}' to be restricted for staff"
            );
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createMockTool(string $name, bool $isReadOnly, array $permissions): ToolInterface
    {
        return new class($name, $isReadOnly, $permissions) implements ToolInterface {
            public function __construct(
                private readonly string $toolName,
                private readonly bool $readOnly,
                private readonly array $perms,
            ) {}

            public function name(): string { return $this->toolName; }
            public function getName(): string { return $this->toolName; }
            public function isReadOnly(): bool { return $this->readOnly; }
            public function getRequiredPermissions(): array { return $this->perms; }
            public function description(): Stringable|string { return 'Mock Tool'; }
            public function schema(JsonSchema $schema): array { return []; }
            public function handle(Request $request): Stringable|string { return 'ok'; }
            public function execute(array $arguments, AgentContext $context): ToolResult {
                return ToolResult::success(['mock' => true]);
            }
        };
    }

    private function createMockAgent(string $agentId, array $tools): AgentInterface
    {
        return new class($agentId, $tools) implements AgentInterface {
            public function __construct(
                private readonly string $id,
                private readonly array $registeredTools,
            ) {}

            public function getAgentId(): string { return $this->id; }
            public function getRoleName(): string { return 'Mock Agent'; }
            public function getSystemPrompt(AgentContext $context): string { return 'Prompt'; }
            public function getRegisteredTools(): array { return $this->registeredTools; }
            public function maxSteps(): int { return 3; }
            public function onTurnStart(AgentContext $context): void {}
            public function onTurnEnd(AgentContext $context, AgentResponse $response): void {}
        };
    }
}
