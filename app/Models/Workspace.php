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

    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WorkspaceWallet::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WorkspaceWalletTransaction::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(WorkspaceOrder::class);
    }

    public function getAiBotName(): string
    {
        return $this->settings['ai_bot_name'] ?? 'AI Assistant';
    }

    public function getAiPersonaTone(): string
    {
        return $this->settings['ai_persona_tone'] ?? 'friendly_banglish';
    }

    public function getCustomSalesPrompt(): ?string
    {
        return $this->settings['custom_sales_prompt'] ?? null;
    }

    public function getFallbackMessage(): string
    {
        return $this->settings['fallback_message'] ?? 'দুঃখিত, এই বিষয়টি সম্পর্কে আমার কাছে সঠিক তথ্য নেই। আমাদের সাপোর্ট টিম দ্রুত আপনার সাথে যোগাযোগ করবে।';
    }

    public function isOrderAutoCaptureEnabled(): bool
    {
        return (bool) ($this->settings['order_auto_capture_enabled'] ?? true);
    }

    public function getAiToneInstructions(): string
    {
        $tone = $this->getAiPersonaTone();
        return match ($tone) {
            'formal_bengali' => "Tone & Persona: Formal, respectful, and polite Standard Bengali (মানসম্মত প্রমিত বাংলা). Use 'সম্মানিত গ্রাহক' or 'আপনি'. Be courteous and professional.",
            'professional_english' => "Tone & Persona: Professional, polished, and friendly English. Clear, concise, and helpful.",
            'balanced' => "Tone & Persona: Balanced, polite, and adaptable. Match customer language seamlessly (Bangla, English, or Banglish).",
            default => "Tone & Persona: Friendly, warm, and natural conversational e-commerce style (জনপ্রিয় ফ্রেন্ডলি বাংলা ও বাংলিশ). Welcoming and helpful (e.g. 'জি ভাইয়া/আপু', 'আপনার জন্য কীভাবে সাহায্য করতে পারি?').",
        };
    }
}
