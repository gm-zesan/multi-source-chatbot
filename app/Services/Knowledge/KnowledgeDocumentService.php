<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\DataTables;

class KnowledgeDocumentService
{
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
            ->rawColumns(['mime_type', 'status_badge'])
            ->make(true);
    }

    /**
     * Store and persist an uploaded knowledge document.
     *
     * Invariants enforced:
     * 1. Workspace is strictly derived from the authenticated user's context.
     * 2. Content SHA-256 is computed before disk persistence.
     * 3. Concurrency-safe duplicate check rejects identical active/pending documents in this workspace.
     * 4. Filename is randomized (UUID) on private storage; original name is stored in DB only.
     * 5. If DB persistence fails, the written file is cleaned up immediately.
     * 6. Duplicate rejection NEVER deletes any existing file.
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
        // Fails fast before disk I/O if an active document already exists.
        $existing = KnowledgeDocument::where('workspace_id', $workspaceId)
            ->where('active_content_hash', $contentHash)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'document' => 'A document with identical content already exists in this workspace.',
            ]);
        }

        // 3. Generate secure private filesystem location
        $fileUuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));
        $storageFilename = "{$fileUuid}.{$extension}";
        $relativeDir = "documents/{$workspaceId}";
        $relativePath = "{$relativeDir}/{$storageFilename}";

        // 4. Save to private local storage disk
        $storedPath = Storage::disk('local')->putFileAs($relativeDir, $file, $storageFilename);

        if (! $storedPath) {
            throw new \RuntimeException('Failed to write file to private storage.');
        }

        // 5. Database persistence with engine-level uniqueness and automatic cleanup on failure
        try {
            return DB::transaction(function () use ($file, $title, $workspaceId, $relativePath, $contentHash) {
                $docTitle = ! empty(trim((string) $title))
                    ? trim((string) $title)
                    : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                return KnowledgeDocument::create([
                    'workspace_id'            => $workspaceId,
                    'title'                   => $docTitle,
                    'original_filename'       => $file->getClientOriginalName(),
                    'file_path'               => $relativePath,
                    'mime_type'               => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size'               => $file->getSize(),
                    'content_hash'            => $contentHash,
                    'active_content_hash'     => $contentHash,
                    'lifecycle_status'        => DocumentLifecycleStatus::PENDING_INGESTION,
                    'chunk_count'         => 0,
                    'version'             => 1,
                    'is_active'           => false,
                    'error_message'       => null,
                    'created_by'          => Auth::id(),
                    'updated_by'          => Auth::id(),
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Delete newly written file on duplicate constraint conflict so no orphan is left behind
            $this->safeDeleteFile($relativePath);

            throw ValidationException::withMessages([
                'document' => 'A document with identical content already exists in this workspace.',
            ]);
        } catch (\Throwable $e) {
            // Delete newly written file on failure so no orphan is left behind
            $this->safeDeleteFile($relativePath);

            throw $e;
        }
    }

    /**
     * Safely delete a file without allowing cleanup exceptions to mask the primary failure.
     */
    private function safeDeleteFile(string $relativePath): void
    {
        try {
            Storage::disk('local')->delete($relativePath);
        } catch (\Throwable $cleanupException) {
            \Illuminate\Support\Facades\Log::warning(
                'Failed to cleanup orphaned knowledge document file: ' . $relativePath,
                ['exception' => $cleanupException]
            );
        }
    }

    /**
     * Soft delete a document.
     */
    public function delete(KnowledgeDocument $document): void
    {
        DB::transaction(function () use ($document) {
            $document->active_content_hash = null;
            $document->is_active = false;
            $document->save();
            $document->delete();
        });
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

