<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\AnalyticsProduct;
use App\Models\StructuredRecord;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Stringable;

/**
 * InventoryAnalyticsTool (inventory_analytics).
 *
 * Governed inventory and stock tracking tool.
 * Evaluates warehouse stock levels, low-stock alerts, out-of-stock items,
 * and inventory commercial valuation across ingested structured records and catalog items.
 */
class InventoryAnalyticsTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'inventory_analytics';
    }

    public function description(): Stringable|string
    {
        return 'Reports on warehouse inventory stock levels, identifying out-of-stock items, low-stock warnings, SKU counts, and total inventory commercial valuation.';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRequiredPermissions(): array
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'low_stock_threshold' => $schema->integer()
                ->description('Threshold quantity below which a product is considered low stock. Defaults to 10.'),
            'category' => $schema->string()
                ->description('Optional product category to filter inventory records.'),
            'limit' => $schema->integer()
                ->description('Maximum number of items to return in the detailed list (1-50). Defaults to 20.'),
        ];
    }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        // 1. SLA Deadline Guard
        $remaining = $context->getRemainingSeconds();
        if ($remaining < 0.5) {
            throw new AgentExecutionTimeoutException(
                message: "Tool [{$this->name()}] execution aborted: remaining SLA budget ({$remaining}s) is below 0.5s threshold.",
                timeoutSeconds: $context->timeoutSeconds,
                elapsedSeconds: $context->timeoutSeconds - $remaining,
            );
        }

        $workspaceId = $context->getWorkspaceId();
        $threshold = max(1, (int) ($arguments['low_stock_threshold'] ?? 10));
        $category = !empty($arguments['category']) ? trim((string) $arguments['category']) : null;
        $limit = min(max(1, (int) ($arguments['limit'] ?? 20)), 50);

        // 2. Query Ingested Structured Inventory Records
        $recordsQuery = StructuredRecord::where('workspace_id', $workspaceId)
            ->whereNotNull('canonical_quantity');

        if ($category !== null && $category !== '') {
            $recordsQuery->where('canonical_category', $category);
        }

        $hasStructuredRecords = (clone $recordsQuery)->exists();

        if ($hasStructuredRecords) {
            return $this->aggregateFromStructuredRecords($recordsQuery, $threshold, $limit, $context);
        }

        // 3. Graceful Fallback to Analytics Products Catalog
        return $this->fallbackToProductCatalog($workspaceId, $category, $context);
    }

    /**
     * Aggregate inventory metrics from ingested StructuredRecord dataset.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function aggregateFromStructuredRecords(
        $query,
        int $threshold,
        int $limit,
        AgentContext $context
    ): ToolResult {
        $totalSkus = (int) (clone $query)->count();
        $totalStockUnits = (float) (clone $query)->sum('canonical_quantity');

        $outOfStockCount = (int) (clone $query)->where('canonical_quantity', '<=', 0)->count();
        $lowStockCount = (int) (clone $query)
            ->where('canonical_quantity', '>', 0)
            ->where('canonical_quantity', '<=', $threshold)
            ->count();

        // Inventory valuation = sum(quantity * amount/cost)
        $inventoryValuation = round((float) (clone $query)->sum(DB::raw('canonical_quantity * COALESCE(canonical_amount, 0)')), 2);

        $items = (clone $query)
            ->orderBy('canonical_quantity', 'asc')
            ->limit($limit)
            ->get()
            ->map(function (StructuredRecord $rec) use ($threshold) {
                $qty = (float) ($rec->canonical_quantity ?? 0);
                $unitCost = (float) ($rec->canonical_amount ?? 0);
                $status = $qty <= 0 ? 'out_of_stock' : ($qty <= $threshold ? 'low_stock' : 'in_stock');

                return [
                    'sku'                 => $rec->record_identifier,
                    'name'                => $rec->canonical_entity_name,
                    'category'            => $rec->canonical_category,
                    'quantity_available'  => $qty,
                    'stock_status'        => $status,
                    // Sensitive financial valuation fields (subject to policy projection)
                    'unit_cost'           => $unitCost,
                    'inventory_cost'      => round($qty * $unitCost, 2),
                ];
            })
            ->all();

        return ToolResult::success([
            'source'                     => 'structured_inventory_records',
            'total_skus'                 => $totalSkus,
            'total_stock_units'          => $totalStockUnits,
            'low_stock_threshold'        => $threshold,
            'low_stock_count'            => $lowStockCount,
            'out_of_stock_count'         => $outOfStockCount,
            // Sensitive financial cost field (scrubbed automatically by policy if unauthorized)
            'total_inventory_cost'       => $inventoryValuation,
            'items'                      => $items,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }

    /**
     * Fallback report when no physical inventory spreadsheet is ingested.
     */
    private function fallbackToProductCatalog(
        int $workspaceId,
        ?string $category,
        AgentContext $context
    ): ToolResult {
        $catalogQuery = AnalyticsProduct::where('workspace_id', $workspaceId)
            ->whereNull('deleted_at');

        if ($category !== null && $category !== '') {
            $catalogQuery->where('category', $category);
        }

        $skuCount = $catalogQuery->count();

        return ToolResult::success([
            'source'                    => 'catalog_metadata_only',
            'status'                    => 'no_inventory_data_ingested',
            'catalog_sku_count'         => $skuCount,
            'message'                   => 'Product catalog is tracked, but physical inventory and stock levels require an uploaded inventory spreadsheet via the spreadsheet ingestion service.',
            'total_stock_units'         => null,
            'low_stock_count'           => 0,
            'out_of_stock_count'        => 0,
            'total_inventory_valuation' => null,
            'items'                     => [],
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }
}
