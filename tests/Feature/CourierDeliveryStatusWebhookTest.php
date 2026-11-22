<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierDeliveryStatusWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $merchantUser;
    private WorkspaceOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->workspace = Workspace::forceCreate([
            'id'            => 888001,
            'name'          => 'Prime Electronics Hub',
            'slug'          => 'prime-electronics-hub',
            'support_phone' => '01711223344',
            'is_active'     => true,
        ]);

        $role = Role::create([
            'name'         => 'prime-merchant-role',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspace->id,
        ]);

        $this->merchantUser = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->merchantUser->assignRole($role);

        $this->order = WorkspaceOrder::create([
            'workspace_id'            => $this->workspace->id,
            'order_number'            => 'ORD-261122-SF01',
            'channel'                 => 'facebook',
            'customer_name'           => 'Rafiqul Islam',
            'customer_phone'          => '01712345678',
            'delivery_address'        => 'House 10, Road 4, Dhanmondi, Dhaka',
            'product_summary'         => 'Mechanical Gaming Keyboard',
            'order_amount'            => 3500.00,
            'status'                  => 'shipped',
            'courier_provider'        => 'steadfast',
            'courier_consignment_id'  => '99887711',
            'courier_tracking_code'   => 'SF99887711',
            'courier_status'          => 'in_review',
        ]);
    }

    public function test_steadfast_webhook_updates_order_status_to_delivered(): void
    {
        $response = $this->postJson(route('api.courier.webhook.steadfast'), [
            'invoice'       => 'ORD-261122-SF01',
            'tracking_code' => 'SF99887711',
            'status'        => 'delivered',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'        => true,
            'order_number'   => 'ORD-261122-SF01',
            'order_status'   => 'delivered',
            'courier_status' => 'delivered',
        ]);

        $this->order->refresh();
        $this->assertSame('delivered', $this->order->status);
        $this->assertSame('delivered', $this->order->courier_status);
        $this->assertStringContainsString('Steadfast Webhook: delivered', (string) $this->order->admin_notes);
    }

    public function test_steadfast_webhook_handles_cancelled_and_returned(): void
    {
        $response = $this->postJson(route('api.courier.webhook.steadfast'), [
            'invoice'       => 'ORD-261122-SF01',
            'tracking_code' => 'SF99887711',
            'status'        => 'cancelled',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'        => true,
            'order_status'   => 'cancelled',
            'courier_status' => 'cancelled',
        ]);

        $this->order->refresh();
        $this->assertSame('cancelled', $this->order->status);
        $this->assertSame('cancelled', $this->order->courier_status);
    }

    public function test_steadfast_webhook_finds_order_by_tracking_code_alone(): void
    {
        $response = $this->postJson(route('api.courier.webhook.steadfast'), [
            'tracking_code' => 'SF99887711',
            'status'        => 'delivered',
        ]);

        $response->assertStatus(200);
        $this->order->refresh();
        $this->assertSame('delivered', $this->order->status);
    }

    public function test_pathao_webhook_updates_order_status(): void
    {
        $this->order->update([
            'courier_provider'       => 'pathao',
            'courier_consignment_id' => 'PATHAO-CONS-77',
            'status'                 => 'shipped',
        ]);

        $response = $this->postJson(route('api.courier.webhook.pathao'), [
            'consignment_id'    => 'PATHAO-CONS-77',
            'merchant_order_id' => 'ORD-261122-SF01',
            'order_status'      => 'Delivered',
        ]);

        $response->assertStatus(200);
        $this->order->refresh();
        $this->assertSame('delivered', $this->order->status);
        $this->assertSame('delivered', $this->order->courier_status);
    }

    public function test_on_demand_sync_courier_endpoint(): void
    {
        $response = $this->actingAs($this->merchantUser)
            ->postJson(route('orders.sync-courier', ['order' => $this->order->id]));

        $response->assertStatus(200);
        $response->assertJson([
            'success'       => true,
            'order_number'  => 'ORD-261122-SF01',
            'tracking_code' => 'SF99887711',
        ]);
        $this->assertStringContainsString('https://steadfast.com.bd/t/SF99887711', $response->json('tracking_url'));
    }
}
