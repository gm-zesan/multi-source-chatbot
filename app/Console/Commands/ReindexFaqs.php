<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FAQ;
use App\Services\FAQ\FAQIndexer;
use App\Services\Search\TypesenseService;
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
    protected $description = 'Reindex all searchable FAQs into Typesense (upsert only, does not delete collection)';

    /**
     * Typesense collection name for FAQs.
     */
    private const COLLECTION = 'faqs';

    public function __construct(
        private readonly FAQIndexer $indexer,
        private readonly TypesenseService $typesense,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // ── Fix 4: Validate Typesense collection before starting ──────
        if (! $this->validateCollection()) {
            return Command::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');

        if (! $this->option('force')) {
            $totalFaqs = FAQ::where('is_active', true)->count();
            if ($totalFaqs === 0) {
                $this->warn('No active FAQs found to reindex.');

                return Command::SUCCESS;
            }

            if (! $this->confirm("Found {$totalFaqs} active FAQs. Proceed with reindexing into Typesense?", true)) {
                $this->info('Reindex cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->output->writeln('');
        $this->info('Starting FAQ reindex...');
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
                $documents = [];

                foreach ($faqs as $faq) {
                    try {
                        if (! $faq->shouldBeSearchable()) {
                            // Remove from Typesense if it exists
                            $this->typesense->deleteDocument(self::COLLECTION, (string) $faq->id);
                            $totalSkipped++;
                            $bar->advance();
                            continue;
                        }

                        $documents[] = $this->indexer->buildDocument($faq);
                        $totalIndexed++;
                    } catch (\Throwable $e) {
                        $totalErrors++;
                        Log::error('[ReindexFaqs] Failed to process FAQ', [
                            'faq_id' => $faq->id,
                            'error'  => $e->getMessage(),
                        ]);
                        $this->warn("  Failed FAQ {$faq->id}: {$e->getMessage()}");
                    }

                    $bar->advance();
                }

                // Batch upsert documents for this chunk
                if (! empty($documents)) {
                    try {
                        $this->typesense->upsertDocuments(self::COLLECTION, $documents);
                    } catch (\Throwable $e) {
                        $totalErrors += count($documents);
                        Log::error('[ReindexFaqs] Batch upsert failed', [
                            'count' => count($documents),
                            'error' => $e->getMessage(),
                        ]);
                        $this->warn("  Batch upsert failed for {$faq->id}: {$e->getMessage()}");
                    }
                }
            });

        $bar->finish();
        $this->output->writeln('');
        $this->output->writeln('');

        // Summary
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total indexed', number_format($totalIndexed)],
                ['Skipped (inactive/deleted)', number_format($totalSkipped)],
                ['Errors', number_format($totalErrors)],
            ],
        );

        if ($totalErrors > 0) {
            $this->warn("Reindex completed with {$totalErrors} errors. Check the logs for details.");

            return Command::SUCCESS;
        }

        $this->info('Reindex completed successfully.');

        return Command::SUCCESS;
    }

    /**
     * Validate that the Typesense collection exists and has the expected schema.
     */
    private function validateCollection(): bool
    {
        $this->line('Validating Typesense collection...');

        try {
            $schema = $this->typesense->getCollectionSchema(self::COLLECTION);

            if ($schema === null) {
                $this->error("Typesense collection 'faqs' does not exist.");
                $this->error('Create it manually or via the Typesense API before running this command.');
                $this->line('');
                $this->line('Example schema (Typesense API): POST /collections');
                $this->line('See config/scout.php (model-settings) for the schema definition.');

                return false;
            }

            // Verify embedding field exists
            $fields = $schema['fields'] ?? [];
            $hasEmbedding = false;
            foreach ($fields as $field) {
                if (($field['name'] ?? '') === 'embedding') {
                    $hasEmbedding = true;
                    break;
                }
            }

            if (! $hasEmbedding) {
                $this->error("Typesense collection 'faqs' exists but is missing the 'embedding' float[] field.");
                $this->error('Recreate the collection with the correct schema including an embedding field.');

                return false;
            }

            $this->info('Typesense collection validated successfully.');
            $this->line('');

            return true;
        } catch (\Throwable $e) {
            $this->error("Failed to validate Typesense collection: {$e->getMessage()}");

            return false;
        }
    }
}
