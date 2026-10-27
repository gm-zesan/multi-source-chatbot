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
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
        private readonly ?RetrievalClient $retrievalClient = null,
    ) {}

    public function retrievalClient(): RetrievalClient
    {
        return $this->retrievalClient ?? app(RetrievalClient::class);
    }

    /**
     * Get the authenticated user's workspace ID.
     */
    public function getWorkspaceId(): ?int
    {
        return Auth::user()?->workspace_id;
    }

    /**
     * Resolve and validate the authorized workspace ID for the current user context.
     * Fails closed when tenant context is missing or invalid.
     * Never silently defaults to workspace 1 or infers an arbitrary workspace.
     *
     * @throws ValidationException
     */
    public function resolveAuthorizedWorkspaceId(?int $explicitWorkspaceId = null): int
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw ValidationException::withMessages([
                'workspace' => 'Unauthenticated user context.',
            ]);
        }

        $isSuperAdmin = $user->hasRole(RoleEnum::SUPERADMIN->value);

        if ($isSuperAdmin) {
            if ($explicitWorkspaceId !== null && $explicitWorkspaceId > 0) {
                $exists = \App\Models\Workspace::where('id', $explicitWorkspaceId)->exists();
                if (! $exists) {
                    throw ValidationException::withMessages([
                        'workspace' => 'The selected workspace does not exist.',
                    ]);
                }

                return $explicitWorkspaceId;
            }

            if ($user->workspace_id !== null && (int) $user->workspace_id > 0) {
                return (int) $user->workspace_id;
            }

            throw ValidationException::withMessages([
                'workspace' => 'No active workspace selected or found for superadmin context.',
            ]);
        }

        // Regular tenant user
        $userWorkspaceId = $user->workspace_id !== null ? (int) $user->workspace_id : 0;
        if ($userWorkspaceId <= 0) {
            throw ValidationException::withMessages([
                'workspace' => 'No active workspace found for this user context.',
            ]);
        }

        if ($explicitWorkspaceId !== null && $explicitWorkspaceId > 0 && $explicitWorkspaceId !== $userWorkspaceId) {
            throw ValidationException::withMessages([
                'workspace' => 'Unauthorized workspace selection. Users may only target their assigned workspace.',
            ]);
        }

        return $userWorkspaceId;
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

        $purpose = $request?->input('purpose');
        $hasPurpose = KnowledgeDocument::hasPurposeColumn();

        if ($purpose === 'data_analytics' || $purpose === 'generic_analytics') {
            $query->where(function ($q) use ($hasPurpose) {
                if ($hasPurpose) {
                    $q->where('purpose', \App\Enums\IngestionPurpose::DATA_ANALYTICS->value)
                      ->orWhere('original_filename', 'LIKE', '%.xlsx')
                      ->orWhere('original_filename', 'LIKE', '%.csv');
                } else {
                    $q->where('original_filename', 'LIKE', '%.xlsx')
                      ->orWhere('original_filename', 'LIKE', '%.csv');
                }
            });
        } elseif ($purpose === 'knowledge_base' || $purpose === 'semantic_knowledge') {
            $query->where(function ($q) use ($hasPurpose) {
                if ($hasPurpose) {
                    $q->where('purpose', \App\Enums\IngestionPurpose::KNOWLEDGE_BASE->value)
                      ->orWhere(function ($sub) {
                          $sub->where('original_filename', 'NOT LIKE', '%.xlsx')
                              ->where('original_filename', 'NOT LIKE', '%.csv');
                      });
                } else {
                    $q->where('original_filename', 'NOT LIKE', '%.xlsx')
                      ->where('original_filename', 'NOT LIKE', '%.csv');
                }
            });
        }

        $user = Auth::user();
        if ($user && $user->hasRole(RoleEnum::SUPERADMIN->value) && $request?->filled('workspace_id')) {
            $query->where('workspace_id', (int) $request->input('workspace_id'));
        }

        return DataTables::of($query->latest())
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
    public function store(
        UploadedFile $file,
        ?string $title = null,
        ?\App\Enums\IngestionPurpose $purpose = null,
        ?int $explicitWorkspaceId = null
    ): KnowledgeDocument {
        $workspaceId = $this->resolveAuthorizedWorkspaceId($explicitWorkspaceId);

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

        // 6. Connect to Python Analytics Source Registration if purpose is data analytics, or ingest for Knowledge Base
        if ($resolvedPurpose === \App\Enums\IngestionPurpose::DATA_ANALYTICS) {
            $this->processAnalyticsRegistration($document);
        } else {
            $this->processKnowledgeBaseIngestion($document);
        }

        return $document;
    }

    /**
     * Store and persist a text-based knowledge document (FAQ / Policy / Text).
     */
    public function storeText(
        string $title,
        string $textContent,
        ?IngestionPurpose $purpose = null,
        ?int $explicitWorkspaceId = null
    ): KnowledgeDocument {
        $workspaceId = $this->resolveAuthorizedWorkspaceId($explicitWorkspaceId);

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
                'lifecycle_status'    => DocumentLifecycleStatus::PENDING_INGESTION,
                'purpose'             => $resolvedPurpose,
                'chunk_count'         => 0,
                'sheets_count'        => 1,
                'version'             => 1,
                'is_active'           => false,
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

        if ($resolvedPurpose === IngestionPurpose::KNOWLEDGE_BASE) {
            $this->processKnowledgeBaseIngestion($document);
        } else {
            $document->update([
                'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
                'is_active'        => true,
            ]);
        }

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
     * Ingest, chunk, and index a knowledge base document into the semantic retrieval engine.
     */
    public function processKnowledgeBaseIngestion(KnowledgeDocument $document): void
    {
        $lock = null;
        try {
            $lock = Cache::lock("knowledge_doc_indexing:{$document->id}", 60);
            $acquired = $lock->get();
        } catch (\Throwable) {
            $acquired = true;
            $lock = null;
        }

        if (! $acquired) {
            // H-01: Non-destructive lock contention - log and skip without corrupting document status
            Log::info("[KnowledgeDocumentService] Concurrency lock could not be acquired for document {$document->id}; an indexing operation is already in progress.");
            return;
        }

        $expectedVersion = (int) ($document->fresh()?->version ?? $document->version ?? 1);
        $wasActive = ($document->lifecycle_status === DocumentLifecycleStatus::ACTIVE && $document->is_active);
        $previousGenerationId = $document->active_generation_id;
        $previousChunkCount = (int) ($document->chunk_count ?? 0);

        $syncedChunkIds = [];
        $generationId = null;

        try {
            // Fix 2: Recover any abandoned pending generation BEFORE writing new pending metadata
            $cleanedAbandoned = $this->cleanupAbandonedPendingGeneration($document);
            if (! $cleanedAbandoned) {
                throw new \RuntimeException("Failed to confirm cleanup of previous abandoned generation from retrieval engine. Ingestion aborted to prevent orphaned chunks.");
            }

            $text = $this->extractDocumentText($document);
            $trimmed = trim($text);

            if ($trimmed === '') {
                throw new \RuntimeException('Document content is empty or contains no extractable text.');
            }

            $chunks = $this->chunkText($trimmed);
            if (empty($chunks)) {
                throw new \RuntimeException('Failed to generate chunks from document text.');
            }

            // Generate new attempt generation ID
            $generationId = (string) Str::uuid();

            // L-01: Durably persist pending generation attempt BEFORE issuing any remote sync mutations
            $meta = $document->fresh()?->metadata ?? $document->metadata ?? [];
            $meta['pending_generation_id'] = $generationId;
            $meta['pending_chunk_count'] = count($chunks);

            $updatePayload = [
                'metadata' => $meta,
            ];

            // Fix 1: If document was already active, preserve its active status and retrievability throughout indexing
            if (! $wasActive) {
                $updatePayload['lifecycle_status'] = DocumentLifecycleStatus::INDEXING;
                $updatePayload['is_active']        = false;
                $updatePayload['error_message']    = null;
            }

            $document->update($updatePayload);

            $indexedCount = 0;
            $client = $this->retrievalClient();

            foreach ($chunks as $idx => $chunk) {
                // C-01: Generation-scoped chunk identity isolates this attempt from any concurrent or older attempts
                $chunkId = "doc_{$document->id}_{$generationId}_{$idx}";
                $synced = $client->syncDocumentChunk(
                    id: $chunkId,
                    workspaceId: (int) $document->workspace_id,
                    title: $document->title,
                    content: $chunk,
                    documentType: 'knowledge_document',
                    sourceId: $document->source_id ?? $document->id
                );

                if (! $synced) {
                    throw new \RuntimeException("Failed to sync chunk {$idx} to retrieval engine.");
                }

                $syncedChunkIds[] = $chunkId;
                $indexedCount++;
            }

            $newVersion = $expectedVersion + 1;

            // M-01 & M-02: Optimistic version check fencing: atomically advance version and publish active_generation_id
            $affected = KnowledgeDocument::where('id', $document->id)
                ->where('workspace_id', $document->workspace_id)
                ->where('version', $expectedVersion)
                ->whereNull('deleted_at')
                ->update([
                    'version'              => $newVersion,
                    'lifecycle_status'     => DocumentLifecycleStatus::ACTIVE,
                    'is_active'            => true,
                    'chunk_count'          => $indexedCount,
                    'active_generation_id' => $generationId,
                    'error_message'        => null,
                ]);

            if ($affected === 0) {
                // Version was superseded or document deleted while indexing was in progress;
                // C-01: Safely roll back ONLY this worker's generation chunks without touching active/newer generation
                $this->cleanupGenerationChunks($document, $generationId, count($syncedChunkIds));
                throw new \RuntimeException("Indexing aborted: document version was superseded by a concurrent operation or deleted (expected version {$expectedVersion}).");
            }

            // Publication succeeded: safely clean up previous generation chunks now that new generation is live
            try {
                if ($previousGenerationId && $previousGenerationId !== $generationId) {
                    $cleanedOld = $this->cleanupGenerationChunks($document, $previousGenerationId, $previousChunkCount);
                    if (! $cleanedOld) {
                        Log::warning("[KnowledgeDocumentService] Failed to clean up superseded generation {$previousGenerationId} after publication", [
                            'document_id' => $document->id,
                            'workspace_id' => $document->workspace_id,
                        ]);
                    }
                }
                // Also clean up any legacy un-versioned chunks if present
                $this->cleanupLegacyChunks($document, $previousChunkCount);
            } catch (\Throwable $cleanupEx) {
                Log::warning("[KnowledgeDocumentService] Obsolete generation cleanup encountered an error: " . $cleanupEx->getMessage(), [
                    'document_id' => $document->id,
                    'workspace_id' => $document->workspace_id,
                ]);
            }

            // Clear pending attempt metadata
            $meta = $document->fresh()?->metadata ?? $document->metadata ?? [];
            unset($meta['pending_generation_id'], $meta['pending_chunk_count']);
            $document->metadata = $meta;
            $document->saveQuietly();
            $document->refresh();

            Log::info("[KnowledgeDocumentService] Successfully indexed knowledge document generation", [
                'document_id'   => $document->id,
                'workspace_id'  => $document->workspace_id,
                'generation_id' => $generationId,
                'chunk_count'   => $indexedCount,
                'version'       => $newVersion,
            ]);
        } catch (\Throwable $e) {
            Log::error("[KnowledgeDocumentService] Failed to index knowledge base document", [
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);

            // Attempt immediate rollback of this generation's chunks
            $uncleanedRollbacks = [];
            foreach ($syncedChunkIds as $rollbackId) {
                $delSuccess = false;
                try {
                    $delSuccess = $this->retrievalClient()->deleteFaq($rollbackId, (int) $document->workspace_id);
                } catch (\Throwable) {}
                if (! $delSuccess) {
                    $uncleanedRollbacks[] = $rollbackId;
                }
            }

            // Check if document was superseded by a newer version or deleted
            $freshDoc = KnowledgeDocument::find($document->id);
            if ($freshDoc && ($freshDoc->version > $expectedVersion || $freshDoc->trashed())) {
                Log::warning("[KnowledgeDocumentService] Stale indexing worker discarded for document {$document->id} (worker v{$expectedVersion} <= current v{$freshDoc->version})");
                return;
            }

            $meta = $document->fresh()?->metadata ?? $document->metadata ?? [];
            if (! empty($uncleanedRollbacks)) {
                $meta['partial_chunks'] = array_values(array_unique(array_merge(
                    $meta['partial_chunks'] ?? [],
                    $uncleanedRollbacks
                )));
            }
            if (isset($chunks) && is_array($chunks)) {
                $meta['last_attempted_chunk_count'] = count($chunks);
            }

            if ($wasActive) {
                // Fix 1: If document was already active, preserve its active status and previous generation!
                $meta['last_reindex_error'] = $e->getMessage();
                if ($generationId !== null && empty($uncleanedRollbacks)) {
                    unset($meta['pending_generation_id'], $meta['pending_chunk_count']);
                }
                $document->update([
                    'metadata' => $meta,
                ]);
            } else {
                if ($generationId !== null && empty($uncleanedRollbacks)) {
                    unset($meta['pending_generation_id'], $meta['pending_chunk_count']);
                }
                $document->update([
                    'lifecycle_status' => DocumentLifecycleStatus::FAILED,
                    'is_active'        => false,
                    'error_message'    => $e->getMessage(),
                    'metadata'         => $meta,
                ]);
            }
        } finally {
            if ($lock) {
                try {
                    $lock->release();
                } catch (\Throwable) {}
            }
        }
    }

    /**
     * Extract raw text from the knowledge document.
     */
    public function extractDocumentText(KnowledgeDocument $document): string
    {
        // 1. Check metadata for raw_text (for storeText or explicit text entries)
        if (! empty($document->metadata['raw_text'])) {
            return (string) $document->metadata['raw_text'];
        }

        $filePath = $document->file_path;
        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            throw new \RuntimeException('Document file is missing from private storage.');
        }

        $extension = strtolower(pathinfo($document->original_filename ?: $filePath, PATHINFO_EXTENSION));

        // Supported text formats: txt, md, json, log, csv
        if (in_array($extension, ['txt', 'md', 'json', 'log', 'csv'], true)) {
            $content = Storage::disk('local')->get($filePath);
            return $content ?? '';
        }

        // Unsupported binary formats (PDF, Word, Excel, PowerPoint)
        if (in_array($extension, ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx'], true)) {
            throw new \RuntimeException(
                "Unsupported file format for text extraction (.{$extension}). Text extraction requires plain text (.txt, .md) or direct text entries."
            );
        }

        $raw = Storage::disk('local')->get($filePath);
        if ($raw !== null && mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        throw new \RuntimeException("Unable to extract text from document of type: .{$extension}");
    }

    /**
     * Deterministically split text into overlapping chunks.
     *
     * @return array<int, string>
     */
    public function chunkText(string $text, int $chunkSize = 500, int $overlap = 80): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return [];
        }

        $length = mb_strlen($text);
        if ($length <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        $step = max(1, $chunkSize - $overlap);

        while ($start < $length) {
            $chunk = mb_substr($text, $start, $chunkSize);
            $trimmed = trim($chunk);
            if ($trimmed !== '') {
                $chunks[] = $trimmed;
            }
            $start += $step;
        }

        return $chunks;
    }

    /**
     * Clean up an abandoned pending generation from a previous crashed or interrupted attempt.
     * Guarantees that active_generation_id is never deleted.
     *
     * @return bool True if cleanup succeeded or no abandoned generation existed; False if cleanup could not be confirmed.
     */
    public function cleanupAbandonedPendingGeneration(KnowledgeDocument $document): bool
    {
        $meta = $document->metadata ?? [];
        $abandonedGenId = $meta['pending_generation_id'] ?? null;
        $abandonedCount = (int) ($meta['pending_chunk_count'] ?? 0);

        if (! empty($abandonedGenId)) {
            // Guard: If pending_generation_id matches active_generation_id, it was successfully published
            // in a prior attempt and only metadata clearing was interrupted. Do NOT delete its active chunks!
            if ($abandonedGenId === $document->active_generation_id) {
                unset($meta['pending_generation_id'], $meta['pending_chunk_count']);
                $document->metadata = $meta;
                $document->saveQuietly();
            } else {
                $count = max($abandonedCount, (int) ($document->chunk_count ?? 0), 10);
                $cleaned = $this->cleanupGenerationChunks($document, $abandonedGenId, $count);
                if (! $cleaned) {
                    return false;
                }
                unset($meta['pending_generation_id'], $meta['pending_chunk_count']);
                $document->metadata = $meta;
                $document->saveQuietly();
            }
        }

        // Also clean up any uncleaned partial chunks that do not belong to active generation
        if (! empty($meta['partial_chunks']) && is_array($meta['partial_chunks'])) {
            $client = $this->retrievalClient();
            $uncleaned = [];
            foreach ($meta['partial_chunks'] as $chunkId) {
                if (is_string($chunkId) && str_starts_with($chunkId, "doc_{$document->id}_")) {
                    if (! empty($document->active_generation_id) && str_contains($chunkId, "_{$document->active_generation_id}_")) {
                        continue;
                    }
                    $success = false;
                    try {
                        $success = $client->deleteFaq($chunkId, (int) $document->workspace_id);
                    } catch (\Throwable) {}
                    if (! $success) {
                        $uncleaned[] = $chunkId;
                    }
                }
            }
            if (! empty($uncleaned)) {
                $meta['partial_chunks'] = $uncleaned;
                $document->metadata = $meta;
                $document->saveQuietly();

                return false;
            }
            unset($meta['partial_chunks']);
            $document->metadata = $meta;
            $document->saveQuietly();
        }

        return true;
    }

    /**
     * Delete chunks for a specific document generation.
     */
    public function cleanupGenerationChunks(KnowledgeDocument $document, string $generationId, int $count): bool
    {
        if ($count <= 0) {
            $count = max((int) ($document->chunk_count ?? 0), 10);
        }

        $client = $this->retrievalClient();
        $allSuccess = true;

        for ($i = 0; $i < $count; $i++) {
            $chunkId = "doc_{$document->id}_{$generationId}_{$i}";
            try {
                $deleted = $client->deleteFaq($chunkId, (int) $document->workspace_id);
                if (! $deleted) {
                    $allSuccess = false;
                }
            } catch (\Throwable) {
                $allSuccess = false;
            }
        }

        return $allSuccess;
    }

    /**
     * Delete legacy non-generation-tagged chunks (doc_{id}_{i}).
     */
    public function cleanupLegacyChunks(KnowledgeDocument $document, int $count): bool
    {
        if ($count <= 0) {
            return true;
        }

        $client = $this->retrievalClient();
        $allSuccess = true;

        for ($i = 0; $i < $count; $i++) {
            $chunkId = "doc_{$document->id}_{$i}";
            try {
                $deleted = $client->deleteFaq($chunkId, (int) $document->workspace_id);
                if (! $deleted) {
                    $allSuccess = false;
                }
            } catch (\Throwable) {
                $allSuccess = false;
            }
        }

        return $allSuccess;
    }

    /**
     * Cleanup all indexed chunks for a knowledge base document in Typesense.
     *
     * @param KnowledgeDocument $document
     * @param int|null $expectedChunkCount Optional count of chunks being prepared for indexing
     * @throws \RuntimeException If cleanup of any chunk cannot be confirmed by the retrieval service
     */
    public function cleanupKnowledgeBaseChunks(KnowledgeDocument $document, ?int $expectedChunkCount = null): void
    {
        $existingCount = (int) ($document->chunk_count ?? 0);
        $metadata = $document->metadata ?? [];
        $lastAttempted = (int) ($metadata['last_attempted_chunk_count'] ?? 0);
        $expected = (int) ($expectedChunkCount ?? 0);
        $maxCount = max($existingCount, $lastAttempted, $expected);

        $chunkIds = [];

        // 1. Legacy chunk IDs
        for ($i = 0; $i < $maxCount; $i++) {
            $chunkIds[] = "doc_{$document->id}_{$i}";
        }

        // 2. Active generation chunk IDs
        if (! empty($document->active_generation_id)) {
            for ($i = 0; $i < $maxCount; $i++) {
                $chunkIds[] = "doc_{$document->id}_{$document->active_generation_id}_{$i}";
            }
        }

        // 3. Pending generation from crash / previous attempt (L-01)
        if (! empty($metadata['pending_generation_id'])) {
            $pendingCount = max((int) ($metadata['pending_chunk_count'] ?? 0), $maxCount);
            for ($i = 0; $i < $pendingCount; $i++) {
                $chunkIds[] = "doc_{$document->id}_{$metadata['pending_generation_id']}_{$i}";
            }
        }

        // 4. Any explicit partial chunk IDs
        if (! empty($metadata['partial_chunks']) && is_array($metadata['partial_chunks'])) {
            foreach ($metadata['partial_chunks'] as $pid) {
                if (is_string($pid) && str_starts_with($pid, "doc_{$document->id}_")) {
                    $chunkIds[] = $pid;
                }
            }
        }

        $chunkIds = array_values(array_unique($chunkIds));
        if (empty($chunkIds)) {
            return;
        }

        $client = $this->retrievalClient();
        $uncleaned = [];

        foreach ($chunkIds as $chunkId) {
            $success = false;
            try {
                $success = $client->deleteFaq($chunkId, (int) $document->workspace_id);
            } catch (\Throwable) {}
            if (! $success) {
                $uncleaned[] = $chunkId;
            }
        }

        if (! empty($uncleaned)) {
            $metadata['partial_chunks'] = array_values(array_unique(array_merge(
                $metadata['partial_chunks'] ?? [],
                $uncleaned
            )));
            $document->metadata = $metadata;
            $document->saveQuietly();

            throw new \RuntimeException(sprintf(
                'Failed to confirm cleanup of %d chunk(s) from retrieval engine: %s',
                count($uncleaned),
                implode(', ', array_slice($uncleaned, 0, 3))
            ));
        }

        // Cleanup confirmed for all chunks - clear partial_chunks, pending_generation_id, last_attempted_chunk_count
        if (isset($metadata['partial_chunks']) || isset($metadata['last_attempted_chunk_count']) || isset($metadata['pending_generation_id'])) {
            unset($metadata['partial_chunks'], $metadata['last_attempted_chunk_count'], $metadata['pending_generation_id'], $metadata['pending_chunk_count']);
            $document->metadata = $metadata;
            $document->saveQuietly();
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

        $document->increment('version');
        $document->update([
            'lifecycle_status' => DocumentLifecycleStatus::PENDING_INGESTION,
            'error_message'    => null,
        ]);

        if ($document->getIngestionPurpose() === IngestionPurpose::DATA_ANALYTICS) {
            $this->processAnalyticsRegistration($document);
        } else {
            $this->processKnowledgeBaseIngestion($document);
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

        // Clean up knowledge base chunks if applicable
        if ($document->getIngestionPurpose() === IngestionPurpose::KNOWLEDGE_BASE) {
            try {
                $this->cleanupKnowledgeBaseChunks($document);
            } catch (\Throwable $e) {
                $cleanupWarning = 'Semantic index chunk cleanup failed: ' . $e->getMessage();
                Log::warning('[KnowledgeDocumentService] Knowledge base chunk cleanup failed', [
                    'workspace_id' => $document->workspace_id,
                    'document_id'  => $document->id,
                    'error'        => $cleanupWarning,
                ]);
            }
        }

        DB::transaction(function () use ($document, $cleanupWarning) {
            // M-02: Increment version, nullify active generation, and mark inactive to immediately fence in-flight workers
            $document->increment('version');
            $document->active_content_hash = null;
            $document->is_active = false;
            $document->active_generation_id = null;

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
        } elseif ($document->getIngestionPurpose() === \App\Enums\IngestionPurpose::KNOWLEDGE_BASE) {
            $document->increment('version');
            $this->processKnowledgeBaseIngestion($document);
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

        $document->increment('version');
        $document->purpose = $newPurpose;
        $meta = $document->metadata ?? [];
        $meta['ingestion_purpose'] = $newPurpose->value;
        $document->metadata = $meta;

        if ($newPurpose === IngestionPurpose::DATA_ANALYTICS) {
            $this->cleanupKnowledgeBaseChunks($document);
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
            $document->save();
            $this->processKnowledgeBaseIngestion($document);
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
