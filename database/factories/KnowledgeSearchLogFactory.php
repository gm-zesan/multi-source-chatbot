<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\KnowledgeSearchLog;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeSearchLog>
 */
class KnowledgeSearchLogFactory extends Factory
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
            'message_id' => Message::factory(),
            'customer_query' => fake()->sentence(),
            'matched_faq_id' => null,
            'keyword_score' => fake()->randomFloat(4, 0, 1),
            'semantic_score' => fake()->randomFloat(4, 0, 1),
            'final_score' => fake()->randomFloat(4, 0, 1),
            'response_time_ms' => fake()->numberBetween(50, 5000),
            'answer_source' => fake()->randomElement([
                'faq_match',
                'keyword',
                'semantic',
                'fallback',
                'none',
            ]),
            'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }

    /**
     * Indicate that the search matched an FAQ.
     */
    public function matched(FAQ $faq): static
    {
        return $this->state(fn (array $attributes) => [
            'matched_faq_id' => $faq->id,
            'keyword_score' => fake()->randomFloat(4, 0.5, 1),
            'semantic_score' => fake()->randomFloat(4, 0.5, 1),
            'final_score' => fake()->randomFloat(4, 0.5, 1),
        ]);
    }

    /**
     * Indicate that no match was found.
     */
    public function unmatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'matched_faq_id' => null,
            'keyword_score' => fake()->randomFloat(4, 0, 0.3),
            'semantic_score' => fake()->randomFloat(4, 0, 0.3),
            'final_score' => fake()->randomFloat(4, 0, 0.3),
            'answer_source' => 'none',
        ]);
    }
}
