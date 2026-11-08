<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\AnalyticsSalesperson;
use App\Models\StructuredRecord;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Stringable;

/**
 * EmployeeAnalyticsTool (employee_analytics).
 *
 * Governed workforce analytics tool.
 * Provides sales force headcount, target performance, and compensation metrics.
 * Enforces granular 5-level sensitivity classification:
 * - Headcount & targets: PUBLIC
 * - Base salary / Payroll: HR (requires hr.view_salaries)
 * - Commission expense: FINANCIAL (requires analytics.view_margins)
 * - Employee cost: FINANCIAL_AND_HR (CONJUNCTIVE: requires both margins and HR permissions)
 */
class EmployeeAnalyticsTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'employee_analytics';
    }

    public function description(): Stringable|string
    {
        return 'Workforce analytics report: sales headcount, sales performance vs targets, and staff compensation expenditure.';
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
                ->description('Maximum number of staff members to return (1-50). Defaults to 20.'),
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
        $limit = min(max(1, (int) ($arguments['limit'] ?? 20)), 50);

        // 2. Query Headcount & Sales Performance from MySQL
        $salespersons = AnalyticsSalesperson::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->limit($limit)
            ->get();

        $totalHeadcount = AnalyticsSalesperson::where('workspace_id', $workspaceId)->count();
        $activeHeadcount = $salespersons->count();

        // 3. Query Ingested Compensation from Structured Records
        $payrollRecords = StructuredRecord::where('workspace_id', $workspaceId)
            ->where(function ($q) {
                $q->where('canonical_category', 'LIKE', '%salary%')
                    ->orWhere('canonical_category', 'LIKE', '%payroll%')
                    ->orWhere('canonical_category', 'LIKE', '%compensation%')
                    ->orWhere('sheet_name', 'LIKE', '%payroll%')
                    ->orWhere('sheet_name', 'LIKE', '%salary%')
                    ->orWhere('sheet_name', 'LIKE', '%employee%');
            })
            ->whereNotNull('canonical_amount');

        $hasPayroll = (clone $payrollRecords)->exists();

        $totalBaseSalary = $hasPayroll ? round((float) (clone $payrollRecords)->sum('canonical_amount'), 2) : 0.0;

        // Commission expense calculated as 5% of completed sales (or 0 if no sales)
        $totalSales = (float) DB::table('analytics_orders')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'completed')
            ->sum('net_amount');

        $totalCommissions = round($totalSales * 0.05, 2);
        $totalEmployeeCost = round($totalBaseSalary + $totalCommissions, 2);

        // Pre-aggregate sales achieved by salesperson in a single query (O(1) lookup, eliminates N+1 query pattern)
        $salesByRep = DB::table('analytics_orders')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'completed')
            ->groupBy('salesperson_id')
            ->select('salesperson_id', DB::raw('SUM(net_amount) as total_achieved'))
            ->pluck('total_achieved', 'salesperson_id');

        // 4. Map Per-Employee Performance and Compensation
        $staffMembers = $salespersons->map(function (AnalyticsSalesperson $sp) use ($hasPayroll, $salesByRep) {
            $achievedSales = (float) ($salesByRep[$sp->id] ?? 0.0);

            $target = (float) $sp->target_amount;
            $achievementRatio = $target > 0 ? round($achievedSales / $target, 4) : 0.0;

            $estSalary = $hasPayroll ? 25000.00 : 0.0; // Standardized per-rep baseline if sheet ingested
            $estCommission = round($achievedSales * 0.05, 2);
            $estCost = round($estSalary + $estCommission, 2);

            return [
                'salesperson_id'    => (int) $sp->id,
                'name'              => (string) $sp->name,
                'employee_code'     => (string) $sp->employee_code,
                'target_amount'     => $target,
                'sales_achieved'    => round($achievedSales, 2),
                'achievement_ratio' => $achievementRatio,
                // Granular Sensitive Metrics
                'base_salary'       => $estSalary, // HR Category
                'commission_expense'=> $estCommission, // Financial Category
                'employee_cost'     => $estCost, // Dual Financial & HR Category (Conjunctive)
            ];
        })->all();

        return ToolResult::success([
            'total_headcount'        => $totalHeadcount,
            'active_headcount'       => $activeHeadcount,
            // Granular Sensitive Metrics
            'base_salary'            => $totalBaseSalary, // HR Category
            'commission_expense'     => $totalCommissions, // Financial Category
            'employee_cost'          => $totalEmployeeCost, // Dual Financial & HR Category (Conjunctive)
            'staff_members'          => $staffMembers,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }
}

