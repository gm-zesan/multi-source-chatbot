<?php

declare(strict_types=1);

namespace App\AI\Contracts;

use App\AI\DTOs\AgentContext;
use App\AI\DTOs\AgentResponse;

/**
 * Enterprise Governed Agent Contract.
 *
 * Defines the identity, registered tool whitelist, system prompt,
 * and lifecycle turn hooks for agents orchestrated by AgentRuntimeEngine.
 */
interface AgentInterface
{
    /**
     * Unique machine-readable identifier for this agent (e.g. 'owner_business_agent').
     */
    public function getAgentId(): string;

    /**
     * Human-readable role classification (e.g. 'Business Intelligence Assistant').
     */
    public function getRoleName(): string;

    /**
     * Generate the authoritative system instructions for the agent under the current context.
     *
     * @param AgentContext $context Trusted server-controlled execution context
     */
    public function getSystemPrompt(AgentContext $context): string;

    /**
     * Get the registered tool instances authorized for this agent.
     *
     * @return array<int, ToolInterface>
     */
    public function getRegisteredTools(): array;

    /**
     * Maximum execution steps/iterations allowed for this agent turn (capped at 3).
     * Discovered and enforced natively by Laravel AI TextGenerationOptions.
     */
    public function maxSteps(): int;

    /**
     * Hook triggered when a runtime turn begins.
     */
    public function onTurnStart(AgentContext $context): void;

    /**
     * Hook triggered when a runtime turn completes, receiving the final response.
     */
    public function onTurnEnd(AgentContext $context, AgentResponse $response): void;
}

