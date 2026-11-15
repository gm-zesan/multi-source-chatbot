<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Decision;

use App\AI\Decision\ToolRegistry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    private ToolRegistry $registry;
    private Workspace $workspace;
    private User $owner;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(ToolRegistry::class);

        $this->workspace = Workspace::create([
            'name' => 'Tool Registry Test WS',
            'slug' => 'tool-reg-ws',
        ]);

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->owner = User::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Test Owner',
            'email'        => 'owner@toolreg.test',
            'password'     => bcrypt('secret'),
        ]);
        $this->owner->assignRole('owner');

        $this->staff = User::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Test Staff',
            'email'        => 'staff@toolreg.test',
            'password'     => bcrypt('secret'),
        ]);
        $this->staff->assignRole('staff');
    }

    public function test_registry_contains_all_11_governed_tools_by_default(): void
    {
        $expectedTools = [
            'sales_analytics',
            'inventory_analytics',
            'financial_analytics',
            'employee_analytics',
            'customer_insights',
            'product_performance',
            'business_overview',
            'owner_support_insights',
            'knowledge_intelligence',
            'legacy_excel_analytics',
            'customer_order_status',
        ];

        foreach ($expectedTools as $toolName) {
            $this->assertTrue($this->registry->hasTool($toolName), "Registry must contain '{$toolName}'");
            $this->assertNotNull($this->registry->getTool($toolName));
            $this->assertSame($toolName, $this->registry->getTool($toolName)->name());
        }
    }

    public function test_customer_channels_strictly_receive_only_customer_safe_tools(): void
    {
        $permitted = $this->registry->getPermittedToolNames(
            isCustomer: true,
            user: $this->owner, // Even if an owner account is tied, customer channel boundary takes precedence
            workspaceId: $this->workspace->id
        );

        $this->assertSame(['knowledge_intelligence', 'customer_order_status'], $permitted);
        $this->assertNotContains('sales_analytics', $permitted);
        $this->assertNotContains('financial_analytics', $permitted);
        $this->assertNotContains('employee_analytics', $permitted);
    }

    public function test_unauthenticated_requests_strictly_receive_only_customer_safe_tools(): void
    {
        $permitted = $this->registry->getPermittedToolNames(
            isCustomer: false,
            user: null,
            workspaceId: $this->workspace->id
        );

        $this->assertSame(['knowledge_intelligence', 'customer_order_status'], $permitted);
        $this->assertNotContains('sales_analytics', $permitted);
        $this->assertNotContains('financial_analytics', $permitted);
    }

    public function test_non_privileged_staff_strictly_receives_only_customer_safe_tools(): void
    {
        $permitted = $this->registry->getPermittedToolNames(
            isCustomer: false,
            user: $this->staff,
            workspaceId: $this->workspace->id
        );

        $this->assertSame(['knowledge_intelligence', 'customer_order_status'], $permitted);
        $this->assertNotContains('sales_analytics', $permitted);
        $this->assertNotContains('financial_analytics', $permitted);
    }

    public function test_authorized_owner_receives_full_suite_of_governed_tools(): void
    {
        $permitted = $this->registry->getPermittedToolNames(
            isCustomer: false,
            user: $this->owner,
            workspaceId: $this->workspace->id
        );

        $this->assertCount(11, $permitted);
        $this->assertContains('sales_analytics', $permitted);
        $this->assertContains('financial_analytics', $permitted);
        $this->assertContains('legacy_excel_analytics', $permitted);
        $this->assertContains('knowledge_intelligence', $permitted);
        $this->assertContains('customer_order_status', $permitted);
    }

    public function test_operation_allowlist_enforcement(): void
    {
        $this->assertTrue($this->registry->isOperationAllowed('sales_analytics', 'get_sales_summary'));
        $this->assertFalse($this->registry->isOperationAllowed('sales_analytics', 'delete_sales_records'));

        $this->assertTrue($this->registry->isOperationAllowed('legacy_excel_analytics', 'query_spreadsheet'));
        $this->assertFalse($this->registry->isOperationAllowed('legacy_excel_analytics', 'drop_database'));
    }

    public function test_format_tools_for_prompt_includes_only_allowed_tools(): void
    {
        $formattedCustomer = $this->registry->formatToolsForPrompt(['knowledge_intelligence']);
        $this->assertStringContainsString('knowledge_intelligence', $formattedCustomer);
        $this->assertStringNotContainsString('sales_analytics', $formattedCustomer);
        $this->assertStringNotContainsString('financial_analytics', $formattedCustomer);

        $formattedOwner = $this->registry->formatToolsForPrompt(['sales_analytics', 'legacy_excel_analytics']);
        $this->assertStringContainsString('sales_analytics', $formattedOwner);
        $this->assertStringContainsString('legacy_excel_analytics', $formattedOwner);
    }
}
