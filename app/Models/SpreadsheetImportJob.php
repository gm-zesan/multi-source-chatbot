<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpreadsheetImportJob extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'spreadsheet_import_jobs';

    protected $fillable = [
        'workspace_id',
        'document_id',
        'sheet_name',
        'status',
        'conflict_mode',
        'generation_id',
        'schema_version',
        'header_row_index',
        'total_rows',
        'processed_rows',
        'staged_rows',
        'failed_rows',
        'checkpoint_row',
        'detected_schema',
        'source_descriptors',
        'mapping_config',
        'validation_errors',
        'error_message',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'header_row_index' => 'integer',
        'total_rows'       => 'integer',
        'processed_rows'   => 'integer',
        'staged_rows'      => 'integer',
        'failed_rows'      => 'integer',
        'checkpoint_row'   => 'integer',
        'detected_schema'  => 'array',
        'source_descriptors' => 'array',
        'mapping_config'   => 'array',
        'validation_errors' => 'array',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(StructuredRecord::class, 'import_job_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'completed_with_errors'], true);
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['inspecting', 'processing'], true);
    }

    public function requiresMapping(): bool
    {
        return $this->status === 'awaiting_mapping';
    }
}

