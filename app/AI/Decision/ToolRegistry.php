<?php

declare(strict_types=1);

namespace App\AI\Decision;

use App\AI\Contracts\ToolInterface;
use App\AI\Tools\BusinessOverviewTool;
use App\AI\Tools\CustomerInsightsTool;
use App\AI\Tools\EmployeeAnalyticsTool;
use App\AI\Tools\FinancialAnalyticsTool;
use App\AI\Tools\InventoryAnalyticsTool;
use App\AI\Tools\KnowledgeIntelligenceTool;
use App\AI\Tools\LegacyExcelAnalyticsTool;
use App\AI\Tools\OwnerCustomerSupportInsightsTool;
use App\AI\Tools\ProductPerformanceTool;
use App\AI\Tools\SalesAnalyticsTool;
use App\Models\User;
use InvalidArgumentException;

/**
 * Server-Controlled Tool Registry.
 *
 * Manages registered enterprise tools, operation allowlists, and contextual eligibility.
 * Prevents the LLM from inventing tools, operations, or executing unauthorized actions.
 */
class ToolRegistry
{
    /**
     * @var array<string, ToolInterface>
     */
    protected array $tools = [];

    /**
     * @var array<string, array<int, string>>
     */
    protected array $allowedOperations = [];

    public function __construct()
    {
        $this->registerDefaultTools();
    }

    protected function registerDefaultTools(): void
    {
        // 10 Governed Analytical & Knowledge Tools
        $this->register(
            tool: app(SalesAnalyticsTool::class),
            operations: ['get_sales_summary', 'get_sales_by_salesperson', 'get_sales_by_interval']
        );

        $this->register(
            tool: app(InventoryAnalyticsTool::class),
            operations: ['get_stock_overview', 'get_low_stock_alerts', 'get_out_of_stock']
        );

        $this->register(
            tool: app(FinancialAnalyticsTool::class),
            operations: ['get_financial_summary', 'get_cash_flow', 'get_margins']
        );

        $this->register(
            tool: app(EmployeeAnalyticsTool::class),
            operations: ['get_salesperson_ranking', 'get_employee_performance']
        );

        $this->register(
            tool: app(CustomerInsightsTool::class),
            operations: ['get_customer_overview', 'get_top_buyers', 'get_dues']
        );

        $this->register(
            tool: app(ProductPerformanceTool::class),
            operations: ['get_top_products', 'get_slow_moving_products']
        );

        $this->register(
            tool: app(BusinessOverviewTool::class),
            operations: ['get_executive_pulse']
        );

        $this->register(
            tool: app(OwnerCustomerSupportInsightsTool::class),
            operations: ['get_support_metrics']
        );

        $this->register(
            tool: app(KnowledgeIntelligenceTool::class),
            operations: ['search_knowledge', 'check_product_availability']
        );

        $this->register(
            tool: app(LegacyExcelAnalyticsTool::class),
            operations: ['query_spreadsheet']
        );
    }

    /**
     * Register a tool and its allowed operations.
     *
     * @param ToolInterface $tool
     * @param array<int, string> $operations
     */
    public function register(ToolInterface $tool, array $operations = []): void
    {
        $this->tools[$tool->name()] = $tool;
        $this->allowedOperations[$tool->name()] = !empty($operations)
            ? $operations
            : ['execute', 'default'];
    }

    public function hasTool(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function getTool(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Check if a specific operation is valid on a given tool.
     */
    public function isOperationAllowed(string $toolName, string $operation): bool
    {
        if (!isset($this->allowedOperations[$toolName])) {
            return false;
        }

        // Empty operations list or 'default'/'execute' allows standard execution
        if (in_array('*', $this->allowedOperations[$toolName], true)) {
            return true;
        }

        return in_array($operation, $this->allowedOperations[$toolName], true);
    }

    /**
     * Get list of permitted tool names based on caller authorization and channel.
     *
     * @param bool $isCustomer Whether request is from an inbound customer channel
     * @param User|null $user Authenticated actor
     * @param int $workspaceId Target tenant workspace
     * @return array<int, string>
     */
    public function getPermittedToolNames(bool $isCustomer, ?User $user, int $workspaceId): array
    {
        // Public customer channels only get read-only knowledge tools
        if ($isCustomer) {
            return [
                'knowledge_intelligence',
            ];
        }

        // Unauthenticated or non-privileged users cannot execute owner analytics tools
        if ($user === null) {
            return [
                'knowledge_intelligence',
            ];
        }

        $isOwnerOrManager = $user->hasRole('superadmin')
            || (
                (int) $user->workspace_id === $workspaceId && (
                    $user->hasRole('admin')
                    || $user->hasRole('owner')
                    || $user->hasRole('manager')
                    || $user->can('view_analytics')
                    || $user->can('analytics.view_basic')
                )
            );

        if (!$isOwnerOrManager) {
            return [
                'knowledge_intelligence',
            ];
        }

        // Privileged Owner / Manager gets the full governed suite
        return array_keys($this->tools);
    }

    /**
     * Format tool signatures for LLM prompt injection.
     *
     * @param array<int, string> $allowedToolNames
     * @return string
     */
    public function formatToolsForPrompt(array $allowedToolNames): string
    {
        $lines = [];
        foreach ($allowedToolNames as $name) {
            if (!isset($this->tools[$name])) {
                continue;
            }
            $tool = $this->tools[$name];
            $ops = implode(', ', $this->allowedOperations[$name] ?? ['default']);
            $lines[] = "- {$name} (operations: {$ops}): " . trim((string) $tool->description());
        }

        return implode("\n", $lines);
    }
}

