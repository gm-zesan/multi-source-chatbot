<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\IngestionPurpose;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\DataTables;

class KnowledgeDocumentService
{
    public function __construct(
        private readonly AnalyticsClient $analyticsClient,
    ) {}

    /**
     * Get the authenticated user's workspace ID.
     */
    public function getWorkspaceId(): ?int
    {
        return Auth::user()?->workspace_id;
    }

    /**
     * Return a query scoped to the current authorized workspace.
     *
     * Superadmin sees all records across all workspaces.
     * Regular users are scoped strictly to their own workspace.
     *
     * @return Builder<KnowledgeDocument>
     */
    public function workspaceQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return KnowledgeDocument::query();
        }

        if ($user) {
            return KnowledgeDocument::where('workspace_id', $this->getWorkspaceId());
        }

        if (app()->runningInConsole()) {
            return KnowledgeDocument::query();
        }

        return KnowledgeDocument::where('workspace_id', $this->getWorkspaceId());
    }

    /**
     * Get all documents for DataTables (server-side).
     */
    public function getDataTables(?Request $request = null): JsonResponse
    {
        $query = $this->workspaceQuery();

        $status = $request?->input('status');
        if ($status === 'trashed') {
            $query->onlyTrashed();
        } elseif ($status === 'active') {
            $query->where('lifecycle_status', DocumentLifecycleStatus::ACTIVE->value);
        } elseif ($status === 'pending') {
            $query->where('lifecycle_status', DocumentLifecycleStatus::PENDING_INGESTION->value);
        } elseif ($status === 'failed') {
            $query->where('lifecycle_status', DocumentLifecycleStatus::FAILED->value);
        } else {
            $query->withTrashed();
        }

        $documents = $query->latest()->get();

        return DataTables::of($documents)
            ->addIndexColumn()
            ->addColumn('title', function (KnowledgeDocument $doc) {
                return e($doc->title);
            })
            ->addColumn('original_filename', function (KnowledgeDocument $doc) {
                return e($doc->original_filename);
            })
            ->addColumn('purpose_badge', function (KnowledgeDocument $doc) {
                $purpose = $doc->getIngestionPurpose();
                return match ($purpose) {
                    IngestionPurpose::DATA_ANALYTICS => '<span class="badge bg-primary text-white" style="font-size: 11px;"><i class="ri-bar-chart-2-line me-1"></i>Data & Analytics</span>',
                    IngestionPurpose::KNOWLEDGE_BASE => '<span class="badge bg-secondary text-white" style="font-size: 11px;"><i class="ri-book-read-line me-1"></i>Knowledge Base</span>',
                };
            })
            ->addColumn('file_size', function (KnowledgeDocument $doc) {
                return $doc->formattedFileSize();
            })
            ->addColumn('mime_type', function (KnowledgeDocument $doc) {
                return '<span class="badge bg-light text-dark border">' . e($doc->mime_type) . '</span>';
            })
            ->addColumn('sheets_count', function (KnowledgeDocument $doc) {
                $count = (int) ($doc->sheets_count ?: 1);
                if ($count > 1) {
                    $sheetNames = $doc->metadata['sheets'] ?? [];
                    $titleAttr = ! empty($sheetNames) ? implode(', ', $sheetNames) : "{$count} sheets";
                    return '<span class="badge bg-primary" title="' . e($titleAttr) . '">' . $count . ' sheets</span>';
                }
                return '<span class="badge bg-light text-dark border">1 sheet</span>';
            })
            ->addColumn('created_at', function (KnowledgeDocument $doc) {
                return $doc->created_at ? $doc->created_at->format('M d, Y H:i') : '-';
            })
            ->addColumn('status_badge', function (KnowledgeDocument $doc) {
                if ($doc->trashed()) {
                    return '<span class="badge bg-secondary"><i class="ri-delete-bin-line me-1"></i>Deleted</span>';
                }

                $status = $doc->lifecycle_status;
                $isReindexing = ! empty($doc->active_generation_id) && in_array($status, [
                    DocumentLifecycleStatus::PENDING_INGESTION,
                    DocumentLifecycleStatus::INDEXING,
                ], true);

                if ($isReindexing) {
                    return '<div class="d-flex flex-column gap-1">' .
                        '<span class="badge bg-success" style="font-size: 10px;"><i class="ri-checkbox-circle-line me-1"></i>Active Catalog Live</span>' .
                        '<span class="badge bg-info text-white" style="font-size: 10px;"><i class="ri-refresh-line ri-spin me-1"></i>Re-indexing New Version</span>' .
                        '</div>';
                }

                $badgeClass = $status?->badgeClass() ?? 'bg-secondary';
                $icon = $status?->icon() ?? 'ri-file-line';
                $label = $status?->label() ?? 'Unknown';

                if ($doc->hasFailed()) {
                    return sprintf(
                        '<span class="badge bg-danger" style="font-size: 11px;" title="%s"><i class="ri-close-circle-line me-1"></i>%s</span>',
                        e($doc->error_message ?? 'Processing failed'),
                        e($label)
                    );
                }

                return sprintf(
                    '<span class="badge %s" style="font-size: 11px;"><i class="%s me-1"></i>%s</span>',
                    e($badgeClass),
                    e($icon),
                    e($label)
                );
            })
            ->addColumn('action-btn', function (KnowledgeDocument $doc) {
                /** @var User|null $user */
                $user = Auth::user();
                $isSuperAdmin = $user && $user->hasRole(RoleEnum::SUPERADMIN->value);
                $canUpdate = $isSuperAdmin || ($user && $user->can(KnowledgeDocumentPermission::UPDATE->value));
                $canDelete = $isSuperAdmin || ($user && $user->can(KnowledgeDocumentPermission::DELETE->value));

                $status = $doc->lifecycle_status;
                $isReindexing = ! empty($doc->active_generation_id) && in_array($status, [
                    DocumentLifecycleStatus::PENDING_INGESTION,
                    DocumentLifecycleStatus::INDEXING,
                ], true);

                $extension = strtolower(pathinfo($doc->original_filename, PATHINFO_EXTENSION));
                $isSpreadsheet = in_array($extension, ['csv', 'xlsx'], true);

                $purpose = $doc->getIngestionPurpose();
                $isDataAnalytics = $purpose === IngestionPurpose::DATA_ANALYTICS;

                return [
                    'id'                    => (string) $doc->id,
                    'title'                 => $doc->title,
                    'filename'              => $doc->original_filename,
                    'purpose'               => $purpose->value,
                    'purpose_label'         => $purpose->label(),
                    'is_data_analytics'     => $isDataAnalytics,
                    'is_generic_analytics'  => $isDataAnalytics,
                    'is_product_catalog'    => false,
                    'source_id'             => $doc->source_id ?: (string) $doc->id,
                    'file_size'             => $doc->formattedFileSize(),
                    'trashed'               => $doc->trashed(),
                    'lifecycle_status'      => $status?->value ?? 'unknown',
                    'has_failed'            => $doc->hasFailed(),
                    'is_active'             => $status === DocumentLifecycleStatus::ACTIVE,
                    'has_active_catalog'    => ! empty($doc->active_generation_id),
                    'is_reindexing'         => $isReindexing,
                    'is_indexing'           => $status === DocumentLifecycleStatus::INDEXING,
                    'is_pending'            => $status === DocumentLifecycleStatus::PENDING_INGESTION,
                    'requires_mapping'      => $status === DocumentLifecycleStatus::REQUIRES_MAPPING,
                    'is_spreadsheet'        => $isSpreadsheet,
                    'can_update'            => $canUpdate,
                    'can_delete'            => $canDelete,
                    'error'                 => $doc->error_message,
                    'status_label'          => $status?->label() ?? 'Unknown',
                    'status_badge'          => $status?->badgeClass() ?? 'bg-secondary',
                    'status_icon'           => $status?->icon() ?? 'ri-file-line',
                    'sheets'                => $doc->metadata['sheets'] ?? [],
                    'sheets_count'          => (int) ($doc->sheets_count ?: 1),
                    'total_rows'            => (int) ($doc->chunk_count ?: 0),
                    'staged_rows'           => (int) ($doc->metadata['checkpoint']['staged_rows_count'] ?? 0),
                    'schema_summary'        => $doc->metadata['schema_summary'] ?? null,
                    'tables'                => $doc->metadata['tables'] ?? [],
                ];
            })
            ->rawColumns(['mime_type', 'sheets_count', 'status_badge', 'purpose_badge'])
            ->make(true);
    }

    /**
     * Store and persist an uploaded knowledge document and register it in the Python analytics engine.
     *
     * Invariants enforced:
     * 1. Workspace is strictly derived from the authenticated user's context.
     * 2. Content SHA-256 is computed before disk persistence.
     * 3. Concurrency-safe duplicate check rejects identical active/pending documents in this workspace.
     * 4. Filename is randomized (UUID) on private storage; original name is stored in DB only.
     * 5. If DB persistence fails, the written file is cleaned up immediately.
     * 6. Connects to Python Analytics Engine to register source and extract sheet-level metadata.
     * 7. Failed registration sets FAILED status with error details and leaves a recoverable record.
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file, ?string $title = null, ?\App\Enums\IngestionPurpose $purpose = null): KnowledgeDocument
    {
        $workspaceId = $this->getWorkspaceId();

        if (! $workspaceId) {
            /** @var User|null $user */
            $user = Auth::user();
            if ($user && $user->hasRole(RoleEnum::SUPERADMIN->value)) {
                // For superadmins, fallback to their own workspace or first available workspace
                $workspaceId = $user->workspace_id ?? \App\Models\Workspace::first()?->id;
            }
        }

        if (! $workspaceId) {
            throw ValidationException::withMessages([
                'workspace' => 'No active workspace found for this user context.',
            ]);
        }

        // 1. Calculate SHA-256 hash from uploaded file stream
        $contentHash = hash_file('sha256', $file->getRealPath());
        if ($contentHash === false) {
            throw new \RuntimeException('Failed to compute file content hash.');
        }

        // 2. Pre-check for duplicate within the authorized workspace.
        // Fails fast if an active or pending document already exists.
        $existing = KnowledgeDocument::where('workspace_id', $workspaceId)
            ->where('active_content_hash', $contentHash)
            ->where('lifecycle_status', '!=', DocumentLifecycleStatus::FAILED->value)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'document' => 'A document with identical content already exists in this workspace.',
            ]);
        }

        // If a previously failed record exists with this hash, clear its active_content_hash to avoid unique constraint collision
        KnowledgeDocument::where('workspace_id', $workspaceId)
            ->where('active_content_hash', $contentHash)
            ->where('lifecycle_status', DocumentLifecycleStatus::FAILED->value)
            ->update(['active_content_hash' => null]);

        // 3. Generate secure private filesystem location
        $fileUuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'xlsx'));
        $storageFilename = "{$fileUuid}.{$extension}";
        $relativeDir = "documents/{$workspaceId}";
        $relativePath = "{$relativeDir}/{$storageFilename}";

        // 4. Save to private local storage disk
        $storedPath = Storage::disk('local')->putFileAs($relativeDir, $file, $storageFilename);

        if (! $storedPath) {
            throw new \RuntimeException('Failed to write file to private storage.');
        }

        $resolvedPurpose = $purpose ?? \App\Enums\IngestionPurpose::resolve(
            value: null,
            document: null,
            filename: $file->getClientOriginalName()
        );

        // 5. Database persistence with engine-level uniqueness
        try {
            $document = DB::transaction(function () use ($file, $title, $workspaceId, $relativePath, $contentHash, $fileUuid, $resolvedPurpose) {
                $docTitle = ! empty(trim((string) $title))
                    ? trim((string) $title)
                    : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                $doc = new KnowledgeDocument([
                    'workspace_id'            => $workspaceId,
                    'title'                   => $docTitle,
                    'original_filename'       => $file->getClientOriginalName(),
                    'file_path'               => $relativePath,
                    'mime_type'               => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size'               => $file->getSize(),
                    'content_hash'            => $contentHash,
                    'active_content_hash'     => $contentHash,
                    'lifecycle_status'        => DocumentLifecycleStatus::PENDING_INGESTION,
                    'purpose'                 => $resolvedPurpose,
                    'chunk_count'             => 0,
                    'sheets_count'            => 1,
                    'version'                 => 1,
                    'is_active'               => false,
                    'error_message'           => null,
                    'metadata'                => [
                        'ingestion_purpose' => $resolvedPurpose->value,
                    ],
                    'created_by'              => Auth::id(),
                    'updated_by'              => Auth::id(),
                ]);
                $doc->id = $fileUuid;
                $doc->source_id = $fileUuid;
                $doc->save();
                return $doc;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $this->safeDeleteFile($relativePath);
            throw ValidationException::withMessages([
                'document' => 'A document with identical content already exists in this workspace.',
            ]);
        } catch (\Throwable $e) {
            $this->safeDeleteFile($relativePath);
            throw $e;
        }

        // 6. Connect to Python Analytics Source Registration if purpose is data analytics
        if ($resolvedPurpose === \App\Enums\IngestionPurpose::DATA_ANALYTICS) {
            $this->processAnalyticsRegistration($document);
        } else {
            $document->update([
                'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
                'is_active'        => true,
            ]);
        }

        return $document;
    }

    /**
     * Store and persist a text-based knowledge document (FAQ / Policy / Text).
     */
    public function storeText(string $title, string $textContent, ?IngestionPurpose $purpose = null): KnowledgeDocument
    {
        /** @var User|null $user */
        $user = Auth::user();
        $workspaceId = (int) ($user?->workspace_id ?? 1);

        $fileUuid = (string) Str::uuid();
        $storageFilename = "{$fileUuid}.txt";
        $relativeDir = "documents/{$workspaceId}";
        $relativePath = "{$relativeDir}/{$storageFilename}";

        // Save raw text to private local storage
        $written = Storage::disk('local')->put($relativePath, $textContent);
        if (! $written) {
            throw new \RuntimeException('Failed to write text document to private storage.');
        }

        $contentHash = hash('sha256', $textContent);
        $resolvedPurpose = $purpose ?? IngestionPurpose::KNOWLEDGE_BASE;

        // Check for active duplicate in workspace
        $existing = KnowledgeDocument::where('workspace_id', $workspaceId)
            ->where('active_content_hash', $contentHash)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            $this->safeDeleteFile($relativePath);
            throw ValidationException::withMessages([
                'content' => 'An active knowledge document with identical content already exists in this workspace.',
            ]);
        }

        $document = DB::transaction(function () use ($title, $textContent, $workspaceId, $relativePath, $contentHash, $fileUuid, $resolvedPurpose) {
            $doc = new KnowledgeDocument([
                'workspace_id'        => $workspaceId,
                'title'               => trim($title),
                'original_filename'   => Str::slug($title) . '.txt',
                'file_path'           => $relativePath,
                'mime_type'           => 'text/plain',
                'file_size'           => strlen($textContent),
                'content_hash'        => $contentHash,
                'active_content_hash' => $contentHash,
                'lifecycle_status'    => DocumentLifecycleStatus::ACTIVE,
                'purpose'             => $resolvedPurpose,
                'chunk_count'         => 1,
                'sheets_count'        => 1,
                'version'             => 1,
                'is_active'           => true,
                'error_message'       => null,
                'metadata'            => [
                    'ingestion_purpose' => $resolvedPurpose->value,
                    'raw_text'          => $textContent,
                ],
                'created_by'          => Auth::id(),
                'updated_by'          => Auth::id(),
            ]);
            $doc->id = $fileUuid;
            $doc->source_id = $fileUuid;
            $doc->save();
            return $doc;
        });

        return $document;
    }

    /**
     * Dispatch spreadsheet ingestion to Python analytics service and update document status.
     */
    public function processAnalyticsRegistration(KnowledgeDocument $document): void
    {
        $realStoredPath = Storage::disk('local')->path($document->file_path);
        if (! file_exists($realStoredPath)) {
            $document->update([
                'lifecycle_status' => DocumentLifecycleStatus::FAILED,
                'is_active'        => false,
                'error_message'    => 'Stored document file not found on disk.',
            ]);
            return;
        }

        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));

        $analyticsResponse = $this->analyticsClient->uploadSpreadsheet(
            filePath: $realStoredPath,
            filename: $document->original_filename,
            workspaceId: $document->workspace_id,
            conversationId: null,
            format: $extension,
            sourceId: $document->source_id ?: $document->id,
        );

        if (! empty($analyticsResponse['success'])) {
            $sourceId = (string) ($analyticsResponse['source_id'] ?? $analyticsResponse['file_id'] ?? $document->id);
            $sheets = $analyticsResponse['sheets'] ?? [];
            $sheetsCount = (int) ($analyticsResponse['sheets_count'] ?? count($sheets));
            $failedSheets = $analyticsResponse['failed_sheets'] ?? [];
            $totalRows = (int) ($analyticsResponse['total_rows'] ?? 0);
            $schemaSummary = (string) ($analyticsResponse['schema_summary'] ?? '');

            $existingMetadata = is_array($document->metadata) ? $document->metadata : [];
            $newMetadata = array_merge($existingMetadata, [
                'source_id'          => $sourceId,
                'sheets'             => $sheets,
                'sheets_count'       => $sheetsCount,
                'failed_sheets'      => $failedSheets,
                'total_rows'         => $totalRows,
                'tables'             => $analyticsResponse['tables'] ?? [],
                'schema_summary'     => $schemaSummary,
                'searchable_catalog' => $analyticsResponse['metadata_catalog'] ?? null,
                'ingestion_purpose'  => $existingMetadata['ingestion_purpose'] ?? \App\Enums\IngestionPurpose::DATA_ANALYTICS->value,
            ]);

            $document->update([
                'source_id'        => $sourceId,
                'sheets_count'     => max(1, $sheetsCount),
                'chunk_count'      => $totalRows,
                'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
                'is_active'        => true,
                'error_message'    => null,
                'metadata'         => $newMetadata,
            ]);
        } else {
            $errorMsg = (string) ($analyticsResponse['message'] ?? 'Failed to register analytical data source in analytics engine.');
            $document->update([
                'lifecycle_status' => DocumentLifecycleStatus::FAILED,
                'is_active'        => false,
                'error_message'    => $errorMsg,
            ]);
        }
    }

    /**
     * Retry registration for a failed document.
     */
    public function retry(string $id): KnowledgeDocument
    {
        /** @var KnowledgeDocument $document */
        $document = $this->workspaceQuery()->findOrFail($id);

        if ($document->lifecycle_status === DocumentLifecycleStatus::ACTIVE && $document->is_active) {
            return $document;
        }

        $document->update([
            'lifecycle_status' => DocumentLifecycleStatus::PENDING_INGESTION,
            'error_message'    => null,
        ]);

        if ($document->getIngestionPurpose() === IngestionPurpose::DATA_ANALYTICS) {
            $this->processAnalyticsRegistration($document);
        } else {
            $document->update([
                'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
                'is_active'        => true,
            ]);
        }

        return $document;
    }

    /**
     * Safely delete a file without allowing cleanup exceptions to mask the primary failure.
     */
    private function safeDeleteFile(string $relativePath): void
    {
        try {
            Storage::disk('local')->delete($relativePath);
        } catch (\Throwable $cleanupException) {
            Log::warning(
                'Failed to cleanup orphaned knowledge document file: ' . $relativePath,
                ['exception' => $cleanupException]
            );
        }
    }

    /**
     * Soft delete a document and clean up derived analytics sources if ownership is confirmed.
     *
     * @return array{success: bool, cleanup_warning: string|null}
     */
    public function delete(KnowledgeDocument $document): array
    {
        $cleanupWarning = null;
        $sourceId = $document->source_id;

        // Conclusively established ownership check
        if (! empty($sourceId) && ! empty($document->workspace_id)) {
            $cleanupResult = $this->analyticsClient->deleteSource($document->workspace_id, $sourceId);
            if (empty($cleanupResult['success'])) {
                $cleanupWarning = 'Derived analytics source cleanup failed: ' . ($cleanupResult['message'] ?? 'Unknown error');
                Log::warning('[KnowledgeDocumentService] Analytics source cleanup failed', [
                    'workspace_id' => $document->workspace_id,
                    'document_id'  => $document->id,
                    'source_id'    => $sourceId,
                    'error'        => $cleanupWarning,
                ]);
            }
        } else {
            Log::info('[KnowledgeDocumentService] Document has no established source_id provenance; skipping remote source deletion', [
                'document_id' => $document->id,
            ]);
        }

        DB::transaction(function () use ($document, $cleanupWarning) {
            $document->active_content_hash = null;
            $document->is_active = false;

            if ($cleanupWarning !== null) {
                $document->error_message = $cleanupWarning;
                $metadata = $document->metadata ?? [];
                $metadata['cleanup_pending'] = true;
                $metadata['cleanup_error'] = $cleanupWarning;
                $metadata['cleanup_failed_at'] = now()->toIso8601String();
                $document->metadata = $metadata;
            }

            $document->save();
            $document->delete();
        });

        return [
            'success'         => true,
            'cleanup_warning' => $cleanupWarning,
        ];
    }

    /**
     * Reconcile and retry remote source cleanup for a soft-deleted document.
     *
     * @return array{success: bool, message: string}
     */
    public function reconcileRemoteCleanup(KnowledgeDocument $document): array
    {
        $sourceId = $document->source_id;
        if (empty($sourceId) || empty($document->workspace_id)) {
            // Nothing to clean up remotely
            $metadata = $document->metadata ?? [];
            unset($metadata['cleanup_pending'], $metadata['cleanup_error'], $metadata['cleanup_failed_at']);
            $document->metadata = $metadata;
            $document->error_message = null;
            $document->save();
            return ['success' => true, 'message' => 'No remote source cleanup required.'];
        }

        $result = $this->analyticsClient->deleteSource($document->workspace_id, $sourceId);
        $metadata = $document->metadata ?? [];

        if (! empty($result['success'])) {
            unset($metadata['cleanup_pending'], $metadata['cleanup_error'], $metadata['cleanup_failed_at']);
            $document->metadata = $metadata;
            $document->error_message = null;
            $document->save();
            return ['success' => true, 'message' => 'Remote source cleaned up successfully.'];
        }

        $errorMsg = 'Remote source cleanup retry failed: ' . ($result['message'] ?? 'Unknown error');
        $metadata['cleanup_pending'] = true;
        $metadata['cleanup_error'] = $errorMsg;
        $document->metadata = $metadata;
        $document->error_message = $errorMsg;
        $document->save();

        return ['success' => false, 'message' => $errorMsg];
    }

    /**
     * Restore a soft-deleted document.
     */
    public function restore(string $id): KnowledgeDocument
    {
        $document = DB::transaction(function () use ($id) {
            /** @var KnowledgeDocument $document */
            $document = $this->workspaceQuery()->onlyTrashed()->lockForUpdate()->findOrFail($id);

            // Verify no active document with the same content exists in this workspace
            $conflict = KnowledgeDocument::where('workspace_id', $document->workspace_id)
                ->where('active_content_hash', $document->content_hash)
                ->where('id', '!=', $document->id)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'document' => 'Cannot restore document because an active document with identical content already exists in this workspace.',
                ]);
            }

            try {
                $document->active_content_hash = $document->content_hash;
                $document->save();
                $document->restore();
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                throw ValidationException::withMessages([
                    'document' => 'Cannot restore document because an active document with identical content already exists in this workspace.',
                ]);
            }

            return $document;
        });

        if ($document->getIngestionPurpose() === \App\Enums\IngestionPurpose::DATA_ANALYTICS) {
            $this->processAnalyticsRegistration($document);
        }

        return $document;
    }

    /**
     * Update the purpose of an existing document and synchronize analytical vs semantic engines.
     */
    public function updatePurpose(KnowledgeDocument $document, IngestionPurpose $newPurpose): KnowledgeDocument
    {
        $oldPurpose = $document->getIngestionPurpose();
        if ($oldPurpose === $newPurpose) {
            return $document;
        }

        $document->purpose = $newPurpose;
        $meta = $document->metadata ?? [];
        $meta['ingestion_purpose'] = $newPurpose->value;
        $document->metadata = $meta;

        if ($newPurpose === IngestionPurpose::DATA_ANALYTICS) {
            $document->save();
            $this->processAnalyticsRegistration($document);
        } else {
            if ($document->source_id) {
                try {
                    $this->analyticsClient->deleteSource($document->source_id, (int) $document->workspace_id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("[KnowledgeDocumentService] Failed to unregister analytics source: " . $e->getMessage());
                }
            }
            $document->lifecycle_status = DocumentLifecycleStatus::ACTIVE;
            $document->is_active = true;
            $document->save();
        }

        return $document;
    }

    /**
     * Get preview data for a knowledge document.
     * Supports spreadsheet (Excel, CSV), Text, and Markdown.
     *
     * @return array<string, mixed>
     */
    public function getDocumentPreview(KnowledgeDocument $document, ?string $sheetName = null, int $maxRows = 100): array
    {
        $filePath = $document->file_path;
        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            return [
                'success'  => false,
                'type'     => 'missing',
                'message'  => 'The physical file is not available on local storage.',
                'filename' => $document->original_filename,
            ];
        }

        $absolutePath = Storage::disk('local')->path($filePath);
        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));

        // 1. Text / Markdown / JSON / Logs
        if (in_array($extension, ['txt', 'md', 'json', 'log'], true)) {
            $content = Storage::disk('local')->get($filePath) ?? '';
            $isTruncated = strlen($content) > 51200;
            $previewText = $isTruncated ? substr($content, 0, 51200) . "\n\n... [Content truncated for preview. Download file to view completely] ..." : $content;

            return [
                'success'      => true,
                'type'         => 'text',
                'filename'     => $document->original_filename,
                'content'      => $previewText,
                'is_truncated' => $isTruncated,
                'stream_url'   => route('documents.preview', ['document' => $document->id, 'stream' => 1]),
            ];
        }

        // 3. Spreadsheets (XLSX, XLS, CSV)
        if (in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            try {
                $preview = $this->extractSpreadsheetPreview($absolutePath, $document->original_filename, $extension, $sheetName, $maxRows);

                // Self-healing: Ensure DB sheets_count & metadata match physical spreadsheet
                if (! empty($preview['sheets']) && is_array($preview['sheets'])) {
                    $physicalCount = count($preview['sheets']);
                    if ((int) $document->sheets_count !== $physicalCount) {
                        $meta = $document->metadata ?? [];
                        $meta['sheets'] = $preview['sheets'];
                        $document->update([
                            'sheets_count' => $physicalCount,
                            'metadata'     => $meta,
                        ]);
                    }
                }

                return $preview;
            } catch (\Throwable $e) {
                Log::warning('[KnowledgeDocumentService] Failed to parse spreadsheet for preview: ' . $e->getMessage(), [
                    'document_id' => $document->id,
                ]);

                return [
                    'success'    => false,
                    'type'       => 'error',
                    'message'    => 'Could not parse spreadsheet data: ' . $e->getMessage(),
                    'filename'   => $document->original_filename,
                    'stream_url' => route('documents.preview', ['document' => $document->id, 'stream' => 1]),
                ];
            }
        }

        // 4. Default / Other files
        return [
            'success'    => true,
            'type'       => 'unsupported',
            'filename'   => $document->original_filename,
            'extension'  => $extension,
            'message'    => 'Inline preview is not directly supported for .' . $extension . ' files. You can download the file below or query it in the simulator.',
            'stream_url' => route('documents.preview', ['document' => $document->id, 'stream' => 1]),
        ];
    }

    /**
     * Parse spreadsheet sheets, headers, and rows for UI preview.
     *
     * @return array<string, mixed>
     */
    private function extractSpreadsheetPreview(string $absolutePath, string $filename, string $extension, ?string $sheetName = null, int $maxRows = 100): array
    {
        // CSV Parsing
        if ($extension === 'csv') {
            $rows = [];
            if (($handle = fopen($absolutePath, 'r')) !== false) {
                $rowCount = 0;
                while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false && $rowCount < $maxRows + 1) {
                    $rows[] = array_map(fn ($val) => is_string($val) ? trim($val) : $val, $data);
                    $rowCount++;
                }
                fclose($handle);
            }

            $headers = ! empty($rows) ? array_shift($rows) : [];
            $rows = array_values(array_filter($rows, fn ($r) => ! empty(array_filter($r, fn ($c) => $c !== '' && $c !== null))));

            return [
                'success'           => true,
                'type'              => 'spreadsheet',
                'filename'          => $filename,
                'extension'         => $extension,
                'sheets'            => ['Default'],
                'active_sheet'      => 'Default',
                'headers'           => $headers,
                'rows'              => $rows,
                'loaded_rows_count' => count($rows),
                'total_rows'        => count($rows),
                'is_truncated'      => count($rows) >= $maxRows,
            ];
        }

        // Excel Parsing (XLSX, XLS)
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);

        $sheetNames = $reader->listWorksheetNames($absolutePath);
        if (empty($sheetNames)) {
            $sheetNames = ['Sheet1'];
        }

        $targetSheet = ($sheetName !== null && in_array($sheetName, $sheetNames, true))
            ? $sheetName
            : $sheetNames[0];

        $reader->setLoadSheetsOnly([$targetSheet]);
        $spreadsheet = $reader->load($absolutePath);
        $worksheet = $spreadsheet->getSheetByName($targetSheet) ?? $spreadsheet->getActiveSheet();

        $highestRow = min((int) $worksheet->getHighestRow(), $maxRows + 1);
        $highestColumn = $worksheet->getHighestColumn();

        // Convert range to array
        $sheetData = $worksheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, true, true, false);

        $headers = [];
        $rows = [];

        if (! empty($sheetData)) {
            $rawHeader = array_shift($sheetData);
            foreach ($rawHeader as $idx => $headerVal) {
                $colName = trim((string) $headerVal);
                $headers[] = $colName !== '' ? $colName : 'Column ' . ($idx + 1);
            }

            foreach ($sheetData as $row) {
                $hasData = false;
                $formattedRow = [];
                foreach ($row as $cell) {
                    $cellStr = is_scalar($cell) ? (string) $cell : '';
                    if (trim($cellStr) !== '') {
                        $hasData = true;
                    }
                    $formattedRow[] = $cellStr;
                }
                if ($hasData) {
                    $rows[] = $formattedRow;
                }
            }
        }

        $totalEstimatedRows = (int) $worksheet->getHighestRow();

        return [
            'success'            => true,
            'type'               => 'spreadsheet',
            'filename'           => $filename,
            'extension'          => $extension,
            'sheets'             => $sheetNames,
            'active_sheet'       => $targetSheet,
            'headers'            => $headers,
            'rows'               => $rows,
            'loaded_rows_count'  => count($rows),
            'total_rows'         => max(0, $totalEstimatedRows - 1),
            'is_truncated'       => $totalEstimatedRows > $maxRows,
        ];
    }

    /**
     * Get binary response for streaming or downloading the raw file.
     */
    public function getDocumentFileStream(KnowledgeDocument $document): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filePath = $document->file_path;
        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            abort(404, 'File not found on storage disk.');
        }

        $absolutePath = Storage::disk('local')->path($filePath);
        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));

        $mimeType = match ($extension) {
            'csv' => 'text/csv; charset=UTF-8',
            'txt', 'log' => 'text/plain; charset=UTF-8',
            'json' => 'application/json',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            default => $document->mime_type ?: 'application/octet-stream',
        };

        return response()->file($absolutePath, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addslashes($document->original_filename) . '"',
        ]);
    }
}
