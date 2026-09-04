<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CRMContactIdentity extends Model
{
    protected $table = 'crm_contact_identities';

    protected $fillable = [
        'crm_contact_id',
        'channel_id',
        'external_user_id',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_primary' => 'boolean',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CRMContact::class, 'crm_contact_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
