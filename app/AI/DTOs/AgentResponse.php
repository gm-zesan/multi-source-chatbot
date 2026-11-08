<?php

declare(strict_types=1);

namespace App\AI\DTOs;

use JsonSerializable;

/**
 * Standardized structured response emitted by AgentRuntimeEngine.
 */
class AgentResponse implements JsonSerializable
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_CLARIFICATION_REQUIRED = 'clarification_required';
    public const STATUS_AUTHORIZATION_DENIED = 'authorization_denied';
    public const STATUS_TOOL_FAILURE = 'tool_execution_failure';
    public const STATUS_RUNTIME_FAILURE = 'runtime_failure';
    public const STATUS_MAX_ITERATIONS_EXCEEDED = 'max_iterations_exceeded';
    public const STATUS_TIMEOUT = 'timeout';

    /**
     * @param string $status Canonical response status
     * @param string $content Safe synthesized textual response for caller
     * @param array<int, array<string, mixed>> $steps Step execution records of tool calls
     * @param array<string, mixed> $telemetry Operational telemetry (latency, iteration count, budgets)
     * @param string|null $errorMessage Sanitized error message, if failed
     * @param array<int, string> $clarificationOptions Structured options if clarification needed
     */
    public function __construct(
        public readonly string $status,
        public readonly string $content,
        public readonly array $steps = [],
        public readonly array $telemetry = [],
        public readonly ?string $errorMessage = null,
        public readonly array $clarificationOptions = [],
    ) {}

    public static function success(
        string $content,
        array $steps = [],
        array $telemetry = [],
    ): self {
        return new self(
            status: self::STATUS_SUCCESS,
            content: $content,
            steps: $steps,
            telemetry: $telemetry,
        );
    }

    public static function clarification(
        string $prompt,
        array $options = [],
        array $steps = [],
        array $telemetry = [],
    ): self {
        return new self(
            status: self::STATUS_CLARIFICATION_REQUIRED,
            content: $prompt,
            steps: $steps,
            telemetry: $telemetry,
            clarificationOptions: $options,
        );
    }

    public static function denied(
        string $reason,
        array $telemetry = [],
    ): self {
        return new self(
            status: self::STATUS_AUTHORIZATION_DENIED,
            content: $reason,
            steps: [],
            telemetry: $telemetry,
            errorMessage: $reason,
        );
    }

    public static function failure(
        string $status,
        string $message,
        array $steps = [],
        array $telemetry = [],
    ): self {
        return new self(
            status: $status,
            content: $message,
            steps: $steps,
            telemetry: $telemetry,
            errorMessage: $message,
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isDenied(): bool
    {
        return $this->status === self::STATUS_AUTHORIZATION_DENIED;
    }

    public function isTimedOut(): bool
    {
        return $this->status === self::STATUS_TIMEOUT;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess();
    }

    public function __get(string $name): mixed
    {
        if ($name === 'error') {
            return $this->errorMessage;
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'status'                => $this->status,
            'content'               => $this->content,
            'steps'                 => $this->steps,
            'telemetry'             => $this->telemetry,
            'error_message'         => $this->errorMessage,
            'clarification_options' => $this->clarificationOptions,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
