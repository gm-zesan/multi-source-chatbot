<?php

declare(strict_types=1);

namespace App\AI\Decision\DTOs;

/**
 * Immutable Tool Call Specification.
 */
class ToolCall
{
    /**
     * @param string $tool Tool identifier (e.g. 'sales_analytics', 'knowledge_intelligence')
     * @param string $operation Specific tool operation to execute
     * @param array<string, mixed> $arguments Parameter dictionary for the operation
     */
    public function __construct(
        public readonly string $tool,
        public readonly string $operation,
        public readonly array $arguments = [],
    ) {}

    /**
     * Convert to array representation matching the schema.
     *
     * @return array{tool: string, operation: string, arguments: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'tool'      => $this->tool,
            'operation' => $this->operation,
            'arguments' => $this->arguments,
        ];
    }
}

