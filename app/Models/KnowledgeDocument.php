<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentLifecycleStatus;
use Database\Factories\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeDocument extends Model
{
    /** @use HasFactory<KnowledgeDocumentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'knowledge_documents';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'title',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'content_hash',
        'active_content_hash',
        'lifecycle_status',
        'chunk_count',
        'version',
        'is_active',
        'error_message',
        'created_by',
        'updated_by',
    ];

    /**
     * Bootstrap the model and its events.
     */
    protected static function booted(): void
    {
        static::creating(function (KnowledgeDocument $doc) {
            if ($doc->deleted_at === null && empty($doc->active_content_hash)) {
                $doc->active_content_hash = $doc->content_hash;
            }
        });

        static::deleting(function (KnowledgeDocument $doc) {
            if (! $doc->isForceDeleting() && $doc->active_content_hash !== null) {
                $doc->active_content_hash = null;
                $doc->saveQuietly();
            }
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle_status' => DocumentLifecycleStatus::class,
            'is_active'        => 'boolean',
            'file_size'        => 'integer',
            'chunk_count'      => 'integer',
            'version'          => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get a human-readable file size string.
     */
    public function formattedFileSize(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Determine if the document is active and searchable.
     */
    public function isReadyForRetrieval(): bool
    {
        return $this->is_active
            && ! $this->trashed()
            && $this->lifecycle_status === DocumentLifecycleStatus::ACTIVE;
    }

    /**
     * Check if processing failed.
     */
    public function hasFailed(): bool
    {
        return $this->lifecycle_status?->hasFailed() ?? false;
    }

    /**
     * Check if document is pending ingestion.
     */
    public function isPending(): bool
    {
        return $this->lifecycle_status?->isPending() ?? true;
    }
}
