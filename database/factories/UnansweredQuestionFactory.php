<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnansweredQuestion>
 */
class UnansweredQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'conversation_id' => Conversation::factory(),
            'original_question' => fake()->sentence(),
            'normalized_question' => null,
            'occurrence_count' => fake()->numberBetween(1, 20),
            'status' => fake()->randomElement(['pending', 'reviewed', 'answered', 'dismissed']),
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }

    /**
     * Indicate that the question is pending review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'notes' => null,
        ]);
    }

    /**
     * Indicate that the question has been answered.
     */
    public function answered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'answered',
            'notes' => 'Added as a new FAQ entry.',
        ]);
    }
}
