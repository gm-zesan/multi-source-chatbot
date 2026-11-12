<?php

declare(strict_types=1);

namespace App\AI\Decision\DTOs;

use App\AI\DTOs\ToolResult;

/**
 * Result of Deterministic Execution.
 */
class ExecutionResponse
{
    /**
     * @param bool $success Overall execution success
     * @param array<int, array{tool: string, operation: string, result: ToolResult|string}> $toolResults
     * @param string|null $errorMessage
     * @param array<string, mixed> $telemetry
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $toolResults = [],
        public readonly ?string $errorMessage = null,
        public readonly array $telemetry = [],
    ) {}

    public function hasToolResults(): bool
    {
        return !empty($this->toolResults);
    }
}

