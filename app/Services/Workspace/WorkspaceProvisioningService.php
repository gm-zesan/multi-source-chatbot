<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Enums\FaqLifecycleStatus;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\FAQ;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Models\WorkspaceUsageQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class WorkspaceProvisioningService
{
    /**
     * Create a brand-new workspace and configure it for a tenant owner.
     *
     * @param string $businessName
     * @param array{name: string, email: string, password: string, avatar?: ?string, google_id?: ?string, facebook_id?: ?string, password_set?: bool, email_verified_at?: mixed, last_login_at?: mixed, last_login_ip?: ?string} $userData
     */
    public function provisionNewTenant(string $businessName, array $userData): User
    {
        return DB::transaction(function () use ($businessName, $userData) {
            // 1. Generate unique slug
            $baseSlug = Str::slug($businessName);
            if (empty($baseSlug)) {
                $baseSlug = 'workspace';
            }
            $slug = $baseSlug . '-' . Str::lower(Str::random(5));
            while (Workspace::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . Str::lower(Str::random(5));
            }

            // 2. Create the Workspace
            $workspace = Workspace::create([
                'name'             => trim($businessName),
                'slug'             => $slug,
                'currency'         => 'BDT',
                'is_active'        => true,
                'operating_hours'  => [
                    'weekdays' => '09:00 AM - 08:00 PM',
                    'weekend'  => '10:00 AM - 06:00 PM',
                ],
                'settings'         => [
                    'ai_tone'            => 'professional_friendly',
                    'auto_reply'         => true,
                    'language'           => 'bn_en',
                    'notifications'      => true,
                ],
            ]);

            // 3. Create Tenant Owner User
            $userData['workspace_id'] = $workspace->id;
            $userData['is_active'] = true;
            $user = User::create($userData);

            // 4. Assign Admin Role (Workspace Owner)
            if (class_exists(Role::class) && Role::where('name', RoleEnum::ADMIN->value)->exists()) {
                $user->assignRole(RoleEnum::ADMIN->value);
            }

            // 5. Provision SaaS Starter / Trial Subscription & Usage Quota
            $this->provisionSubscription($workspace);

            // 6. Provision Default Web Widget Channel Account
            $this->provisionWebWidget($workspace);

            // 7. Provision Starter Template FAQs
            $this->provisionStarterKnowledge($workspace, $user);

            return $user;
        });
    }

    /**
     * Provision a dedicated workspace for an existing social login user (Google/Facebook)
     * if they somehow authenticate without an assigned workspace.
     */
    public function provisionForExistingUser(User $user, string $businessName): Workspace
    {
        return DB::transaction(function () use ($user, $businessName) {
            $baseSlug = Str::slug($businessName);
            if (empty($baseSlug)) {
                $baseSlug = 'workspace';
            }
            $slug = $baseSlug . '-' . Str::lower(Str::random(5));
            while (Workspace::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . Str::lower(Str::random(5));
            }

            $workspace = Workspace::create([
                'name'             => trim($businessName),
                'slug'             => $slug,
                'currency'         => 'BDT',
                'is_active'        => true,
                'operating_hours'  => [
                    'weekdays' => '09:00 AM - 08:00 PM',
                    'weekend'  => '10:00 AM - 06:00 PM',
                ],
                'settings'         => [
                    'ai_tone'       => 'professional_friendly',
                    'auto_reply'    => true,
                    'language'      => 'bn_en',
                ],
            ]);

            $user->update(['workspace_id' => $workspace->id]);

            if (class_exists(Role::class) && Role::where('name', RoleEnum::ADMIN->value)->exists()) {
                $user->assignRole(RoleEnum::ADMIN->value);
            }

            $this->provisionSubscription($workspace);
            $this->provisionWebWidget($workspace);
            $this->provisionStarterKnowledge($workspace, $user);

            return $workspace;
        });
    }

    /**
     * Provision Starter Trial Subscription & Current Month Quota Bucket.
     */
    public function provisionSubscription(Workspace $workspace): WorkspaceSubscription
    {
        $defaultPlan = SubscriptionPlan::firstOrCreate(
            ['slug' => 'starter-trial'],
            [
                'name'                      => 'Starter Trial',
                'price_monthly'             => 0.00,
                'price_yearly'              => 0.00,
                'currency'                  => 'BDT',
                'max_conversations_monthly' => 500,
                'max_knowledge_docs'        => 10,
                'max_staff_seats'           => 3,
                'features'                  => ['chat_widget', 'faqs', 'knowledge_base', 'simulator', 'basic_analytics'],
                'is_active'                 => true,
            ]
        );

        $subscription = WorkspaceSubscription::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'status'       => 'active',
            ],
            [
                'plan_id'         => $defaultPlan->id,
                'billing_cycle'   => 'monthly',
                'starts_at'       => now(),
                'ends_at'         => now()->addMonths(1),
                'trial_ends_at'   => now()->addDays(14),
                'payment_gateway' => 'manual',
            ]
        );

        // Ensure current month quota usage record exists
        WorkspaceUsageQuota::firstOrCreate(
            [
                'workspace_id'      => $workspace->id,
                'period_year_month' => Carbon::now()->format('Y-m'),
            ],
            [
                'conversation_count'        => 0,
                'ai_messages_count'         => 0,
                'spreadsheet_queries_count' => 0,
            ]
        );

        return $subscription;
    }

    /**
     * Provision Default Web Widget Channel Account for instant embedding.
     */
    public function provisionWebWidget(Workspace $workspace): ChannelAccount
    {
        $websiteChannel = Channel::firstOrCreate(
            ['slug' => 'website'],
            ['name' => 'Website Chat', 'icon' => 'fas fa-globe', 'is_active' => true]
        );

        return ChannelAccount::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'channel_id'   => $websiteChannel->id,
            ],
            [
                'name'         => $workspace->name . ' Web Widget',
                'display_name' => $workspace->name,
                'external_id'  => 'widget_' . Str::random(12),
                'access_token' => 'widget_token_' . Str::random(24),
                'settings'     => [
                    'welcome_message' => "Welcome to {$workspace->name}! How can our AI assistant help you today?",
                    'primary_color'   => '#0284C7',
                    'header_title'    => $workspace->name . ' Assistant',
                ],
                'is_active'    => true,
            ]
        );
    }

    /**
     * Seed starter FAQs so newly created workspace AI has immediate context.
     */
    public function provisionStarterKnowledge(Workspace $workspace, ?User $user = null): void
    {
        $starterFaqs = [
            [
                'question' => "What are the operating hours of {$workspace->name}?",
                'answer'   => "Our standard operating hours are Saturday to Thursday, 9:00 AM to 8:00 PM. On weekends, our automated assistant remains online 24/7.",
            ],
            [
                'question' => "How can I contact customer support?",
                'answer'   => "You can reach our team directly through this chat window or by emailing support@{$workspace->slug}.com.",
            ],
        ];

        foreach ($starterFaqs as $faq) {
            FAQ::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'question'     => $faq['question'],
                ],
                [
                    'answer'           => $faq['answer'],
                    'document_type'    => 'faq',
                    'lifecycle_status' => FaqLifecycleStatus::ACTIVE,
                    'is_active'        => true,
                    'created_by'       => $user?->id,
                ]
            );
        }
    }
}
