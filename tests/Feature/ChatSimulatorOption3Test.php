<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Models\OrderFeedback;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatSimulatorOption3Test extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $superadmin;
    private User $regularAdmin;
    private User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'workspace_agent', 'guard_name' => 'web']);

        $this->workspace = Workspace::create([
            'id' => 10,
            'name' => 'Apex Test Enterprise',
            'slug' => 'apex-test-enterprise',
        ]);

        $this->superadmin = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->superadmin->assignRole('superadmin');

        $this->regularAdmin = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->regularAdmin->assignRole('admin');

        $this->staffUser = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->staffUser->assignRole('workspace_agent');
    }

    public function test_simulator_page_renders_phase3_agents_and_scenarios_for_superadmin(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/dashboard/simulator');

        $response->assertStatus(200);
        $response->assertSee('value="whatsapp"', false);
        $response->assertSee('WhatsApp Cloud API Channel', false);
        $response->assertSee('value="csat"', false);
        $response->assertSee('Post-Delivery CSAT Feedback', false);
        $response->assertSee('value="catalog_sync"', false);
        $response->assertSee('Shopify / WooCommerce Bridge', false);

        // Quick Scenario Lab UI
        $response->assertSee('id="quickScenariosModal"', false);
        $response->assertSee('runScenario(\'whatsapp\'', false);
        $response->assertSee('runScenario(\'delivery-csat\'', false);
        $response->assertSee('runScenario(\'catalog-sync\'', false);
    }

    public function test_superadmin_can_simulate_whatsapp_interactive_button_scenario(): void
    {
        $response = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/scenario/whatsapp', [
            'type' => 'button',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertSame('whatsapp', $data['agent_mode']);
        $this->assertSame('WhatsAppCloudApiConnector (WhatsAppDriver)', $data['agent_tested']);
        $this->assertArrayHasKey('whatsapp_outbound', $data);
        $this->assertSame('whatsapp', $data['whatsapp_outbound']['messaging_product']);
        $this->assertSame('8801700000000', $data['whatsapp_outbound']['to']);
    }

    public function test_superadmin_can_simulate_whatsapp_media_image_scenario(): void
    {
        $response = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/scenario/whatsapp', [
            'type' => 'media',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertSame('whatsapp', $data['agent_mode']);
        $this->assertArrayHasKey('pipeline_diagnostics', $data);
        $this->assertSame('image', $data['pipeline_diagnostics']['inbound_type']);
    }

    public function test_superadmin_can_simulate_delivery_csat_trigger_scenario(): void
    {
        $response = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/scenario/delivery-csat', [
            'action' => 'trigger_delivery',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertSame('csat', $data['agent_mode']);
        $this->assertSame('PostDeliveryFeedbackService (CSAT Dispatch)', $data['agent_tested']);
        $this->assertStringContainsString('কুরিয়ার স্ট্যাটাস', $data['reply']);
        $this->assertStringContainsString('Steadfast/Pathao', $data['reply']);
    }

    public function test_superadmin_can_simulate_csat_rating_reply_scenario(): void
    {
        // 1. First trigger courier delivered event to dispatch CSAT request to the simulator conversation
        $dispatchResp = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/scenario/delivery-csat', [
            'action' => 'trigger_delivery',
        ]);
        $dispatchResp->assertStatus(200);

        // 2. Now simulate customer reply with 5-star rating
        $response = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/scenario/delivery-csat', [
            'action' => 'rating_5',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertSame('csat', $data['agent_mode']);
        $this->assertSame('PostDeliveryFeedbackService (CSAT Rating)', $data['agent_tested']);
        $this->assertSame(5, $data['csat_recorded']['rating']);
        $this->assertStringContainsString('⭐⭐⭐⭐⭐', $data['reply']);
        $this->assertEquals(5.0, $data['csat_recorded']['stats']['average_rating']);
    }

    public function test_superadmin_can_simulate_catalog_sync_shopify_scenario(): void
    {
        $response = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/scenario/catalog-sync', [
            'platform' => 'shopify',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertSame('catalog_sync', $data['agent_mode']);
        $this->assertSame('ExternalStoreCatalogBridgeService', $data['agent_tested']);
        $this->assertArrayHasKey('synced_product', $data);
        $this->assertSame('Shopify Silk Embroidered Panjabi', $data['synced_product']['name']);

        // Verify product was created in database
        $this->assertDatabaseHas('analytics_products', [
            'workspace_id' => $this->workspace->id,
            'name'         => 'Shopify Silk Embroidered Panjabi',
        ]);
    }

    public function test_superadmin_can_simulate_catalog_sync_woocommerce_scenario(): void
    {
        $response = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/scenario/catalog-sync', [
            'platform' => 'woocommerce',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertSame('catalog_sync', $data['agent_mode']);
        $this->assertSame('ExternalStoreCatalogBridgeService', $data['agent_tested']);
        $this->assertArrayHasKey('synced_product', $data);
        $this->assertSame('WooCommerce Ergonomic Office Chair', $data['synced_product']['name']);

        // Verify product was created in database
        $this->assertDatabaseHas('analytics_products', [
            'workspace_id' => $this->workspace->id,
            'name'         => 'WooCommerce Ergonomic Office Chair',
        ]);
    }

    public function test_simulator_send_endpoint_supports_all_new_agent_modes(): void
    {
        // WhatsApp Agent Mode
        $waResp = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/send', [
            'message'    => 'ক্যাশ অন ডেলিভারি নিয়ম কি?',
            'agent_mode' => 'whatsapp',
        ]);
        $waResp->assertStatus(200);
        $waData = $waResp->json();
        $this->assertTrue($waData['success']);
        $this->assertSame('whatsapp', $waData['agent_mode']);

        // CSAT Agent Mode
        $csatResp = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/send', [
            'message'    => 'Courier Delivered order #999',
            'agent_mode' => 'csat',
        ]);
        $csatResp->assertStatus(200);
        $csatData = $csatResp->json();
        $this->assertTrue($csatData['success']);
        $this->assertSame('csat', $csatData['agent_mode']);

        // Catalog Sync Agent Mode
        $catResp = $this->actingAs($this->superadmin)->postJson('/dashboard/simulator/send', [
            'message'    => 'Sync Shopify Store Catalog Product',
            'agent_mode' => 'catalog_sync',
        ]);
        $catResp->assertStatus(200);
        $catData = $catResp->json();
        $this->assertTrue($catData['success']);
        $this->assertSame('catalog_sync', $catData['agent_mode']);
    }

    public function test_non_superadmin_users_are_forbidden_from_all_simulator_scenario_routes(): void
    {
        $scenarioEndpoints = [
            '/dashboard/simulator/scenario/whatsapp'      => ['type' => 'text'],
            '/dashboard/simulator/scenario/delivery-csat' => ['action' => 'trigger_delivery'],
            '/dashboard/simulator/scenario/catalog-sync'  => ['platform' => 'shopify'],
        ];

        foreach ($scenarioEndpoints as $endpoint => $payload) {
            // Admin receives 403
            $this->actingAs($this->regularAdmin)
                ->postJson($endpoint, $payload)
                ->assertStatus(403);

            // Staff receives 403
            $this->actingAs($this->staffUser)
                ->postJson($endpoint, $payload)
                ->assertStatus(403);

            // Unauthenticated receives 401 or 403 (Spatie UnauthorizedException)
            $unauthResp = $this->postJson($endpoint, $payload);
            $this->assertTrue(in_array($unauthResp->status(), [401, 403]), 'Expected 401 or 403 but got ' . $unauthResp->status());
        }
    }
}
