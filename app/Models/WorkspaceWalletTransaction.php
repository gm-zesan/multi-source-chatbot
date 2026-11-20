<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceWalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'workspace_wallet_transactions';

    protected $fillable = [
        'wallet_id',
        'workspace_id',
        'type',
        'category',
        'amount',
        'balance_after',
        'reference_id',
        'description',
        'meta',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta'          => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WorkspaceWallet::class, 'wallet_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
