<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\AnalyticsCustomer;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Stringable;

/**
 * CustomerInsightsTool (customer_insights).
 *
 * Governed customer analytics tool.
 * Provides aggregate behavioral metrics: repeat buyer ratios, order frequency,
 * customer tier distribution, and spending cohorts with zero personal PII egress.
 */
class CustomerInsightsTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'customer_insights';
    }

    public function description(): Stringable|string
    {
        return 'Aggregate customer cohort intelligence: repeat purchase ratios, customer spending distribution, tier segments (VIP, Regular, New), and lifetime order frequency without exposing individual PII.';
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
            'limit' => $schema->integer()
                ->description('Maximum number of top customer cohorts to return (1-50). Defaults to 10.'),
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
        $limit = min(max(1, (int) ($arguments['limit'] ?? 10)), 50);

        // 2. Total Customer Counts
        $totalCustomers = AnalyticsCustomer::where('workspace_id', $workspaceId)->count();
        $activeCustomers = AnalyticsCustomer::where('workspace_id', $workspaceId)->where('is_active', true)->count();

        // 3. Aggregate Orders Per Customer
        $customerStats = DB::table('analytics_orders')
            ->select([
                'customer_id',
                DB::raw('COUNT(id) as order_count'),
                DB::raw('ROUND(SUM(net_amount), 2) as total_spend'),
                DB::raw('ROUND(AVG(net_amount), 2) as average_order_value'),
            ])
            ->where('workspace_id', $workspaceId)
            ->where('status', 'completed')
            ->groupBy('customer_id')
            ->orderBy('total_spend', 'desc')
            ->get();

        $orderingCustomersCount = $customerStats->count();
        $repeatBuyerCount = $customerStats->where('order_count', '>', 1)->count();
        $singleOrderCount = $customerStats->where('order_count', '=', 1)->count();

        $repeatBuyerRatio = $orderingCustomersCount > 0
            ? round($repeatBuyerCount / $orderingCustomersCount, 4)
            : 0.0;

        $totalRevenueFromOrders = round((float) $customerStats->sum('total_spend'), 2);
        $averageSpendPerCustomer = $orderingCustomersCount > 0
            ? round($totalRevenueFromOrders / $orderingCustomersCount, 2)
            : 0.0;

        // 4. Segmentation Tiers (VIP >= 5000, Regular 1000 - 4999.99, One-Time / Low < 1000)
        $vipCount = $customerStats->where('total_spend', '>=', 5000.0)->count();
        $vipRevenue = round((float) $customerStats->where('total_spend', '>=', 5000.0)->sum('total_spend'), 2);

        $regularCount = $customerStats->where('total_spend', '>=', 1000.0)->where('total_spend', '<', 5000.0)->count();
        $regularRevenue = round((float) $customerStats->where('total_spend', '>=', 1000.0)->where('total_spend', '<', 5000.0)->sum('total_spend'), 2);

        $entryCount = $customerStats->where('total_spend', '<', 1000.0)->count();
        $entryRevenue = round((float) $customerStats->where('total_spend', '<', 1000.0)->sum('total_spend'), 2);

        // 5. Anonymized Top Customer Cohorts (Strict Zero PII - No names, phones, or emails)
        $topCohorts = $customerStats->take($limit)->map(function ($stat, $index) {
            return [
                'cohort_rank'         => $index + 1,
                'customer_id'         => (int) $stat->customer_id,
                'order_count'         => (int) $stat->order_count,
                'total_spend'         => (float) $stat->total_spend,
                'average_order_value' => (float) $stat->average_order_value,
            ];
        })->values()->all();

        return ToolResult::success([
            'total_registered_customers' => $totalCustomers,
            'active_customers'           => $activeCustomers,
            'ordering_customers_count'   => $orderingCustomersCount,
            'repeat_buyer_count'         => $repeatBuyerCount,
            'single_order_buyer_count'   => $singleOrderCount,
            'repeat_buyer_ratio'         => $repeatBuyerRatio,
            'total_revenue'              => $totalRevenueFromOrders,
            'average_spend_per_customer' => $averageSpendPerCustomer,
            'tier_distribution'          => [
                'vip'      => ['count' => $vipCount, 'revenue' => $vipRevenue],
                'regular'  => ['count' => $regularCount, 'revenue' => $regularRevenue],
                'entry'    => ['count' => $entryCount, 'revenue' => $entryRevenue],
            ],
            'top_cohorts'                => $topCohorts,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }
}

