<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'order_number',
        'channel',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'product_summary',
        'order_amount',
        'delivery_fee',
        'status',
        'customer_notes',
        'admin_notes',
        'captured_at',
    ];

    protected $casts = [
        'order_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'captured_at'  => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function getCleanPhone(): ?string
    {
        if (empty($this->customer_phone)) {
            return null;
        }

        $cleaned = preg_replace('/[^\d]/', '', $this->customer_phone);
        if (str_starts_with($cleaned, '8801')) {
            return $cleaned;
        }
        if (str_starts_with($cleaned, '01')) {
            return '88' . $cleaned;
        }

        return $cleaned;
    }

    public function getWhatsAppUrl(): ?string
    {
        $phone = $this->getCleanPhone();
        if (! $phone) {
            return null;
        }

        $msg = urlencode("Hello {$this->customer_name}, regarding your order {$this->order_number} from {$this->workspace?->name}:");
        return "https://wa.me/{$phone}?text={$msg}";
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'pending'   => 'app-badge-warning',
            'confirmed' => 'app-badge-primary',
            'shipped'   => 'app-badge-info',
            'delivered' => 'app-badge-success',
            'cancelled' => 'app-badge-danger',
            default     => 'app-badge-secondary',
        };
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }
}
