<?php

namespace Database\Seeders;

use App\Models\FAQCategory;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FAQCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workspace = Workspace::first();

        if (! $workspace) {
            return;
        }

        $categories = [
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'New user onboarding and platform basics.',
                'icon' => 'heroicon-o-rocket-launch',
                'order_column' => 1,
            ],
            [
                'name' => 'Account & Billing',
                'slug' => 'account-billing',
                'description' => 'Manage your account settings and subscription.',
                'icon' => 'heroicon-o-credit-card',
                'order_column' => 2,
            ],
            [
                'name' => 'Troubleshooting',
                'slug' => 'troubleshooting',
                'description' => 'Common issues and how to resolve them.',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'order_column' => 3,
            ],
            [
                'name' => 'Features & Integrations',
                'slug' => 'features-integrations',
                'description' => 'Explore platform features and third-party integrations.',
                'icon' => 'heroicon-o-puzzle-piece',
                'order_column' => 4,
            ],
            [
                'name' => 'Security & Privacy',
                'slug' => 'security-privacy',
                'description' => 'Data protection, encryption, and compliance.',
                'icon' => 'heroicon-o-shield-check',
                'order_column' => 5,
            ],
            [
                'name' => 'API Reference',
                'slug' => 'api-reference',
                'description' => 'API endpoints, authentication, and usage guidelines.',
                'icon' => 'heroicon-o-code-bracket',
                'order_column' => 6,
            ],
            [
                'name' => 'Best Practices',
                'slug' => 'best-practices',
                'description' => 'Tips and recommendations for optimal usage.',
                'icon' => 'heroicon-o-light-bulb',
                'order_column' => 7,
            ],
            [
                'name' => 'Pricing & Plans',
                'slug' => 'pricing-plans',
                'description' => 'Compare plans and understand pricing tiers.',
                'icon' => 'heroicon-o-currency-dollar',
                'order_column' => 8,
            ],
        ];

        foreach ($categories as $data) {
            FAQCategory::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'],
                'icon' => $data['icon'],
                'is_active' => true,
                'order_column' => $data['order_column'],
            ]);
        }
    }
}
