<?php

namespace App\Models;

use Database\Factories\FAQFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FAQ extends Model
{
    /** @use HasFactory<FAQFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'faqs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'category_id',
        'document_type',
        'question',
        'answer',
        'searchable_text',
        'embedding_version',
        'lifecycle_status',
        'sync_error',
        'is_active',
        'priority',
        'hit_count',
        'last_used_at',
        'created_by',
        'updated_by',
    ];

    /**
     * Whether this document represents an official policy or legal document.
     */
    public function isPolicy(): bool
    {
        return in_array($this->document_type, [
            'terms', 'privacy_policy', 'refund_policy', 'return_policy',
            'exchange_policy', 'delivery_policy', 'payment_policy',
            'cancellation_policy', 'warranty_policy', 'customer_support',
            'social_media_policy',
        ], true);
    }

    /**
     * Human-readable document type badge label.
     */
    public function documentTypeLabel(): string
    {
        return match ($this->document_type) {
            'about_us'            => 'About Us',
            'terms'               => 'Terms & Conditions',
            'privacy_policy'      => 'Privacy Policy',
            'refund_policy'       => 'Refund Policy',
            'return_policy'       => 'Return Policy',
            'exchange_policy'     => 'Exchange Policy',
            'delivery_policy'     => 'Delivery Policy',
            'payment_policy'      => 'Payment Policy',
            'cancellation_policy' => 'Cancellation Policy',
            'warranty_policy'     => 'Warranty Policy',
            'contact'             => 'Contact Information',
            'customer_support'    => 'Customer Support Policy',
            'social_media_policy' => 'F-Commerce & Social Policy',
            default               => 'FAQ',
        };
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'lifecycle_status' => \App\Enums\FaqLifecycleStatus::class,
            'priority'         => 'integer',
            'hit_count'        => 'integer',
            'last_used_at'     => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FAQCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lexicon(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FaqLexicon::class, 'faq_id');
    }

    /**
     * Increment the hit count and update last_used_at.
     */
    public function recordUsage(): void
    {
        $this->increment('hit_count');
        $this->update(['last_used_at' => now()]);
    }

    // ─── Lifecycle & Search Indexing ──────────────────────────────

    /**
     * Determine if the model should be searchable.
     * Core Invariant: Only FAQs with ACTIVE lifecycle status are eligible for customer retrieval.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active
            && ! $this->trashed()
            && ($this->lifecycle_status === \App\Enums\FaqLifecycleStatus::ACTIVE || $this->lifecycle_status === null);
    }

    public function isReadyForRetrieval(): bool
    {
        return $this->shouldBeSearchable();
    }

    public function isValidating(): bool
    {
        return $this->lifecycle_status === \App\Enums\FaqLifecycleStatus::VALIDATING;
    }

    public function isSyncing(): bool
    {
        return $this->lifecycle_status === \App\Enums\FaqLifecycleStatus::SYNCING;
    }

    public function hasFailed(): bool
    {
        return $this->lifecycle_status?->hasFailed() ?? false;
    }
}
