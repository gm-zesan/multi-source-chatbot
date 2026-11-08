<?php

declare(strict_types=1);

namespace App\Services\Analytics\DTOs\Contract3;

/**
 * Stage C Normalized tabular execution response returned by Python service.
 */
class AnalyticsExecutionResponse
{
    public const CONTRACT_VERSION = '3.0.0';

    /**
     * @param bool $success
     * @param string $contractVersion
     * @param string $requestId
     * @param int $workspaceId
     * @param string $datasetId
     * @param string $generationId
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $columns
     * @param int $rowCount
     * @param string $report
     * @param float $latencyMs
     * @param string|null $error
     * @param string|null $errorCode
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $contractVersion,
        public readonly string $requestId,
        public readonly int $workspaceId,
        public readonly string $datasetId,
        public readonly string $generationId,
        public readonly array $rows = [],
        public readonly array $columns = [],
        public readonly int $rowCount = 0,
        public readonly string $report = '',
        public readonly float $latencyMs = 0.0,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
    ) {}

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'contract_version' => $this->contractVersion,
            'request_id' => $this->requestId,
            'workspace_id' => $this->workspaceId,
            'dataset_id' => $this->datasetId,
            'generation_id' => $this->generationId,
            'rows' => $this->rows,
            'columns' => $this->columns,
            'row_count' => $this->rowCount,
            'report' => $this->report,
            'latency_ms' => $this->latencyMs,
            'error' => $this->error,
            'error_code' => $this->errorCode,
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
            success: (bool) ($data['success'] ?? false),
            contractVersion: (string) ($data['contract_version'] ?? self::CONTRACT_VERSION),
            requestId: (string) ($data['request_id'] ?? ''),
            workspaceId: (int) ($data['workspace_id'] ?? 0),
            datasetId: (string) ($data['dataset_id'] ?? ''),
            generationId: (string) ($data['generation_id'] ?? ''),
            rows: (array) ($data['rows'] ?? []),
            columns: (array) ($data['columns'] ?? []),
            rowCount: (int) ($data['row_count'] ?? count($data['rows'] ?? [])),
            report: (string) ($data['report'] ?? ''),
            latencyMs: (float) ($data['latency_ms'] ?? 0.0),
            error: isset($data['error']) ? (string) $data['error'] : null,
            errorCode: isset($data['error_code']) ? (string) $data['error_code'] : null,
        );
    }
}

