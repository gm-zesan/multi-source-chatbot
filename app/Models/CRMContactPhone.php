<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CRMContactPhone extends Model
{
    protected $table = 'c_r_m_contact_phones';

    protected $fillable = [
        'c_r_m_contact_id',
        'phone',
        'is_primary',
    ];

    public function crmContact()
    {
        return $this->belongsTo(CRMContact::class, 'c_r_m_contact_id');
    }
}
