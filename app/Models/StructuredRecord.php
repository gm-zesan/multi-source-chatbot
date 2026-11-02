<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StructuredRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'structured_records';

    protected $fillable = [
        'workspace_id',
        'document_id',
        'import_job_id',
        'sheet_name',
        'row_index',
        'record_identifier',
        'canonical_entity_name',
        'canonical_category',
        'canonical_amount',
        'canonical_quantity',
        'canonical_date',
        'canonical_status',
        'canonical_person',
        'canonical_contact',
        'raw_values',
        'source_values',
        'mapped_attributes',
        'semantic_projections',
        'field_sensitivities',
        'custom_attributes',
        'content_fingerprint',
        'is_public',
    ];

    protected $casts = [
        'row_index'             => 'integer',
        'canonical_amount'      => 'decimal:4',
        'canonical_quantity'    => 'decimal:4',
        'canonical_date'        => 'datetime',
        'raw_values'            => 'array',
        'source_values'         => 'array',
        'mapped_attributes'     => 'array',
        'semantic_projections'  => 'array',
        'field_sensitivities'   => 'array',
        'custom_attributes'     => 'array',
        'is_public'             => 'boolean',
    ];

    /**
     * Defense-in-depth: Never expose raw source values or unprojected cells in direct model serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'raw_values',
        'source_values',
    ];

    protected static function booted(): void
    {
        static::creating(function (StructuredRecord $rec) {
            if ($rec->raw_values === null) {
                $rec->raw_values = $rec->source_values ?? [];
            }
        });
    }

    /**
     * Backward-compatible accessor: falls back to raw_values if source_values is null.
     *
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function getSourceValuesAttribute($value): array
    {
        if ($value === null) {
            return $this->raw_values ?? [];
        }
        return is_string($value) ? (json_decode($value, true) ?: []) : (array) $value;
    }

    /**
     * Backward-compatible accessor: constructs baseline projections from legacy canonical columns if null.
     *
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function getSemanticProjectionsAttribute($value): array
    {
        if ($value === null) {
            $projections = [];
            if ($this->canonical_amount !== null) {
                $projections['amount'] = (float) $this->canonical_amount;
            }
            if ($this->canonical_quantity !== null) {
                $projections['quantity'] = (float) $this->canonical_quantity;
            }
            if ($this->canonical_date !== null) {
                $projections['date'] = $this->canonical_date instanceof \DateTimeInterface
                    ? $this->canonical_date->format('Y-m-d H:i:s')
                    : (string) $this->canonical_date;
            }
            if ($this->canonical_person !== null) {
                $projections['person'] = $this->canonical_person;
            }
            if ($this->canonical_entity_name !== null) {
                $projections['entity_name'] = $this->canonical_entity_name;
            }
            return $projections;
        }
        return is_string($value) ? (json_decode($value, true) ?: []) : (array) $value;
    }

    /**
     * Backward-compatible accessor: returns empty array if null.
     *
     * @param mixed $value
     * @return array<string, string>
     */
    public function getFieldSensitivitiesAttribute($value): array
    {
        if ($value === null) {
            return [];
        }
        return is_string($value) ? (json_decode($value, true) ?: []) : (array) $value;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetImportJob::class, 'import_job_id');
    }
}

