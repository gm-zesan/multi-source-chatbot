<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function channelAccounts(): HasMany
    {
        return $this->hasMany(ChannelAccount::class);
    }

    public function crmContacts(): HasMany
    {
        return $this->hasMany(CRMContact::class);
    }

    public function knowledgeDocuments(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function spreadsheetImportJobs(): HasMany
    {
        return $this->hasMany(SpreadsheetImportJob::class);
    }

    public function structuredRecords(): HasMany
    {
        return $this->hasMany(StructuredRecord::class);
    }
}
