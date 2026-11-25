<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsPayment extends Model
{
    use HasFactory;

    protected $table = 'analytics_payments';

    protected $fillable = [
        'workspace_id',
        'order_id',
        'customer_id',
        'salesperson_id',
        'amount',
        'transaction_type',
        'provider',
        'payment_method',
        'transaction_ref',
        'collected_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'collected_at' => 'datetime',
    ];

    /**
     * Financial Ledger Invariant:
     * Payment records are strictly immutable once created.
     * Direct mutations and deletions are blocked at the model layer and DB trigger layer.
     * Provider must never be null to guarantee composite index uniqueness.
     * All adjustments, refunds, and chargebacks must be recorded as compensating ledger entries.
     */
    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (empty($payment->provider)) {
                $payment->provider = 'manual';
            }
            if (empty($payment->transaction_type)) {
                $payment->transaction_type = 'courier_settlement';
            }
        });

        static::updating(function (self $payment): void {
            throw new \LogicException("AnalyticsPayment ledger record #{$payment->id} is strictly immutable and cannot be updated. Use a compensating transaction.");
        });

        static::deleting(function (self $payment): void {
            throw new \LogicException("AnalyticsPayment ledger record #{$payment->id} is strictly immutable and cannot be deleted.");
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AnalyticsOrder::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(AnalyticsCustomer::class, 'customer_id');
    }

    /**
     * Collector who collected this payment
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSalesperson::class, 'salesperson_id');
    }
}
