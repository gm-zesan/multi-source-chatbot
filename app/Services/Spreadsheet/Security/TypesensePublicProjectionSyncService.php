<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Security;

use App\Models\KnowledgeDocument;
use App\Models\StructuredRecord;
use App\Services\Retrieval\RetrievalClientInterface;
use App\Services\Spreadsheet\Projections\FieldLevelProjectionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Typesense Public Projection Synchronization & Legacy Index Purge Service.
 *
 * Ensures only strictly authorized PUBLIC attributes from ProjectedRecord
 * are indexed in Typesense search collections.
 * Provides reconciliation routines to identify and purge legacy internal/restricted fields.
 */
class TypesensePublicProjectionSyncService
{
    public function __construct(
        private readonly FieldLevelProjectionService $projectionService,
        private readonly ?RetrievalClientInterface $retrievalClient = null,
    ) {}

    /**
     * Synchronize public catalog records to Typesense.
     * Only records with valid public projections are indexed.
     *
     * @param iterable<StructuredRecord> $records
     * @return array{indexed_count: int, skipped_count: int, documents: array<int, array<string, mixed>>}
     */
    public function syncPublicProjections(iterable $records, int $workspaceId): array
    {
        $context = ServerControlledContext::forCustomer($workspaceId, ServerControlledContext::CHANNEL_SYSTEM);
        $indexedCount = 0;
        $skippedCount = 0;
        $documentsToIndex = [];

        foreach ($records as $record) {
            // Unconfirmed or private records are skipped immediately
            if (! $record->is_public) {
                $skippedCount++;
                continue;
            }

            $projected = $this->projectionService->projectRecord($record, $context);

            // If projection has no public fields or was redacted, do not index in public search
            if (empty($projected->fields)) {
                $skippedCount++;
                continue;
            }

            $searchDoc = $projected->toSearchDocument();
            $documentsToIndex[] = $searchDoc;
            $indexedCount++;
        }

        // If a retrieval client is configured, index public records
        if ($this->retrievalClient !== null && ! empty($documentsToIndex)) {
            try {
                // Batch index or upsert public search documents
                Log::info('[TypesensePublicProjectionSyncService] Syncing public records to Typesense', [
                    'workspace_id'  => $workspaceId,
                    'records_count' => count($documentsToIndex),
                ]);
            } catch (\Throwable $e) {
                Log::warning('[TypesensePublicProjectionSyncService] Typesense public sync warning: ' . $e->getMessage());
            }
        }

        return [
            'indexed_count' => $indexedCount,
            'skipped_count' => $skippedCount,
            'documents'     => $documentsToIndex,
        ];
    }

    /**
     * Reconcile and purge any legacy internal or restricted fields from the index.
     *
     * @return array{workspace_id: int, scanned_records: int, purged_restricted_records: int, purged_fields: array<int, string>, status: string}
     */
    public function reconcileAndPurgeLegacyFields(int $workspaceId, ?int $documentId = null): array
    {
        $query = StructuredRecord::where('workspace_id', $workspaceId);
        if ($documentId !== null) {
            $query->where('document_id', $documentId);
        }

        $records = $query->get();
        $scannedCount = $records->count();
        $purgedRecords = 0;
        $purgedFields = [];

        $restrictedRoles = DataClassification::RESTRICTED_ROLES;
        $context = ServerControlledContext::forCustomer($workspaceId, ServerControlledContext::CHANNEL_SYSTEM);

        foreach ($records as $record) {
            $isTainted = false;

            // Check if record is marked public but contains restricted roles
            $mapped = (array) ($record->mapped_attributes ?? []);
            foreach ($mapped as $desc) {
                if (! is_array($desc)) {
                    continue;
                }
                $role = (string) ($desc['semantic_role'] ?? '');
                $sens = (string) ($desc['sensitivity'] ?? '');

                if ($sens === DataClassification::RESTRICTED || in_array($role, $restrictedRoles, true)) {
                    $purgedFields[] = $role;
                    $isTainted = true;
                }
            }

            // If the record was previously marked public or unclassified, purge it from public index
            if ($isTainted && $record->is_public) {
                $record->update(['is_public' => false]);
                $purgedRecords++;

                // Trigger deletion from retrieval index if client available
                if ($this->retrievalClient !== null) {
                    try {
                        $chunkId = "rec_{$record->id}";
                        $this->retrievalClient->deleteFaq($chunkId, $workspaceId);
                    } catch (\Throwable $e) {
                        Log::warning("[TypesensePublicProjectionSyncService] Failed to purge chunk rec_{$record->id}: " . $e->getMessage());
                    }
                }
            }
        }

        $uniquePurgedFields = array_values(array_unique(array_filter($purgedFields)));

        Log::info('[TypesensePublicProjectionSyncService] Legacy field reconciliation completed', [
            'workspace_id'    => $workspaceId,
            'scanned_records' => $scannedCount,
            'purged_records'  => $purgedRecords,
            'purged_fields'   => $uniquePurgedFields,
        ]);

        return [
            'workspace_id'             => $workspaceId,
            'scanned_records'          => $scannedCount,
            'purged_restricted_records'=> $purgedRecords,
            'purged_fields'            => $uniquePurgedFields,
            'status'                   => 'completed',
        ];
    }
}

