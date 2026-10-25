<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FAQ;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReindexFaqs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'faq:reindex
        {--chunk=200 : Number of FAQs to process per chunk}
        {--lock-timeout=10 : Maximum seconds to wait for per-FAQ lock}
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
        $lockTimeout = max(0, (int) $this->option('lock-timeout'));

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
                $lockTimeout,
                $bar,
            ) {
                foreach ($faqs as $faq) {
                    $lock = Cache::lock("faq_indexing:{$faq->id}", 60);
                    $acquired = false;

                    try {
                        $lock->block($lockTimeout);
                        $acquired = true;

                        try {
                            $faq->refresh();
                            $exists = true;
                        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                            $exists = false;
                        }

                        if (! $exists) {
                            $deleted = $this->retrievalClient->deleteFaq($faq->id, $faq->workspace_id);
                            if ($deleted) {
                                $totalSkipped++;
                            } else {
                                $totalErrors++;
                                Log::error('[ReindexFaqs] Failed to purge deleted FAQ from index', ['faq_id' => $faq->id]);
                                $this->warn("  Failed to purge deleted FAQ {$faq->id} from index.");
                            }
                        } elseif (! $faq->shouldBeSearchable()) {
                            $deleted = $this->retrievalClient->deleteFaq($faq->id, $faq->workspace_id);
                            if ($deleted) {
                                $totalSkipped++;
                            } else {
                                $totalErrors++;
                                Log::error('[ReindexFaqs] Failed to purge inactive FAQ from index', ['faq_id' => $faq->id]);
                                $this->warn("  Failed to purge inactive FAQ {$faq->id} from index.");
                            }
                        } else {
                            $capturedUpdatedAt = $faq->updated_at;
                            $capturedContentHash = hash('sha256', $faq->question . "\0" . $faq->answer . "\0" . $faq->workspace_id);

                            $success = $this->retrievalClient->syncFaq($faq);

                            // Inspect state post-sync to guard against in-flight races
                            $currentFaq = FAQ::withTrashed()->find($faq->id);
                            if (! $currentFaq || ! $currentFaq->shouldBeSearchable()) {
                                $deleted = $this->retrievalClient->deleteFaq($faq->id, $faq->workspace_id);
                                if ($deleted) {
                                    $totalSkipped++;
                                } else {
                                    $totalErrors++;
                                    Log::error('[ReindexFaqs] Post-sync cleanup delete failed', ['faq_id' => $faq->id]);
                                    $this->warn("  Post-sync cleanup delete failed for FAQ {$faq->id}; index may contain stale entry.");
                                }
                            } else {
                                $currentContentHash = hash('sha256', $currentFaq->question . "\0" . $currentFaq->answer . "\0" . $currentFaq->workspace_id);
                                if ($currentFaq->updated_at != $capturedUpdatedAt || $currentContentHash !== $capturedContentHash) {
                                    Log::warning('[ReindexFaqs] FAQ modified during remote sync window', [
                                        'faq_id' => $faq->id,
                                    ]);
                                    $totalErrors++;
                                } elseif ($success) {
                                    $totalIndexed++;
                                } else {
                                    $totalErrors++;
                                }
                            }
                        }
                    } catch (LockTimeoutException $e) {
                        $totalErrors++;
                        Log::warning('[ReindexFaqs] Lock timeout for FAQ; skipping to prevent concurrent race', [
                            'faq_id' => $faq->id,
                            'error'  => $e->getMessage(),
                        ]);
                        $this->warn("  Lock timeout for FAQ {$faq->id}; skipped remote sync.");
                    } catch (\Throwable $e) {
                        $totalErrors++;
                        Log::error('[ReindexFaqs] Failed to sync FAQ', [
                            'faq_id' => $faq->id,
                            'error'  => $e->getMessage(),
                        ]);
                        $this->warn("  Failed FAQ {$faq->id}: {$e->getMessage()}");
                    } finally {
                        if ($acquired) {
                            optional($lock)->release();
                        }
                        $bar->advance();
                    }
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
