<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\AnalyticsOrder;
use App\Models\StructuredRecord;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Stringable;

/**
 * FinancialAnalyticsTool (financial_analytics).
 *
 * Privileged financial reporting and profitability analysis tool.
 * Requires explicit 'analytics.view_margins' permission at invocation.
 * Reports on Gross Sales, Discounts, Net Revenue, COGS, Gross Profit,
 * and Operating Expenses / Net Profit (when backed by ingested expense sheets).
 */
class FinancialAnalyticsTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'financial_analytics';
    }

    public function description(): Stringable|string
    {
        return 'Commercial profitability and financial performance report: Gross Sales, Net Revenue, COGS, Gross Profit, Margin Rates, and Operating Expenses / Net Profit.';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRequiredPermissions(): array
    {
        return ['analytics.view_margins'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'start_date' => $schema->string()
                ->description("Analysis start date in 'YYYY-MM-DD' format. Optional; defaults to 30 days ago."),
            'end_date' => $schema->string()
                ->description("Analysis end date in 'YYYY-MM-DD' format. Optional; defaults to today."),
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

        // 2. Parse Date Boundaries
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

        // 3. Query Orders for Revenue Breakdown
        $ordersQuery = AnalyticsOrder::where('workspace_id', $workspaceId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [$startDateTime, $endDateTime]);

        $grossSales = round((float) $ordersQuery->sum('total_amount'), 2);
        $totalDiscount = round((float) $ordersQuery->sum('discount'), 2);
        $netRevenue = round((float) $ordersQuery->sum('net_amount'), 2);

        // 4. Calculate COGS via Order Items + Product Cost Prices
        $cogs = round((float) DB::table('analytics_order_items')
            ->join('analytics_orders', 'analytics_orders.id', '=', 'analytics_order_items.order_id')
            ->join('analytics_products', 'analytics_products.id', '=', 'analytics_order_items.product_id')
            ->where('analytics_orders.workspace_id', $workspaceId)
            ->where('analytics_orders.status', 'completed')
            ->whereBetween('analytics_orders.order_date', [$startDateTime, $endDateTime])
            ->sum(DB::raw('analytics_order_items.quantity * analytics_products.cost_price')), 2);

        $grossProfit = round($netRevenue - $cogs, 2);
        $grossMarginRate = $netRevenue > 0 ? round($grossProfit / $netRevenue, 4) : 0.0;

        // 5. Query Operating Expenses from Ingested Expense Records (if present)
        $expenseRecords = StructuredRecord::where('workspace_id', $workspaceId)
            ->where(function ($q) {
                $q->where('canonical_category', 'Expense')
                    ->orWhere('canonical_category', 'Operating Expense')
                    ->orWhere('sheet_name', 'LIKE', '%expense%')
                    ->orWhere('sheet_name', 'LIKE', '%cost%');
            })
            ->whereNotNull('canonical_amount');

        $hasExpenses = (clone $expenseRecords)->exists();

        if ($hasExpenses) {
            $operatingExpenses = round((float) (clone $expenseRecords)->sum('canonical_amount'), 2);
            $netProfit = round($grossProfit - $operatingExpenses, 2);
            $netMarginRate = $netRevenue > 0 ? round($netProfit / $netRevenue, 4) : 0.0;
            $expenseNote = 'Operating expenses loaded from ingested expense records.';
        } else {
            $operatingExpenses = null;
            $netProfit = null;
            $netMarginRate = null;
            $expenseNote = 'Operating expenses and net profit require an uploaded expense spreadsheet; currently reporting verified gross profit and gross margin.';
        }

        return ToolResult::success([
            'start_date'         => $startDate->toDateString(),
            'end_date'           => $endDate->toDateString(),
            'gross_sales'        => $grossSales,
            'total_discount'     => $totalDiscount,
            'net_revenue'        => $netRevenue,
            // Sensitive financial metrics
            'cogs'               => $cogs,
            'gross_profit'       => $grossProfit,
            'gross_margin_rate'  => $grossMarginRate,
            'operating_expenses' => $operatingExpenses,
            'net_profit'         => $netProfit,
            'net_margin_rate'    => $netMarginRate,
            'financial_note'     => $expenseNote,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }
}

