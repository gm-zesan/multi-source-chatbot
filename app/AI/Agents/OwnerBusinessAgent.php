<?php

declare(strict_types=1);

namespace App\AI\Agents;

use App\AI\Contracts\AgentInterface;
use App\AI\Contracts\ToolInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\AgentResponse;
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
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Agent as LaravelAiAgent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Enterprise Business Owner Intelligence Agent (BO-2).
 *
 * Provides a unified conversational interface for business owners, executives,
 * and managers across 10 governed, read-only analytical tools.
 * Enforces strict role-based data projection, SLA boundaries, and 3-step ceiling.
 */
class OwnerBusinessAgent implements AgentInterface, LaravelAiAgent, HasTools
{
    use Promptable;

    public const AGENT_ID = 'owner_business_agent';
    public const ROLE_NAME = 'Enterprise Business Intelligence Assistant';
    public const MAX_STEPS = 3;

    /**
     * Whitelist of governed analytical tools registered on this agent.
     *
     * @var array<int, ToolInterface>
     */
    protected array $tools;

    public function __construct(
        ?BusinessOverviewTool $businessOverview = null,
        ?SalesAnalyticsTool $salesAnalytics = null,
        ?ProductPerformanceTool $productPerformance = null,
        ?InventoryAnalyticsTool $inventoryAnalytics = null,
        ?CustomerInsightsTool $customerInsights = null,
        ?FinancialAnalyticsTool $financialAnalytics = null,
        ?EmployeeAnalyticsTool $employeeAnalytics = null,
        ?OwnerCustomerSupportInsightsTool $ownerSupportInsights = null,
        ?KnowledgeIntelligenceTool $knowledgeIntelligence = null,
        ?LegacyExcelAnalyticsTool $legacyExcelAnalytics = null,
    ) {
        $this->tools = [
            $businessOverview ?? app(BusinessOverviewTool::class),
            $salesAnalytics ?? app(SalesAnalyticsTool::class),
            $productPerformance ?? app(ProductPerformanceTool::class),
            $inventoryAnalytics ?? app(InventoryAnalyticsTool::class),
            $customerInsights ?? app(CustomerInsightsTool::class),
            $financialAnalytics ?? app(FinancialAnalyticsTool::class),
            $employeeAnalytics ?? app(EmployeeAnalyticsTool::class),
            $ownerSupportInsights ?? app(OwnerCustomerSupportInsightsTool::class),
            $knowledgeIntelligence ?? app(KnowledgeIntelligenceTool::class),
            $legacyExcelAnalytics ?? app(LegacyExcelAnalyticsTool::class),
        ];
    }

    /**
     * Unique machine-readable agent identifier.
     */
    public function getAgentId(): string
    {
        return self::AGENT_ID;
    }

    /**
     * Human-readable role classification.
     */
    public function getRoleName(): string
    {
        return self::ROLE_NAME;
    }

    /**
     * Maximum execution iterations allowed per turn (capped at 3).
     */
    public function maxSteps(): int
    {
        return self::MAX_STEPS;
    }

    /**
     * Return registered tools for ToolAuthorizationPolicy whitelisting.
     *
     * @return array<int, ToolInterface>
     */
    public function getRegisteredTools(): array
    {
        return $this->tools;
    }

    /**
     * Laravel AI SDK tool discovery contract.
     *
     * @return iterable<ToolInterface>
     */
    public function tools(): iterable
    {
        return $this->tools;
    }

    /**
     * Laravel AI SDK system instruction hook.
     */
    public function instructions(): Stringable|string
    {
        $context = AgentContext::hasCurrent() ? AgentContext::current() : null;
        return $this->buildSystemPrompt($context);
    }

    /**
     * Governed AgentInterface system prompt hook.
     */
    public function getSystemPrompt(AgentContext $context): string
    {
        return $this->buildSystemPrompt($context);
    }

    /**
     * Hook triggered when a runtime turn begins.
     */
    public function onTurnStart(AgentContext $context): void
    {
        Log::info('[OwnerBusinessAgent] Turn started', [
            'agent_id'       => $this->getAgentId(),
            'workspace_id'   => $context->getWorkspaceId(),
            'user_role'      => $context->getUserRole(),
            'correlation_id' => $context->correlationId,
        ]);
    }

    /**
     * Hook triggered when a runtime turn completes.
     */
    public function onTurnEnd(AgentContext $context, AgentResponse $response): void
    {
        Log::info('[OwnerBusinessAgent] Turn completed', [
            'agent_id'       => $this->getAgentId(),
            'workspace_id'   => $context->getWorkspaceId(),
            'status'         => $response->status,
            'elapsed_ms'     => $context->getElapsedMs(),
            'correlation_id' => $context->correlationId,
        ]);
    }

    /**
     * Build authoritative system instructions tailored to tenant and caller role.
     */
    protected function buildSystemPrompt(?AgentContext $context = null): string
    {
        $userRole = $context !== null ? $context->getUserRole() : 'owner';
        $workspaceId = $context !== null ? $context->getWorkspaceId() : 0;

        return <<<PROMPT
You are the Enterprise Business Intelligence Assistant (BO-2), serving business owners, executives, and department managers for Workspace #{$workspaceId}.
Your caller role is '{$userRole}'.

You provide authoritative, fact-based business insights by executing governed, read-only analytics tools:

1. `business_overview`: High-level commercial pulse (GMV, net revenue, total orders, average order value, returns, fulfillment rate).
2. `sales_analytics`: Granular sales performance, trends over time, breakdown by salesperson and category.
3. `product_performance`: Product sales ranking, top/bottom sellers, return rates, and unit margins (where authorized).
4. `inventory_analytics`: Stock levels, low-stock warnings, inventory valuation, and reorder urgency.
5. `customer_insights`: Aggregate customer cohorts, retention, repeat rates, lifetime value (strictly zero PII).
6. `financial_analytics`: Executive P&L, gross profit, net revenue, operating expenses (restricted to financial decision-makers).
7. `employee_analytics`: Team headcount, department distribution, salary/commission overheads (governed by HR/financial permissions).
8. `owner_support_insights`: Customer support operations, ticket volumes, resolution SLAs, CSAT, escalations.
9. `knowledge_intelligence`: Search enterprise FAQs and active business policy documents.
10. `legacy_excel_analytics`: Virtual query over uploaded spreadsheet records via Contract 3.0 SQLite gateway.

CRITICAL OPERATIONAL RULES:
- You operate under strict Data Loss Prevention (DLP). If a field is omitted or filtered due to permissions, explain what is available without speculating or hallucinating unauthorized figures.
- Rely exclusively on structured data returned by tools. Never fabricate or guess financial or HR figures.
- Customer PII (names, phone numbers, addresses) is never exposed. Provide aggregate cohort metrics only.
- Execute tools with precise date ranges and filters matching the user's intent. You are capped at a maximum of 3 tool execution steps per turn.
- Formulate clear, concise, executive-level summaries in Bengali or English based on the user's inquiry language.
- Conclude responses with actionable, data-driven business recommendations when appropriate.
PROMPT;
    }
}

