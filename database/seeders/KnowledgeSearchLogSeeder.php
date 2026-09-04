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
            $hasMatch = (rand(1, 100) <= 70);
            $keywordScore = $hasMatch ? (rand(5000, 10000) / 10000) : (rand(0, 3000) / 10000);
            $semanticScore = $hasMatch ? (rand(4000, 10000) / 10000) : (rand(0, 2500) / 10000);
            $finalScore = $hasMatch ? (rand(5000, 10000) / 10000) : (rand(0, 2500) / 10000);
            $responseTime = rand(50, 5000);
            $answerSources = $hasMatch ? ['faq_match', 'keyword', 'semantic'] : ['fallback', 'none'];
            $answerSource = $answerSources[array_rand($answerSources)];
            $createdAt = now()->subDays(rand(0, 30))->subMinutes(rand(0, 1440));

            KnowledgeSearchLog::create([
                'workspace_id' => $workspace->id,
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'customer_query' => $query,
                'matched_faq_id' => $hasMatch ? $matchedFaq->id : null,
                'keyword_score' => $keywordScore,
                'semantic_score' => $semanticScore,
                'final_score' => $finalScore,
                'response_time_ms' => $responseTime,
                'answer_source' => $answerSource,
                'created_at' => $createdAt,
            ]);
        }
    }
}
