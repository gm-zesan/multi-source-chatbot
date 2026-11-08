<?php

declare(strict_types=1);

namespace App\AI\DTOs;

use JsonSerializable;
use Stringable;

/**
 * Standardized result DTO for all governed tool executions.
 * Implements Stringable for native integration with Laravel AI Tool contracts.
 */
class ToolResult implements Stringable, JsonSerializable
{
    /**
     * @param bool $success Whether the tool executed successfully
     * @param array<string, mixed> $data Structured payload resulting from execution
     * @param string|null $error Safe, sanitized error message (no stack traces or secrets)
     * @param array<string, mixed> $metadata Execution telemetry and auxiliary signals
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $data = [],
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Factory for successful execution results.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     */
    public static function success(array $data = [], array $metadata = []): self
    {
        return new self(
            success: true,
            data: $data,
            error: null,
            metadata: $metadata,
        );
    }

    /**
     * Factory for failed execution results.
     *
     * @param string $error
     * @param array<string, mixed> $metadata
     */
    public static function failure(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            data: [],
            error: $error,
            metadata: $metadata,
        );
    }

    /**
     * Convert the result into a safe string representation for LLM prompt ingestion.
     */
    public function toResponseString(): string
    {
        if (! $this->success) {
            return 'Tool execution failed: ' . ($this->error ?? 'Unknown error');
        }

        if (empty($this->data)) {
            return 'Tool executed successfully with no returned data.';
        }

        // If data contains a top-level report or summary string, prioritize it
        if (isset($this->data['report']) && is_string($this->data['report'])) {
            return $this->data['report'];
        }

        return json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * Stringable implementation required by Laravel AI SDK handle() contract.
     */
    public function __toString(): string
    {
        return $this->toResponseString();
    }

    /**
     * Convert to array representation for logging and telemetry.
     *
     * @return array{success: bool, data: array<string, mixed>, error: string|null, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'success'  => $this->success,
            'data'     => $this->data,
            'error'    => $this->error,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * JsonSerializable implementation.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

