<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\Permissions\ConversationPermission;
use App\Enums\RoleEnum;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Courier\CourierManager;
use App\Services\Courier\DTO\CourierBookingResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceCourierIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Workspace $otherWorkspace;
    private User $owner;
    private User $otherOwner;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::WORKSPACE_AGENT->value, 'guard_name' => 'web']);

        $this->workspace = Workspace::create([
            'name'      => 'Ananya Fashion Hub',
            'slug'      => 'ananya-fashion',
            'is_active' => true,
            'settings'  => [
                'courier' => [
                    'default_provider' => 'steadfast',
                    'steadfast' => [
                        'api_key'    => 'mock_stdf_key',
                        'secret_key' => 'mock_stdf_secret',
                        'is_active'  => true,
                    ],
                ],
            ],
        ]);

        $this->otherWorkspace = Workspace::create([
            'name'      => 'Other Store',
            'slug'      => 'other-store',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email'        => 'merchant@ananya.com',
        ]);
        $this->owner->assignRole(RoleEnum::ADMIN->value);

        $this->otherOwner = User::factory()->create([
            'workspace_id' => $this->otherWorkspace->id,
            'email'        => 'merchant@other.com',
        ]);
        $this->otherOwner->assignRole(RoleEnum::ADMIN->value);

        $this->staff = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email'        => 'agent@ananya.com',
        ]);
        $this->staff->assignRole(RoleEnum::WORKSPACE_AGENT->value);
    }

    public function test_owner_can_view_courier_settings_page(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('workspace.courier.settings'));
        $response->assertOk();
        $response->assertSee('Courier Logistics', false);
        $response->assertSee('Steadfast Courier', false);
        $response->assertSee('Pathao Courier', false);
        $response->assertSee('RedX Logistics', false);
    }

    public function test_agent_cannot_access_courier_settings_page(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get(route('workspace.courier.settings'));
        $response->assertStatus(403);
    }

    public function test_owner_can_update_courier_settings(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post(route('workspace.courier.update'), [
            'default_provider' => 'steadfast',
            'steadfast' => [
                'api_key'    => 'live_key_123',
                'secret_key' => 'live_secret_456',
                'is_active'  => 1,
            ],
        ]);

        $response->assertRedirect(route('workspace.courier.settings'));

        $this->workspace->refresh();
        $settings = $this->workspace->settings['courier'];
        $this->assertEquals('live_key_123', $settings['steadfast']['api_key']);
        $this->assertEquals('live_secret_456', $settings['steadfast']['secret_key']);
    }

    public function test_ping_endpoint_returns_json_result(): void
    {
        $this->actingAs($this->owner);

        Http::fake([
            'https://portal.packzy.com/api/v1/get_balance' => Http::response([
                'status'          => 200,
                'current_balance' => 1500.50,
            ], 200),
        ]);

        $response = $this->postJson(route('workspace.courier.ping'), [
            'provider'    => 'steadfast',
            'credentials' => [
                'api_key'    => 'test_key',
                'secret_key' => 'test_secret',
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'balance' => 1500.50,
        ]);
    }

    public function test_single_order_dispatch_calls_steadfast_and_updates_order(): void
    {
        $this->actingAs($this->owner);

        $order = WorkspaceOrder::create([
            'workspace_id'     => $this->workspace->id,
            'order_number'     => 'ORD-2026-TEST01',
            'channel'          => 'facebook',
            'customer_name'    => 'Sarah Khan',
            'customer_phone'   => '01711223344',
            'delivery_address' => 'House 10, Dhanmondi, Dhaka',
            'product_summary'  => 'Jamdani Saree Blue',
            'order_amount'     => 3500.00,
            'status'           => 'pending',
        ]);

        Http::fake([
            'https://portal.packzy.com/api/v1/create_order' => Http::response([
                'status'  => 200,
                'message' => 'Order created successfully',
                'consignment' => [
                    'consignment_id' => 987654,
                    'invoice'        => 'ORD-2026-TEST01',
                    'tracking_code'  => 'STDF987654',
                    'status'         => 'in_review',
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('orders.send-courier', $order->id), [
            'provider' => 'steadfast',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success'       => true,
            'tracking_code' => 'STDF987654',
            'provider'      => 'steadfast',
        ]);

        $order->refresh();
        $this->assertEquals('steadfast', $order->courier_provider);
        $this->assertEquals('987654', $order->courier_consignment_id);
        $this->assertEquals('STDF987654', $order->courier_tracking_code);
        $this->assertEquals('shipped', $order->status);
        $this->assertTrue($order->isDispatchedToCourier());
    }

    public function test_bulk_order_dispatch_processes_multiple_orders(): void
    {
        $this->actingAs($this->owner);

        $order1 = WorkspaceOrder::create([
            'workspace_id'     => $this->workspace->id,
            'order_number'     => 'ORD-BULK-01',
            'channel'          => 'facebook',
            'customer_name'    => 'Customer One',
            'customer_phone'   => '01711000001',
            'delivery_address' => 'Mirpur 10, Dhaka',
            'product_summary'  => 'Silk Shirt',
            'order_amount'     => 1200.00,
            'status'           => 'confirmed',
        ]);

        $order2 = WorkspaceOrder::create([
            'workspace_id'     => $this->workspace->id,
            'order_number'     => 'ORD-BULK-02',
            'channel'          => 'whatsapp',
            'customer_name'    => 'Customer Two',
            'customer_phone'   => '01811000002',
            'delivery_address' => 'Gulshan 2, Dhaka',
            'product_summary'  => 'Cotton Kurti',
            'order_amount'     => 1800.00,
            'status'           => 'confirmed',
        ]);

        Http::fake([
            'https://portal.packzy.com/api/v1/create_order' => Http::sequence()
                ->push([
                    'status' => 200,
                    'consignment' => [
                        'consignment_id' => 101,
                        'tracking_code'  => 'STDF-101',
                    ],
                ], 200)
                ->push([
                    'status' => 200,
                    'consignment' => [
                        'consignment_id' => 102,
                        'tracking_code'  => 'STDF-102',
                    ],
                ], 200),
        ]);

        $response = $this->postJson(route('orders.bulk-send-courier'), [
            'order_ids' => [$order1->id, $order2->id],
            'provider'  => 'steadfast',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'summary' => [
                'total'      => 2,
                'successful' => 2,
                'failed'     => 0,
            ],
        ]);

        $order1->refresh();
        $order2->refresh();

        $this->assertEquals('STDF-101', $order1->courier_tracking_code);
        $this->assertEquals('shipped', $order1->status);

        $this->assertEquals('STDF-102', $order2->courier_tracking_code);
        $this->assertEquals('shipped', $order2->status);
    }

    public function test_tenant_isolation_prevents_dispatching_other_workspace_order(): void
    {
        $this->actingAs($this->owner);

        $otherOrder = WorkspaceOrder::create([
            'workspace_id'     => $this->otherWorkspace->id,
            'order_number'     => 'ORD-OTHER-01',
            'channel'          => 'facebook',
            'customer_name'    => 'Hacker Victim',
            'customer_phone'   => '01911000003',
            'delivery_address' => 'Chittagong',
            'order_amount'     => 5000.00,
            'status'           => 'pending',
        ]);

        $response = $this->postJson(route('orders.send-courier', $otherOrder->id), [
            'provider' => 'steadfast',
        ]);

        $response->assertStatus(403);
    }
}
