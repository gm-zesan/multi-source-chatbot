<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalyticsOrder extends Model
{
    use HasFactory;

    protected $table = 'analytics_orders';

    protected $fillable = [
        'workspace_id',
        'customer_id',
        'salesperson_id',
        'order_number',
        'total_amount',
        'discount',
        'net_amount',
        'status',
        'order_date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount'     => 'decimal:2',
        'net_amount'   => 'decimal:2',
        'order_date'   => 'date',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(AnalyticsCustomer::class, 'customer_id');
    }

    /**
     * Seller who created the order
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSalesperson::class, 'salesperson_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AnalyticsOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AnalyticsPayment::class, 'order_id');
    }

    public function dueAssignment(): HasOne
    {
        return $this->hasOne(AnalyticsDueAssignment::class, 'order_id');
    }

    /**
     * Dynamic calculation: Due = net_amount - total collected payments
     */
    public function getOutstandingDueAttribute(): float
    {
        $paid = (float) $this->payments()->sum('amount');
        return max(0.0, (float) $this->net_amount - $paid);
    }
}
