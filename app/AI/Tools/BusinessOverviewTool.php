<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsPayment;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Stringable;

/**
 * BusinessOverviewTool (business_overview).
 *
 * Governed executive business health KPI overview tool.
 * Provides high-level visibility into company revenue, order volume, cash collections,
 * outstanding receivables, active customer count, and commercial margins.
 */
class BusinessOverviewTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'business_overview';
    }

    public function description(): Stringable|string
    {
        return 'Executive dashboard summarizing core business KPIs: gross sales revenue, order counts, cash collections, outstanding receivables, active customers, and gross commercial margins for a given time period.';
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
            'period' => $schema->string()
                ->description("Time period shortcut: 'today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', or 'all_time'. Defaults to 'last_30_days'.")
                ->enum(['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'all_time', 'custom']),
            'start_date' => $schema->string()
                ->description("Custom start date in 'YYYY-MM-DD' format. Required if period is 'custom'."),
            'end_date' => $schema->string()
                ->description("Custom end date in 'YYYY-MM-DD' format. Optional; defaults to today if period is 'custom'."),
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
            [$startDate, $endDate, $periodLabel] = $this->resolveDateRange($arguments);
        } catch (\Throwable) {
            return ToolResult::failure("Invalid date format provided. Expected format is 'YYYY-MM-DD'.");
        }

        if ($startDate !== null && $endDate !== null && $startDate->gt($endDate)) {
            return ToolResult::failure("'start_date' cannot be after 'end_date'.");
        }

        $startDateTime = $startDate?->copy()->startOfDay()->toDateTimeString();
        $endDateTime = $endDate?->copy()->endOfDay()->toDateTimeString();

        // 3. Query Orders (Revenue & Volume)
        $ordersQuery = AnalyticsOrder::where('workspace_id', $workspaceId)
            ->where('status', 'completed');

        if ($startDateTime !== null && $endDateTime !== null) {
            $ordersQuery->whereBetween('order_date', [$startDateTime, $endDateTime]);
        } elseif ($startDateTime !== null) {
            $ordersQuery->where('order_date', '>=', $startDateTime);
        } elseif ($endDateTime !== null) {
            $ordersQuery->where('order_date', '<=', $endDateTime);
        }

        $grossSales = round((float) $ordersQuery->sum('net_amount'), 2);
        $totalOrderAmount = round((float) $ordersQuery->sum('total_amount'), 2);
        $totalDiscount = round((float) $ordersQuery->sum('discount'), 2);
        $orderCount = (int) $ordersQuery->count();

        // 4. Query Payments (Cash Collections)
        $paymentsQuery = AnalyticsPayment::where('workspace_id', $workspaceId);

        if ($startDateTime !== null && $endDateTime !== null) {
            $paymentsQuery->whereBetween('collected_at', [$startDateTime, $endDateTime]);
        } elseif ($startDateTime !== null) {
            $paymentsQuery->where('collected_at', '>=', $startDateTime);
        } elseif ($endDateTime !== null) {
            $paymentsQuery->where('collected_at', '<=', $endDateTime);
        }

        $cashCollected = round((float) $paymentsQuery->sum('amount'), 2);

        // Outstanding dues across current completed orders
        $outstandingDues = max(0.0, round($grossSales - $cashCollected, 2));

        // 5. Query Customers
        $activeCustomers = AnalyticsCustomer::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->count();

        // 6. Calculate COGS & Commercial Margin (Aggregating Order Items + Product Cost)
        // Enforces Cartesian safety by calculating COGS in a single subquery join
        $cogsQuery = DB::table('analytics_order_items')
            ->join('analytics_orders', 'analytics_orders.id', '=', 'analytics_order_items.order_id')
            ->join('analytics_products', 'analytics_products.id', '=', 'analytics_order_items.product_id')
            ->where('analytics_orders.workspace_id', $workspaceId)
            ->where('analytics_orders.status', 'completed');

        if ($startDateTime !== null && $endDateTime !== null) {
            $cogsQuery->whereBetween('analytics_orders.order_date', [$startDateTime, $endDateTime]);
        } elseif ($startDateTime !== null) {
            $cogsQuery->where('analytics_orders.order_date', '>=', $startDateTime);
        } elseif ($endDateTime !== null) {
            $cogsQuery->where('analytics_orders.order_date', '<=', $endDateTime);
        }

        $cogs = round((float) $cogsQuery->sum(DB::raw('analytics_order_items.quantity * analytics_products.cost_price')), 2);
        $grossMargin = round($grossSales - $cogs, 2);
        $grossMarginRate = $grossSales > 0 ? round($grossMargin / $grossSales, 4) : 0.0;

        return ToolResult::success([
            'period'             => $periodLabel,
            'start_date'         => $startDate?->toDateString(),
            'end_date'           => $endDate?->toDateString(),
            'gross_sales'        => $grossSales,
            'total_order_amount' => $totalOrderAmount,
            'total_discount'     => $totalDiscount,
            'order_count'        => $orderCount,
            'cash_collected'     => $cashCollected,
            'outstanding_dues'   => $outstandingDues,
            'active_customers'   => $activeCustomers,
            // Sensitive financial metrics (subject to automatic field-level projection by policy)
            'cogs'               => $cogs,
            'gross_margin'       => $grossMargin,
            'gross_margin_rate'  => $grossMarginRate,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }

    /**
     * Resolve date boundaries from tool arguments.
     *
     * @return array{0: Carbon|null, 1: Carbon|null, 2: string}
     */
    private function resolveDateRange(array $arguments): array
    {
        $period = strtolower(trim((string) ($arguments['period'] ?? '')));
        if ($period === '' && (!empty($arguments['start_date']) || !empty($arguments['end_date']))) {
            $period = 'custom';
        } elseif ($period === '') {
            $period = 'last_30_days';
        }

        return match ($period) {
            'today' => [
                Carbon::today(),
                Carbon::today(),
                'today',
            ],
            'yesterday' => [
                Carbon::yesterday(),
                Carbon::yesterday(),
                'yesterday',
            ],
            'last_7_days' => [
                Carbon::today()->subDays(6),
                Carbon::today(),
                'last_7_days',
            ],
            'this_month' => [
                Carbon::today()->startOfMonth(),
                Carbon::today(),
                'this_month',
            ],
            'last_month' => [
                Carbon::today()->subMonth()->startOfMonth(),
                Carbon::today()->subMonth()->endOfMonth(),
                'last_month',
            ],
            'all_time' => [
                null,
                null,
                'all_time',
            ],
            'custom' => [
                !empty($arguments['start_date']) ? Carbon::parse((string) $arguments['start_date']) : Carbon::today()->subDays(29),
                !empty($arguments['end_date']) ? Carbon::parse((string) $arguments['end_date']) : Carbon::today(),
                'custom',
            ],
            default => [
                Carbon::today()->subDays(29),
                Carbon::today(),
                'last_30_days',
            ],
        };
    }
}
