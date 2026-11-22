<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Decision\UnifiedOrchestrator;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Models\KnowledgeDocument;
use App\Models\Role;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Business\BusinessSourceOfTruthService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class KnowledgeBaseProductCatalogAndStockToggleTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $userA;
    protected User $userB;
    protected KnowledgeDocument $docA;
    protected StructuredRecord $recordA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->workspaceA = Workspace::create(['name' => 'Apex Crafts', 'slug' => 'apex-crafts']);
        $this->workspaceB = Workspace::create(['name' => 'Boutique House', 'slug' => 'boutique-house']);

        $roleA = Role::create([
            'name'         => 'merchant-admin-a',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $roleA->givePermissionTo([
            KnowledgeDocumentPermission::VIEW->value,
            KnowledgeDocumentPermission::CREATE->value,
            KnowledgeDocumentPermission::UPDATE->value,
            KnowledgeDocumentPermission::DELETE->value,
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->userA->assignRole($roleA);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
        ]);

        $this->docA = KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Craft Inventory 2026',
            'original_filename' => 'craft_inventory.xlsx',
            'file_path'         => 'documents/craft_inventory.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 10240,
            'content_hash'      => hash('sha256', 'craft_inventory_sample'),
            'is_active'         => true,
            'lifecycle_status'  => 'active',
            'purpose'           => 'data_analytics',
        ]);

        $this->recordA = StructuredRecord::create([
            'workspace_id'          => $this->workspaceA->id,
            'document_id'           => $this->docA->id,
            'row_index'             => 2,
            'record_identifier'     => 'SKU-CLAY-001',
            'canonical_entity_name' => 'Terracotta Tea Cup Set',
            'canonical_category'    => 'Kitchenware',
            'canonical_amount'      => 850.00,
            'canonical_quantity'    => 15,
            'canonical_status'      => 'in_stock',
            'raw_values'            => ['Item' => 'Terracotta Tea Cup Set', 'Price' => '850', 'Stock' => '15'],
            'mapped_attributes'     => [
                ['header' => 'Item', 'value' => 'Terracotta Tea Cup Set'],
                ['header' => 'Price', 'value' => '850'],
                ['header' => 'Stock', 'value' => '15'],
                ['header' => 'Color', 'value' => 'Earthy Brown'],
            ],
            'is_public'             => true,
        ]);
    }

    public function test_user_can_view_product_catalog_tab_and_data_endpoint(): void
    {
        $response = $this->actingAs($this->userA)
            ->get(route('knowledge-base.index', ['tab' => 'product_catalog']));

        $response->assertStatus(200);
        $response->assertSee('Product Catalog');

        $dataResponse = $this->actingAs($this->userA)
            ->get(route('knowledge-base.products.data'));

        $dataResponse->assertStatus(200);
        $dataResponse->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'category',
                    'price',
                    'price_formatted',
                    'stock',
                    'is_in_stock',
                    'status',
                    'specs',
                    'source_document',
                ],
            ],
            'stats' => [
                'total',
                'in_stock',
                'out_of_stock',
            ],
        ]);

        $json = $dataResponse->json();
        $this->assertCount(1, $json['data']);
        $this->assertSame('Terracotta Tea Cup Set', $json['data'][0]['name']);
        $this->assertSame(850.0, (float) $json['data'][0]['price']);
        $this->assertTrue($json['data'][0]['is_in_stock']);
        $this->assertSame(15, (int) $json['data'][0]['stock']);
        $this->assertSame(1, $json['stats']['in_stock']);
        $this->assertSame(0, $json['stats']['out_of_stock']);
    }

    public function test_quick_stock_toggle_flips_stock_and_clears_cache(): void
    {
        // Populate cache first
        Cache::put("ws_{$this->workspaceA->id}_all_products_v2", ['cached_dummy_val'], 60);

        // 1. Toggle OFF (In Stock -> Out of Stock)
        $toggleOffResponse = $this->actingAs($this->userA)
            ->postJson(route('knowledge-base.products.toggle-stock', ['id' => 'sr_' . $this->recordA->id]));

        $toggleOffResponse->assertStatus(200);
        $toggleOffResponse->assertJson([
            'success'        => true,
            'is_in_stock'    => false,
            'stock_quantity' => 0,
            'status'         => 'out_of_stock',
        ]);

        $this->recordA->refresh();
        $this->assertSame('0.0000', (string) $this->recordA->canonical_quantity);
        $this->assertSame('out_of_stock', $this->recordA->canonical_status);

        // Assert cache was invalidated
        $this->assertFalse(Cache::has("ws_{$this->workspaceA->id}_all_products_v2"));

        // 2. Toggle back ON (Out of Stock -> In Stock)
        $toggleOnResponse = $this->actingAs($this->userA)
            ->postJson(route('knowledge-base.products.toggle-stock', ['id' => 'sr_' . $this->recordA->id]));

        $toggleOnResponse->assertStatus(200);
        $toggleOnResponse->assertJson([
            'success'     => true,
            'is_in_stock' => true,
            'status'      => 'in_stock',
        ]);

        $this->recordA->refresh();
        $this->assertTrue((int) $this->recordA->canonical_quantity > 0);
        $this->assertSame('in_stock', $this->recordA->canonical_status);
    }

    public function test_update_quantity_endpoint(): void
    {
        $response = $this->actingAs($this->userA)
            ->postJson(route('knowledge-base.products.update-stock', ['id' => 'sr_' . $this->recordA->id]), [
                'quantity' => 45,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'        => true,
            'is_in_stock'    => true,
            'stock_quantity' => 45,
            'status'         => 'in_stock',
        ]);

        $this->recordA->refresh();
        $this->assertSame(45, (int) $this->recordA->canonical_quantity);
        $this->assertSame('in_stock', $this->recordA->canonical_status);
    }

    public function test_stock_toggle_immediately_affects_ai_chatbot_response(): void
    {
        $orchestrator = app(UnifiedOrchestrator::class);

        // 1. In Stock State
        $resInStock = $orchestrator->process(
            query: 'Terracotta Tea Cup Set এর দাম কত?',
            workspaceId: $this->workspaceA->id,
            channelSlug: 'web',
            isCustomer: true
        );
        $this->assertStringContainsString('850', $resInStock['reply']);

        // 2. Toggle to Out of Stock via Quick Stock Toggle API
        $this->actingAs($this->userA)
            ->postJson(route('knowledge-base.products.toggle-stock', ['id' => 'sr_' . $this->recordA->id]))
            ->assertStatus(200);

        // 3. Immediately Query AI again - must report Out of Stock!
        $resOutStock = $orchestrator->process(
            query: 'Terracotta Tea Cup Set এর দাম কত এবং স্টক আছে?',
            workspaceId: $this->workspaceA->id,
            channelSlug: 'web',
            isCustomer: true
        );

        $this->assertStringContainsString('স্টক আউট', $resOutStock['reply']);
    }

    public function test_tenant_isolation_prevents_toggling_other_workspace_products(): void
    {
        // User B belongs to Workspace B, attempts to toggle Workspace A's product
        $response = $this->actingAs($this->userB)
            ->postJson(route('knowledge-base.products.toggle-stock', ['id' => 'sr_' . $this->recordA->id]));

        $response->assertStatus(403); // Or 404 if permission granted but workspace mismatch
    }
}
