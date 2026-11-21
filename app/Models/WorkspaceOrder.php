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
        'courier_provider',
        'courier_consignment_id',
        'courier_tracking_code',
        'courier_status',
        'courier_response',
        'courier_dispatched_at',
        'customer_notes',
        'admin_notes',
        'captured_at',
    ];

    protected $casts = [
        'order_amount'          => 'decimal:2',
        'delivery_fee'          => 'decimal:2',
        'courier_response'      => 'array',
        'courier_dispatched_at' => 'datetime',
        'captured_at'           => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Check if this order has already been booked with a courier.
     */
    public function isDispatchedToCourier(): bool
    {
        return ! empty($this->courier_tracking_code) || ! empty($this->courier_consignment_id);
    }

    /**
     * Get 11-digit clean BD phone number e.g. 01700000000.
     */
    public function getFormatted11DigitPhone(): string
    {
        if (empty($this->customer_phone)) {
            return '01700000000';
        }

        $cleaned = preg_replace('/[^\d]/', '', $this->customer_phone);

        if (str_starts_with($cleaned, '8801') && strlen($cleaned) >= 13) {
            return substr($cleaned, 2, 11);
        }

        if (str_starts_with($cleaned, '01') && strlen($cleaned) >= 11) {
            return substr($cleaned, 0, 11);
        }

        if (str_starts_with($cleaned, '1') && strlen($cleaned) === 10) {
            return '0' . $cleaned;
        }

        return strlen($cleaned) === 11 ? $cleaned : substr($cleaned, 0, 11);
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

    public function getCourierTrackingUrl(): ?string
    {
        $code = $this->courier_tracking_code ?: $this->courier_consignment_id;
        if (! $code) {
            return null;
        }

        return match ($this->courier_provider) {
            'steadfast' => "https://steadfast.com.bd/t/{$code}",
            'pathao'    => "https://merchant.pathao.com/tracking?consignment_id={$code}",
            'redx'      => "https://redx.com.bd/track-parcel/?trackingId={$code}",
            default     => null,
        };
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

    public function getCourierBadgeClass(): string
    {
        return match ($this->courier_provider) {
            'steadfast' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30',
            'pathao'    => 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30',
            'redx'      => 'bg-red-500/15 text-red-600 dark:text-red-400 border border-red-500/30',
            default     => 'bg-brand-500/15 text-brand-500 border border-brand-500/30',
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
