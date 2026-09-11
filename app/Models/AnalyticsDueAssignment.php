<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsDueAssignment extends Model
{
    use HasFactory;

    protected $table = 'analytics_due_assignments';

    protected $fillable = [
        'workspace_id',
        'order_id',
        'customer_id',
        'assigned_salesperson_id',
        'status',
        'due_date',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
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
     * Salesperson assigned to recover this due
     */
    public function assignedCollector(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSalesperson::class, 'assigned_salesperson_id');
    }

    /**
     * Dynamically derived due amount from the related order
     */
    public function getDerivedDueAmountAttribute(): float
    {
        return $this->order ? $this->order->outstanding_due : 0.0;
    }
}
