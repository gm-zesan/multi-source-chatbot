<?php

declare(strict_types=1);

namespace App\AI\Policies;

use App\AI\Contracts\AgentInterface;
use App\AI\Contracts\ToolInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\UnauthorizedToolException;
use App\Services\Spreadsheet\Security\DataClassification;
use App\Services\Spreadsheet\Security\DataProjectionPolicy;
use Illuminate\Support\Facades\Log;

/**
 * Centralized, fail-closed Policy Decision Point (PDP) for governed tool execution.
 * Enforces agent tool whitelists, tenant workspace scoping, customer barriers,
 * and role-based field-level permissions.
 */
class ToolAuthorizationPolicy
{
    public const SENSITIVITY_PUBLIC = 'PUBLIC';
    public const SENSITIVITY_FINANCIAL = 'FINANCIAL';
    public const SENSITIVITY_HR = 'HR';
    public const SENSITIVITY_FINANCIAL_AND_HR = 'FINANCIAL_AND_HR';
    public const SENSITIVITY_RESTRICTED = 'RESTRICTED';

    public function __construct(
        private readonly DataProjectionPolicy $projectionPolicy = new DataProjectionPolicy(),
    ) {}

    /**
     * Authorize a specific tool invocation requested by an agent under an active AgentContext.
     *
     * @throws UnauthorizedToolException If authorization is denied (Fail-Closed).
     */
    public function authorize(AgentInterface $agent, ToolInterface $tool, AgentContext $context): bool
    {
        $toolName = $tool->name();
        $agentId = $agent->getAgentId();
        $workspaceId = $context->getWorkspaceId();

        // 1. Agent Whitelist Check: Tool must be explicitly registered on the Agent
        $registeredTools = $agent->getRegisteredTools();
        $isWhitelisted = false;
        foreach ($registeredTools as $regTool) {
            if ($regTool->name() === $toolName) {
                $isWhitelisted = true;
                break;
            }
        }

        if (! $isWhitelisted) {
            Log::warning('[ToolAuthorizationPolicy] Tool invocation rejected: tool not whitelisted on agent', [
                'agent_id'     => $agentId,
                'tool_name'    => $toolName,
                'workspace_id' => $workspaceId,
            ]);

            throw new UnauthorizedToolException(
                message: "Tool '{$toolName}' is not registered on agent '{$agentId}'.",
                toolName: $toolName,
                agentId: $agentId,
                workspaceId: $workspaceId,
            );
        }

        // 2. Authorize Tool Against Caller Context
        return $this->authorizeToolForContext($tool, $context, $agentId);
    }

    /**
     * Authorize a tool invocation directly against an AgentContext (used by SDK handle adapters).
     *
     * @throws UnauthorizedToolException
     */
    public function authorizeToolForContext(ToolInterface $tool, AgentContext $context, ?string $agentId = null): bool
    {
        $toolName = $tool->name();
        $workspaceId = $context->getWorkspaceId();

        // Fail-Closed: Workspace must be a positive integer
        if ($workspaceId <= 0) {
            throw new UnauthorizedToolException(
                message: "Invalid workspace context: '{$workspaceId}'.",
                toolName: $toolName,
                agentId: $agentId,
                workspaceId: $workspaceId,
            );
        }

        // ── Customer Security Barrier ─────────────────────────────────────────
        if ($context->isCustomer()) {
            // Customer channels are strictly forbidden from executing state-mutating tools
            if (! $tool->isReadOnly()) {
                Log::warning('[ToolAuthorizationPolicy] Customer channel attempted mutating tool execution', [
                    'tool_name'    => $toolName,
                    'workspace_id' => $workspaceId,
                    'channel'      => $context->getChannel(),
                ]);

                throw new UnauthorizedToolException(
                    message: "Customer channels are not authorized to execute mutating tool '{$toolName}'.",
                    toolName: $toolName,
                    agentId: $agentId,
                    workspaceId: $workspaceId,
                );
            }

            // Customer channels are forbidden from executing tools requiring internal permissions
            $requiredPermissions = $tool->getRequiredPermissions();
            if (! empty($requiredPermissions)) {
                Log::warning('[ToolAuthorizationPolicy] Customer channel attempted internal permissioned tool execution', [
                    'tool_name'    => $toolName,
                    'permissions'  => $requiredPermissions,
                    'workspace_id' => $workspaceId,
                ]);

                throw new UnauthorizedToolException(
                    message: "Customer channels are not authorized to execute privileged tool '{$toolName}'.",
                    toolName: $toolName,
                    agentId: $agentId,
                    workspaceId: $workspaceId,
                );
            }

            return true;
        }

        // ── Authenticated User & Role Verification ───────────────────────────
        $user = $context->getUser();
        if ($user === null) {
            throw new UnauthorizedToolException(
                message: "Unauthenticated execution context rejected for tool '{$toolName}'.",
                toolName: $toolName,
                agentId: $agentId,
                workspaceId: $workspaceId,
            );
        }

        // ── SuperAdmin Unrestricted Access ───────────────────────────────────
        if ($context->getUserRole() === 'superadmin') {
            return true;
        }

        // ── Required Tool Permissions Check ──────────────────────────────────
        $requiredPermissions = $tool->getRequiredPermissions();
        $isTrueOwner = $context->getUserRole() === 'owner';

        foreach ($requiredPermissions as $permission) {
            // Workspace owner has full commercial margin and tool authorization
            if ($isTrueOwner) {
                continue;
            }

            // Financial margins (cost_price / procurement)
            if ($permission === 'analytics.view_margins') {
                if (! $context->hasPermission('analytics.view_margins')) {
                    Log::warning('[ToolAuthorizationPolicy] User lacks analytics.view_margins for tool', [
                        'user_id'      => $user->id,
                        'user_role'    => $context->getUserRole(),
                        'tool_name'    => $toolName,
                        'workspace_id' => $workspaceId,
                    ]);

                    throw new UnauthorizedToolException(
                        message: "Access denied: Missing required permission 'analytics.view_margins' for tool '{$toolName}'.",
                        toolName: $toolName,
                        agentId: $agentId,
                        workspaceId: $workspaceId,
                    );
                }

                continue;
            }

            // General permissions check
            if (! $context->hasPermission($permission)) {
                Log::warning('[ToolAuthorizationPolicy] User lacks required permission for tool', [
                    'user_id'      => $user->id,
                    'permission'   => $permission,
                    'tool_name'    => $toolName,
                    'workspace_id' => $workspaceId,
                ]);

                throw new UnauthorizedToolException(
                    message: "Access denied: Missing required permission '{$permission}' for tool '{$toolName}'.",
                    toolName: $toolName,
                    agentId: $agentId,
                    workspaceId: $workspaceId,
                );
            }
        }

        return true;
    }

    /**
     * Check if context represents a SuperAdmin with an active, unexpired governance session.
     */
    public function isSuperAdminWithValidSession(AgentContext $context): bool
    {
        if ($context->getUserRole() !== 'superadmin') {
            return false;
        }

        $session = $context->serverControlledContext->governanceSession;
        if ($session === null || ! $session->isValid()) {
            return false;
        }

        $user = $context->getUser();
        $sessionUserId = (int) ($session->actor_id ?? $session->user_id);
        if ($user === null || $sessionUserId !== (int) $user->id) {
            return false;
        }

        return (int) $session->target_workspace_id === $context->getWorkspaceId();
    }

    /**
     * Apply central data projection policy to scrub unauthorized sensitive fields from tool output.
     */
    public function projectToolResult(ToolResult $result, AgentContext $context): ToolResult
    {
        if (! $result->success) {
            return $result;
        }

        $projectedData = $this->projectData($result->data, $context);
        $safeMetadata = $this->sanitizeMetadata($result->metadata);

        return new ToolResult(
            success: true,
            data: $projectedData,
            error: null,
            metadata: $safeMetadata,
        );
    }

    /**
     * Recursively project and sanitize data structures.
     */
    public function projectData(mixed $data, AgentContext $context): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $projected = [];

        foreach ($data as $key => $value) {
            // Check if string key itself is restricted
            if (is_string($key)) {
                if ($this->isFieldRestricted($key, $context)) {
                    continue; // Omit unauthorized field
                }

                // If key is a text report or narrative summary, scrub sensitive figures
                if (in_array(strtolower(trim($key)), ['report', 'summary', 'text'], true) && is_string($value)) {
                    $projected[$key] = $this->sanitizeReportText($value, $context);
                    continue;
                }
            }

            if (is_array($value)) {
                $projected[$key] = $this->projectData($value, $context);
            } else {
                $projected[$key] = $value;
            }
        }

        return $projected;
    }

    /**
     * Normalize a field identifier into lower snake_case for robust semantic matching.
     * Accurately handles snake_case, camelCase, PascalCase, kebab-case, dot notation, and mixed casing.
     */
    public function normalizeFieldName(string $field): string
    {
        $clean = trim($field);
        if (str_contains($clean, '_') || str_contains($clean, '-') || str_contains($clean, '.')) {
            $norm = strtolower(str_replace(['.', '-'], '_', $clean));
            return trim((string) preg_replace('/_+/', '_', $norm), '_');
        }

        $snake = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $clean);
        $snake = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', (string) $snake);
        $normalized = strtolower(preg_replace('/[\s\-]+/', '_', (string) $snake));
        return trim((string) preg_replace('/_+/', '_', (string) $normalized), '_');
    }

    /**
     * Classify field into an authoritative sensitivity category.
     */
    public function classifyField(string $field): string
    {
        $normalized = $this->normalizeFieldName($field);

        // 1. Explicit False Positive Exclusions -> PUBLIC
        if (str_contains($normalized, 'non_profit') ||
            str_contains($normalized, 'profitability_score') ||
            str_contains($normalized, 'loyalty') ||
            str_contains($normalized, 'points') ||
            in_array($normalized, ['revenue', 'order_count', 'product_count', 'sales_count', 'customer_bonus_points'], true)) {
            return self::SENSITIVITY_PUBLIC;
        }

        $isFin = $this->isFinancialCostField($normalized);
        $isHr = $this->isWorkforceCompensationField($normalized);

        // Explicit dual workforce financial costs
        $isDual = str_contains($normalized, 'employee_cost') ||
                  str_contains($normalized, 'labor_cost') ||
                  str_contains($normalized, 'staff_expense') ||
                  str_contains($normalized, 'worker_cost') ||
                  ($isFin && $isHr);

        if ($isDual) {
            return self::SENSITIVITY_FINANCIAL_AND_HR;
        }

        if ($isFin) {
            return self::SENSITIVITY_FINANCIAL;
        }

        if ($isHr) {
            return self::SENSITIVITY_HR;
        }

        // Generic sensitive stems that must fail-closed as RESTRICTED
        if (str_contains($normalized, 'cost') || str_contains($normalized, 'margin') || str_contains($normalized, 'profit')) {
            return self::SENSITIVITY_FINANCIAL;
        }
        if (str_contains($normalized, 'wage') || str_contains($normalized, 'salary') || str_contains($normalized, 'payroll') || str_ends_with($normalized, '_pay')) {
            return self::SENSITIVITY_HR;
        }

        return self::SENSITIVITY_PUBLIC;
    }

    /**
     * Determine whether a field key is restricted under the current context.
     * Enforces strict, independent separation and conjunctive dual-permission evaluation.
     */
    public function isFieldRestricted(string $field, AgentContext $context): bool
    {
        // Workspace Owner and SuperAdmin have full commercial access
        if ($context->getUserRole() === 'owner' || $context->getUserRole() === 'superadmin') {
            return false;
        }

        $normalized = $this->normalizeFieldName($field);
        $category = $this->classifyField($field);

        // Customer channel barrier: any non-public data is strictly restricted
        if ($context->isCustomer()) {
            if ($category !== self::SENSITIVITY_PUBLIC) {
                return true;
            }
            $classification = DataClassification::classifyRole($normalized);
            return $classification !== DataClassification::PUBLIC;
        }

        // Dual Financial & HR category: CONJUNCTIVE permission check (requires BOTH!)
        if ($category === self::SENSITIVITY_FINANCIAL_AND_HR) {
            return ! ($context->hasPermission('analytics.view_margins') && $context->hasPermission('hr.view_salaries'));
        }

        // Financial category (Category A)
        if ($category === self::SENSITIVITY_FINANCIAL) {
            return ! $context->hasPermission('analytics.view_margins');
        }

        // Workforce Compensation category (Category B)
        if ($category === self::SENSITIVITY_HR) {
            return ! $context->hasPermission('hr.view_salaries');
        }

        // Clinical PHI Roles (Category C)
        if ($this->projectionPolicy->isClinicalPhiRole($normalized)) {
            return ! $context->hasPermission('medical.view_phi');
        }

        // Restricted/Unknown sensitive fields fail closed
        if ($category === self::SENSITIVITY_RESTRICTED) {
            return true;
        }

        return false;
    }

    /**
     * Check if a normalized field identifier represents restricted commercial financial/cost data.
     */
    public function isFinancialCostField(string $normalized): bool
    {
        if (str_contains($normalized, 'non_profit')) {
            return false;
        }

        if ($this->projectionPolicy->isFinancialCostRole($normalized)) {
            return true;
        }

        $exactKeys = [
            'cost_price', 'cogs', 'purchase_cost', 'supplier_price', 'supplier_cost',
            'unit_cost', 'margin', 'gross_margin', 'net_margin', 'profit',
            'gross_profit', 'net_profit', 'markup', 'procurement_cost',
            'internal_target', 'due_balance', 'outstanding_balance', 'profit_margin',
            'cost_of_goods', 'cost_of_goods_sold', 'wholesale_price', 'margin_rate',
            'expense_cost', 'commission_expense', 'commission_cost',
        ];

        if (in_array($normalized, $exactKeys, true)) {
            return true;
        }

        // Compound matching: e.g. "product_cost_price", "total_cogs", "item_gross_margin"
        if (str_contains($normalized, 'cost_price') ||
            str_contains($normalized, 'purchase_cost') ||
            str_contains($normalized, 'gross_margin') ||
            str_contains($normalized, 'net_margin') ||
            str_contains($normalized, 'gross_profit') ||
            str_contains($normalized, 'net_profit') ||
            str_contains($normalized, 'supplier_cost') ||
            str_contains($normalized, 'cost_of_goods') ||
            str_contains($normalized, 'wholesale_price') ||
            str_contains($normalized, 'margin_rate') ||
            str_contains($normalized, 'procurement_cost')) {
            return true;
        }

        // Endings/prefixes with clear financial cost intent
        return str_ends_with($normalized, '_cogs') ||
            str_starts_with($normalized, 'cogs_') ||
            str_ends_with($normalized, '_margin') ||
            str_ends_with($normalized, '_cost') ||
            str_ends_with($normalized, '_profit');
    }

    /**
     * Check if a normalized field identifier represents restricted workforce HR compensation data.
     */
    public function isWorkforceCompensationField(string $normalized): bool
    {
        // Guard against customer loyalty/points
        if (str_contains($normalized, 'loyalty') || str_contains($normalized, 'points')) {
            return false;
        }

        if ($this->projectionPolicy->isWorkforceCompensationRole($normalized)) {
            return true;
        }

        $exactKeys = [
            'salary', 'base_salary', 'hourly_wage', 'hourly_rate', 'wage', 'wages',
            'payroll', 'remuneration', 'stipend', 'bonus', 'employee_bonus', 'incentive',
            'commission', 'payout', 'compensation', 'employee_compensation', 'overtime_pay',
            'take_home_pay', 'worker_pay', 'payroll_tax', 'salary_expense',
        ];

        if (in_array($normalized, $exactKeys, true)) {
            return true;
        }

        // Compound matching: e.g. "staff_salary", "driver_wage", "monthly_payroll"
        if (str_contains($normalized, 'salary') ||
            str_contains($normalized, 'base_salary') ||
            str_contains($normalized, 'hourly_wage') ||
            str_contains($normalized, 'hourly_rate') ||
            str_contains($normalized, 'payroll') ||
            str_contains($normalized, 'compensation') ||
            str_contains($normalized, 'stipend') ||
            str_contains($normalized, 'remuneration')) {
            return true;
        }

        return str_ends_with($normalized, '_wage') ||
            str_ends_with($normalized, '_wages') ||
            str_ends_with($normalized, '_pay') ||
            str_ends_with($normalized, '_bonus') ||
            str_ends_with($normalized, '_payout') ||
            str_ends_with($normalized, '_incentive');
    }

    /**
     * Sanitize free-form report text to redact unauthorized commercial margin patterns and workforce HR compensation.
     * Evaluates financial and HR permissions strictly and independently.
     */
    public function sanitizeReportText(string $report, AgentContext $context): string
    {
        $isOwnerOrSuperAdmin = $context->getUserRole() === 'owner' || $context->getUserRole() === 'superadmin';
        $canViewMargins = $isOwnerOrSuperAdmin || $context->hasPermission('analytics.view_margins');
        $canViewSalaries = $isOwnerOrSuperAdmin || $context->hasPermission('hr.view_salaries');

        // Full access if authorized for both categories
        if ($canViewMargins && $canViewSalaries) {
            return $report;
        }

        $sanitized = $report;

        // 1. Redact lines containing financial cost, cogs, profit, margin if caller lacks analytics.view_margins
        if (! $canViewMargins) {
            $financialPattern = '/(?m)^.*?\b(cost_price|purchase_cost|unit_cost|cogs|supplier_price|supplier_cost|procurement_cost|gross_margin|net_margin|margin|gross_profit|net_profit|profit|markup|cost_of_goods|wholesale_price|margin_rate|(?:employee|labor|worker)[\s_]+cost|staff[\s_]+expense|wholesale[\s_]+price|cost[\s_]+of[\s_]+goods)\b.*?$(\r?\n)?/ui';
            $sanitized = preg_replace($financialPattern, "[REDACTED]\n", $sanitized) ?? $sanitized;
        }

        // 2. Redact lines containing workforce compensation, salary, wage, payroll if caller lacks hr.view_salaries
        if (! $canViewSalaries) {
            $hrPattern = '/(?m)^.*?\b(salary|base_salary|hourly_wage|hourly_rate|wage|wages|payroll|remuneration|stipend|bonus|employee_bonus|compensation|employee_compensation|payout|overtime_pay|take_home_pay|worker_pay|staff_expense|(?:employee|labor|worker)[\s_]+cost|staff[\s_]+expense|hourly[\s_]+(?:wage|rate)|overtime[\s_]+pay)\b.*?$(\r?\n)?/ui';
            $sanitized = preg_replace($hrPattern, "[REDACTED]\n", $sanitized) ?? $sanitized;
        }

        return $sanitized;
    }

    /**
     * Sanitize metadata to allow only safe telemetry keys.
     */
    public function sanitizeMetadata(array $metadata): array
    {
        $safeKeys = [
            'execution_time_ms', 'latency_ms', 'source', 'sources', 'record_count',
            'total_count', 'page', 'exception_class', 'tool_name', 'status',
            'contract_version', 'query_fingerprint', 'relationships_active',
            'source_status', 'is_cached_summary', 'dataset_generation', 'dataset_freshness', 'offline_fallback_reason',
            'retrieval_hits', 'top_hit',
        ];

        $cleaned = [];
        foreach ($metadata as $key => $value) {
            $k = strtolower(trim((string) $key));
            if (in_array($k, $safeKeys, true)) {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}

