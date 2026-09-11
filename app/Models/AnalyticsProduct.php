<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsProduct extends Model
{
    use HasFactory;

    protected $table = 'analytics_products';

    protected $fillable = [
        'workspace_id',
        'name',
        'category',
        'unit_price',
        'cost_price',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(AnalyticsOrderItem::class, 'product_id');
    }
}
