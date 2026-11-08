<?php

declare(strict_types=1);

namespace App\Services\Analytics\DTOs\Contract3;

/**
 * Stage A Response Artifact: Deterministic compiled SQL, bound parameters,
 * and referenced entities for independent AST authorization.
 * NOT EXECUTED AT THIS STAGE.
 */
class PreparedQueryArtifact
{
    public const CONTRACT_VERSION = '3.0.0';

    /**
     * @param string $contractVersion
     * @param string $requestId
     * @param int $workspaceId
     * @param string $datasetId
     * @param string $generationId
     * @param string $queryFingerprint
     * @param string $schemaFingerprint
     * @param string $sqlTemplate Parameterized SQL statement with '?' placeholders
     * @param array<int, mixed> $params
     * @param array<int, string> $referencedTables
     * @param array<int, string> $referencedColumns
     * @param array<int, string> $confirmedEdgeIds
     * @param string $compilerVersion
     * @param string $status
     * @param string $createdAt
     */
    public function __construct(
        public readonly string $contractVersion,
        public readonly string $requestId,
        public readonly int $workspaceId,
        public readonly string $datasetId,
        public readonly string $generationId,
        public readonly string $queryFingerprint,
        public readonly string $schemaFingerprint,
        public readonly string $sqlTemplate,
        public readonly array $params = [],
        public readonly array $referencedTables = [],
        public readonly array $referencedColumns = [],
        public readonly array $confirmedEdgeIds = [],
        public readonly string $compilerVersion = '3.0.0',
        public readonly string $status = 'prepared',
        public readonly string $createdAt = '',
    ) {}

    /**
     * Convert to array representation matching Python contract.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contract_version' => $this->contractVersion,
            'request_id' => $this->requestId,
            'workspace_id' => $this->workspaceId,
            'dataset_id' => $this->datasetId,
            'generation_id' => $this->generationId,
            'query_fingerprint' => $this->queryFingerprint,
            'schema_fingerprint' => $this->schemaFingerprint,
            'sql_template' => $this->sqlTemplate,
            'params' => $this->params,
            'referenced_tables' => $this->referencedTables,
            'referenced_columns' => $this->referencedColumns,
            'confirmed_edge_ids' => $this->confirmedEdgeIds,
            'compiler_version' => $this->compilerVersion,
            'status' => $this->status,
            'created_at' => $this->createdAt,
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
            contractVersion: (string) ($data['contract_version'] ?? self::CONTRACT_VERSION),
            requestId: (string) ($data['request_id'] ?? ''),
            workspaceId: (int) ($data['workspace_id'] ?? 0),
            datasetId: (string) ($data['dataset_id'] ?? ''),
            generationId: (string) ($data['generation_id'] ?? ''),
            queryFingerprint: (string) ($data['query_fingerprint'] ?? ''),
            schemaFingerprint: (string) ($data['schema_fingerprint'] ?? ''),
            sqlTemplate: (string) ($data['sql_template'] ?? ''),
            params: (array) ($data['params'] ?? []),
            referencedTables: (array) ($data['referenced_tables'] ?? []),
            referencedColumns: (array) ($data['referenced_columns'] ?? []),
            confirmedEdgeIds: (array) ($data['confirmed_edge_ids'] ?? []),
            compilerVersion: (string) ($data['compiler_version'] ?? '3.0.0'),
            status: (string) ($data['status'] ?? 'prepared'),
            createdAt: (string) ($data['created_at'] ?? gmdate('c')),
        );
    }
}

