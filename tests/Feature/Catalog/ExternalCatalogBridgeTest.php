<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\AnalyticsProduct;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Business\BusinessSourceOfTruthService;
use App\Services\Catalog\ExternalStoreCatalogBridgeService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExternalCatalogBridgeTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $permView = Permission::firstOrCreate(['name' => KnowledgeDocumentPermission::VIEW->value, 'guard_name' => 'web']);
        $permUpdate = Permission::firstOrCreate(['name' => KnowledgeDocumentPermission::UPDATE->value, 'guard_name' => 'web']);
        $adminRole->givePermissionTo([$permView, $permUpdate]);

        $this->workspace = Workspace::create([
            'name'      => 'MultiSource Retail Hub',
            'slug'      => 'multisource-retail-hub',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->owner->assignRole(RoleEnum::ADMIN->value);
    }

    public function test_shopify_inbound_webhook_syncs_product_and_live_stock(): void
    {
        $payload = [
            'id'           => 9011223344,
            'title'        => 'Oxford Premium Slim-fit Shirt',
            'body_html'    => '<p>100% pure Egyptian cotton shirt.</p>',
            'vendor'       => 'Apex Outfitters',
            'product_type' => 'Apparel',
            'handle'       => 'oxford-premium-shirt',
            'status'       => 'active',
            'variants'     => [
                [
                    'id'                 => 10101,
                    'price'              => '2200.00',
                    'sku'                => 'OXF-SLIM-BLU-L',
                    'inventory_quantity' => 18,
                ],
            ],
            'images'       => [
                ['src' => 'https://cdn.shopify.com/s/files/shirt_front.jpg'],
            ],
        ];

        $response = $this->postJson(route('api.integrations.shopify.webhook', ['workspace_id' => $this->workspace->id]), $payload, [
            'X-Shopify-Topic' => 'products/create',
        ]);

        $response->assertOk()
            ->assertJson([
                'success'        => true,
                'sku'            => 'OXF-SLIM-BLU-L',
                'stock_quantity' => 18,
                'is_active'      => true,
            ]);

        $product = AnalyticsProduct::where('workspace_id', $this->workspace->id)
            ->where('sku', 'OXF-SLIM-BLU-L')
            ->first();

        $this->assertNotNull($product);
        $this->assertSame('Oxford Premium Slim-fit Shirt', $product->name);
        $this->assertSame('2200.00', (string) $product->unit_price);
        $this->assertSame(18, $product->stock_quantity);
        $this->assertSame('shopify', $product->source_type);
        $this->assertSame('shopify_9011223344', $product->source_row_key);
        $this->assertSame('https://cdn.shopify.com/s/files/shirt_front.jpg', $product->source_metadata['image_url']);

        // Subsequent update webhook updates existing record without duplicate
        $updatePayload = $payload;
        $updatePayload['variants'][0]['inventory_quantity'] = 0; // sold out
        $updatePayload['variants'][0]['price'] = '2400.00';

        $updateResponse = $this->postJson(route('api.integrations.shopify.webhook', ['workspace_id' => $this->workspace->id]), $updatePayload, [
            'X-Shopify-Topic' => 'products/update',
        ]);

        $updateResponse->assertOk()
            ->assertJson([
                'success'        => true,
                'stock_quantity' => 0,
                'is_active'      => false,
            ]);

        $this->assertSame(1, AnalyticsProduct::where('workspace_id', $this->workspace->id)->count());
        $product->refresh();
        $this->assertSame(0, $product->stock_quantity);
        $this->assertFalse($product->is_active);
    }

    public function test_woocommerce_inbound_webhook_syncs_product_and_stock(): void
    {
        $payload = [
            'id'                => 7788,
            'name'              => 'Wireless Noise Cancelling Headphones',
            'sku'               => 'HEADPHONE-BT-NC',
            'price'             => '4500.00',
            'regular_price'     => '4800.00',
            'stock_quantity'    => 12,
            'stock_status'      => 'instock',
            'status'            => 'publish',
            'short_description' => 'Active noise cancellation with 40-hour battery life.',
            'categories'        => [
                ['name' => 'Consumer Audio'],
            ],
            'images'            => [
                ['src' => 'https://myfashionstore.com/wp-content/uploads/headphones.jpg'],
            ],
        ];

        $response = $this->postJson(route('api.integrations.woocommerce.webhook', ['workspace_id' => $this->workspace->id]), $payload, [
            'X-WC-Webhook-Topic' => 'product.created',
        ]);

        $response->assertOk()
            ->assertJson([
                'success'        => true,
                'sku'            => 'HEADPHONE-BT-NC',
                'stock_quantity' => 12,
                'is_active'      => true,
            ]);

        $product = AnalyticsProduct::where('workspace_id', $this->workspace->id)
            ->where('sku', 'HEADPHONE-BT-NC')
            ->first();

        $this->assertNotNull($product);
        $this->assertSame('Wireless Noise Cancelling Headphones', $product->name);
        $this->assertSame('4500.00', (string) $product->unit_price);
        $this->assertSame('Consumer Audio', $product->category);
        $this->assertSame('woocommerce', $product->source_type);
        $this->assertSame('woo_7788', $product->source_row_key);
    }

    public function test_one_click_import_shopify_rest_api(): void
    {
        Http::fake([
            'https://brand-demo.myshopify.com/admin/api/2024-01/products.json*' => Http::response([
                'products' => [
                    [
                        'id'           => 101,
                        'title'        => 'Canvas Sneaker Classic',
                        'product_type' => 'Footwear',
                        'status'       => 'active',
                        'variants'     => [
                            ['sku' => 'SNEAKER-01', 'price' => '3200.00', 'inventory_quantity' => 8],
                        ],
                    ],
                    [
                        'id'           => 102,
                        'title'        => 'Leather Minimalist Wallet',
                        'product_type' => 'Accessories',
                        'status'       => 'active',
                        'variants'     => [
                            ['sku' => 'WALLET-LTH-01', 'price' => '1200.00', 'inventory_quantity' => 20],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('integrations.catalog-sync'), [
                'platform'    => 'shopify',
                'credentials' => [
                    'shop_domain'  => 'brand-demo.myshopify.com',
                    'access_token' => 'shpat_test_mock_token_123',
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'result'  => [
                    'platform'     => 'shopify',
                    'synced_count' => 2,
                ],
            ]);

        $this->assertSame(2, AnalyticsProduct::where('workspace_id', $this->workspace->id)->count());
    }

    public function test_one_click_import_woocommerce_rest_api(): void
    {
        Http::fake([
            'https://mystore.test/wp-json/wc/v3/products*' => Http::response([
                [
                    'id'             => 501,
                    'name'           => 'Organic Green Tea 200g',
                    'sku'            => 'TEA-GRN-200',
                    'price'          => '650.00',
                    'stock_quantity' => 40,
                    'stock_status'   => 'instock',
                    'status'         => 'publish',
                    'categories'     => [['name' => 'Organic Groceries']],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('integrations.catalog-sync'), [
                'platform'    => 'woocommerce',
                'credentials' => [
                    'store_url'       => 'https://mystore.test',
                    'consumer_key'    => 'ck_mock_key_999',
                    'consumer_secret' => 'cs_mock_secret_888',
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'result'  => [
                    'platform'     => 'woocommerce',
                    'synced_count' => 1,
                ],
            ]);

        $tea = AnalyticsProduct::where('workspace_id', $this->workspace->id)->where('sku', 'TEA-GRN-200')->first();
        $this->assertNotNull($tea);
        $this->assertSame(40, $tea->stock_quantity);
    }

    public function test_synced_products_appear_in_catalog_data_and_knowledge_service(): void
    {
        AnalyticsProduct::create([
            'workspace_id'    => $this->workspace->id,
            'source_type'     => 'shopify',
            'source_row_key'  => 'shopify_9999',
            'name'            => 'Smart LED Desk Lamp',
            'sku'             => 'LAMP-LED-01',
            'category'        => 'Lighting',
            'unit_price'      => 1500.00,
            'stock_quantity'  => 5,
            'track_inventory' => true,
            'is_active'       => true,
        ]);

        // 1. DataTables endpoint
        $response = $this->actingAs($this->owner)
            ->getJson(route('knowledge-base.products.data'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('Smart LED Desk Lamp', $data[0]['name']);
        $this->assertSame(5, $data[0]['stock']);
        $this->assertTrue($data[0]['is_in_stock']);

        // 2. BusinessSourceOfTruthService query
        $truthService = app(BusinessSourceOfTruthService::class);
        $prods = $truthService->getAllWorkspaceProducts($this->workspace->id);
        $this->assertNotEmpty($prods['catalog']);
        $this->assertSame('Smart LED Desk Lamp', $prods['catalog'][0]['name']);
        $this->assertSame(5, $prods['catalog'][0]['stock']);
    }
}
