<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Models\AnalyticsProduct;
use App\Models\StructuredRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductCatalogController extends Controller
{
    /**
     * Get JSON data for DataTables displaying workspace products.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::VIEW->value);

        $workspaceId = $this->resolveWorkspaceId($request);
        if (! $workspaceId) {
            return response()->json([
                'draw'            => (int) $request->input('draw', 1),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'stats'           => ['total' => 0, 'in_stock' => 0, 'out_of_stock' => 0],
            ]);
        }

        $statusFilter = (string) $request->input('status_filter', 'all');
        $search = trim((string) $request->input('search.value', $request->input('q', '')));

        // 1. Fetch StructuredRecords (Excel / Spreadsheet ingested)
        $srQuery = StructuredRecord::where('workspace_id', $workspaceId)
            ->whereNotNull('canonical_entity_name')
            ->with(['document:id,original_filename'])
            ->orderBy('id', 'desc');

        $srItems = $srQuery->get();

        // 2. Fetch AnalyticsProduct (Manual / direct catalog)
        $apItems = AnalyticsProduct::where('workspace_id', $workspaceId)
            ->orderBy('id', 'desc')
            ->get();

        $rows = [];
        $totalCount = 0;
        $inStockCount = 0;
        $outOfStockCount = 0;

        foreach ($srItems as $sr) {
            $name = trim((string) $sr->canonical_entity_name);
            if ($name === '') {
                continue;
            }

            $price = $sr->canonical_amount !== null && (float) $sr->canonical_amount > 0 ? (float) $sr->canonical_amount : null;
            $quantity = $sr->canonical_quantity !== null ? (int) $sr->canonical_quantity : null;
            $status = $sr->canonical_status;

            // In-stock definition: quantity > 0 (or null if untracked) AND status is not out_of_stock
            $isInStock = ($status !== 'out_of_stock') && ($quantity === null || $quantity > 0);

            $totalCount++;
            if ($isInStock) {
                $inStockCount++;
            } else {
                $outOfStockCount++;
            }

            // Extract dynamic specifications from mapped_attributes
            $specsList = [];
            $mapped = is_array($sr->mapped_attributes) ? $sr->mapped_attributes : (json_decode($sr->mapped_attributes ?? '[]', true) ?: []);
            foreach ($mapped as $colData) {
                if (! is_array($colData)) {
                    continue;
                }
                $hdr = trim((string) ($colData['header'] ?? ''));
                $val = trim((string) ($colData['value'] ?? $colData['raw_value'] ?? ''));
                if ($val !== '' && ! preg_match('/(name|title|product|price|mrp|cost|rate|qty|quantity|stock|পণ্য|দাম|মূল্য|পরিমাণ|স্টক)/ui', $hdr)) {
                    $specsList[] = $hdr . ': ' . $val;
                }
            }

            $row = [
                'id'              => 'sr_' . $sr->id,
                'raw_id'          => $sr->id,
                'source_type'     => 'structured_record',
                'name'            => $name,
                'category'        => $sr->canonical_category ?: 'General',
                'price'           => $price,
                'price_formatted' => $price !== null ? '৳ ' . number_format($price, 2) : '—',
                'stock'           => $quantity ?? 0,
                'is_in_stock'     => $isInStock,
                'status'          => $status ?: ($isInStock ? 'in_stock' : 'out_of_stock'),
                'specs'           => implode(' • ', array_slice($specsList, 0, 4)),
                'source_document' => $sr->document?->original_filename ?: 'Uploaded File',
                'created_at'      => $sr->created_at?->format('d M Y, h:i A'),
            ];

            // Apply search
            if ($search !== '') {
                $haystack = mb_strtolower($row['name'] . ' ' . $row['category'] . ' ' . $row['specs'] . ' ' . $row['source_document']);
                if (! str_contains($haystack, mb_strtolower($search))) {
                    continue;
                }
            }

            // Apply status filter
            if ($statusFilter === 'in_stock' && ! $isInStock) {
                continue;
            }
            if ($statusFilter === 'out_of_stock' && $isInStock) {
                continue;
            }

            $rows[] = $row;
        }

        foreach ($apItems as $ap) {
            $name = trim((string) $ap->name);
            if ($name === '') {
                continue;
            }

            $price = $ap->unit_price !== null && (float) $ap->unit_price > 0 ? (float) $ap->unit_price : null;
            $quantity = $ap->stock_quantity !== null ? (int) $ap->stock_quantity : null;
            $isInStock = $ap->is_active && ($quantity === null || $quantity > 0);

            $totalCount++;
            if ($isInStock) {
                $inStockCount++;
            } else {
                $outOfStockCount++;
            }

            $row = [
                'id'              => 'ap_' . $ap->id,
                'raw_id'          => $ap->id,
                'source_type'     => 'analytics_product',
                'name'            => $name,
                'category'        => $ap->category ?: 'General',
                'price'           => $price,
                'price_formatted' => $price !== null ? '৳ ' . number_format($price, 2) : '—',
                'stock'           => $quantity ?? 0,
                'is_in_stock'     => $isInStock,
                'status'          => $isInStock ? 'in_stock' : 'out_of_stock',
                'specs'           => $ap->sku ? 'SKU: ' . $ap->sku : '',
                'source_document' => 'Direct Store Catalog',
                'created_at'      => $ap->created_at?->format('d M Y, h:i A'),
            ];

            if ($search !== '') {
                $haystack = mb_strtolower($row['name'] . ' ' . $row['category'] . ' ' . $row['specs']);
                if (! str_contains($haystack, mb_strtolower($search))) {
                    continue;
                }
            }

            if ($statusFilter === 'in_stock' && ! $isInStock) {
                continue;
            }
            if ($statusFilter === 'out_of_stock' && $isInStock) {
                continue;
            }

            $rows[] = $row;
        }

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $totalCount,
            'recordsFiltered' => count($rows),
            'data'            => $rows,
            'stats'           => [
                'total'        => $totalCount,
                'in_stock'     => $inStockCount,
                'out_of_stock' => $outOfStockCount,
            ],
        ]);
    }

    /**
     * Instant Quick Stock Toggle endpoint.
     */
    public function toggleStock(Request $request, string $id): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);

        $workspaceId = $this->resolveWorkspaceId($request);
        if (! $workspaceId) {
            return response()->json(['success' => false, 'message' => 'Workspace not found.'], 404);
        }

        if (str_starts_with($id, 'sr_')) {
            $rawId = (int) substr($id, 3);
            $record = StructuredRecord::where('workspace_id', $workspaceId)->find($rawId);
            if (! $record) {
                return response()->json(['success' => false, 'message' => 'Product record not found.'], 404);
            }

            $currentQty = $record->canonical_quantity !== null ? (int) $record->canonical_quantity : null;
            $currentStatus = $record->canonical_status;
            $currentlyInStock = ($currentStatus !== 'out_of_stock') && ($currentQty === null || $currentQty > 0);

            if ($currentlyInStock) {
                // Toggle OFF -> Out of stock
                $record->canonical_quantity = 0;
                $record->canonical_status = 'out_of_stock';
                $newInStock = false;
                $newQty = 0;
            } else {
                // Toggle ON -> In stock
                $record->canonical_quantity = ($currentQty !== null && $currentQty > 0) ? $currentQty : 10;
                $record->canonical_status = 'in_stock';
                $newInStock = true;
                $newQty = (int) $record->canonical_quantity;
            }

            $record->save();
        } elseif (str_starts_with($id, 'ap_')) {
            $rawId = (int) substr($id, 3);
            $product = AnalyticsProduct::where('workspace_id', $workspaceId)->find($rawId);
            if (! $product) {
                return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
            }

            $currentlyInStock = $product->is_active && ($product->stock_quantity === null || $product->stock_quantity > 0);
            if ($currentlyInStock) {
                $product->stock_quantity = 0;
                $product->is_active = false;
                $newInStock = false;
                $newQty = 0;
            } else {
                $product->stock_quantity = ($product->stock_quantity > 0) ? $product->stock_quantity : 10;
                $product->is_active = true;
                $newInStock = true;
                $newQty = (int) $product->stock_quantity;
            }

            $product->save();
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid product identifier prefix.'], 400);
        }

        // Flush workspace product cache so AI bot immediately respects the stock state
        Cache::forget("ws_{$workspaceId}_all_products_v2");

        return response()->json([
            'success'        => true,
            'is_in_stock'    => $newInStock,
            'stock_quantity' => $newQty,
            'status'         => $newInStock ? 'in_stock' : 'out_of_stock',
            'message'        => $newInStock ? 'Product marked In Stock.' : 'Product marked Out of Stock.',
        ]);
    }

    /**
     * Update exact stock quantity for a product.
     */
    public function updateQuantity(Request $request, string $id): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);

        $workspaceId = $this->resolveWorkspaceId($request);
        if (! $workspaceId) {
            return response()->json(['success' => false, 'message' => 'Workspace not found.'], 404);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $qty = (int) $validated['quantity'];
        $isInStock = $qty > 0;

        if (str_starts_with($id, 'sr_')) {
            $rawId = (int) substr($id, 3);
            $record = StructuredRecord::where('workspace_id', $workspaceId)->find($rawId);
            if (! $record) {
                return response()->json(['success' => false, 'message' => 'Product record not found.'], 404);
            }

            $record->canonical_quantity = $qty;
            $record->canonical_status = $isInStock ? 'in_stock' : 'out_of_stock';
            $record->save();
        } elseif (str_starts_with($id, 'ap_')) {
            $rawId = (int) substr($id, 3);
            $product = AnalyticsProduct::where('workspace_id', $workspaceId)->find($rawId);
            if (! $product) {
                return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
            }

            $product->stock_quantity = $qty;
            $product->is_active = $isInStock;
            $product->save();
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid product identifier prefix.'], 400);
        }

        Cache::forget("ws_{$workspaceId}_all_products_v2");

        return response()->json([
            'success'        => true,
            'is_in_stock'    => $isInStock,
            'stock_quantity' => $qty,
            'status'         => $isInStock ? 'in_stock' : 'out_of_stock',
            'message'        => 'Stock quantity updated successfully.',
        ]);
    }

    private function resolveWorkspaceId(Request $request): ?int
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        if ($user->workspace_id) {
            return (int) $user->workspace_id;
        }

        if ($request->filled('workspace_id')) {
            return (int) $request->input('workspace_id');
        }

        return session('current_workspace_id') ? (int) session('current_workspace_id') : null;
    }

    private function authorizeAction(string $permission): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value)) {
            return;
        }

        if (! $user || ! $user->can($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
