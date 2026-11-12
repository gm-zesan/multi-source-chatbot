<?php

declare(strict_types=1);

namespace App\AI\Decision\DTOs;

use App\AI\Routing\RouteType;
use InvalidArgumentException;

/**
 * Validated Decision Result DTO.
 *
 * Encapsulates the output of the Unified Decision Engine conforming strictly to Schema v1.0.
 */
class DecisionResult
{
    public const SCHEMA_VERSION = '1.0';

    public const INTENT_CHAT = 'CHAT';
    public const INTENT_KNOWLEDGE = 'KNOWLEDGE';
    public const INTENT_ANALYTICS = 'ANALYTICS';
    public const INTENT_OOD = 'OOD';
    public const INTENT_UNCERTAIN = 'UNCERTAIN';

    public const MODE_DIRECT_RESPONSE = 'DIRECT_RESPONSE';
    public const MODE_TOOL_REQUIRED = 'TOOL_REQUIRED';
    public const MODE_CLARIFICATION_REQUIRED = 'CLARIFICATION_REQUIRED';
    public const MODE_SAFE_REFUSAL = 'SAFE_REFUSAL';
    public const MODE_OUT_OF_DOMAIN = 'OUT_OF_DOMAIN';

    /**
     * @param string $schemaVersion Schema specification version (must be '1.0')
     * @param string $intent Standardized intent capability
     * @param string $responseMode Deterministic orchestration mode
     * @param array<int, ToolCall> $toolCalls Planned tool invocations
     * @param array{question: string, options?: array<int, string>}|null $clarification Ambiguity prompt
     * @param string|null $directResponse Answer for direct response mode
     * @param array<string, mixed> $decisionMetadata Observability and telemetry properties
     */
    public function __construct(
        public readonly string $schemaVersion,
        public readonly string $intent,
        public readonly string $responseMode,
        public readonly array $toolCalls = [],
        public readonly ?array $clarification = null,
        public readonly ?string $directResponse = null,
        public readonly array $decisionMetadata = [],
    ) {
        $allowedIntents = [
            self::INTENT_CHAT,
            self::INTENT_KNOWLEDGE,
            self::INTENT_ANALYTICS,
            self::INTENT_OOD,
            self::INTENT_UNCERTAIN,
        ];
        if (!in_array($this->intent, $allowedIntents, true)) {
            throw new InvalidArgumentException("Invalid intent: '{$this->intent}'");
        }

        $allowedModes = [
            self::MODE_DIRECT_RESPONSE,
            self::MODE_TOOL_REQUIRED,
            self::MODE_CLARIFICATION_REQUIRED,
            self::MODE_SAFE_REFUSAL,
            self::MODE_OUT_OF_DOMAIN,
        ];
        if (!in_array($this->responseMode, $allowedModes, true)) {
            throw new InvalidArgumentException("Invalid response_mode: '{$this->responseMode}'");
        }
    }

    public function isChat(): bool
    {
        return $this->intent === self::INTENT_CHAT;
    }

    public function isKnowledge(): bool
    {
        return $this->intent === self::INTENT_KNOWLEDGE;
    }

    public function isAnalytics(): bool
    {
        return $this->intent === self::INTENT_ANALYTICS;
    }

    public function isUncertain(): bool
    {
        return $this->intent === self::INTENT_UNCERTAIN;
    }

    public function isOod(): bool
    {
        return $this->intent === self::INTENT_OOD;
    }

    public function isDirectResponse(): bool
    {
        return $this->responseMode === self::MODE_DIRECT_RESPONSE;
    }

    public function isToolRequired(): bool
    {
        return $this->responseMode === self::MODE_TOOL_REQUIRED;
    }

    public function requiresTool(): bool
    {
        return $this->isToolRequired();
    }

    public function isClarificationRequired(): bool
    {
        return $this->responseMode === self::MODE_CLARIFICATION_REQUIRED;
    }

    public function isClarification(): bool
    {
        return $this->isClarificationRequired();
    }

    public function isSafeRefusal(): bool
    {
        return $this->responseMode === self::MODE_SAFE_REFUSAL;
    }

    /**
     * Map to existing RouteType for backward-compatibility with downstream components.
     */
    public function toRouteType(): RouteType
    {
        return match ($this->intent) {
            self::INTENT_CHAT      => RouteType::CHAT,
            self::INTENT_KNOWLEDGE => RouteType::KNOWLEDGE,
            self::INTENT_ANALYTICS => RouteType::ANALYTICS,
            self::INTENT_OOD       => RouteType::OOD,
            default                => (str_starts_with($this->intent, 'customer_') || str_starts_with($this->intent, 'sales_') || str_starts_with($this->intent, 'due_') || in_array($this->intent, ['customer_outstanding_due', 'customer_total_sales', 'customer_total_payments'], true))
                ? RouteType::ANALYTICS
                : RouteType::UNCERTAIN,
        };
    }

    /**
     * Convert to array structure adhering strictly to the JSON contract.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version'    => $this->schemaVersion,
            'intent'            => $this->intent,
            'response_mode'     => $this->responseMode,
            'tool_calls'        => array_map(fn(ToolCall $tc) => $tc->toArray(), $this->toolCalls),
            'clarification'     => $this->clarification,
            'direct_response'   => $this->directResponse,
            'decision_metadata' => $this->decisionMetadata,
        ];
    }
}
