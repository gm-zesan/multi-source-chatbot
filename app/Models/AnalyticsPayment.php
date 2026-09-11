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
        'payment_method',
        'transaction_ref',
        'collected_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'collected_at' => 'datetime',
    ];

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
