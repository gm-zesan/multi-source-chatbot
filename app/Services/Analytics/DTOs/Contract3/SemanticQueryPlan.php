<?php

declare(strict_types=1);

namespace App\Services\Analytics\DTOs\Contract3;

/**
 * Structured, provider-independent Semantic Query Plan AST for Contract 3.0.0.
 * Eliminates raw SQL generation from LLM and drives deterministic compiler.
 */
class SemanticQueryPlan
{
    /**
     * @param string $domain Business domain (e.g., 'sales', 'inventory', 'customers')
     * @param array<int, array{name: string, aggregation: string, alias?: string|null}> $measures
     * @param array<int, array{name: string, alias?: string|null}> $dimensions
     * @param array<int, array{field: string, operator: string, value: mixed}> $filters
     * @param array<int, string> $groupBy
     * @param array<int, array{field: string, direction: string}> $orderBy
     * @param int $limit
     * @param bool $needsClarification
     * @param bool $isSecurityRejection
     * @param string|null $requestId
     */
    public function __construct(
        public readonly string $domain = 'sales',
        public readonly array $measures = [],
        public readonly array $dimensions = [],
        public readonly array $filters = [],
        public readonly array $groupBy = [],
        public readonly array $orderBy = [],
        public readonly int $limit = 50,
        public readonly bool $needsClarification = false,
        public readonly bool $isSecurityRejection = false,
        public readonly ?string $requestId = null,
    ) {}

    /**
     * Convert to array representation matching Python contract.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'measures' => $this->measures,
            'dimensions' => $this->dimensions,
            'filters' => $this->filters,
            'group_by' => $this->groupBy,
            'order_by' => $this->orderBy,
            'limit' => $this->limit,
            'needs_clarification' => $this->needsClarification,
            'is_security_rejection' => $this->isSecurityRejection,
            'request_id' => $this->requestId,
        ];
    }

    /**
     * Construct instance from associative array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            domain: (string) ($data['domain'] ?? 'sales'),
            measures: (array) ($data['measures'] ?? []),
            dimensions: (array) ($data['dimensions'] ?? []),
            filters: (array) ($data['filters'] ?? []),
            groupBy: (array) ($data['group_by'] ?? $data['groupBy'] ?? []),
            orderBy: (array) ($data['order_by'] ?? $data['orderBy'] ?? []),
            limit: (int) ($data['limit'] ?? 50),
            needsClarification: (bool) ($data['needs_clarification'] ?? $data['needsClarification'] ?? false),
            isSecurityRejection: (bool) ($data['is_security_rejection'] ?? $data['isSecurityRejection'] ?? false),
            requestId: isset($data['request_id']) ? (string) $data['request_id'] : (isset($data['requestId']) ? (string) $data['requestId'] : null),
        );
    }
}

