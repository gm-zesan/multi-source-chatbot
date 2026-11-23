<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'external_message_id',
        'direction',
        'type',
        'body',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Determine if the message originated from the end-user / customer.
     */
    public function getIsFromUserAttribute(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Determine if this outbound message was blocked by Meta Zero-Charge Protection Guard.
     */
    public function isBlocked(): bool
    {
        if ($this->status === 'blocked') {
            return true;
        }

        if (! empty($this->metadata['blocked'])) {
            return true;
        }

        $rawStatus = (string) ($this->metadata['status'] ?? '');
        return str_starts_with($rawStatus, 'blocked_');
    }

    /**
     * Get a human-readable explanation of why the message delivery was blocked.
     */
    public function getBlockedReason(): ?string
    {
        if (! $this->isBlocked()) {
            return null;
        }

        $rawStatus = (string) ($this->metadata['status'] ?? '');
        $error = (string) ($this->metadata['error'] ?? $this->metadata['reason'] ?? '');

        if ($rawStatus === 'blocked_quota_exhausted' || str_contains($error, 'কোটা শেষ') || str_contains($error, 'quota_exhausted')) {
            return 'WhatsApp ফ্রি ১,০০০ মেসেজের কোটা শেষ হওয়ায় অতিরিক্ত মেটা চার্জ রোধ করতে বার্তাটি পাঠানো হয়নি (মেটার জিরো-চার্জ সুরক্ষা সক্রিয়)।';
        }

        if ($rawStatus === 'blocked_messenger_24h_window' || str_contains($error, '24-hour')) {
            return 'মেসেঞ্জারের ২৪ ঘণ্টার ফ্রি সার্ভিস উইন্ডো পার হওয়ায় মেটার পলিসি মেনে বার্তাটি স্থগিত রাখা হয়েছে।';
        }

        if ($rawStatus === 'blocked_marketing_fee') {
            return 'মার্কেটিং মেসেজে অতিরিক্ত মেটা ফি থাকায় জিরো-চার্জ ফ্রি মোডে বার্তাটি স্থগিত রাখা হয়েছে।';
        }

        return ! empty($error) ? $error : 'মেটার জিরো-চার্জ সুরক্ষা পলিসি অনুযায়ী বার্তাটি স্থগিত রাখা হয়েছে।';
    }
}
