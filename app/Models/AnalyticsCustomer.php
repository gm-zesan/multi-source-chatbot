<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsCustomer extends Model
{
    use HasFactory;

    protected $table = 'analytics_customers';

    protected $fillable = [
        'workspace_id',
        'name',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(AnalyticsOrder::class, 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AnalyticsPayment::class, 'customer_id');
    }

    public function dueAssignments(): HasMany
    {
        return $this->hasMany(AnalyticsDueAssignment::class, 'customer_id');
    }
}
