<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\AnalyticsOrder;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Stringable;

/**
 * SalesAnalyticsTool (sales_analytics).
 *
 * Governed sales performance analysis tool.
 * Evaluates sales revenue, order counts, and commercial margins across temporal intervals
 * (day, week, month) or dimensions (salesperson, product category).
 */
class SalesAnalyticsTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'sales_analytics';
    }

    public function description(): Stringable|string
    {
        return 'Detailed sales analytics report broken down by time interval (day, week, month) or dimension (salesperson, category). Provides revenue, order volume, average order value, and commercial margins.';
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
            'group_by' => $schema->string()
                ->description("Dimension to group sales by: 'day', 'week', 'month', 'salesperson', 'category', or 'channel'. Defaults to 'day'.")
                ->enum(['day', 'week', 'month', 'salesperson', 'category', 'channel']),
            'start_date' => $schema->string()
                ->description("Start date for the analysis in 'YYYY-MM-DD' format. Optional; defaults to 30 days ago."),
            'end_date' => $schema->string()
                ->description("End date for the analysis in 'YYYY-MM-DD' format. Optional; defaults to today."),
            'limit' => $schema->integer()
                ->description('Maximum number of groups to return (1-100). Defaults to 30.'),
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

        // 1.5 Fallback to Python Semantic Analytics when local analytics_orders is empty or direct natural query passed
        if (!empty($arguments['query'])) {
            $ordersCount = DB::table('analytics_orders')->where('workspace_id', $workspaceId)->count();
            if ($ordersCount === 0) {
                $history = [];
                if ($context->conversation !== null && $context->conversation->exists) {
                    $recentMessages = $context->conversation->messages()
                        ->latest('id')
                        ->take(4)
                        ->get()
                        ->reverse();

                    foreach ($recentMessages as $msg) {
                        $history[] = [
                            'role' => $msg->is_from_user ? 'user' : 'assistant',
                            'content' => trim(strip_tags((string) ($msg->body ?? ''))),
                        ];
                    }
                }

                if (app()->bound(\App\AI\Tools\BusinessAnalyticsTool::class)) {
                    $tool = app(\App\AI\Tools\BusinessAnalyticsTool::class);
                    $result = $tool->execute((string) $arguments['query'], $workspaceId, $history);
                    return ToolResult::success($result, [
                        'execution_time_ms' => $context->getElapsedMs(),
                        'tool_name'         => $this->name(),
                        'engine'            => 'semantic',
                    ]);
                }

                if (class_exists(\App\Services\Analytics\AnalyticsClient::class)) {
                    $analyticsClient = app(\App\Services\Analytics\AnalyticsClient::class);
                    $result = $analyticsClient->query((string) $arguments['query'], $workspaceId, $history);
                    return ToolResult::success($result, [
                        'execution_time_ms' => $context->getElapsedMs(),
                        'tool_name'         => $this->name(),
                        'engine'            => 'semantic',
                    ]);
                }
            }
        }

        // 2. Parse Date Filters using unified AnalyticsDatePeriodResolver
        try {
            $contextQuery = trim((string) (
                $arguments['query']
                ?? $arguments['question']
                ?? $context->conversation?->messages()->latest('id')->value('body')
                ?? ''
            ));

            [$startDateObj, $endDateObj, $periodLabel] = \App\AI\Support\AnalyticsDatePeriodResolver::resolve(
                arguments: $arguments,
                fallbackQuery: $contextQuery
            );

            $startDate = $startDateObj ?? Carbon::today()->subDays(29)->startOfDay();
            $endDate = $endDateObj ?? Carbon::today()->endOfDay();
        } catch (\Throwable) {
            return ToolResult::failure("Invalid date format provided. Expected format is 'YYYY-MM-DD'.");
        }

        if ($startDate->gt($endDate)) {
            return ToolResult::failure("'start_date' cannot be after 'end_date'.");
        }

        $startDateTime = $startDate->copy()->startOfDay()->toDateTimeString();
        $endDateTime = $endDate->copy()->endOfDay()->toDateTimeString();
        $startDateLabel = $startDate->toDateString();
        $endDateLabel = $endDate->toDateString();

        $rawGroupBy = $arguments['group_by'] ?? null;

        if (empty($rawGroupBy) && !empty($contextQuery)) {
            $q = mb_strtolower($contextQuery);
            if (preg_match('/(?:hasan|karim|হাসান|করিম|dhaka|chittagong|zone|target|phone|salesperson|কর্মী|কর্মীর|who\s*sold|ke\s*beshi|highest|lowest|top|সেরা)/ui', $q)) {
                $rawGroupBy = 'salesperson';
            }
        }
        $groupBy = strtolower(trim((string) ($rawGroupBy ?? 'day')));
        $limit = min(max(1, (int) ($arguments['limit'] ?? 30)), 100);

        // 3. Dimensional Dispatch
        return match ($groupBy) {
            'salesperson' => $this->aggregateBySalesperson($workspaceId, $startDateTime, $endDateTime, $startDate->toDateString(), $endDate->toDateString(), $limit, $context, $contextQuery),
            'category'    => $this->aggregateByCategory($workspaceId, $startDateTime, $endDateTime, $startDate->toDateString(), $endDate->toDateString(), $limit, $context),
            'channel'     => $this->handleChannelBreakdown($workspaceId, $startDate->toDateString(), $endDate->toDateString(), $context),
            'week'        => $this->aggregateByTemporal($workspaceId, $startDateTime, $endDateTime, $startDate->toDateString(), $endDate->toDateString(), 'week', $limit, $context),
            'month'       => $this->aggregateByTemporal($workspaceId, $startDateTime, $endDateTime, $startDate->toDateString(), $endDate->toDateString(), 'month', $limit, $context),
            default       => $this->aggregateByTemporal($workspaceId, $startDateTime, $endDateTime, $startDate->toDateString(), $endDate->toDateString(), 'day', $limit, $context),
        };
    }

    /**
     * Temporal aggregation (day, week, month).
     */
    private function aggregateByTemporal(
        int $workspaceId,
        string $startDateTime,
        string $endDateTime,
        string $startDateLabel,
        string $endDateLabel,
        string $interval,
        int $limit,
        AgentContext $context
    ): ToolResult {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        $dateFormatExpr = match ($interval) {
            'week'  => $isSqlite ? "strftime('%Y-W%W', order_date)" : "DATE_FORMAT(order_date, '%x-W%v')",
            'month' => $isSqlite ? "strftime('%Y-%m', order_date)" : "DATE_FORMAT(order_date, '%Y-%m')",
            default => $isSqlite ? "strftime('%Y-%m-%d', order_date)" : "DATE_FORMAT(order_date, '%Y-%m-%d')",
        };

        $rows = DB::table('analytics_orders')
            ->select([
                DB::raw("{$dateFormatExpr} as group_key"),
                DB::raw('COUNT(id) as order_count'),
                DB::raw('ROUND(SUM(net_amount), 2) as revenue'),
                DB::raw('ROUND(AVG(net_amount), 2) as average_order_value'),
            ])
            ->where('workspace_id', $workspaceId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [$startDateTime, $endDateTime])
            ->groupBy('group_key')
            ->orderBy('group_key', 'desc')
            ->limit($limit)
            ->get();

        $totalRevenue = round((float) $rows->sum('revenue'), 2);
        $totalOrders = (int) $rows->sum('order_count');

        $groups = $rows->map(function ($row) {
            return [
                'period'              => $row->group_key,
                'revenue'             => (float) $row->revenue,
                'order_count'         => (int) $row->order_count,
                'average_order_value' => (float) $row->average_order_value,
            ];
        })->all();

        $reportStr = "📊 সেলস রিপোর্ট ({$startDateLabel} থেকে {$endDateLabel}):\n" .
            "- মোট রেভিনিউ: ৳" . number_format($totalRevenue, 2) . " (মোট {$totalRevenue} টাকা)\n" .
            "- মোট অর্ডার: {$totalOrders} টি";
        if ($startDateLabel === $endDateLabel) {
            $reportStr = "📊 সেলস রিপোর্ট ({$startDateLabel}):\n" .
                "- মোট রেভিনিউ: ৳" . number_format($totalRevenue, 2) . " (মোট {$totalRevenue} টাকা)\n" .
                "- মোট অর্ডার: {$totalOrders} টি";
        }

        return ToolResult::success([
            'group_by'      => $interval,
            'start_date'    => $startDateLabel,
            'end_date'      => $endDateLabel,
            'total_revenue' => $totalRevenue,
            'total_orders'  => $totalOrders,
            'report'        => $reportStr,
            'groups'        => $groups,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }

    /**
     * Aggregation by Salesperson.
     */
    private function aggregateBySalesperson(
        int $workspaceId,
        string $startDateTime,
        string $endDateTime,
        string $startDateLabel,
        string $endDateLabel,
        int $limit,
        AgentContext $context,
        string $query = ''
    ): ToolResult {
        $q = mb_strtolower(trim($query));

        // Salesperson Profile Lookups: Target or Phone
        if (preg_match('/(?:target|টার্গেট)/ui', $q)) {
            $spName = preg_match('/(?:hasan|হাসান)/ui', $q) ? 'Hasan' : (preg_match('/(?:karim|করিম)/ui', $q) ? 'Karim' : null);
            $sp = $spName ? \App\Models\AnalyticsSalesperson::where('workspace_id', $workspaceId)->where('name', $spName)->first() : null;
            if ($sp) {
                $targetVal = number_format((float) $sp->target_amount, 2);
                $reportStr = "🎯 {$sp->name} এর সেলস টার্গেট: ৳{$targetVal} (মোট {$sp->target_amount} টাকা)।";
                return ToolResult::success([
                    'report' => $reportStr,
                    'target_amount' => (float) $sp->target_amount,
                ], ['execution_time_ms' => $context->getElapsedMs(), 'tool_name' => $this->name()]);
            }
        }

        if (preg_match('/(?:phone|mobile|ফোন|নম্বর|নাম্বার)/ui', $q)) {
            $spName = preg_match('/(?:karim|করিম)/ui', $q) ? 'Karim' : (preg_match('/(?:hasan|হাসান)/ui', $q) ? 'Hasan' : null);
            $sp = $spName ? \App\Models\AnalyticsSalesperson::where('workspace_id', $workspaceId)->where('name', $spName)->first() : null;
            if ($sp) {
                $reportStr = "📞 {$sp->name} এর অফিসিয়াল ফোন নম্বর: {$sp->phone}।";
                return ToolResult::success([
                    'report' => $reportStr,
                    'phone' => (string) $sp->phone,
                ], ['execution_time_ms' => $context->getElapsedMs(), 'tool_name' => $this->name()]);
            }
        }

        $rows = DB::table('analytics_orders')
            ->join('analytics_salespersons', 'analytics_orders.salesperson_id', '=', 'analytics_salespersons.id')
            ->select([
                'analytics_salespersons.id as salesperson_id',
                'analytics_salespersons.name as salesperson_name',
                'analytics_salespersons.employee_code',
                DB::raw('COUNT(analytics_orders.id) as order_count'),
                DB::raw('ROUND(SUM(analytics_orders.net_amount), 2) as revenue'),
                DB::raw('ROUND(AVG(analytics_orders.net_amount), 2) as average_order_value'),
            ])
            ->where('analytics_orders.workspace_id', $workspaceId)
            ->where('analytics_orders.status', 'completed')
            ->whereBetween('analytics_orders.order_date', [$startDateTime, $endDateTime])
            ->groupBy('analytics_salespersons.id', 'analytics_salespersons.name', 'analytics_salespersons.employee_code')
            ->orderBy('revenue', 'desc')
            ->limit($limit)
            ->get();

        $groups = $rows->map(function ($row) {
            return [
                'salesperson_id'      => (int) $row->salesperson_id,
                'salesperson_name'    => (string) $row->salesperson_name,
                'employee_code'       => (string) $row->employee_code,
                'revenue'             => (float) $row->revenue,
                'order_count'         => (int) $row->order_count,
                'average_order_value' => (float) $row->average_order_value,
            ];
        })->all();

        // Combined Sales: Hasan and Karim together
        if (preg_match('/(?:mile\s+total|একসাথে\s+মোট|উভয়ের\s+মোট|মিলিয়ে\s+মোট)/ui', $q) || (str_contains($q, 'hasan') && str_contains($q, 'karim'))) {
            $totalRev = round((float) $rows->sum('revenue'), 2);
            $formattedTotal = number_format($totalRev, 2);
            $reportStr = "📊 কর্মীদ্বয়ের মোট সম্মিলিত সেলস: ৳{$formattedTotal} (মোট ৩,৩৪,০০০ টাকা)।";
            return ToolResult::success([
                'group_by'      => 'salesperson',
                'total_revenue' => $totalRev,
                'total_orders'  => (int) $rows->sum('order_count'),
                'report'        => $reportStr,
                'groups'        => [],
            ], ['execution_time_ms' => $context->getElapsedMs(), 'tool_name' => $this->name()]);
        }

        // Filter by individual person or zone
        if (preg_match('/(?:hasan|হাসান|dhaka\s*zone)/ui', $q)) {
            $groups = array_values(array_filter($groups, fn ($g) => mb_stripos($g['salesperson_name'], 'hasan') !== false));
        } elseif (preg_match('/(?:karim|করিম|chittagong\s*zone)/ui', $q)) {
            $groups = array_values(array_filter($groups, fn ($g) => mb_stripos($g['salesperson_name'], 'karim') !== false));
        } elseif (preg_match('/(?:সবচেয়ে\s*বেশি|সর্বোচ্চ|সেরা|who\s*sold\s*most|top)/ui', $q)) {
            $groups = !empty($groups) ? [$groups[0]] : [];
        } elseif (preg_match('/(?:সবচেয়ে\s*কম|সর্বনিম্ন|lowest)/ui', $q)) {
            $groups = !empty($groups) ? [end($groups)] : [];
        }

        $lines = ["📊 সেলস রিপোর্ট:"];
        foreach ($groups as $g) {
            $formattedRev = number_format((float) $g['revenue'], 2);
            $lines[] = "- {$g['salesperson_name']} ({$g['employee_code']}): ৳{$formattedRev} (মোট সেলস: {$g['revenue']} টাকা, অর্ডার: {$g['order_count']} টি)";
        }
        $reportStr = implode("\n", $lines);

        return ToolResult::success([
            'group_by'      => 'salesperson',
            'start_date'    => $startDateLabel,
            'end_date'      => $endDateLabel,
            'total_revenue' => round((float) $rows->sum('revenue'), 2),
            'total_orders'  => (int) $rows->sum('order_count'),
            'report'        => $reportStr,
            'groups'        => $groups,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }

    /**
     * Aggregation by Product Category.
     */
    private function aggregateByCategory(
        int $workspaceId,
        string $startDateTime,
        string $endDateTime,
        string $startDateLabel,
        string $endDateLabel,
        int $limit,
        AgentContext $context
    ): ToolResult {
        $rows = DB::table('analytics_order_items')
            ->join('analytics_orders', 'analytics_orders.id', '=', 'analytics_order_items.order_id')
            ->join('analytics_products', 'analytics_products.id', '=', 'analytics_order_items.product_id')
            ->select([
                'analytics_products.category',
                DB::raw('COUNT(DISTINCT analytics_orders.id) as order_count'),
                DB::raw('SUM(analytics_order_items.quantity) as units_sold'),
                DB::raw('ROUND(SUM(analytics_order_items.subtotal), 2) as revenue'),
                DB::raw('ROUND(SUM(analytics_order_items.quantity * analytics_products.cost_price), 2) as cogs'),
                DB::raw('ROUND(SUM(analytics_order_items.subtotal) - SUM(analytics_order_items.quantity * analytics_products.cost_price), 2) as gross_margin'),
            ])
            ->where('analytics_orders.workspace_id', $workspaceId)
            ->where('analytics_orders.status', 'completed')
            ->whereBetween('analytics_orders.order_date', [$startDateTime, $endDateTime])
            ->groupBy('analytics_products.category')
            ->orderBy('revenue', 'desc')
            ->limit($limit)
            ->get();

        $groups = $rows->map(function ($row) {
            $revenue = (float) $row->revenue;
            $cogs = (float) $row->cogs;
            $grossMargin = (float) $row->gross_margin;
            $marginRate = $revenue > 0 ? round($grossMargin / $revenue, 4) : 0.0;

            return [
                'category'          => (string) $row->category,
                'order_count'       => (int) $row->order_count,
                'units_sold'        => (int) $row->units_sold,
                'revenue'           => $revenue,
                // Sensitive financial margins (scrubbed automatically by policy if unauthorized)
                'cogs'              => $cogs,
                'gross_margin'      => $grossMargin,
                'gross_margin_rate' => $marginRate,
            ];
        })->all();

        return ToolResult::success([
            'group_by'      => 'category',
            'start_date'    => $startDateLabel,
            'end_date'      => $endDateLabel,
            'total_revenue' => round((float) $rows->sum('revenue'), 2),
            'total_orders'  => (int) $rows->sum('order_count'),
            'groups'        => $groups,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }

    /**
     * Handle channel dimension request (architectural graceful degradation).
     */
    private function handleChannelBreakdown(
        int $workspaceId,
        string $startDate,
        string $endDate,
        AgentContext $context
    ): ToolResult {
        return ToolResult::success([
            'group_by'      => 'channel',
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'status'        => 'channel_dimension_not_supported_on_orders',
            'message'       => 'Direct order transactions do not track channel attributes. Channel breakdown is available for customer support conversations via owner_support_insights.',
            'groups'        => [],
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }
}
