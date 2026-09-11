<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsSalesperson extends Model
{
    use HasFactory;

    protected $table = 'analytics_salespersons';

    protected $fillable = [
        'workspace_id',
        'name',
        'phone',
        'email',
        'employee_code',
        'target_amount',
        'is_active',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Orders sold by this salesperson (Seller role)
     */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(AnalyticsOrder::class, 'salesperson_id');
    }

    /**
     * Payments collected by this salesperson (Collector role)
     */
    public function collectedPayments(): HasMany
    {
        return $this->hasMany(AnalyticsPayment::class, 'salesperson_id');
    }

    /**
     * Dues assigned to this salesperson for collection (Due-Assignee role)
     */
    public function assignedDues(): HasMany
    {
        return $this->hasMany(AnalyticsDueAssignment::class, 'assigned_salesperson_id');
    }
}
