<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsOrderItem extends Model
{
    use HasFactory;

    protected $table = 'analytics_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'quantity'   => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(AnalyticsOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AnalyticsProduct::class, 'product_id');
    }
}
