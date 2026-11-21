<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Commerce\OrderCaptureService;
use App\Services\Courier\CourierManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class E2ECourierAndOrderFulfillmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $merchantA;
    private User $managerA;
    private User $agentA;
    private User $merchantB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Roles
        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::WORKSPACE_MANAGER->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::WORKSPACE_AGENT->value, 'guard_name' => 'web']);

        // 2. Setup Workspace A (Apex Fashion)
        $this->workspaceA = Workspace::create([
            'name'      => 'Apex Fashion Store',
            'slug'      => 'apex-fashion',
            'is_active' => true,
            'settings'  => [
                'courier' => [
                    'default_provider' => 'steadfast',
                    'steadfast' => [
                        'api_key'    => 'stdf_key_apex',
                        'secret_key' => 'stdf_sec_apex',
                        'is_active'  => true,
                    ],
                    'pathao' => [
                        'client_id'     => 'pathao_cid',
                        'client_secret' => 'pathao_sec',
                        'username'      => 'user@apex.com',
                        'password'      => 'secret',
                        'store_id'      => '555',
                        'is_active'     => true,
                    ],
                ],
            ],
        ]);

        // 3. Setup Workspace B (Competitor Store)
        $this->workspaceB = Workspace::create([
            'name'      => 'Rival Store',
            'slug'      => 'rival-store',
            'is_active' => true,
        ]);

        // 4. Users in Workspace A
        $this->merchantA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'owner@apex.com',
        ]);
        $this->merchantA->assignRole(RoleEnum::ADMIN->value);

        $this->managerA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'manager@apex.com',
        ]);
        $this->managerA->assignRole(RoleEnum::WORKSPACE_MANAGER->value);

        $this->agentA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'agent@apex.com',
        ]);
        $this->agentA->assignRole(RoleEnum::WORKSPACE_AGENT->value);

        // 5. Merchant in Workspace B
        $this->merchantB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'email'        => 'owner@rival.com',
        ]);
        $this->merchantB->assignRole(RoleEnum::ADMIN->value);
    }

    /**
     * Test the entire E2E flow from customer conversation to automated order extraction to bulk courier dispatch.
     */
    public function test_complete_end_to_end_conversational_order_and_courier_dispatch_lifecycle(): void
    {
        // ── STEP 1: Simulate Live Customer Conversation ──────────────────
        $channel = Channel::firstOrCreate(
            ['slug' => 'facebook'],
            ['name' => 'Facebook Messenger', 'is_active' => true]
        );

        $channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'channel_id'   => $channel->id,
            'name'         => 'Apex Facebook Page',
            'external_id'  => 'fb_page_123',
            'access_token' => 'mock_token',
            'is_active'    => true,
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'user_fb_999',
            'customer_name'      => 'Sabrina Rahman',
            'last_direction'     => 'inbound',
            'status'             => 'active',
            'last_message_at'    => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Hi, I want to confirm order for Blue Silk Jamdani Saree (৳3,200). Name: Sabrina Rahman, Phone: 01799887766, Address: House 25, Road 8, Dhanmondi, Dhaka.',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Thank you Sabrina! We have received your delivery details for the Blue Silk Jamdani Saree.',
        ]);

        // ── STEP 2: Automated Order & Lead Extraction by AI ──────────────
        $orderService = app(OrderCaptureService::class);
        $extractedOrder = $orderService->extractAndCapture(
            $conversation,
            'Hi, I want to confirm order for Blue Silk Jamdani Saree (৳3,200). Name: Sabrina Rahman, Phone: 01799887766, Address: House 25, Road 8, Dhanmondi, Dhaka.',
            'Thank you Sabrina! We have received your delivery details for the Blue Silk Jamdani Saree.'
        );

        $this->assertNotNull($extractedOrder);
        $this->assertEquals('Sabrina Rahman', $extractedOrder->customer_name);
        $this->assertEquals('01799887766', $extractedOrder->getFormatted11DigitPhone());
        $this->assertStringContainsString('Dhanmondi, Dhaka', $extractedOrder->delivery_address);
        $this->assertEquals('pending', $extractedOrder->status);
        $this->assertFalse($extractedOrder->isDispatchedToCourier());

        // Create a second confirmed order for bulk testing
        $order2 = WorkspaceOrder::create([
            'workspace_id'     => $this->workspaceA->id,
            'order_number'     => 'ORD-2026-E2E02',
            'channel'          => 'whatsapp',
            'customer_name'    => 'Tanvir Ahmed',
            'customer_phone'   => '01811223344',
            'delivery_address' => 'Mirpur DOHS, Dhaka',
            'product_summary'  => 'Formal White Shirt',
            'order_amount'     => 1500.00,
            'status'           => 'confirmed',
        ]);

        // ── STEP 3: Merchant Views Orders on Web Dashboard ──────────────
        $this->actingAs($this->merchantA);

        $orderIndexResponse = $this->get(route('orders.index'));
        $orderIndexResponse->assertOk();
        $orderIndexResponse->assertSee($extractedOrder->order_number);
        $orderIndexResponse->assertSee('Sabrina Rahman');
        $orderIndexResponse->assertSee($order2->order_number);
        $orderIndexResponse->assertSee('Bulk Dispatch to Courier', false);

        // ── STEP 4: Mock Courier API (Steadfast) ──────────────────────────
        Http::fake([
            'https://portal.packzy.com/api/v1/create_order' => Http::sequence()
                ->push([
                    'status' => 200,
                    'consignment' => [
                        'consignment_id' => 991122,
                        'invoice'        => $extractedOrder->order_number,
                        'tracking_code'  => 'STDF991122',
                        'status'         => 'in_review',
                    ],
                ], 200)
                ->push([
                    'status' => 200,
                    'consignment' => [
                        'consignment_id' => 991123,
                        'invoice'        => $order2->order_number,
                        'tracking_code'  => 'STDF991123',
                        'status'         => 'in_review',
                    ],
                ], 200),
        ]);

        // ── STEP 5: Execute Bulk Courier Dispatch ────────────────────────
        $bulkResponse = $this->postJson(route('orders.bulk-send-courier'), [
            'order_ids' => [$extractedOrder->id, $order2->id],
            'provider'  => 'steadfast',
        ]);

        $bulkResponse->assertOk();
        $bulkResponse->assertJson([
            'success' => true,
            'summary' => [
                'total'      => 2,
                'successful' => 2,
                'failed'     => 0,
            ],
        ]);

        // ── STEP 6: Assert Database Persistence & Status Progression ────
        $extractedOrder->refresh();
        $order2->refresh();

        $this->assertEquals('shipped', $extractedOrder->status);
        $this->assertEquals('steadfast', $extractedOrder->courier_provider);
        $this->assertEquals('STDF991122', $extractedOrder->courier_tracking_code);
        $this->assertTrue($extractedOrder->isDispatchedToCourier());
        $this->assertEquals('https://steadfast.com.bd/t/STDF991122', $extractedOrder->getCourierTrackingUrl());

        $this->assertEquals('shipped', $order2->status);
        $this->assertEquals('STDF991123', $order2->courier_tracking_code);
        $this->assertTrue($order2->isDispatchedToCourier());

        // ── STEP 7: Re-attempting Already Dispatched Order (Idempotency Guard) ─
        $duplicateResponse = $this->postJson(route('orders.bulk-send-courier'), [
            'order_ids' => [$extractedOrder->id],
            'provider'  => 'steadfast',
        ]);

        $duplicateResponse->assertOk();
        $duplicateData = $duplicateResponse->json('summary.results.0');
        $this->assertTrue($duplicateData['already_sent']);
        $this->assertEquals('STDF991122', $duplicateData['tracking_code']);

        // ── STEP 8: Multi-Tenant Boundary Security Check ─────────────────
        // Merchant B attempts to dispatch Merchant A's order -> 403 Forbidden
        $this->actingAs($this->merchantB);

        $unauthorizedResponse = $this->postJson(route('orders.send-courier', $extractedOrder->id), [
            'provider' => 'steadfast',
        ]);
        $unauthorizedResponse->assertStatus(403);
    }
}
