<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * SuperAdmin Governance Session.
 *
 * Governs and cryptographically audits cross-tenant data access by SuperAdmins.
 * Cross-tenant access is prohibited unless an active, unexpired governance session exists.
 */
class SuperAdminGovernanceSession extends Model
{
    use HasFactory;

    protected $table = 'super_admin_governance_sessions';

    public const DEFAULT_DURATION_MINUTES = 30;
    public const MAX_DURATION_MINUTES = 120;

    protected $fillable = [
        'actor_id',
        'user_id',
        'target_workspace_id',
        'justification',
        'reason',
        'ticket_ref',
        'ticket_reference',
        'read_only_scope',
        'started_at',
        'expires_at',
        'revoked_at',
        'audit_log',
    ];

    protected $casts = [
        'actor_id'            => 'integer',
        'user_id'             => 'integer',
        'target_workspace_id' => 'integer',
        'read_only_scope'     => 'boolean',
        'started_at'          => 'datetime',
        'expires_at'          => 'datetime',
        'revoked_at'          => 'datetime',
        'audit_log'           => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SuperAdminGovernanceSession $session) {
            if ($session->actor_id === null && $session->user_id !== null) {
                $session->actor_id = $session->user_id;
            }
            if ($session->user_id === null && $session->actor_id !== null) {
                $session->user_id = $session->actor_id;
            }
            if ($session->justification === null && $session->reason !== null) {
                $session->justification = $session->reason;
            }
            if ($session->reason === null && $session->justification !== null) {
                $session->reason = $session->justification;
            }
            if ($session->ticket_ref === null && $session->ticket_reference !== null) {
                $session->ticket_ref = $session->ticket_reference;
            }
            if ($session->ticket_reference === null && $session->ticket_ref !== null) {
                $session->ticket_reference = $session->ticket_ref;
            }
        });
    }

    /**
     * Create an active governance session for a SuperAdmin.
     */
    public static function createSession(
        User $user,
        int $targetWorkspaceId,
        string $reason,
        ?string $ticketReference = null,
        int $durationMinutes = self::DEFAULT_DURATION_MINUTES,
        bool $readOnlyScope = true,
    ): self {
        if (! $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            throw new InvalidArgumentException('Only SuperAdmins may establish a cross-tenant governance session.');
        }

        $trimmedReason = trim($reason);
        if (strlen($trimmedReason) < 10) {
            throw new InvalidArgumentException('A detailed justification (minimum 10 characters) is required for governance session creation.');
        }

        $clampedDuration = max(1, min($durationMinutes, self::MAX_DURATION_MINUTES));
        $now = Carbon::now();

        return self::create([
            'actor_id'            => $user->id,
            'user_id'             => $user->id,
            'target_workspace_id' => $targetWorkspaceId,
            'justification'       => $trimmedReason,
            'reason'              => $trimmedReason,
            'ticket_ref'          => $ticketReference ? trim($ticketReference) : null,
            'ticket_reference'    => $ticketReference ? trim($ticketReference) : null,
            'read_only_scope'     => $readOnlyScope,
            'started_at'          => $now,
            'expires_at'          => $now->copy()->addMinutes($clampedDuration),
            'revoked_at'          => null,
            'audit_log'           => [
                [
                    'action'    => 'session_created',
                    'timestamp' => $now->toIso8601String(),
                    'reason'    => $trimmedReason,
                    'ticket'    => $ticketReference,
                ],
            ],
        ]);
    }

    /**
     * Scope to find active sessions for a specific user and workspace.
     */
    public function scopeActiveFor(Builder $query, int $userId, int $workspaceId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('actor_id', $userId)->orWhere('user_id', $userId);
        })
            ->where('target_workspace_id', $workspaceId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now());
    }

    /**
     * Determine if this session is currently active and unexpired.
     */
    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at instanceof Carbon
            ? $this->expires_at->isFuture()
            : Carbon::parse($this->expires_at)->isFuture();
    }

    /**
     * Record an audited access event inside this governance session.
     *
     * @param array<string, mixed> $context
     */
    public function recordAction(string $action, array $context = []): void
    {
        $log = $this->audit_log ?? [];
        $log[] = [
            'action'    => $action,
            'timestamp' => Carbon::now()->toIso8601String(),
            'context'   => $context,
        ];

        $this->update(['audit_log' => $log]);
    }

    /**
     * Explicitly terminate/revoke this governance session.
     */
    public function revoke(string $reason = 'Manual revocation'): void
    {
        $log = $this->audit_log ?? [];
        $log[] = [
            'action'    => 'session_revoked',
            'timestamp' => Carbon::now()->toIso8601String(),
            'reason'    => $reason,
        ];

        $this->update([
            'revoked_at' => Carbon::now(),
            'audit_log'  => $log,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'target_workspace_id');
    }
}
