<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnansweredQuestionSeeder extends Seeder
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

        if ($conversations->isEmpty()) {
            return;
        }

        $unanswered = [
            [
                'original_question' => 'Can I get a discount for annual billing?',
                'normalized_question' => 'annual billing discount',
                'occurrence_count' => 12,
                'status' => 'pending',
                'notes' => null,
            ],
            [
                'original_question' => 'Do you have a mobile app?',
                'normalized_question' => 'mobile app availability',
                'occurrence_count' => 8,
                'status' => 'answered',
                'notes' => 'Added to FAQ: "Platform Features & Integrations" category.',
            ],
            [
                'original_question' => 'How do I delete my account permanently?',
                'normalized_question' => 'delete account permanently',
                'occurrence_count' => 5,
                'status' => 'pending',
                'notes' => null,
            ],
            [
                'original_question' => 'What is the maximum file size for uploads?',
                'normalized_question' => 'maximum upload file size',
                'occurrence_count' => 7,
                'status' => 'reviewed',
                'notes' => 'Need to check with engineering team.',
            ],
            [
                'original_question' => 'Can I connect multiple WhatsApp numbers?',
                'normalized_question' => 'multiple whatsapp numbers connection',
                'occurrence_count' => 4,
                'status' => 'pending',
                'notes' => null,
            ],
            [
                'original_question' => 'How do I transfer my workspace to another owner?',
                'normalized_question' => 'transfer workspace ownership',
                'occurrence_count' => 3,
                'status' => 'dismissed',
                'notes' => 'Feature not currently supported. Inform user via email.',
            ],
            [
                'original_question' => 'Can I schedule messages to send later?',
                'normalized_question' => 'schedule message future send',
                'occurrence_count' => 6,
                'status' => 'pending',
                'notes' => null,
            ],
            [
                'original_question' => 'What reporting features are available?',
                'normalized_question' => 'available reporting features',
                'occurrence_count' => 9,
                'status' => 'answered',
                'notes' => 'Added reporting overview to FAQ documentation.',
            ],
        ];

        foreach ($unanswered as $i => $data) {
            $conversation = $conversations->random();

            UnansweredQuestion::create([
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversation->id,
                'original_question' => $data['original_question'],
                'normalized_question' => $data['normalized_question'],
                'occurrence_count' => $data['occurrence_count'],
                'status' => $data['status'],
                'notes' => $data['notes'],
            ]);
        }
    }
}
