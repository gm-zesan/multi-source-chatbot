<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CRMContactEmail extends Model
{
    protected $table = 'crm_contact_emails';

    protected $fillable = [
        'crm_contact_id',
        'email',
        'is_primary',
        'is_verified',
    ];

    public function crmContact()
    {
        return $this->belongsTo(CRMContact::class, 'crm_contact_id');
    }
}
