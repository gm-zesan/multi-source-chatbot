<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents policy cue phrases and their target document types for reranking.
 * Replaces POLICY_INTENT_MAP in retrieval_engine.py.
 *
 * Used in B3 Direct Commerce Policy Intent Alignment:
 * When user query contains a cue_phrase, retrieval reranker boosts documents
 * of matching target_doc_type over other document types (on close score delta).
 *
 * workspace_id = 0 means GLOBAL.
 */
class PolicyIntentMapping extends Model
{
    use HasFactory;

    protected $table = 'policy_intent_mappings';

    public const GLOBAL_WORKSPACE_ID = 0;

    protected $fillable = [
        'workspace_id',
        'policy_name',
        'cue_phrase',
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

    public function scopeForPolicy(Builder $q, string $policyName): Builder
    {
        return $q->where('policy_name', $policyName);
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
