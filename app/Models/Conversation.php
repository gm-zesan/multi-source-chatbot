<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'channel_account_id',
        'external_user_id',
        'customer_name',
        'customer_avatar',
        'last_message',
        'last_message_at',
        'last_direction',
        'unread_count',
        'status',
        'assigned_to_user_id',
        'priority',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'metadata'        => 'array',
        'last_message_at' => 'datetime',
        'resolved_at'     => 'datetime',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class, 'channel_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(WorkspaceOrder::class);
    }

    public function isAiPaused(): bool
    {
        $paused = (bool) ($this->metadata['ai_paused'] ?? false);
        if (! $paused) {
            return false;
        }

        $until = $this->metadata['ai_paused_until'] ?? null;
        if ($until && \Carbon\Carbon::parse($until)->isPast()) {
            $this->resumeAi();
            return false;
        }

        return true;
    }

    public function pauseAi(?\Carbon\Carbon $until = null): void
    {
        $meta = $this->metadata ?? [];
        $meta['ai_paused'] = true;
        $meta['ai_paused_until'] = $until?->toIso8601String();
        $meta['ai_paused_at'] = now()->toIso8601String();
        $this->metadata = $meta;
        $this->save();
    }

    public function resumeAi(): void
    {
        $meta = $this->metadata ?? [];
        $meta['ai_paused'] = false;
        unset($meta['ai_paused_until']);
        $meta['ai_resumed_at'] = now()->toIso8601String();
        $this->metadata = $meta;
        $this->save();
    }

    public function channelAccount()
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function crmContact()
    {
        return $this->hasOne(CRMContact::class);
    }
}
