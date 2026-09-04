<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a single phrase → expansion mapping for deterministic query expansion.
 * Replaces LOCAL_DOMAIN_LEXICON in retrieval_engine.py.
 *
 * workspace_id = 0 means GLOBAL (applies to all workspaces).
 * workspace_id > 0 means workspace-specific override/addition.
 * Workspace entries win over global on identical (concept_key, pattern) pairs.
 */
class LexiconDomainEntry extends Model
{
    use HasFactory;

    protected $table = 'lexicon_domain_entries';

    /** Sentinel value: workspace_id=0 means global scope */
    public const GLOBAL_WORKSPACE_ID = 0;

    protected $fillable = [
        'workspace_id',
        'concept_key',
        'pattern',
        'expansion',
        'language',
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

    public function scopeForConcept(Builder $q, string $conceptKey): Builder
    {
        return $q->where('concept_key', $conceptKey);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isGlobal(): bool
    {
        return $this->workspace_id === self::GLOBAL_WORKSPACE_ID;
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
