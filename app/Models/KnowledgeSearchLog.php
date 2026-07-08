<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeSearchLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'knowledge_search_logs';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'message_id',
        'customer_query',
        'matched_faq_id',
        'keyword_score',
        'semantic_score',
        'final_score',
        'response_time_ms',
        'answer_source',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keyword_score'    => 'decimal:4',
            'semantic_score'   => 'decimal:4',
            'final_score'      => 'decimal:4',
            'response_time_ms' => 'integer',
            'created_at'       => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function matchedFaq(): BelongsTo
    {
        return $this->belongsTo(FAQ::class, 'matched_faq_id');
    }
}
