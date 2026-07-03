<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CRMContactWebsite extends Model
{
    protected $table = 'c_r_m_contact_websites';

    protected $fillable = [
        'c_r_m_contact_id',
        'website',
        'is_primary',
    ];

    public function crmContact()
    {
        return $this->belongsTo(CRMContact::class, 'c_r_m_contact_id');
    }
}
