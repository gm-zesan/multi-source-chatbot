<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Projections;

use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Projected Record (Policy Enforcement Point - PEP Output DTO).
 *
 * Immutable, authorized egress representation of a StructuredRecord.
 * Downstream consumers (Customer RAG, LLM context, Typesense search,
 * Virtual DB, Export, REST API) MUST consume this DTO and NEVER raw Eloquent models.
 */
class ProjectedRecord implements Arrayable, JsonSerializable
{
    public const PROJECTION_VERSION = '2.2.0';

    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $documentId,
        public readonly int $rowIndex,
        public readonly array $fields,
        public readonly array $allowedRoles,
        public readonly int $redactedCount = 0,
        public readonly bool $isPublic = false,
        public readonly string $purpose = ServerControlledContext::PURPOSE_CUSTOMER_SUPPORT,
        public readonly string $projectionVersion = self::PROJECTION_VERSION,
    ) {}

    /**
     * Retrieve an authorized field by its semantic role.
     */
    public function get(string $role, mixed $default = null): mixed
    {
        return $this->fields[$role] ?? $default;
    }

    /**
     * Check if a field is present in the authorized projection.
     */
    public function has(string $role): bool
    {
        return array_key_exists($role, $this->fields);
    }

    /**
     * Format this record as a clean, zero-token-leakage string for LLM grounding.
     * Guaranteed to contain zero restricted tokens, zero column IDs, and zero redaction placeholders.
     */
    public function toPromptString(): string
    {
        $pairs = [];

        foreach ($this->fields as $role => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Clean human-readable role name (e.g. 'selling_price' -> 'Selling Price')
            $humanName = ucwords(str_replace('_', ' ', $role));
            $formattedVal = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;

            $pairs[] = "{$humanName}: {$formattedVal}";
        }

        return implode(' | ', $pairs);
    }

    /**
     * Convert to an array representation for public search indexing (Typesense).
     *
     * @return array<string, mixed>
     */
    public function toSearchDocument(): array
    {
        $doc = [
            'id'          => "rec_{$this->id}",
            'record_id'   => $this->id,
            'document_id' => $this->documentId,
            'workspace_id'=> $this->workspaceId,
            'row_index'   => $this->rowIndex,
            'is_public'   => $this->isPublic,
        ];

        foreach ($this->fields as $role => $value) {
            $doc[$role] = $value;
        }

        return $doc;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'workspace_id'       => $this->workspaceId,
            'document_id'        => $this->documentId,
            'row_index'          => $this->rowIndex,
            'fields'             => $this->fields,
            'allowed_roles'      => $this->allowedRoles,
            'redacted_count'     => $this->redactedCount,
            'is_public'          => $this->isPublic,
            'purpose'            => $this->purpose,
            'projection_version' => $this->projectionVersion,
        ];
    }

    /**
     * JSON serialization format.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

