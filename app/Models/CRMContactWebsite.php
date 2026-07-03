<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CRMContactWebsite extends Model
{
    protected $table = 'crm_contact_websites';

    protected $fillable = [
        'crm_contact_id',
        'website',
        'is_primary',
    ];

    public function crmContact()
    {
        return $this->belongsTo(CRMContact::class, 'crm_contact_id');
    }
}
