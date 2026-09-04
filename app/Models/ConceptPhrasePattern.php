<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a canonical commerce concept and its associated phrases.
 * Replaces canonical_mapper.CONCEPT_PATTERNS and MULTI_ENTITY_CUES in Python.
 *
 * Three pattern_types:
 *   CONCEPT_META   — one per (workspace, concept_key); defines target_doc_type; phrase IS NULL
 *   POSITIVE       — trigger phrase; target_doc_type IS NULL
 *   NEGATIVE_GUARD — blocking phrase; target_doc_type IS NULL
 *
 * MULTI_ENTITY_DETECTION is stored here as concept_key='MULTI_ENTITY_DETECTION'
 * with CONCEPT_META (target_doc_type=NULL) and POSITIVE phrase rows.
 *
 * workspace_id = 0 means GLOBAL.
 */
class ConceptPhrasePattern extends Model
{
    use HasFactory;

    protected $table = 'concept_phrase_patterns';

    public const GLOBAL_WORKSPACE_ID = 0;

    public const TYPE_META           = 'CONCEPT_META';
    public const TYPE_POSITIVE       = 'POSITIVE';
    public const TYPE_NEGATIVE_GUARD = 'NEGATIVE_GUARD';

    /** Concept key used for multi-entity detection (no target_doc_type) */
    public const CONCEPT_MULTI_ENTITY = 'MULTI_ENTITY_DETECTION';

    protected $fillable = [
        'workspace_id',
        'concept_key',
        'pattern_type',
        'phrase',
        'target_doc_type',
        'status',
        'version',
        'activated_by',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'workspace_id' => 'integer',
            'version'      => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'ACTIVE');
    }

    public function scopeGlobal(Builder $q): Builder
    {
        return $q->where('workspace_id', self::GLOBAL_WORKSPACE_ID);
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function scopeMeta(Builder $q): Builder
    {
        return $q->where('pattern_type', self::TYPE_META);
    }

    public function scopePositive(Builder $q): Builder
    {
        return $q->where('pattern_type', self::TYPE_POSITIVE);
    }

    public function scopeNegativeGuard(Builder $q): Builder
    {
        return $q->where('pattern_type', self::TYPE_NEGATIVE_GUARD);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isMeta(): bool
    {
        return $this->pattern_type === self::TYPE_META;
    }

    public function isPositive(): bool
    {
        return $this->pattern_type === self::TYPE_POSITIVE;
    }

    public function isNegativeGuard(): bool
    {
        return $this->pattern_type === self::TYPE_NEGATIVE_GUARD;
    }

    public function isGlobal(): bool
    {
        return $this->workspace_id === self::GLOBAL_WORKSPACE_ID;
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Application-level validation invariants (enforced in FormRequest, not DB).
     *
     * CONCEPT_META:   phrase must be null, target_doc_type must be present
     * POSITIVE:       phrase must be present, target_doc_type must be null
     * NEGATIVE_GUARD: phrase must be present, target_doc_type must be null
     */
    public function validateInvariants(): bool
    {
        return match ($this->pattern_type) {
            self::TYPE_META           => is_null($this->phrase) && !is_null($this->target_doc_type),
            self::TYPE_POSITIVE       => !is_null($this->phrase) && is_null($this->target_doc_type),
            self::TYPE_NEGATIVE_GUARD => !is_null($this->phrase) && is_null($this->target_doc_type),
            default                   => false,
        };
    }
}
