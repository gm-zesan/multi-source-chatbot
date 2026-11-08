<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Stringable;

/**
 * ProductPerformanceTool (product_performance).
 *
 * Governed product performance and commercial profitability analysis tool.
 * Ranks products by units sold, total revenue, and commercial gross margins,
 * respecting soft-delete catalog boundaries and DLP projection policies.
 */
class ProductPerformanceTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'product_performance';
    }

    public function description(): Stringable|string
    {
        return 'Ranks product catalog items by sales volume, units sold, revenue, and gross profit margins for a given time period or category.';
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
            'start_date' => $schema->string()
                ->description("Analysis start date in 'YYYY-MM-DD' format. Optional; defaults to 30 days ago."),
            'end_date' => $schema->string()
                ->description("Analysis end date in 'YYYY-MM-DD' format. Optional; defaults to today."),
            'category' => $schema->string()
                ->description('Filter products by category name. Optional.'),
            'sort_by' => $schema->string()
                ->description("Attribute to sort rankings by: 'revenue', 'units_sold', or 'gross_margin'. Defaults to 'revenue'.")
                ->enum(['revenue', 'units_sold', 'gross_margin']),
            'direction' => $schema->string()
                ->description("Sort direction: 'desc' (highest first) or 'asc' (lowest first). Defaults to 'desc'.")
                ->enum(['asc', 'desc']),
            'limit' => $schema->integer()
                ->description('Maximum number of products to return (1-50). Defaults to 10.'),
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

        // 2. Parse Arguments
        try {
            $startDate = !empty($arguments['start_date'])
                ? Carbon::parse((string) $arguments['start_date'])
                : Carbon::today()->subDays(29);

            $endDate = !empty($arguments['end_date'])
                ? Carbon::parse((string) $arguments['end_date'])
                : Carbon::today();
        } catch (\Throwable) {
            return ToolResult::failure("Invalid date format provided. Expected format is 'YYYY-MM-DD'.");
        }

        if ($startDate->gt($endDate)) {
            return ToolResult::failure("'start_date' cannot be after 'end_date'.");
        }

        $startDateTime = $startDate->copy()->startOfDay()->toDateTimeString();
        $endDateTime = $endDate->copy()->endOfDay()->toDateTimeString();

        $category = !empty($arguments['category']) ? trim((string) $arguments['category']) : null;
        $sortBy = strtolower(trim((string) ($arguments['sort_by'] ?? 'revenue')));
        $direction = strtolower(trim((string) ($arguments['direction'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
        $limit = min(max(1, (int) ($arguments['limit'] ?? 10)), 50);

        // 3. Build Aggregation Query
        $query = DB::table('analytics_products')
            ->join('analytics_order_items', 'analytics_products.id', '=', 'analytics_order_items.product_id')
            ->join('analytics_orders', 'analytics_orders.id', '=', 'analytics_order_items.order_id')
            ->select([
                'analytics_products.id as product_id',
                'analytics_products.name',
                'analytics_products.sku',
                'analytics_products.category',
                'analytics_products.unit_price',
                'analytics_products.cost_price',
                DB::raw('SUM(analytics_order_items.quantity) as units_sold'),
                DB::raw('ROUND(SUM(analytics_order_items.subtotal), 2) as revenue'),
                DB::raw('ROUND(SUM(analytics_order_items.quantity * analytics_products.cost_price), 2) as cogs'),
                DB::raw('ROUND(SUM(analytics_order_items.subtotal) - SUM(analytics_order_items.quantity * analytics_products.cost_price), 2) as gross_margin'),
            ])
            ->where('analytics_products.workspace_id', $workspaceId)
            ->whereNull('analytics_products.deleted_at')
            ->where('analytics_orders.workspace_id', $workspaceId)
            ->where('analytics_orders.status', 'completed')
            ->whereBetween('analytics_orders.order_date', [$startDateTime, $endDateTime]);

        if ($category !== null && $category !== '') {
            $query->where('analytics_products.category', $category);
        }

        $sortColumn = match ($sortBy) {
            'units_sold'   => 'units_sold',
            'gross_margin' => 'gross_margin',
            default        => 'revenue',
        };

        $rows = $query->groupBy(
            'analytics_products.id',
            'analytics_products.name',
            'analytics_products.sku',
            'analytics_products.category',
            'analytics_products.unit_price',
            'analytics_products.cost_price'
        )
            ->orderBy($sortColumn, $direction)
            ->limit($limit)
            ->get();

        $products = $rows->map(function ($row) {
            $revenue = (float) $row->revenue;
            $cogs = (float) $row->cogs;
            $grossMargin = (float) $row->gross_margin;
            $marginRate = $revenue > 0 ? round($grossMargin / $revenue, 4) : 0.0;

            return [
                'product_id'        => (int) $row->product_id,
                'name'              => (string) $row->name,
                'sku'               => $row->sku ? (string) $row->sku : null,
                'category'          => (string) $row->category,
                'unit_price'        => (float) $row->unit_price,
                'units_sold'        => (int) $row->units_sold,
                'revenue'           => $revenue,
                // Sensitive financial metrics (scrubbed automatically by policy if unauthorized)
                'cost_price'        => (float) $row->cost_price,
                'cogs'              => $cogs,
                'gross_margin'      => $grossMargin,
                'gross_margin_rate' => $marginRate,
            ];
        })->all();

        return ToolResult::success([
            'start_date' => $startDate->toDateString(),
            'end_date'   => $endDate->toDateString(),
            'category'   => $category,
            'sort_by'    => $sortColumn,
            'direction'  => $direction,
            'count'      => count($products),
            'products'   => $products,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }
}
