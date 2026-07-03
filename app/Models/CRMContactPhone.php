<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CRMContactPhone extends Model
{
    protected $table = 'crm_contact_phones';

    protected $fillable = [
        'crm_contact_id',
        'phone',
        'is_primary',
    ];

    public function crmContact()
    {
        return $this->belongsTo(CRMContact::class, 'crm_contact_id');
    }
}
