<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed initial commercial plans matching live commerce requirements.
     */
    public function run(): void
    {
        // 1. Metered Billing
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'metered-billing'],
            [
                'name'                      => 'Metered Billing',
                'badge'                     => 'Standard Metered',
                'tagline'                   => 'Ideal for emerging social commerce brands and online stores.',
                'price_monthly'             => 0.40,
                'price_yearly'              => 0.00,
                'currency'                  => 'BDT',
                'billing_period_text'       => '/ AI reply',
                'price_note'                => 'Zero monthly commitment • Deducted per response',
                'max_conversations_monthly' => -1,
                'max_knowledge_docs'        => 10,
                'max_staff_seats'           => 3,
                'features'                  => [
                    'Fixed ৳0.40 BDT per response',
                    'Facebook Messenger & WhatsApp Business',
                    'TikTok DMs & Telegram Bot',
                    'Storefront Live Web Chat Widget',
                    'Automated Order & Lead Extraction',
                    'Dynamic Excel & Document Training',
                    'Unified Inbox & Human Agent Takeover',
                    'bKash, Nagad & Card Wallet Top-up',
                ],
                'is_featured'               => false,
                'sort_order'                => 1,
                'cta_text'                  => 'Get Started with ৳50 Bonus',
                'cta_url'                   => '/register',
                'is_active'                 => true,
            ]
        );

        // 2. Starter Bonus (Featured)
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'starter-bonus'],
            [
                'name'                      => 'Starter Bonus',
                'badge'                     => 'Free Trial',
                'tagline'                   => 'Test everything live with your real store catalog & customers.',
                'price_monthly'             => 50.00,
                'price_yearly'              => 0.00,
                'currency'                  => 'BDT',
                'billing_period_text'       => 'Free Credit',
                'price_note'                => '125 Complimentary AI Messages • No card required',
                'max_conversations_monthly' => 125,
                'max_knowledge_docs'        => 5,
                'max_staff_seats'           => 2,
                'features'                  => [
                    '125 Full AI Messages included free',
                    '10-Second Instant Workspace Setup',
                    'Connect Facebook Page or WhatsApp',
                    '1-Line Web Chat Widget Embed',
                    'Live AI Chat Simulator Testing',
                    'Real-time Order & Phone Capture',
                    'Live Channel Diagnostics & Test Ping',
                    'Immediate Dashboard Access',
                ],
                'is_featured'               => true,
                'sort_order'                => 2,
                'cta_text'                  => 'Create Free Account',
                'cta_url'                   => '/register',
                'is_active'                 => true,
            ]
        );

        // 3. Enterprise Scale
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'enterprise-scale'],
            [
                'name'                      => 'Enterprise Scale',
                'badge'                     => 'High Volume (50K+)',
                'tagline'                   => 'For retail chains, multi-hub franchises, and high-security compliance.',
                'price_monthly'             => 0.00,
                'price_yearly'              => 0.00,
                'currency'                  => 'BDT',
                'billing_period_text'       => 'Volume Discount',
                'price_note'                => 'Custom discounted rate & bespoke deployment',
                'max_conversations_monthly' => -1,
                'max_knowledge_docs'        => 100,
                'max_staff_seats'           => 50,
                'features'                  => [
                    'Discounted volume rate (< ৳0.30/msg)',
                    'Custom ERP / Inventory / Courier Webhook Sync',
                    'Dedicated Solutions Architect & Onboarding',
                    'Tailored LLM Fine-Tuning & Custom Prompt Directives',
                    'Multi-Tenant Franchise RBAC Delegation',
                    '99.9% Uptime SLA & Priority 24/7 Hotline Support',
                ],
                'is_featured'               => false,
                'sort_order'                => 3,
                'cta_text'                  => 'Talk to Enterprise Team',
                'cta_url'                   => '/contact',
                'is_active'                 => true,
            ]
        );
    }
}
