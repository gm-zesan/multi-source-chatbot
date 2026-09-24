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
}
