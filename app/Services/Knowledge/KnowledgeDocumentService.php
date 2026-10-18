<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\DocumentLifecycleStatus;
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
                return [
                    'id'         => $doc->id,
                    'trashed'    => $doc->trashed(),
                    'has_failed' => $doc->hasFailed(),
                    'error'      => $doc->error_message,
                ];
            })
            ->rawColumns(['mime_type', 'sheets_count', 'status_badge'])
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
    public function store(UploadedFile $file, ?string $title = null): KnowledgeDocument
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

        // 5. Database persistence with engine-level uniqueness
        try {
            $document = DB::transaction(function () use ($file, $title, $workspaceId, $relativePath, $contentHash, $fileUuid) {
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
                    'chunk_count'             => 0,
                    'sheets_count'            => 1,
                    'version'                 => 1,
                    'is_active'               => false,
                    'error_message'           => null,
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

        // 6. Connect to Python Analytics Source Registration
        $this->processAnalyticsRegistration($document);

        return $document;
    }

    /**
     * Dispatch spreadsheet ingestion to Python analytics service and update document status.
     */
    protected function processAnalyticsRegistration(KnowledgeDocument $document): void
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

            $document->update([
                'source_id'        => $sourceId,
                'sheets_count'     => max(1, $sheetsCount),
                'chunk_count'      => $totalRows,
                'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
                'is_active'        => true,
                'error_message'    => null,
                'metadata'         => [
                    'source_id'      => $sourceId,
                    'sheets'         => $sheets,
                    'sheets_count'   => $sheetsCount,
                    'failed_sheets'  => $failedSheets,
                    'total_rows'     => $totalRows,
                    'tables'         => $analyticsResponse['tables'] ?? [],
                    'schema_summary' => $schemaSummary,
                ],
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

        $this->processAnalyticsRegistration($document);

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
        return DB::transaction(function () use ($id) {
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
    }
}
