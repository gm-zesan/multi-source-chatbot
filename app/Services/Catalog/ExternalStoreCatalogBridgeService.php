<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\AnalyticsProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalStoreCatalogBridgeService
{
    /**
     * Ingest or update a single product from Shopify webhook / API.
     */
    public function syncShopifyProduct(int $workspaceId, array $payload): AnalyticsProduct
    {
        $shopifyId = (string) ($payload['id'] ?? uniqid('sh_'));
        $title = trim((string) ($payload['title'] ?? 'Untitled Shopify Product'));
        $variants = (array) ($payload['variants'] ?? []);
        $firstVariant = $variants[0] ?? [];

        $sku = trim((string) ($firstVariant['sku'] ?? ''));
        if ($sku === '') {
            $sku = 'SHOPIFY-' . $shopifyId;
        }

        $price = (float) ($firstVariant['price'] ?? 0.0);
        $stock = isset($firstVariant['inventory_quantity']) ? (int) $firstVariant['inventory_quantity'] : 10;
        $category = trim((string) ($payload['product_type'] ?? 'Shopify Catalog')) ?: 'Shopify Catalog';
        $description = trim(strip_tags((string) ($payload['body_html'] ?? '')));
        $status = (string) ($payload['status'] ?? 'active');
        $isActive = ($status === 'active') && ($stock > 0);

        // Images
        $images = (array) ($payload['images'] ?? []);
        $imageUrl = $images[0]['src'] ?? ($payload['image']['src'] ?? null);

        // Find existing by source_row_key or SKU in workspace
        $product = AnalyticsProduct::where('workspace_id', $workspaceId)
            ->where(function ($q) use ($shopifyId, $sku) {
                $q->where('source_row_key', 'shopify_' . $shopifyId)
                  ->orWhere('sku', $sku);
            })
            ->first();

        $metadata = [
            'shopify_product_id' => $shopifyId,
            'vendor'             => $payload['vendor'] ?? null,
            'image_url'          => $imageUrl,
            'variants_count'     => count($variants),
            'handle'             => $payload['handle'] ?? null,
            'tags'               => $payload['tags'] ?? null,
            'last_synced_at'     => now()->toIso8601String(),
        ];

        if (! $product) {
            $product = AnalyticsProduct::create([
                'workspace_id'    => $workspaceId,
                'source_type'     => 'shopify',
                'source_row_key'  => 'shopify_' . $shopifyId,
                'name'            => $title,
                'sku'             => $sku,
                'category'        => $category,
                'unit_price'      => $price,
                'stock_quantity'  => $stock,
                'track_inventory' => true,
                'description'     => $description,
                'source_metadata' => $metadata,
                'is_active'       => $isActive,
            ]);
        } else {
            $product->update([
                'name'            => $title,
                'sku'             => $sku,
                'category'        => $category,
                'unit_price'      => $price,
                'stock_quantity'  => $stock,
                'track_inventory' => true,
                'description'     => $description,
                'source_type'     => 'shopify',
                'source_row_key'  => 'shopify_' . $shopifyId,
                'source_metadata' => array_merge($product->source_metadata ?? [], $metadata),
                'is_active'       => $isActive,
            ]);
        }

        // Bust knowledge base product catalog cache
        Cache::forget("ws_{$workspaceId}_all_products_v2");

        Log::info('[CatalogBridge] Shopify product synchronized', [
            'workspace_id' => $workspaceId,
            'product_id'   => $product->id,
            'sku'          => $sku,
            'stock'        => $stock,
        ]);

        return $product;
    }

    /**
     * Ingest or update a single product from WooCommerce webhook / API.
     */
    public function syncWooCommerceProduct(int $workspaceId, array $payload): AnalyticsProduct
    {
        $wooId = (string) ($payload['id'] ?? uniqid('wc_'));
        $title = trim((string) ($payload['name'] ?? 'Untitled WooCommerce Product'));

        $sku = trim((string) ($payload['sku'] ?? ''));
        if ($sku === '') {
            $sku = 'WOO-' . $wooId;
        }

        $price = (float) ($payload['price'] ?? ($payload['regular_price'] ?? 0.0));
        $stock = isset($payload['stock_quantity']) && $payload['stock_quantity'] !== null
            ? (int) $payload['stock_quantity']
            : (($payload['stock_status'] ?? 'instock') === 'instock' ? 10 : 0);

        $categories = (array) ($payload['categories'] ?? []);
        $category = trim((string) ($categories[0]['name'] ?? 'WooCommerce Catalog')) ?: 'WooCommerce Catalog';
        $description = trim(strip_tags((string) ($payload['short_description'] ?? ($payload['description'] ?? ''))));
        $status = (string) ($payload['status'] ?? 'publish');
        $stockStatus = (string) ($payload['stock_status'] ?? 'instock');
        $isActive = ($status === 'publish') && ($stockStatus !== 'outofstock') && ($stock > 0);

        // Images
        $images = (array) ($payload['images'] ?? []);
        $imageUrl = $images[0]['src'] ?? null;

        $product = AnalyticsProduct::where('workspace_id', $workspaceId)
            ->where(function ($q) use ($wooId, $sku) {
                $q->where('source_row_key', 'woo_' . $wooId)
                  ->orWhere('sku', $sku);
            })
            ->first();

        $metadata = [
            'woocommerce_id' => $wooId,
            'permalink'      => $payload['permalink'] ?? null,
            'image_url'      => $imageUrl,
            'stock_status'   => $stockStatus,
            'last_synced_at' => now()->toIso8601String(),
        ];

        if (! $product) {
            $product = AnalyticsProduct::create([
                'workspace_id'    => $workspaceId,
                'source_type'     => 'woocommerce',
                'source_row_key'  => 'woo_' . $wooId,
                'name'            => $title,
                'sku'             => $sku,
                'category'        => $category,
                'unit_price'      => $price,
                'stock_quantity'  => $stock,
                'track_inventory' => true,
                'description'     => $description,
                'source_metadata' => $metadata,
                'is_active'       => $isActive,
            ]);
        } else {
            $product->update([
                'name'            => $title,
                'sku'             => $sku,
                'category'        => $category,
                'unit_price'      => $price,
                'stock_quantity'  => $stock,
                'track_inventory' => true,
                'description'     => $description,
                'source_type'     => 'woocommerce',
                'source_row_key'  => 'woo_' . $wooId,
                'source_metadata' => array_merge($product->source_metadata ?? [], $metadata),
                'is_active'       => $isActive,
            ]);
        }

        Cache::forget("ws_{$workspaceId}_all_products_v2");

        Log::info('[CatalogBridge] WooCommerce product synchronized', [
            'workspace_id' => $workspaceId,
            'product_id'   => $product->id,
            'sku'          => $sku,
            'stock'        => $stock,
        ]);

        return $product;
    }

    /**
     * 1-Click Import & Full Catalog Sync via REST API.
     */
    public function oneClickImport(int $workspaceId, string $platform, array $credentials): array
    {
        $platform = strtolower(trim($platform));
        $synced = 0;
        $errors = [];

        if ($platform === 'shopify') {
            $shopDomain = trim((string) ($credentials['shop_domain'] ?? ''));
            $token = trim((string) ($credentials['access_token'] ?? ''));

            if ($shopDomain === '' || $token === '') {
                throw new \InvalidArgumentException('Shopify store domain and access token are required.');
            }

            // Normalize domain
            $shopDomain = str_replace(['https://', 'http://'], '', rtrim($shopDomain, '/'));
            if (! str_contains($shopDomain, '.')) {
                $shopDomain .= '.myshopify.com';
            }

            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type'           => 'application/json',
                ])
                ->get("https://{$shopDomain}/admin/api/2024-01/products.json?limit=250");

            if (! $response->successful()) {
                $err = $response->json('errors') ?? 'Failed to connect to Shopify API';
                $errMsg = is_array($err) ? json_encode($err) : (string) $err;
                throw new \RuntimeException("Shopify API Error: {$errMsg}");
            }

            $products = (array) ($response->json('products') ?? []);
            foreach ($products as $p) {
                try {
                    $this->syncShopifyProduct($workspaceId, $p);
                    $synced++;
                } catch (\Throwable $e) {
                    $errors[] = "Product ID {$p['id']}: " . $e->getMessage();
                }
            }
        } elseif ($platform === 'woocommerce') {
            $storeUrl = trim((string) ($credentials['store_url'] ?? ''));
            $consumerKey = trim((string) ($credentials['consumer_key'] ?? ''));
            $consumerSecret = trim((string) ($credentials['consumer_secret'] ?? ''));

            if ($storeUrl === '' || $consumerKey === '' || $consumerSecret === '') {
                throw new \InvalidArgumentException('WooCommerce store URL, Consumer Key, and Consumer Secret are required.');
            }

            $storeUrl = rtrim($storeUrl, '/');
            if (! str_starts_with($storeUrl, 'http://') && ! str_starts_with($storeUrl, 'https://')) {
                $storeUrl = 'https://' . $storeUrl;
            }

            $response = Http::timeout(15)
                ->get("{$storeUrl}/wp-json/wc/v3/products", [
                    'consumer_key'    => $consumerKey,
                    'consumer_secret' => $consumerSecret,
                    'per_page'        => 100,
                ]);

            if (! $response->successful()) {
                $errMsg = $response->json('message') ?? 'Failed to connect to WooCommerce REST API';
                throw new \RuntimeException("WooCommerce API Error: {$errMsg}");
            }

            $products = (array) ($response->json() ?? []);
            foreach ($products as $p) {
                if (! is_array($p) || empty($p['id'])) {
                    continue;
                }
                try {
                    $this->syncWooCommerceProduct($workspaceId, $p);
                    $synced++;
                } catch (\Throwable $e) {
                    $errors[] = "Product ID {$p['id']}: " . $e->getMessage();
                }
            }
        } else {
            throw new \InvalidArgumentException("Unsupported external store platform [{$platform}]. Must be 'shopify' or 'woocommerce'.");
        }

        return [
            'platform'     => $platform,
            'synced_count' => $synced,
            'errors'       => $errors,
            'timestamp'    => now()->toIso8601String(),
        ];
    }

    /**
     * Verify Shopify Webhook HMAC header.
     */
    public function verifyShopifyHmac(string $rawContent, string $hmacHeader, string $secret): bool
    {
        if (empty($secret) || empty($hmacHeader)) {
            return false;
        }

        $calculated = base64_encode(hash_hmac('sha256', $rawContent, $secret, true));
        return hash_equals($calculated, $hmacHeader);
    }
}
