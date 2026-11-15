<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OwnerWorkspaceAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $ownerA;
    private User $ownerB;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->workspaceA = Workspace::create([
            'name'      => 'Aura Fashion Ltd',
            'slug'      => 'aura-fashion',
            'currency'  => 'BDT',
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::create([
            'name'      => 'Tech Matrix Ltd',
            'slug'      => 'tech-matrix',
            'currency'  => 'USD',
            'is_active' => true,
        ]);

        $this->ownerA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'ownerA@aura.test',
        ]);
        $this->ownerA->assignRole($this->adminRole);

        $this->ownerB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'email'        => 'ownerB@tech.test',
        ]);
        $this->ownerB->assignRole($this->adminRole);
    }

    public function test_owner_can_view_workspace_settings(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->get(route('workspace.settings.edit'));
        $response->assertOk();
        $response->assertSee('Aura Fashion Ltd');
        $response->assertSee('Workspace Business Profile');
    }

    public function test_owner_can_update_workspace_business_profile(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->post(route('workspace.settings.update'), [
            'name'             => 'Aura Lifestyle & Fashion',
            'currency'         => 'BDT',
            'support_phone'    => '+8801700112233',
            'support_email'    => 'care@auralifestyle.com',
            'business_address' => 'Gulshan-2, Dhaka, Bangladesh',
            'operating_hours'  => [
                'weekdays' => '09:00 AM - 09:00 PM',
                'weekend'  => '10:00 AM - 08:00 PM',
            ],
        ]);

        $response->assertRedirect(route('workspace.settings.edit'));
        $this->workspaceA->refresh();

        $this->assertSame('Aura Lifestyle & Fashion', $this->workspaceA->name);
        $this->assertSame('+8801700112233', $this->workspaceA->support_phone);
        $this->assertSame('care@auralifestyle.com', $this->workspaceA->support_email);
        $this->assertSame('09:00 AM - 09:00 PM', $this->workspaceA->operating_hours['weekdays']);
    }

    public function test_owner_can_view_analytics_dashboard_with_isolated_metrics(): void
    {
        // 1. Seed commerce data for Workspace A
        $customerA = AnalyticsCustomer::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Customer Alpha',
            'orders_count' => 2,
            'is_active'    => true,
        ]);

        $productA = AnalyticsProduct::create([
            'workspace_id'   => $this->workspaceA->id,
            'name'           => 'Cotton Panjabi',
            'sku'            => 'PANJABI-01',
            'unit_price'     => 1500.00,
            'cost_price'     => 900.00,
            'stock_quantity' => 25,
            'is_active'      => true,
        ]);

        $salespersonA = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceA->id,
            'name'          => 'Rahim Sales',
            'employee_code' => 'EMP-01',
            'target_amount' => 50000.00,
            'is_active'     => true,
        ]);

        $orderA = AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $customerA->id,
            'salesperson_id' => $salespersonA->id,
            'order_number'   => 'ORD-A-101',
            'total_amount'   => 3000.00,
            'discount'       => 200.00,
            'net_amount'     => 2800.00,
            'status'         => 'completed',
            'order_date'     => Carbon::today()->toDateString(),
        ]);

        AnalyticsOrderItem::create([
            'order_id'   => $orderA->id,
            'product_id' => $productA->id,
            'quantity'   => 2,
            'unit_price' => 1500.00,
            'subtotal'   => 3000.00,
        ]);

        // 2. Seed rival data for Workspace B (must NOT leak into Workspace A)
        $customerB = AnalyticsCustomer::create([
            'workspace_id' => $this->workspaceB->id,
            'name'         => 'Customer Beta',
            'orders_count' => 5,
            'is_active'    => true,
        ]);

        $salespersonB = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceB->id,
            'name'          => 'Foreign Sales',
            'employee_code' => 'FOR-99',
            'target_amount' => 100000.00,
            'is_active'     => true,
        ]);

        AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceB->id,
            'customer_id'    => $customerB->id,
            'salesperson_id' => $salespersonB->id,
            'order_number'   => 'ORD-B-999',
            'total_amount'   => 50000.00,
            'discount'       => 5000.00,
            'net_amount'     => 45000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::today()->toDateString(),
        ]);

        // 3. Test HTML Visual View for Owner A
        $this->actingAs($this->ownerA);
        $htmlResponse = $this->get(route('analytics.index'));
        $htmlResponse->assertOk();
        $htmlResponse->assertSee('Aura Fashion Ltd');
        $htmlResponse->assertSee('2,800.00'); // Workspace A net sales
        $htmlResponse->assertDontSee('45,000.00'); // Workspace B net sales strictly isolated

        // 4. Test JSON Endpoint for Owner A
        $jsonResponse = $this->getJson(route('analytics.data', ['range' => '30d']));
        $jsonResponse->assertOk();
        $data = $jsonResponse->json('data');

        $this->assertEquals(2800.00, $data['summary']['net_sales']);
        $this->assertEquals(3000.00, $data['summary']['gross_sales']);
        $this->assertEquals(200.00, $data['summary']['total_discount']);
        $this->assertSame(1, $data['summary']['total_orders']);
        $this->assertSame('BDT', $data['currency']);

        // Check top products
        $this->assertCount(1, $data['top_products']);
        $this->assertSame('Cotton Panjabi', $data['top_products'][0]['name']);
        $this->assertEquals(2.0, (float) $data['top_products'][0]['units_sold']);

        // Check sales rep leaderboard
        $this->assertCount(1, $data['sales_reps']);
        $this->assertSame('Rahim Sales', $data['sales_reps'][0]['name']);
        $this->assertEquals(2800.00, (float) $data['sales_reps'][0]['sales']);
    }

    public function test_superadmin_has_unrestricted_access_to_any_workspace_analytics_and_settings(): void
    {
        $superadminRole = Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $superadmin = User::factory()->create([
            'workspace_id' => null, // Superadmin with no specific workspace assignment
            'email'        => 'superadmin@global.test',
        ]);
        $superadmin->assignRole($superadminRole);

        $this->actingAs($superadmin);

        // 1. Superadmin can access analytics without explicit workspace (defaults safely to first workspace)
        $respDefault = $this->get(route('analytics.index'));
        $respDefault->assertOk();

        // 2. Superadmin can explicitly inspect Workspace B analytics
        $respWsB = $this->get(route('analytics.index', ['workspace_id' => $this->workspaceB->id]));
        $respWsB->assertOk();
        $respWsB->assertSee('Tech Matrix Ltd');

        // 3. Superadmin can access and edit settings for any workspace
        $respSettings = $this->get(route('workspace.settings.edit', ['workspace_id' => $this->workspaceB->id]));
        $respSettings->assertOk();
        $respSettings->assertSee('Tech Matrix Ltd');
    }
}
