<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CRMContactEmail extends Model
{
    protected $table = 'c_r_m_contact_emails';

    protected $fillable = [
        'c_r_m_contact_id',
        'email',
        'is_primary',
        'is_verified',
    ];

    public function crmContact()
    {
        return $this->belongsTo(CRMContact::class, 'c_r_m_contact_id');
    }
}
