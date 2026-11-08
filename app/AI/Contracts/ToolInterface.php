<?php

declare(strict_types=1);

namespace App\AI\Contracts;

use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use Laravel\Ai\Contracts\Tool as LaravelAiTool;

/**
 * Enterprise Governed Tool Interface.
 *
 * Bridges the official Laravel AI Tool contract with server-controlled tenant scoping,
 * field-level data protection policies, and bounded execution budgets.
 */
interface ToolInterface extends LaravelAiTool
{
    /**
     * Unique machine-readable tool name (e.g. 'business_analytics').
     * Used by Laravel AI ToolNameResolver when mapping tools to LLM function schemas.
     */
    public function name(): string;

    /**
     * Backward-compatible alias for name().
     */
    public function getName(): string;

    /**
     * Operational safety classification: returns true if tool performs zero state mutations.
     */
    public function isReadOnly(): bool;

    /**
     * Governed execution method under trusted, server-controlled context.
     *
     * @param array<string, mixed> $arguments Cleaned and validated arguments from LLM tool call
     * @param AgentContext $context Trusted server-controlled execution context
     */
    public function execute(array $arguments, AgentContext $context): ToolResult;

    /**
     * List of specific permissions required to execute this tool (e.g. ['analytics.view_margins']).
     *
     * @return array<int, string>
     */
    public function getRequiredPermissions(): array;
}

