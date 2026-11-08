<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\AI\Contracts\ToolInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\MissingAgentContextException;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Policies\ToolAuthorizationPolicy;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Abstract Base Class for Governed Enterprise Tools.
 *
 * Implements the dual-layer contract pattern:
 * - Adapts Laravel AI SDK Request invocations to governed execute() calls.
 * - Enforces fail-closed AgentContext retrieval and policy authorization prior to execution.
 * - Traps exceptions safely, preventing secret and stack-trace leakage to LLMs.
 */
abstract class AbstractGovernedTool implements ToolInterface
{
    /**
     * Compatibility alias for name() matching enterprise specifications.
     */
    public function getName(): string
    {
        return $this->name();
    }

    /**
     * Default list of required permissions. Concrete tools override if privileged.
     *
     * @return array<int, string>
     */
    public function getRequiredPermissions(): array
    {
        return [];
    }

    /**
     * Framework entry point invoked by Laravel AI TextGenerationLoop.
     *
     * @param Request $request Framework-provided argument container
     * @return Stringable|string Safe, serialized tool result
     *
     * @throws MissingAgentContextException If ambient context is absent (Fail-Closed)
     * @throws UnauthorizedToolException If caller lacks authorization
     */
    public function handle(Request $request): Stringable|string
    {
        // 1. Resolve Ambient Execution Context (Fail-Closed)
        $context = AgentContext::current();

        // 2. Enforce Tool Authorization Policy
        $policy = app(ToolAuthorizationPolicy::class);
        $agent = $context->getAgent();

        if ($agent !== null) {
            $policy->authorize($agent, $this, $context);
        } else {
            $policy->authorizeToolForContext($this, $context);
        }

        // 3. Extract and Validate Tool Arguments
        $arguments = $request->all();

        // 4. Governed Execution with Safe Exception Guard
        try {
            $result = $this->execute($arguments, $context);
            // 5. Central Data Projection (Fail-Closed Field-Level Protection)
            $result = $policy->projectToolResult($result, $context);
        } catch (UnauthorizedToolException $e) {
            // Re-throw security violations to abort turn
            throw $e;
        } catch (Throwable $e) {
            Log::error("[{$this->name()}] Execution threw unexpected exception", [
                'tool'         => $this->name(),
                'workspace_id' => $context->getWorkspaceId(),
                'error'        => $e->getMessage(),
            ]);

            // Return sanitized failure without exposing internal stack traces
            $result = ToolResult::failure(
                error: 'An internal error occurred while processing this tool request.',
                metadata: ['exception_class' => class_basename($e)]
            );
        }

        return $result;
    }

    /**
     * Unique machine-readable tool name (e.g. 'business_analytics').
     */
    abstract public function name(): string;

    /**
     * The description of what the tool does for LLM prompt generation.
     */
    abstract public function description(): Stringable|string;

    /**
     * Tool schema definition for LLM tool calling.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    abstract public function schema(JsonSchema $schema): array;

    /**
     * Whether the tool is read-only (zero state mutations).
     */
    abstract public function isReadOnly(): bool;

    /**
     * Governed execution method under trusted, server-controlled context.
     *
     * @param array<string, mixed> $arguments Cleaned and validated arguments from LLM
     * @param AgentContext $context Trusted server-controlled execution context
     */
    abstract public function execute(array $arguments, AgentContext $context): ToolResult;
}

