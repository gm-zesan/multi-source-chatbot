<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents action intent trigger keywords and reranking signals.
 * Replaces ACTION_INTENT_MAP in retrieval_engine.py.
 *
 * INVARIANT: execution_enabled is FUTURE-ONLY metadata.
 * Python runtime MUST treat execution_enabled as FALSE regardless of DB value.
 * Current release NEVER executes tools based on this flag.
 * This invariant is enforced in Python by OMITTING execution_enabled from snapshot payload.
 *
 * workspace_id = 0 means GLOBAL.
 */
class ActionIntentMapping extends Model
{
    use HasFactory;

    protected $table = 'action_intent_mappings';

    public const GLOBAL_WORKSPACE_ID = 0;

    protected $fillable = [
        'workspace_id',
        'intent_name',
        'action_keyword',
        'target_phrase',
        'penalty_phrase',
        'execution_enabled',
        'execution_handler',
        'status',
        'version',
        'activated_by',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'workspace_id'     => 'integer',
            'execution_enabled' => 'boolean',
            'version'          => 'integer',
            'activated_at'     => 'datetime',
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

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isGlobal(): bool
    {
        return $this->workspace_id === self::GLOBAL_WORKSPACE_ID;
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Application-level validation: execution_enabled=TRUE requires execution_handler.
     * DB does not enforce this; FormRequest must validate.
     */
    public function validateExecutionInvariant(): bool
    {
        if ($this->execution_enabled && empty($this->execution_handler)) {
            return false;
        }
        return true;
    }
}
