<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Relationships\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Candidate Key Descriptor.
 *
 * Encapsulates profiling statistics and primary key viability metrics for a single column
 * or bounded composite column combination within a spreadsheet sheet.
 */
class CandidateKeyDescriptor implements Arrayable, JsonSerializable
{
    /**
     * @param string $sheetName The name of the worksheet
     * @param array<int, string> $columnIds The column IDs forming the key (e.g. ['col_000'])
     * @param array<int, string> $headers Original header labels for display/diagnostics
     * @param array<int, string> $physicalTypes Inferred physical data types
     * @param int $totalRowCount Total number of rows evaluated
     * @param int $nullCount Number of null or missing values
     * @param float $nullRatio Ratio of nulls (0.0 to 1.0)
     * @param int $distinctCount Number of distinct non-null values
     * @param float $uniquenessRatio Ratio of distinct non-null values over non-null row count (0.0 to 1.0)
     * @param float $emptyStringRatio Ratio of empty string values ('')
     * @param bool $isComposite True if key consists of multiple columns
     * @param bool $isCandidateKey True if satisfies mathematical candidate key constraints (nullRatio == 0.0 && uniquenessRatio >= 0.99)
     * @param float $confidence Overall score representing primary key viability [0.0 - 1.0]
     * @param array<int, string> $evidence Deterministic mathematical and structural justifications
     */
    public function __construct(
        public readonly string $sheetName,
        public readonly array $columnIds,
        public readonly array $headers,
        public readonly array $physicalTypes,
        public readonly int $totalRowCount,
        public readonly int $nullCount,
        public readonly float $nullRatio,
        public readonly int $distinctCount,
        public readonly float $uniquenessRatio,
        public readonly float $emptyStringRatio = 0.0,
        public readonly bool $isComposite = false,
        public readonly bool $isCandidateKey = false,
        public readonly float $confidence = 0.0,
        public readonly array $evidence = [],
    ) {}

    /**
     * Instantiate from array payload.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sheetName: (string) ($data['sheet_name'] ?? 'Sheet1'),
            columnIds: (array) ($data['column_ids'] ?? []),
            headers: (array) ($data['headers'] ?? []),
            physicalTypes: (array) ($data['physical_types'] ?? []),
            totalRowCount: (int) ($data['total_row_count'] ?? 0),
            nullCount: (int) ($data['null_count'] ?? 0),
            nullRatio: (float) ($data['null_ratio'] ?? 0.0),
            distinctCount: (int) ($data['distinct_count'] ?? 0),
            uniquenessRatio: (float) ($data['uniqueness_ratio'] ?? 0.0),
            emptyStringRatio: (float) ($data['empty_string_ratio'] ?? 0.0),
            isComposite: (bool) ($data['is_composite'] ?? false),
            isCandidateKey: (bool) ($data['is_candidate_key'] ?? false),
            confidence: (float) ($data['confidence'] ?? 0.0),
            evidence: (array) ($data['evidence'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sheet_name'         => $this->sheetName,
            'column_ids'         => $this->columnIds,
            'headers'            => $this->headers,
            'physical_types'     => $this->physicalTypes,
            'total_row_count'    => $this->totalRowCount,
            'null_count'         => $this->nullCount,
            'null_ratio'         => round($this->nullRatio, 4),
            'distinct_count'     => $this->distinctCount,
            'uniqueness_ratio'   => round($this->uniquenessRatio, 4),
            'empty_string_ratio' => round($this->emptyStringRatio, 4),
            'is_composite'       => $this->isComposite,
            'is_candidate_key'   => $this->isCandidateKey,
            'confidence'         => round($this->confidence, 4),
            'evidence'           => $this->evidence,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

