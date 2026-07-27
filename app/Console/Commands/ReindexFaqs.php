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
        {--force : Skip confirmation prompt}
        {--fresh : Drop and recreate the Typesense collection before indexing}
        {--create-collection : Create the Typesense collection if it does not exist}';

    /**
     * The console command description.
     */
    protected $description = 'Reindex all searchable FAQs into Typesense. Use --create-collection on first deployment.';

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
                        $this->warn("  Batch upsert failed: {$e->getMessage()}");
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

    // ─────────────────────────────────────────────────────────────────────
    // Collection Management
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Validate that the Typesense collection exists and has the expected schema.
     *
     * When --create-collection is passed and the collection is absent, it is
     * created automatically using the canonical schema defined in getFaqsSchema().
     */
    private function validateCollection(): bool
    {
        $this->line('Validating Typesense collection...');

        if ($this->option('fresh')) {
            return $this->createCollection();
        }

        try {
            $schema = $this->typesense->getCollectionSchema(self::COLLECTION);

            if ($schema === null) {
                if ($this->option('create-collection')) {
                    return $this->createCollection();
                }

                $this->error("Typesense collection 'faqs' does not exist.");
                $this->line('');
                $this->line('  Run the following to create it automatically:');
                $this->line('  php artisan faq:reindex --create-collection');
                $this->line('');

                return false;
            }

            // Verify the embedding float[] field exists
            $fields       = $schema['fields'] ?? [];
            $hasEmbedding = false;

            foreach ($fields as $field) {
                if (($field['name'] ?? '') === 'embedding') {
                    $hasEmbedding = true;
                    break;
                }
            }

            if (! $hasEmbedding) {
                $this->error("Typesense collection 'faqs' exists but is missing the 'embedding' float[] field.");
                $this->error('Drop and recreate it: php artisan faq:reindex --fresh');

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

    /**
     * Create the 'faqs' Typesense collection using the canonical schema.
     */
    private function createCollection(): bool
    {
        try {
            if ($this->typesense->collectionExists(self::COLLECTION)) {
                $this->line("  Deleting existing collection '" . self::COLLECTION . "' to purge stale records...");
                $this->typesense->deleteCollection(self::COLLECTION);
            }

            $this->line("  Creating collection '" . self::COLLECTION . "'...");
            $this->typesense->createCollection(self::COLLECTION, $this->getFaqsSchema());

            $this->info("  Collection 'faqs' created successfully.");
            $this->line('');

            Log::info('[ReindexFaqs] Typesense collection created', [
                'collection' => self::COLLECTION,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->error("  Failed to create collection: {$e->getMessage()}");

            Log::error('[ReindexFaqs] Failed to create Typesense collection', [
                'collection' => self::COLLECTION,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Return the canonical Typesense schema for the 'faqs' collection.
     *
     * This is the single source of truth for the collection structure.
     * Embedding dimensions are read from config/embedding.php (CHATBOT_EMBEDDING_DIMENSIONS).
     *
     * @return array<string, mixed>
     */
    private function getFaqsSchema(): array
    {
        $dimensions = (int) config('embedding.dimensions', 768);

        return [
            'fields' => [
                ['name' => 'id',              'type' => 'string'],
                ['name' => 'workspace_id',    'type' => 'int32'],
                ['name' => 'question',        'type' => 'string'],
                ['name' => 'answer',          'type' => 'string'],
                ['name' => 'searchable_text', 'type' => 'string'],
                ['name' => 'priority',        'type' => 'int32'],
                ['name' => 'is_active',       'type' => 'bool',    'index' => true],
                ['name' => 'created_at',      'type' => 'int64'],
                [
                    'name'     => 'embedding',
                    'type'     => 'float[]',
                    'num_dim'  => $dimensions,
                    'optional' => true,   // allows indexing FAQs even when embedding fails
                ],
            ],
            'default_sorting_field' => 'priority',
        ];
    }
}
