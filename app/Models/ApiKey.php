<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $table = 'api_keys';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'key_hash',
        'key_prefix',
        'abilities',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'abilities'    => 'array',
        'is_active'    => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    /**
     * Generate a new API key pair for a workspace.
     *
     * @param Workspace $workspace
     * @param User|null $user
     * @param string $name
     * @param array<string> $abilities
     * @param Carbon|null $expiresAt
     * @return array{model: self, plain_token: string}
     */
    public static function generate(
        Workspace $workspace,
        ?User $user,
        string $name,
        array $abilities = ['*'],
        ?Carbon $expiresAt = null
    ): array {
        $plainToken = 'ea_live_' . Str::random(40);
        $keyHash = hash('sha256', $plainToken);
        $keyPrefix = substr($plainToken, 0, 16) . '...';

        $model = self::create([
            'workspace_id' => $workspace->id,
            'user_id'      => $user?->id,
            'name'         => trim($name),
            'key_hash'     => $keyHash,
            'key_prefix'   => $keyPrefix,
            'abilities'    => $abilities,
            'expires_at'   => $expiresAt,
            'is_active'    => true,
        ]);

        return [
            'model'       => $model,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Find an active API key by plain token.
     */
    public static function findByToken(string $plainToken): ?self
    {
        $hash = hash('sha256', trim($plainToken));

        return self::with('workspace')
            ->where('key_hash', $hash)
            ->where('is_active', true)
            ->first();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return (bool) $this->workspace?->is_active;
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? ['*'];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function recordUsage(?string $ip = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);
    }
}
