<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceWallet extends Model
{
    use HasFactory;

    protected $table = 'workspace_wallets';

    protected $fillable = [
        'workspace_id',
        'balance',
        'currency',
        'rate_per_message',
        'is_frozen',
        'low_balance_threshold',
    ];

    protected $casts = [
        'balance'               => 'decimal:2',
        'rate_per_message'      => 'decimal:2',
        'is_frozen'             => 'boolean',
        'low_balance_threshold' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WorkspaceWalletTransaction::class, 'wallet_id');
    }

    /**
     * Check if the wallet has sufficient balance to send a message.
     */
    public function canAffordMessage(?float $rate = null): bool
    {
        if ($this->is_frozen) {
            return false;
        }

        $cost = $rate ?? (float) $this->rate_per_message;

        return (float) $this->balance >= $cost;
    }

    /**
     * Estimated remaining messages based on current balance.
     */
    public function estimatedRemainingMessages(): int
    {
        $rate = (float) $this->rate_per_message;
        if ($rate <= 0.0) {
            return 999999;
        }

        return max(0, (int) floor((float) $this->balance / $rate));
    }

    /**
     * Whether the wallet balance is low.
     */
    public function isLowBalance(): bool
    {
        return (float) $this->balance <= (float) $this->low_balance_threshold;
    }
}
