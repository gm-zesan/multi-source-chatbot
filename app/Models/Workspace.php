<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'currency',
        'support_phone',
        'support_email',
        'business_address',
        'operating_hours',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'operating_hours'  => 'array',
        'settings'         => 'array',
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

    public function faqs(): HasMany
    {
        return $this->hasMany(FAQ::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(WorkspaceSubscription::class);
    }

    public function spreadsheetImportJobs(): HasMany
    {
        return $this->hasMany(SpreadsheetImportJob::class);
    }

    public function structuredRecords(): HasMany
    {
        return $this->hasMany(StructuredRecord::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }
}
