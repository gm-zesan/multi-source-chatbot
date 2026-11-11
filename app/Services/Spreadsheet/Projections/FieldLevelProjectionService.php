<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Projections;

use App\Enums\DataClassification as DataClassificationEnum;
use App\Models\StructuredRecord;
use App\Services\Spreadsheet\Security\DataClassification;
use App\Services\Spreadsheet\Security\DataProjectionPolicy;
use App\Services\Spreadsheet\Security\Exceptions\TenantScopeViolationException;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Field-Level Projection Service (Policy Enforcement Point - PEP Engine).
 *
 * The single authoritative gateway for structured record data egress.
 * Guarantees that no downstream consumer touches Eloquent models directly,
 * all egress fields pass through DataProjectionPolicy, and multi-tenant / multi-persona
 * caching remains strictly isolated.
 */
class FieldLevelProjectionService
{
    public const CACHE_TTL_SECONDS = 300;

    public function __construct(
        protected readonly DataProjectionPolicy $policy = new DataProjectionPolicy(),
    ) {}

    /**
     * Primary projection method (Contract v2.2.0).
     */
    public function project(StructuredRecord $record, ServerControlledContext $context): ProjectedRecord
    {
        return $this->projectRecord($record, $context);
    }

    /**
     * Primary batch projection method (Contract v2.2.0).
     *
     * @param iterable<StructuredRecord> $records
     * @return Collection<int, ProjectedRecord>
     */
    public function projectBatch(iterable $records, ServerControlledContext $context): Collection
    {
        return $this->projectRecords($records, $context);
    }

    /**
     * Project a single StructuredRecord through the PDP/PEP authorization pipeline.
     */
    public function projectRecord(StructuredRecord $record, ServerControlledContext $context): ProjectedRecord
    {
        // 1. Strict Tenant Boundary Verification
        $recordWorkspaceId = (int) $record->workspace_id;
        if ($recordWorkspaceId !== (int) $context->workspaceId) {
            if ($context->userRole === 'superadmin') {
                if ($context->governanceSession !== null && $context->governanceSession->isValid()) {
                    $context->governanceSession->recordAction('cross_tenant_record_projection', [
                        'record_id'    => $record->id,
                        'document_id'  => $record->document_id,
                        'workspace_id' => $recordWorkspaceId,
                    ]);
                }
            } else {
                throw new TenantScopeViolationException(
                    message: "Cross-tenant record projection denied: Record belongs to workspace {$recordWorkspaceId}, context is {$context->workspaceId}.",
                    requestWorkspaceId: $context->workspaceId,
                    targetWorkspaceId: $recordWorkspaceId,
                    userId: $context->userId,
                );
            }
        }

        // 2. Multi-Tenant Multi-Persona Cache Lookup
        $cacheKey = $this->buildCacheKey($record, $context);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['projection_version'] ?? '') === DataProjectionPolicy::POLICY_VERSION) {
            return new ProjectedRecord(
                id: (int) $cached['id'],
                workspaceId: (int) $cached['workspace_id'],
                documentId: (int) $cached['document_id'],
                rowIndex: (int) $cached['row_index'],
                fields: (array) $cached['fields'],
                allowedRoles: (array) $cached['allowed_roles'],
                redactedCount: (int) ($cached['redacted_count'] ?? 0),
                isPublic: (bool) ($cached['is_public'] ?? false),
                purpose: (string) ($cached['purpose'] ?? $context->purpose),
                projectionVersion: (string) ($cached['projection_version'] ?? DataProjectionPolicy::POLICY_VERSION),
            );
        }

        // 3. Policy Evaluation per Field
        $mappedAttrs = (array) ($record->mapped_attributes ?? []);
        $sensitivities = (array) ($record->field_sensitivities ?? []);
        $semanticProjections = (array) ($record->semantic_projections ?? []);

        $allowedRoles = [];
        $authorizedColIds = [];
        $redactedCount = 0;

        foreach ($mappedAttrs as $colId => $desc) {
            if (! is_array($desc)) {
                continue;
            }

            $sourceColId = (string) ($desc['source_column_id'] ?? $colId);
            $role = (string) ($desc['semantic_role'] ?? 'custom_attribute');
            $sensitivity = (string) ($desc['sensitivity'] ?? ($sensitivities[$sourceColId] ?? DataClassification::RESTRICTED));
            $header = (string) ($desc['header'] ?? $desc['original_header'] ?? '');

            if ($this->policy->canProjectField($sourceColId, $role, $sensitivity, $context, $header)) {
                $allowedRoles[$role] = true;
                $authorizedColIds[$sourceColId] = true;
            } else {
                $redactedCount++;
            }
        }

        // 4. Field Projection Assembly
        $projectedFields = [];
        $isCustomer = $context->isCustomer();

        foreach ($semanticProjections as $role => $val) {
            $cleanRole = (string) $role;

            // Handle duplicate role aliases (e.g. 'doctor_col_001', 'selling_price_col_002')
            if (preg_match('/^(.+)_(col_\d{3})$/', $cleanRole, $matches)) {
                $baseRole = $matches[1];
                $aliasColId = $matches[2];

                // In customer context, strip all column ID aliases (Zero-Token Leakage)
                if ($isCustomer) {
                    continue;
                }

                if (isset($authorizedColIds[$aliasColId]) && isset($allowedRoles[$baseRole])) {
                    $projectedFields[$cleanRole] = $val;
                }
                continue;
            }

            if (! isset($allowedRoles[$cleanRole])) {
                continue;
            }

            // Handle multi-column arrays
            if (is_array($val) && isset($val['_is_multi_column'])) {
                $filtered = [];
                foreach ($val as $subKey => $subVal) {
                    if ($subKey === '_is_multi_column') {
                        continue;
                    }
                    if (isset($authorizedColIds[$subKey])) {
                        $filtered[$subKey] = $subVal;
                    }
                }

                if (! empty($filtered)) {
                    if ($isCustomer) {
                        // Under customer context, collapse to first available value (no internal col_* keys)
                        $projectedFields[$cleanRole] = reset($filtered);
                    } else {
                        $filtered['_is_multi_column'] = true;
                        $projectedFields[$cleanRole] = $filtered;
                    }
                }
            } else {
                $projectedFields[$cleanRole] = $val;
            }
        }

        // Backward compatibility fallback for legacy unmigrated records
        if (empty($projectedFields) && empty($mappedAttrs)) {
            $projectedFields = $this->projectLegacyRecordFallback($record, $context);
        }

        $projected = new ProjectedRecord(
            id: (int) $record->id,
            workspaceId: $recordWorkspaceId,
            documentId: (int) $record->document_id,
            rowIndex: (int) $record->row_index,
            fields: $projectedFields,
            allowedRoles: array_keys($allowedRoles),
            redactedCount: $redactedCount,
            isPublic: (bool) ($record->is_public && $redactedCount === 0),
            purpose: $context->purpose,
            projectionVersion: DataProjectionPolicy::POLICY_VERSION,
        );

        // 5. Store in Multi-Tenant Isolated Cache
        Cache::put($cacheKey, $projected->toArray(), self::CACHE_TTL_SECONDS);

        return $projected;
    }

    /**
     * Batch project multiple records with eager tenant boundary verification.
     *
     * @param iterable<StructuredRecord> $records
     * @return Collection<int, ProjectedRecord>
     */
    public function projectRecords(iterable $records, ServerControlledContext $context): Collection
    {
        $projected = new Collection();

        foreach ($records as $record) {
            $projected->push($this->projectRecord($record, $context));
        }

        return $projected;
    }

    /**
     * Project a raw CSV/XLSX row through the authorization matrix for preview/export streams.
     *
     * @param array<int, mixed> $rawRow
     * @param array<int, array<string, mixed>> $sourceDescriptors
     * @return array<int, mixed>
     */
    public function projectRawRow(array $rawRow, array $sourceDescriptors, ServerControlledContext $context): array
    {
        $projected = [];
        $descriptorsByIndex = [];

        foreach ($sourceDescriptors as $key => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (is_int($key)) {
                $descriptorsByIndex[$key] = $item;
            } elseif (isset($item['column_index']) && is_numeric($item['column_index'])) {
                $descriptorsByIndex[(int) $item['column_index']] = $item;
            }
        }

        foreach ($rawRow as $idx => $cellVal) {
            $colKey = 'col_' . str_pad((string) $idx, 3, '0', STR_PAD_LEFT);
            $desc = $sourceDescriptors[$idx]
                ?? $descriptorsByIndex[$idx]
                ?? $sourceDescriptors[$colKey]
                ?? null;

            if (! is_array($desc)) {
                // Unclassified column defaults to RESTRICTED (fail-closed)
                if ($context->isCustomer()) {
                    continue;
                }
                if (! $context->isOwner()) {
                    $projected[$idx] = '***';
                    continue;
                }
                $projected[$idx] = $cellVal;
                continue;
            }

            $colId = (string) ($desc['source_column_id'] ?? ('col_' . str_pad((string) $idx, 3, '0', STR_PAD_LEFT)));
            $role = (string) ($desc['semantic_role'] ?? 'custom_attribute');
            $sensitivity = (string) ($desc['sensitivity'] ?? DataClassification::RESTRICTED);
            $header = (string) ($desc['original_header'] ?? $desc['header'] ?? '');

            if ($this->policy->canProjectField($colId, $role, $sensitivity, $context, $header)) {
                $projected[$idx] = $cellVal;
            } else {
                // If not authorized:
                if ($context->isCustomer()) {
                    // Under customer context, column is suppressed entirely
                    continue;
                } else {
                    // Under non-authorized dashboard preview, value is masked
                    $projected[$idx] = '***';
                }
            }
        }

        return $projected;
    }

    /**
     * Construct a cryptographically partitioned composite cache key:
     * {workspace_id}:{policy_scope_hash}:{channel}:{record_id}:{policy_version}
     */
    public function buildCacheKey(StructuredRecord $record, ServerControlledContext $context): string
    {
        $scopeHash = $context->getPolicyScopeHash();

        $composite = sprintf(
            '%d:%s:%s:%d:%s',
            $record->workspace_id,
            $scopeHash,
            $context->channel,
            $record->id,
            DataProjectionPolicy::POLICY_VERSION
        );

        return 'projection:' . hash('sha256', $composite);
    }

    /**
     * Invalidate cached projections for a given record.
     */
    public function invalidateRecord(StructuredRecord $record): void
    {
        try {
            Cache::forget("projection_rec_{$record->id}");
        } catch (\Throwable $e) {
            Log::warning('[FieldLevelProjectionService] Invalidation warning: ' . $e->getMessage());
        }
    }

    /**
     * Fallback projection logic for legacy unmigrated records.
     *
     * @return array<string, mixed>
     */
    protected function projectLegacyRecordFallback(StructuredRecord $record, ServerControlledContext $context): array
    {
        $fields = [];

        if ($record->canonical_entity_name !== null) {
            $fields['entity_name'] = $record->canonical_entity_name;
        }

        if ($record->canonical_quantity !== null) {
            $fields['quantity'] = (float) $record->canonical_quantity;
        }

        if ($record->canonical_amount !== null) {
            if ($record->is_public || ! $context->isCustomer()) {
                $fields['amount'] = (float) $record->canonical_amount;
            }
        }

        if ($record->canonical_person !== null && ! $context->isCustomer()) {
            $fields['person'] = $record->canonical_person;
        }

        return $fields;
    }
}

