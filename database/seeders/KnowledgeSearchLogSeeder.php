<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\KnowledgeSearchLog;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class KnowledgeSearchLogSeeder extends Seeder
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

        $conversations = Conversation::where('channel_account_id', $workspace->channelAccounts->first()?->id ?? 0)->take(5)->get();
        $faqs = FAQ::where('workspace_id', $workspace->id)->take(10)->get();

        if ($conversations->isEmpty()) {
            return;
        }

        $sampleQueries = [
            'How do I reset my password?',
            'What are your business hours?',
            'How can I upgrade my plan?',
            'Do you offer refunds?',
            'How do I integrate with Slack?',
            'Can I customize the dashboard?',
            'What is the cancellation policy?',
            'How do I export my data?',
            'Is my data encrypted?',
            'How do I invite team members?',
            'What payment methods do you accept?',
            'How do I set up two-factor authentication?',
            'Can I use the platform on mobile?',
            'How do I change my email address?',
            'What happens when my trial ends?',
        ];

        foreach ($sampleQueries as $i => $query) {
            $message = Message::where('conversation_id', $conversations->random()->id)
                ->inRandomOrder()
                ->first();

            if (! $message) {
                continue;
            }

            $matchedFaq = $faqs->random();
            $hasMatch = fake()->boolean(70);

            KnowledgeSearchLog::create([
                'workspace_id' => $workspace->id,
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'customer_query' => $query,
                'matched_faq_id' => $hasMatch ? $matchedFaq->id : null,
                'keyword_score' => $hasMatch ? fake()->randomFloat(4, 0.5, 1) : fake()->randomFloat(4, 0, 0.3),
                'semantic_score' => $hasMatch ? fake()->randomFloat(4, 0.4, 1) : fake()->randomFloat(4, 0, 0.25),
                'final_score' => $hasMatch ? fake()->randomFloat(4, 0.5, 1) : fake()->randomFloat(4, 0, 0.25),
                'response_time_ms' => fake()->numberBetween(50, 5000),
                'answer_source' => $hasMatch
                    ? fake()->randomElement(['faq_match', 'keyword', 'semantic'])
                    : fake()->randomElement(['fallback', 'none']),
                'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
            ]);
        }
    }
}
