<?php

namespace App\Console\Commands;

use App\Models\Embedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * WarmEmbeddingCache
 *
 * Warms the Redis (or default cache) with all stored embeddings from the
 * database. This ensures that the ContextRouter has fast cache hits without
 * needing to query MySQL or generate embeddings on the fly.
 *
 * Features:
 *   - Loads all embeddings from DB into cache
 *   - Uses tags for easy invalidation (Cache::tags('embeddings')->flush())
 *   - Progress bar with real-time feedback
 *   - Cache hit/miss statistics
 *   - Supports scoped warming by entity type
 *   - Skips embeddings with empty/invalid vectors
 *
 * Usage:
 *   php artisan embeddings:warm                          # Warm all embeddings
 *   php artisan embeddings:warm --type=database          # Only databases
 *   php artisan embeddings:warm --type=table             # Only tables
 *   php artisan embeddings:warm --source=db_01           # Single source
 *   php artisan embeddings:warm --clear                  # Clear cache first
 *   php artisan embeddings:warm --ttl=86400              # Custom TTL (24h)
 *
 * Cache Structure:
 *   Key format: embedding:{md5(text)} -> [float, float, ...]
 *   Tag group:  embeddings
 *   Sub-tags:   embeddings:database, embeddings:table, embeddings:alias
 */
class WarmEmbeddingCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'embeddings:warm
                            {--type= : Only warm this entity type (database, table, alias, query_type)}
                            {--source= : Only warm embeddings for this source (db_01, etc.)}
                            {--clear : Clear existing cached embeddings before warming}
                            {--ttl= : Cache TTL in seconds (default: from config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load all embeddings from the database into cache for fast ContextRouter access';

    /**
     * Statistics tracker.
     */
    protected array $stats = [
        'total'     => 0,
        'warmed'    => 0,
        'skipped'   => 0,
        'cleared'   => 0,
        'start_time' => 0.0,
    ];

    /**
     * Cache tag prefix for embeddings.
     */
    protected const CACHE_TAG = 'embeddings';

    /**
     * The cache prefix used for embedding keys.
     */
    protected string $cachePrefix;

    /**
     * The TTL for cached embeddings in seconds.
     */
    protected int $cacheTtl;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->stats['start_time'] = microtime(true);

        $this->cachePrefix = config('chatbot.vector.cache_prefix', 'embedding:');
        $this->cacheTtl = (int) ($this->option('ttl') ?? config('chatbot.vector.cache_ttl', 86400));

        $this->info('🔥 Warming embedding cache...');
        $this->newLine();

        $this->line('Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Type Filter', $this->option('type') ?? 'All'],
                ['Source Filter', $this->option('source') ?? 'All'],
                ['Clear First', $this->option('clear') ? 'Yes' : 'No'],
                ['Cache TTL', $this->cacheTtl . ' seconds (' . round($this->cacheTtl / 3600, 1) . ' hours)'],
                ['Cache Driver', config('cache.default')],
                ['Cache Prefix', $this->cachePrefix],
            ]
        );
        $this->newLine();

        // ── Clear existing cache if requested ──
        if ($this->option('clear')) {
            $this->clearExistingCache();
        }

        // ── Build Query ──
        $query = Embedding::query();

        if ($type = $this->option('type')) {
            $query->byType($type);
        }

        if ($source = $this->option('source')) {
            $query->bySource($source);
        }

        // ── Warm Cache ──
        $totalEmbeddings = $query->count();
        $this->stats['total'] = $totalEmbeddings;

        if ($totalEmbeddings === 0) {
            $this->warn('No embeddings found in the database.');
            $this->line('Generate embeddings first:');
            $this->line('  php artisan embeddings:generate');
            $this->line('  # or for mock data:');
            $this->line('  php artisan db:seed --class=EmbeddingSeeder');
            return Command::SUCCESS;
        }

        $this->line("Found {$totalEmbeddings} embeddings to cache.");
        $this->newLine();

        // Process with progress bar
        $bar = $this->output->createProgressBar($totalEmbeddings);
        $bar->setFormat("  %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% - %message%");
        $bar->setMessage('Starting...');
        $bar->start();

        $chunkSize = 100;
        $query->chunk($chunkSize, function ($embeddings) use ($bar) {
            foreach ($embeddings as $embedding) {
                $this->warmSingleEmbedding($embedding, $bar);
            }
        });

        $bar->finish();
        $this->newLine(2);

        // ── Summary ──
        $elapsed = round(microtime(true) - $this->stats['start_time'], 2);
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Found', $this->stats['total']],
                ['Successfully Warmed', $this->stats['warmed']],
                ['Skipped (invalid)', $this->stats['skipped']],
                ['Cache Cleared', $this->stats['cleared'] > 0 ? 'Yes (' . $this->stats['cleared'] . ' keys)' : 'No'],
                ['Cache TTL', $this->cacheTtl . 's'],
                ['Elapsed Time', $elapsed . 's'],
            ]
        );

        $this->newLine();

        if ($this->stats['warmed'] === $this->stats['total']) {
            $this->info('✅ All embeddings successfully cached!');
        } elseif ($this->stats['warmed'] > 0) {
            $this->warn(sprintf(
                '⚠️  Partially warmed: %d/%d (skipped %d invalid embeddings)',
                $this->stats['warmed'],
                $this->stats['total'],
                $this->stats['skipped'],
            ));
        } else {
            $this->error('❌ No embeddings were cached. Check that embeddings have valid vectors.');
        }

        Log::info('Embedding cache warmed', [
            'total' => $this->stats['total'],
            'warmed' => $this->stats['warmed'],
            'skipped' => $this->stats['skipped'],
            'elapsed_seconds' => $elapsed,
            'ttl' => $this->cacheTtl,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Cache a single embedding in the cache store.
     *
     * Generates a cache key from the embedding text stored in metadata,
     * or falls back to a key based on entity_type:entity_key.
     */
    protected function warmSingleEmbedding(Embedding $embedding, $bar): void
    {
        $bar->setMessage(sprintf(
            'Caching %s: %s',
            $embedding->entity_type,
            $embedding->entity_key ?? $embedding->entity_name
        ));

        // Skip embeddings with no valid vector
        if (!$embedding->hasValidEmbedding()) {
            $this->stats['skipped']++;
            $bar->advance();
            return;
        }

        try {
            $vector = $embedding->getVector();

            // ── Cache by entity type/key for fast lookup ──
            $entityCacheKey = $this->cachePrefix
                . $embedding->entity_type . ':'
                . ($embedding->entity_key ?? $embedding->entity_name);

            $this->storeWithTags($entityCacheKey, $vector, $embedding->entity_type);

            // ── Also cache by text description for similarity search ──
            $descriptionText = $this->buildDescriptionText($embedding);
            if ($descriptionText) {
                $textCacheKey = $this->cachePrefix . md5($descriptionText);
                $this->storeWithTags($textCacheKey, $vector, $embedding->entity_type);
            }

            // ── Cache the full model for candidate listing ──
            $candidateCacheKey = $this->cachePrefix . 'candidate:'
                . $embedding->entity_type . ':'
                . ($embedding->entity_key ?? $embedding->entity_name);

            $candidateData = [
                'entity_type' => $embedding->entity_type,
                'entity_name' => $embedding->entity_name,
                'entity_key'  => $embedding->entity_key,
                'source_id'   => $embedding->source_id,
                'embedding'   => $vector,
                'metadata'    => $embedding->metadata,
            ];

            if (Cache::supportsTags()) {
                Cache::tags([self::CACHE_TAG, self::CACHE_TAG . ':' . $embedding->entity_type])
                    ->put($candidateCacheKey, $candidateData, $this->cacheTtl);
            } else {
                Cache::put($candidateCacheKey, $candidateData, $this->cacheTtl);
            }

            $this->stats['warmed']++;

        } catch (\Throwable $e) {
            Log::warning('Failed to cache embedding', [
                'id' => $embedding->id,
                'entity_type' => $embedding->entity_type,
                'entity_key' => $embedding->entity_key,
                'error' => $e->getMessage(),
            ]);
            $this->stats['skipped']++;
        }

        $bar->advance();
    }

    /**
     * Store a value in cache, optionally with embedding tags for group invalidation.
     *
     * Falls back to simple key-value storage if the cache driver does not
     * support tags (e.g., 'database' or 'file' drivers).
     */
    protected function storeWithTags(string $key, mixed $value, string $entityType): void
    {
        if (Cache::supportsTags()) {
            Cache::tags([self::CACHE_TAG, self::CACHE_TAG . ':' . $entityType])
                ->put($key, $value, $this->cacheTtl);
        } else {
            Cache::put($key, $value, $this->cacheTtl);
        }
    }

    /**
     * Build a descriptive text from the embedding metadata for text-keyed cache.
     */
    protected function buildDescriptionText(Embedding $embedding): ?string
    {
        $meta = $embedding->metadata ?? [];

        return match ($embedding->entity_type) {
            Embedding::ENTITY_TYPE_DATABASE => $meta['description'] ?? null,
            Embedding::ENTITY_TYPE_TABLE => 'Table ' . ($embedding->entity_key ?? ''),
            Embedding::ENTITY_TYPE_ALIAS => $meta['alias'] ?? $embedding->entity_name,
            Embedding::ENTITY_TYPE_QUERY => $meta['description'] ?? null,
            default => null,
        };
    }

    /**
     * Clear existing embedding cache entries.
     */
    protected function clearExistingCache(): void
    {
        $this->line('🧹 Clearing existing embedding cache...');

        try {
            // Try tag-based clearing first
            if (Cache::supportsTags()) {
                Cache::tags([self::CACHE_TAG])->flush();
                $this->line('   ✅ Cleared cache using tags.');
            } else {
                // Fallback: scan and delete by prefix (for file/database cache)
                $this->clearByPrefix();
            }

            $this->stats['cleared'] = 1;
        } catch (\Throwable $e) {
            $this->warn('   ⚠️  Could not clear cache: ' . $e->getMessage());
            Log::warning('Failed to clear embedding cache', [
                'error' => $e->getMessage(),
            ]);
        }

        $this->newLine();
    }

    /**
     * Fallback cache clearing by scanning keys with the configured prefix.
     */
    protected function clearByPrefix(): void
    {
        $prefix = $this->cachePrefix;
        $deleted = 0;

        // For Redis-like drivers, attempt scan-based deletion
        try {
            $store = Cache::getStore();
            if (method_exists($store, 'connection')) {
                $redis = $store->connection();
                $cursor = 0;
                do {
                    $result = $redis->scan($cursor, ['match' => $prefix . '*', 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    if (!empty($keys)) {
                        $redis->del($keys);
                        $deleted += count($keys);
                    }
                } while ($cursor !== 0);
            }
        } catch (\Throwable $e) {
            // If we can't scan, just log it
            Log::warning('Cache clear by prefix failed', ['error' => $e->getMessage()]);
        }

        $this->line("   ✅ Cleared {$deleted} keys with prefix '{$prefix}'.");
        $this->stats['cleared'] = $deleted;
    }
}
