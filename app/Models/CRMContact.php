<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CRMContact extends Model
{
    protected $table = 'c_r_m_contacts';

    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'name',
        'avatar',
        'source',
        'external_user_id',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function emails()
    {
        return $this->hasMany(CRMContactEmail::class);
    }

    public function phones()
    {
        return $this->hasMany(CRMContactPhone::class);
    }

    public function websites()
    {
        return $this->hasMany(CRMContactWebsite::class);
    }
}
