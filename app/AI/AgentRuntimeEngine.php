<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Contracts\AgentInterface;
use App\AI\Contracts\ToolInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\AgentResponse;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\AI\Exceptions\MaxIterationsExceededException;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Policies\ToolAuthorizationPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Agent as LaravelAiAgent;
use Laravel\Ai\Events\StartingStep;
use Throwable;

/**
 * Central Orchestration Engine for Governed AI Agents.
 *
 * Responsibilities:
 * 1. Enforces strict server-controlled execution context and tenant scoping.
 * 2. Enforces fail-closed tool authorization via ToolAuthorizationPolicy.
 * 3. Enforces bounded execution loops (hard cap <= 3 iterations).
 * 4. Meters and enforces monotonic wall-clock SLA deadlines (defaults to <= 4.0s).
 * 5. Integrates natively with the installed Laravel AI SDK without creating a competing router.
 */
class AgentRuntimeEngine
{
    public const MAX_ALLOWED_ITERATIONS = 3;

    public function __construct(
        private readonly ToolAuthorizationPolicy $authorizationPolicy = new ToolAuthorizationPolicy(),
    ) {}

    /**
     * Execute a turn for the given agent under the specified server-controlled context.
     *
     * @param AgentInterface $agent Governed agent to run
     * @param string $userPrompt Ingress user prompt or question
     * @param AgentContext $context Trusted server-controlled context
     * @return AgentResponse Standardized structured response
     */
    public function run(
        AgentInterface $agent,
        string $userPrompt,
        AgentContext $context,
    ): AgentResponse {
        $boundContext = $context->withAgent($agent);

        return AgentContext::withContext($boundContext, function (AgentContext $ctx) use ($agent, $userPrompt): AgentResponse {
            $steps = [];
            $agentId = $agent->getAgentId();
            $workspaceId = $ctx->getWorkspaceId();

            Log::info("[AgentRuntimeEngine] Starting turn for agent '{$agentId}'", [
                'agent_id'       => $agentId,
                'workspace_id'   => $workspaceId,
                'correlation_id' => $ctx->correlationId,
                'max_iterations' => $ctx->maxIterations,
                'timeout_s'      => $ctx->timeoutSeconds,
            ]);

            // 1. Invoke agent lifecycle hook
            try {
                $agent->onTurnStart($ctx);
            } catch (Throwable $e) {
                Log::error("[AgentRuntimeEngine] onTurnStart failed for agent '{$agentId}'", [
                    'error' => $e->getMessage(),
                ]);
            }

            // 2. Validate SLA budget before beginning
            if ($ctx->isExpired()) {
                return $this->buildFailureResponse(
                    AgentResponse::STATUS_TIMEOUT,
                    "Execution deadline expired before starting turn ({$ctx->timeoutSeconds}s SLA).",
                    $steps,
                    $ctx,
                    $agent
                );
            }

            $effectiveMaxIterations = min($ctx->maxIterations, self::MAX_ALLOWED_ITERATIONS);
            $currentPrompt = trim($userPrompt);

            // 3. Execution Path Selection:
            // If the agent also implements native Laravel\Ai\Contracts\Agent, delegate model execution
            // to the framework while tool invocations route through AbstractGovernedTool.
            if ($agent instanceof LaravelAiAgent) {
                try {
                    $response = $this->runNativeLaravelAiAgent($agent, $currentPrompt, $ctx);
                    $agent->onTurnEnd($ctx, $response);
                    return $response;
                } catch (UnauthorizedToolException $e) {
                    return $this->buildDeniedResponse($e->getMessage(), $steps, $ctx, $agent);
                } catch (MaxIterationsExceededException $e) {
                    return $this->buildFailureResponse(
                        AgentResponse::STATUS_MAX_ITERATIONS_EXCEEDED,
                        $e->getMessage(),
                        $steps,
                        $ctx,
                        $agent
                    );
                } catch (Throwable $e) {
                    Log::error("[AgentRuntimeEngine] Laravel AI native run failed for agent '{$agentId}'", [
                        'error' => $e->getMessage(),
                    ]);
                    return $this->buildFailureResponse(
                        AgentResponse::STATUS_RUNTIME_FAILURE,
                        'An unexpected error occurred during AI agent execution.',
                        $steps,
                        $ctx,
                        $agent
                    );
                }
            }

            // 4. Default Governed Step-by-Step Loop
            $iteration = 0;
            $content = '';
            try {
                while ($iteration < $effectiveMaxIterations) {
                    $iteration++;
                    $ctx->recordStep();

                    // Check monotonic SLA deadline on every iteration step
                    if ($ctx->isExpired()) {
                        Log::warning("[AgentRuntimeEngine] SLA deadline breached on iteration {$iteration}", [
                            'agent_id'   => $agentId,
                            'elapsed_ms' => $ctx->getElapsedMs(),
                        ]);

                        return $this->buildFailureResponse(
                            AgentResponse::STATUS_TIMEOUT,
                            "Execution exceeded {$ctx->timeoutSeconds}s wall-clock SLA budget.",
                            $steps,
                            $ctx,
                            $agent
                        );
                    }

                    if (method_exists($agent, 'executeStep')) {
                        $stepText = $agent->executeStep($iteration, $currentPrompt, $ctx);
                        if ($stepText !== null) {
                            $content .= $stepText;
                        }
                        $steps[] = ['iteration' => $iteration, 'output' => $stepText];

                        if (method_exists($agent, 'hasMoreSteps') && ! $agent->hasMoreSteps()) {
                            break;
                        }
                    } else {
                        $content = $currentPrompt !== '' ? "Processed inquiry for {$agent->getRoleName()}." : '';
                        $steps[] = ['iteration' => $iteration, 'output' => $content];
                        break;
                    }
                }

                // If agent requested more steps beyond the hard ceiling of effectiveMaxIterations
                if (method_exists($agent, 'hasMoreSteps') && $agent->hasMoreSteps()) {
                    throw new MaxIterationsExceededException(
                        message: "Execution halted: requested steps exceeding maximum allowed iterations ({$effectiveMaxIterations}).",
                        maxIterations: $effectiveMaxIterations,
                        attemptedIterations: $iteration + 1,
                    );
                }

                $response = AgentResponse::success(
                    content: $content,
                    steps: $steps,
                    telemetry: [
                        'iterations'     => $iteration,
                        'elapsed_ms'     => $ctx->getElapsedMs(),
                        'correlation_id' => $ctx->correlationId,
                    ]
                );

                $agent->onTurnEnd($ctx, $response);
                return $response;
            } catch (UnauthorizedToolException $e) {
                return $this->buildDeniedResponse($e->getMessage(), $steps, $ctx, $agent);
            } catch (MaxIterationsExceededException $e) {
                return $this->buildFailureResponse(
                    AgentResponse::STATUS_MAX_ITERATIONS_EXCEEDED,
                    $e->getMessage(),
                    $steps,
                    $ctx,
                    $agent
                );
            } catch (Throwable $e) {
                return $this->buildFailureResponse(
                    AgentResponse::STATUS_RUNTIME_FAILURE,
                    $e->getMessage(),
                    $steps,
                    $ctx,
                    $agent
                );
            }
        });
    }

    /**
     * Execute a specific governed tool directly within the engine, enforcing authorization & SLA.
     *
     * @param ToolInterface $tool Tool instance to execute
     * @param array<string, mixed> $arguments Tool input parameters
     * @param AgentContext $context Server-controlled context
     * @param AgentInterface|null $agent Optional requesting agent
     * @return ToolResult
     *
     * @throws UnauthorizedToolException If tool authorization is rejected
     * @throws AgentExecutionTimeoutException If execution deadline has expired
     */
    public function executeTool(
        ToolInterface $tool,
        array $arguments,
        AgentContext $context,
        ?AgentInterface $agent = null,
    ): ToolResult {
        $started = hrtime(true);

        // 1. SLA Deadline Check (Pre-Execution Boundary)
        if ($context->isExpired()) {
            throw new AgentExecutionTimeoutException(
                message: "Cannot invoke tool '{$tool->name()}': SLA budget of {$context->timeoutSeconds}s exhausted.",
                timeoutSeconds: $context->timeoutSeconds,
                elapsedSeconds: $context->getElapsedMs() / 1000,
            );
        }

        // 2. Enforce Tool Authorization Policy
        if ($agent !== null) {
            $this->authorizationPolicy->authorize($agent, $tool, $context);
        } else {
            $this->authorizationPolicy->authorizeToolForContext($tool, $context);
        }

        // 3. Governed Tool Execution
        $result = $tool->execute($arguments, $context);

        // 4. Central Data Projection (Fail-Closed Field-Level Protection)
        $result = $this->authorizationPolicy->projectToolResult($result, $context);

        $elapsedMs = round(((hrtime(true) - $started) / 1e6), 2);

        // 5. Post-Execution SLA Check & Diagnostics Logging
        if ($context->isExpired()) {
            Log::warning("[AgentRuntimeEngine] SLA deadline breached during tool execution: '{$tool->name()}'", [
                'tool'       => $tool->name(),
                'elapsed_ms' => $elapsedMs,
                'total_ms'   => $context->getElapsedMs(),
            ]);
        }

        Log::info("[AgentRuntimeEngine] Tool '{$tool->name()}' executed in {$elapsedMs}ms", [
            'tool'       => $tool->name(),
            'success'    => $result->success,
            'elapsed_ms' => $elapsedMs,
        ]);

        return $result;
    }

    /**
     * Run an agent that implements native Laravel AI contracts (Promptable).
     */
    private function runNativeLaravelAiAgent(
        LaravelAiAgent $agent,
        string $prompt,
        AgentContext $context,
    ): AgentResponse {
        $started = hrtime(true);
        $effectiveMaxSteps = min($context->maxIterations, self::MAX_ALLOWED_ITERATIONS);
        $iterations = 1;
        $steps = [];

        // 1. Propagate Remaining SLA Budget to SDK where supported
        $remainingTimeout = max(1, (int) ceil($context->getRemainingSeconds()));

        // 2. Register Framework Event Listener to Enforce Max 3 Steps Ceiling
        $stepListener = function (StartingStep $event) use ($context, $effectiveMaxSteps, &$iterations) {
            $iterations = $event->stepNumber + 1;
            $context->recordStep();

            if ($event->stepNumber >= $effectiveMaxSteps) {
                throw new MaxIterationsExceededException(
                    message: "Execution halted: step count {$iterations} exceeds maximum allowed iterations ({$effectiveMaxSteps}).",
                    maxIterations: $effectiveMaxSteps,
                    attemptedIterations: $iterations,
                );
            }
        };

        Event::listen(StartingStep::class, $stepListener);

        try {
            // 3. Execute prompt with propagated timeout
            $aiResponse = $agent->prompt($prompt, timeout: $remainingTimeout);
            $content = (string) $aiResponse;

            // Extract SDK steps if available on the response
            if (isset($aiResponse->steps) && is_iterable($aiResponse->steps)) {
                $stepIdx = 0;
                foreach ($aiResponse->steps as $sdkStep) {
                    $stepIdx++;
                    $steps[] = [
                        'step_number' => $stepIdx,
                        'text'        => $sdkStep->text ?? '',
                        'tool_calls'  => count($sdkStep->toolCalls ?? []),
                    ];
                }
                $iterations = max(1, count($steps));
            }
        } finally {
            Event::forget(StartingStep::class);
        }

        $elapsedMs = round(((hrtime(true) - $started) / 1e6), 2);

        return AgentResponse::success(
            content: $content,
            steps: $steps,
            telemetry: [
                'iterations'     => $iterations,
                'elapsed_ms'     => $elapsedMs,
                'correlation_id' => $context->correlationId,
            ]
        );
    }

    private function buildFailureResponse(
        string $status,
        string $message,
        array $steps,
        AgentContext $context,
        AgentInterface $agent,
    ): AgentResponse {
        $response = AgentResponse::failure(
            status: $status,
            message: $message,
            steps: $steps,
            telemetry: [
                'elapsed_ms'     => $context->getElapsedMs(),
                'correlation_id' => $context->correlationId,
            ]
        );

        try {
            $agent->onTurnEnd($context, $response);
        } catch (Throwable $e) {
            // Ignore hook failures on error teardown
        }

        return $response;
    }

    private function buildDeniedResponse(
        string $reason,
        array $steps,
        AgentContext $context,
        AgentInterface $agent,
    ): AgentResponse {
        $response = AgentResponse::denied(
            reason: $reason,
            telemetry: [
                'elapsed_ms'     => $context->getElapsedMs(),
                'correlation_id' => $context->correlationId,
            ]
        );

        try {
            $agent->onTurnEnd($context, $response);
        } catch (Throwable $e) {
            // Ignore hook failures on error teardown
        }

        return $response;
    }
}

