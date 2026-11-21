<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'slug',
        'badge',
        'tagline',
        'price_monthly',
        'price_yearly',
        'currency',
        'billing_period_text',
        'price_note',
        'max_conversations_monthly',
        'max_knowledge_docs',
        'max_staff_seats',
        'features',
        'is_featured',
        'sort_order',
        'cta_text',
        'cta_url',
        'is_active',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'max_conversations_monthly' => 'integer',
        'max_knowledge_docs' => 'integer',
        'max_staff_seats' => 'integer',
        'features' => 'array',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(WorkspaceSubscription::class, 'plan_id');
    }

    public function hasFeature(string $feature): bool
    {
        if (empty($this->features)) {
            return false;
        }

        return in_array($feature, $this->features, true);
    }
}
