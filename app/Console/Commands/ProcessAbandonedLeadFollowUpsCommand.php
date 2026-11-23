<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendAbandonedLeadFollowUpJob;
use App\Services\Retention\AbandonedLeadRecoveryService;
use Illuminate\Console\Command;

class ProcessAbandonedLeadFollowUpsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retention:process-abandoned-leads 
                            {--workspace= : Specific workspace ID to process}
                            {--sync : Run immediately without dispatching to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan and trigger polite follow-ups for abandoned carts and price inquiries within Meta 24-hr window';

    public function handle(AbandonedLeadRecoveryService $service): int
    {
        $workspaceId = $this->option('workspace') ? (int) $this->option('workspace') : null;
        $sync = (bool) $this->option('sync');

        $this->info("Scanning for eligible abandoned leads (Workspace: " . ($workspaceId ?? 'All') . ")...");

        $conversations = $service->findEligibleConversations($workspaceId);

        if ($conversations->isEmpty()) {
            $this->info("No eligible abandoned leads found.");
            return Command::SUCCESS;
        }

        $this->info("Found {$conversations->count()} conversation(s) ready for follow-up.");

        $processed = 0;
        foreach ($conversations as $conversation) {
            $this->line(" -> Processing Conversation #{$conversation->id} ({$conversation->customer_name})");

            if ($sync) {
                $result = $service->sendFollowUp($conversation);
                if ($result['success']) {
                    $this->info("    ✓ Sent follow-up message [ID: {$result['message_id']}]");
                    $processed++;
                } else {
                    $this->warn("    ✗ Skipped: {$result['reason']}");
                }
            } else {
                SendAbandonedLeadFollowUpJob::dispatch($conversation->id);
                $this->info("    ✓ Dispatched SendAbandonedLeadFollowUpJob to retention queue");
                $processed++;
            }
        }

        $this->info("Completed processing. Total actions: {$processed}");
        return Command::SUCCESS;
    }
}
