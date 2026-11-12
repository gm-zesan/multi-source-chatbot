<?php

declare(strict_types=1);

namespace App\AI\Decision;

use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\DTOs\ExecutionResponse;
use App\AI\Decision\DTOs\ToolCall;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Policies\ToolAuthorizationPolicy;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deterministic Execution Controller.
 *
 * Enforces policy validation, permission checks, tenant workspace isolation,
 * and deterministic tool execution on approved decision plans.
 */
class DeterministicExecutionController
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ToolAuthorizationPolicy $authorizationPolicy,
    ) {}

    /**
     * Validate decision policy and execute approved tools.
     *
     * @param DecisionResult $decision Validated decision plan
     * @param DecisionRequest $request Preflight-evaluated request container
     * @return ExecutionResponse Normalized execution output
     */
    public function execute(DecisionResult $decision, DecisionRequest $request): ExecutionResponse
    {
        $tStart = microtime(true);

        // 1. If no tool execution is required, return immediately
        if (!$decision->isToolRequired() || empty($decision->toolCalls)) {
            return new ExecutionResponse(
                success: true,
                toolResults: [],
                errorMessage: null,
                telemetry: ['execution_ms' => 0.0]
            );
        }

        // 2. Build Trusted ServerControlledContext & AgentContext (Zero LLM Trust)
        $trustedWorkspaceId = $request->workspaceId;
        $context = $this->buildAgentContext($request, $trustedWorkspaceId);

        // Activate ambient context for SDK-compliant tool adapters
        $previousContext = AgentContext::hasCurrent() ? AgentContext::current() : null;
        AgentContext::setCurrent($context);

        $results = [];
        $overallSuccess = true;
        $errorMessage = null;

        try {
            foreach ($decision->toolCalls as $toolCall) {
                $toolName = $toolCall->tool;
                $operation = $toolCall->operation;

                // 3. Verify Tool Registration
                $tool = $this->toolRegistry->getTool($toolName);
                if ($tool === null) {
                    Log::warning("[DeterministicExecutionController] Decision requested unregistered tool '{$toolName}'");
                    $overallSuccess = false;
                    $errorMessage = "Tool '{$toolName}' is not registered.";
                    continue;
                }

                // 4. Verify Operation Allowlist
                if (!$this->toolRegistry->isOperationAllowed($toolName, $operation)) {
                    Log::warning("[DeterministicExecutionController] Operation '{$operation}' is not allowed for tool '{$toolName}'");
                    $overallSuccess = false;
                    $errorMessage = "Operation '{$operation}' is not permitted on tool '{$toolName}'.";
                    continue;
                }

                // 5. Authorize Tool Against AgentContext (Fail-Closed RBAC & Tenant Scoping)
                try {
                    $this->authorizationPolicy->authorizeToolForContext($tool, $context);
                } catch (UnauthorizedToolException $authEx) {
                    Log::warning("[DeterministicExecutionController] Tool authorization denied: " . $authEx->getMessage());
                    return new ExecutionResponse(
                        success: false,
                        toolResults: [],
                        errorMessage: "Unauthorized tool execution: " . $authEx->getMessage(),
                        telemetry: ['security_violation' => true]
                    );
                }

                // 6. Execute Governed Tool with Trusted Injected Arguments
                try {
                    $args = $toolCall->arguments;
                    // Ensure LLM cannot override trusted workspace_id
                    unset($args['workspace_id'], $args['tenant_id'], $args['user_id']);

                    $toolResult = $tool->execute($args, $context);

                    // Central Data Projection Policy (DLP)
                    $projectedResult = $this->authorizationPolicy->projectToolResult($toolResult, $context);

                    if (! $projectedResult->isSuccess()) {
                        $overallSuccess = false;
                        $errorMessage = $projectedResult->error ?? 'Tool execution failed.';
                    }

                    $results[] = [
                        'tool'      => $toolName,
                        'operation' => $operation,
                        'result'    => $projectedResult,
                    ];
                } catch (Throwable $e) {
                    Log::error("[DeterministicExecutionController] Tool '{$toolName}' execution failed: " . $e->getMessage());
                    $results[] = [
                        'tool'      => $toolName,
                        'operation' => $operation,
                        'result'    => ToolResult::failure('Tool execution failed: ' . $e->getMessage()),
                    ];
                    $overallSuccess = false;
                    $errorMessage = $e->getMessage();
                }
            }
        } finally {
            AgentContext::setCurrent($previousContext);
        }

        $latency = round((microtime(true) - $tStart) * 1000, 2);

        return new ExecutionResponse(
            success: $overallSuccess,
            toolResults: $results,
            errorMessage: $errorMessage,
            telemetry: [
                'execution_ms' => $latency,
                'tool_count'   => count($results),
            ]
        );
    }

    /**
     * Build trusted, immutable AgentContext for server-side tool execution.
     */
    protected function buildAgentContext(DecisionRequest $request, int $workspaceId): AgentContext
    {
        $channel = $request->channelSlug;
        $user = $request->user;

        if ($request->isCustomer || $user === null) {
            return AgentContext::forCustomer(
                workspaceId: $workspaceId,
                channel: $channel,
                conversation: $request->conversation,
            );
        }

        return AgentContext::forUser(
            user: $user,
            workspaceId: $workspaceId,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            permissions: [],
            channel: $channel,
            conversation: $request->conversation,
        );
    }
}
