<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class RecordAiFeedbackCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:feedback
                            {message_id : Target outbound AI message ID}
                            {rating : Rating outcome: positive, negative, like, dislike, correct, incorrect}
                            {--comment= : Merchant or reviewer feedback comment}
                            {--agent=system : Reviewer name, email, or agent identifier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Record quality review feedback on a specific AI response turn for audit and retraining traces';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $messageId = (string) $this->argument('message_id');
        $rating = strtolower((string) $this->argument('rating'));
        $comment = $this->option('comment') ? (string) $this->option('comment') : null;
        $agent = (string) ($this->option('agent') ?: 'system');

        $message = Message::find($messageId);
        if (! $message) {
            $this->error("Message #{$messageId} not found.");
            return self::FAILURE;
        }

        $normalizedRating = match ($rating) {
            'positive', 'like', 'correct', 'pass', 'thumbs_up' => 'positive',
            'negative', 'dislike', 'incorrect', 'fail', 'thumbs_down' => 'negative',
            default => $rating,
        };

        $metadata = $message->metadata ?? [];
        $metadata['ai_feedback'] = [
            'rating'      => $normalizedRating,
            'comment'     => $comment,
            'agent'       => $agent,
            'recorded_at' => now()->toIso8601String(),
        ];

        $message->metadata = $metadata;
        $message->save();

        $this->info("Successfully recorded AI feedback for Message #{$messageId}: [{$normalizedRating}]");
        if ($comment) {
            $this->line("Comment: {$comment}");
        }

        return self::SUCCESS;
    }
}
