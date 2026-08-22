<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FAQ;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ReindexFaqs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'faq:reindex
        {--chunk=200 : Number of FAQs to process per chunk}
        {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Sync all searchable FAQs to the Python Retrieval Service for embedding & indexing.';

    public function __construct(
        private readonly RetrievalClient $retrievalClient,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');

        if (! $this->option('force')) {
            $totalFaqs = FAQ::where('is_active', true)->count();
            if ($totalFaqs === 0) {
                $this->warn('No active FAQs found to sync.');

                return Command::SUCCESS;
            }

            if (! $this->confirm("Found {$totalFaqs} active FAQs. Proceed with syncing to Python Retrieval Service?", true)) {
                $this->info('Sync cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->output->writeln('');
        $this->info('Starting FAQ synchronization to Python Retrieval Service...');
        $this->output->writeln('');

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% FAQs processed [%bar%] %percent:3s%% %elapsed:6s% %memory:6s%');
        $bar->start();

        $totalIndexed = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        FAQ::withTrashed()
            ->orderBy('id')
            ->chunk($chunkSize, function (Collection $faqs) use (
                &$totalIndexed,
                &$totalSkipped,
                &$totalErrors,
                $bar,
            ) {
                foreach ($faqs as $faq) {
                    try {
                        if (! $faq->shouldBeSearchable()) {
                            $this->retrievalClient->deleteFaq((int) $faq->id, $faq->workspace_id);
                            $totalSkipped++;
                            $bar->advance();
                            continue;
                        }

                        $success = $this->retrievalClient->syncFaq($faq);
                        if ($success) {
                            $totalIndexed++;
                        } else {
                            $totalErrors++;
                        }
                    } catch (\Throwable $e) {
                        $totalErrors++;
                        Log::error('[ReindexFaqs] Failed to sync FAQ', [
                            'faq_id' => $faq->id,
                            'error'  => $e->getMessage(),
                        ]);
                        $this->warn("  Failed FAQ {$faq->id}: {$e->getMessage()}");
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->output->writeln('');
        $this->output->writeln('');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Synced to Python Service', number_format($totalIndexed)],
                ['Deleted / Inactive', number_format($totalSkipped)],
                ['Errors', number_format($totalErrors)],
            ],
        );

        if ($totalErrors > 0) {
            $this->warn("Sync completed with {$totalErrors} errors. Check the logs for details.");

            return Command::SUCCESS;
        }

        $this->info('FAQ synchronization completed successfully.');

        return Command::SUCCESS;
    }
}
