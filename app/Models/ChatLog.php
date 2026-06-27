<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatLog extends Model
{
    protected $fillable = [
        'query',
        'intent',
        'routing_confidence',
        'routing_source',
        'routing_method',
    ];

    protected $casts = [
        'routing_confidence' => 'float',
    ];
}
