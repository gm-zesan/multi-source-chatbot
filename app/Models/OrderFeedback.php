<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFeedback extends Model
{
    use HasFactory;

    protected $table = 'order_feedbacks';

    protected $fillable = [
        'workspace_id',
        'workspace_order_id',
        'conversation_id',
        'customer_name',
        'customer_phone',
        'channel',
        'rating',
        'review_text',
        'status',
        'feedback_requested_at',
        'feedback_received_at',
        'metadata',
    ];

    protected $casts = [
        'rating'                => 'integer',
        'metadata'              => 'array',
        'feedback_requested_at' => 'datetime',
        'feedback_received_at'  => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WorkspaceOrder::class, 'workspace_order_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
