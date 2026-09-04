<?php

namespace Database\Factories;

use App\Models\FAQCategory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FAQCategory>
 */
class FAQCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Getting Started',
            'Account & Billing',
            'Troubleshooting',
            'Features & Integrations',
            'Security & Privacy',
            'API Reference',
            'Best Practices',
            'Pricing & Plans',
        ]);

        return [
            'workspace_id' => Workspace::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional(0.7)->sentence(),
            'icon' => fake()->optional(0.5)->randomElement([
                'heroicon-o-rocket-launch',
                'heroicon-o-credit-card',
                'heroicon-o-wrench-screwdriver',
                'heroicon-o-puzzle-piece',
                'heroicon-o-shield-check',
                'heroicon-o-code-bracket',
                'heroicon-o-light-bulb',
                'heroicon-o-currency-dollar',
            ]),
            'is_active' => true,
            'order_column' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
