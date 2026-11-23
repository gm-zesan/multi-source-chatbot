<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Catalog\ExternalStoreCatalogBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StoreCatalogBridgeController extends Controller
{
    public function __construct(
        protected ExternalStoreCatalogBridgeService $bridgeService,
    ) {}

    /**
     * Inbound Shopify Product Webhook Callback (products/create, products/update, inventory_levels/update).
     */
    public function handleShopifyWebhook(Request $request): JsonResponse
    {
        $workspaceId = (int) ($request->query('workspace_id') ?: $request->input('workspace_id') ?: 1);
        $topic = (string) ($request->header('X-Shopify-Topic') ?: 'products/update');

        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            return response()->json(['success' => false, 'message' => 'Workspace not found.'], 404);
        }

        $payload = $request->all();
        if (empty($payload)) {
            return response()->json(['success' => false, 'message' => 'Empty webhook payload.'], 400);
        }

        try {
            $product = $this->bridgeService->syncShopifyProduct($workspace->id, $payload);

            return response()->json([
                'success'        => true,
                'topic'          => $topic,
                'product_id'     => $product->id,
                'sku'            => $product->sku,
                'stock_quantity' => $product->stock_quantity,
                'is_active'      => $product->is_active,
            ]);
        } catch (\Throwable $e) {
            Log::error('[ShopifyWebhook] Product sync failed', [
                'workspace_id' => $workspaceId,
                'error'        => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Inbound WooCommerce Product Webhook Callback (product.created, product.updated).
     */
    public function handleWooCommerceWebhook(Request $request): JsonResponse
    {
        $workspaceId = (int) ($request->query('workspace_id') ?: $request->input('workspace_id') ?: 1);
        $topic = (string) ($request->header('X-WC-Webhook-Topic') ?: 'product.updated');

        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            return response()->json(['success' => false, 'message' => 'Workspace not found.'], 404);
        }

        $payload = $request->all();
        if (empty($payload)) {
            return response()->json(['success' => false, 'message' => 'Empty webhook payload.'], 400);
        }

        try {
            $product = $this->bridgeService->syncWooCommerceProduct($workspace->id, $payload);

            return response()->json([
                'success'        => true,
                'topic'          => $topic,
                'product_id'     => $product->id,
                'sku'            => $product->sku,
                'stock_quantity' => $product->stock_quantity,
                'is_active'      => $product->is_active,
            ]);
        } catch (\Throwable $e) {
            Log::error('[WooCommerceWebhook] Product sync failed', [
                'workspace_id' => $workspaceId,
                'error'        => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Merchant-Triggered 1-Click Full Catalog Sync (Shopify or WooCommerce REST API).
     */
    public function oneClickSync(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'platform'    => ['required', 'string', 'in:shopify,woocommerce'],
            'credentials' => ['required', 'array'],
            'workspace_id'=> ['nullable', 'integer', 'exists:workspaces,id'],
        ]);

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && ! empty($validated['workspace_id']))
            ? (int) $validated['workspace_id']
            : (int) ($user->workspace_id ?: 1);

        try {
            $result = $this->bridgeService->oneClickImport(
                $workspaceId,
                $validated['platform'],
                $validated['credentials']
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'result'  => $result,
                    'message' => "Successfully imported {$result['synced_count']} products from " . ucfirst($validated['platform']) . "!",
                ]);
            }

            return redirect()->back()->with('success', "Catalog 1-Click Sync complete: {$result['synced_count']} products imported and stock levels updated!");
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', '1-Click Sync failed: ' . $e->getMessage());
        }
    }
}
