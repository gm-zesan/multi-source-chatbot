<?php

namespace App\Models;

use Database\Factories\FAQFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class FAQ extends Model
{
    /** @use HasFactory<FAQFactory> */
    use HasFactory, HasUuids, Searchable, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'faqs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'category_id',
        'question',
        'answer',
        'searchable_text',
        'embedding_version',
        'is_active',
        'priority',
        'hit_count',
        'last_used_at',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'priority'     => 'integer',
            'hit_count'    => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FAQCategory::class, 'category_id');
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
     * Increment the hit count and update last_used_at.
     */
    public function recordUsage(): void
    {
        $this->increment('hit_count');
        $this->update(['last_used_at' => now()]);
    }

    // ─── Scout / Typesense ─────────────────────────────────────────────

    /**
     * Get the index name for Typesense.
     */
    public function searchableAs(): string
    {
        return 'faqs';
    }

    /**
     * Get the indexable data array for the model.
     *
     * Uses the preprocessed searchable_text if available (set by FAQIndexer),
     * otherwise falls back to raw question + answer.
     */
    public function toSearchableArray(): array
    {
        return [
            'id'              => $this->id,
            'workspace_id'     => $this->workspace_id,
            'question'         => $this->question,
            'answer'           => $this->answer,
            'searchable_text'  => $this->searchable_text ?? $this->getSearchableText(),
            'priority'         => $this->priority,
            'embedding'        => [],
            'is_active'        => $this->is_active,
            'created_at'       => $this->created_at?->timestamp ?? time(),
        ];
    }

    /**
     * Build the combined searchable text from raw question and answer.
     */
    public function getSearchableText(): string
    {
        return strip_tags(trim($this->question . ' ' . $this->answer));
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active && ! $this->trashed();
    }
}
