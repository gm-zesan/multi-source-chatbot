<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Source Column Semantic Descriptor.
 *
 * Implements the shared versioned contract between Laravel Ingestion Engine and
 * Python AI / Virtual Database Engine (aligns with ColumnSemanticDescriptor).
 */
class SourceColumnDescriptor implements Arrayable, JsonSerializable
{
    public const CONTRACT_VERSION = '2.1.0';

    public function __construct(
        public readonly string $sourceColumnId,
        public readonly string $originalHeader,
        public readonly string $normalizedHeader,
        public readonly string $sheetName,
        public readonly int $columnIndex,
        public readonly string $physicalType,
        public readonly string $suggestedCategory,
        public readonly string $semanticRole,
        public readonly array $candidateRoles = [],
        public readonly string $entityType = 'unknown',
        public readonly ?string $unit = null,
        public readonly string $sensitivity = 'restricted', // 'public', 'internal', 'restricted'
        public readonly float $confidence = 0.50,
        public readonly array $evidence = [],
        public readonly bool $isAmbiguous = false,
        public readonly string $mappingSource = 'inferred', // 'inferred', 'owner_confirmed', 'template'
        public readonly bool $ownerConfirmed = false,
    ) {}

    /**
     * Instantiate from array payload.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, int $defaultIndex = 0, string $defaultSheet = 'Sheet1'): self
    {
        $colId = (string) ($data['source_column_id'] ?? ('col_' . str_pad((string) $defaultIndex, 3, '0', STR_PAD_LEFT)));
        $header = (string) ($data['original_header'] ?? $data['name'] ?? "Column_{$defaultIndex}");
        $normHeader = (string) ($data['normalized_header'] ?? strtolower(trim(preg_replace('/[^\w]+/u', '_', $header), '_')));

        return new self(
            sourceColumnId: $colId,
            originalHeader: $header,
            normalizedHeader: $normHeader,
            sheetName: (string) ($data['sheet_name'] ?? $defaultSheet),
            columnIndex: (int) ($data['column_index'] ?? $data['index'] ?? $defaultIndex),
            physicalType: (string) ($data['physical_type'] ?? 'string'),
            suggestedCategory: (string) ($data['suggested_category'] ?? $data['category'] ?? 'custom_attribute'),
            semanticRole: (string) ($data['semantic_role'] ?? 'custom_attribute'),
            candidateRoles: (array) ($data['candidate_roles'] ?? []),
            entityType: (string) ($data['entity_type'] ?? 'unknown'),
            unit: isset($data['unit']) ? (string) $data['unit'] : null,
            sensitivity: in_array($data['sensitivity'] ?? '', ['public', 'internal', 'restricted'], true)
                ? (string) $data['sensitivity']
                : 'restricted',
            confidence: round((float) ($data['confidence'] ?? 0.50), 4),
            evidence: (array) ($data['evidence'] ?? []),
            isAmbiguous: (bool) ($data['is_ambiguous'] ?? false),
            mappingSource: (string) ($data['mapping_source'] ?? 'inferred'),
            ownerConfirmed: (bool) ($data['owner_confirmed'] ?? false),
        );
    }

    /**
     * Convert to array representation for serialization and DB storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_column_id'   => $this->sourceColumnId,
            'original_header'    => $this->originalHeader,
            'normalized_header'  => $this->normalizedHeader,
            'sheet_name'         => $this->sheetName,
            'column_index'       => $this->columnIndex,
            'physical_type'      => $this->physicalType,
            'suggested_category' => $this->suggestedCategory,
            'semantic_role'      => $this->semanticRole,
            'candidate_roles'    => $this->candidateRoles,
            'entity_type'        => $this->entityType,
            'unit'               => $this->unit,
            'sensitivity'        => $this->sensitivity,
            'confidence'         => $this->confidence,
            'evidence'           => $this->evidence,
            'is_ambiguous'       => $this->isAmbiguous,
            'mapping_source'     => $this->mappingSource,
            'owner_confirmed'    => $this->ownerConfirmed,
            'contract_version'   => self::CONTRACT_VERSION,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Export in format directly consumable by Python ColumnSemanticDescriptor.
     *
     * @return array<string, mixed>
     */
    public function toPythonDescriptor(): array
    {
        $pyType = match ($this->physicalType) {
            'decimal', 'currency', 'percentage' => 'REAL',
            'integer'                           => 'INTEGER',
            'date', 'datetime'                  => 'DATE',
            default                             => 'TEXT',
        };

        $pyRole = match ($this->suggestedCategory) {
            'identifier' => 'identifier',
            'entity_name' => 'entity_name',
            'amount' => 'currency',
            'quantity' => 'quantity',
            'date_time' => 'date_time',
            'status' => 'status',
            'category' => 'category',
            'description' => 'free_text',
            default => 'attribute',
        };

        return [
            'name'             => $this->originalHeader,
            'physical_type'    => $pyType,
            'semantic_role'    => $pyRole,
            'entity_type'      => $this->entityType !== 'unknown' ? $this->entityType : null,
            'business_meaning' => "Role: {$this->semanticRole} | Entity: {$this->entityType}",
            'unit'             => $this->unit,
            'is_nullable'      => true,
            'uniqueness_ratio' => 0.0,
            'evidence'         => $this->evidence,
            'source_column_id' => $this->sourceColumnId,
            'sensitivity'      => $this->sensitivity,
            'contract_version' => self::CONTRACT_VERSION,
        ];
    }
}

