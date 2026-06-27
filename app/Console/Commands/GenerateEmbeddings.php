<?php

namespace App\Console\Commands;

use App\Models\Embedding;
use App\Models\SourceTable;
use App\Models\SourceTableColumn;
use App\Services\VectorEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateEmbeddings extends Command
{
    protected $signature = 'chatbot:generate-embeddings
                            {--type= : Only generate for this entity type (database, table, alias, column)}
                            {--force : Regenerate existing embeddings}
                            {--batch=50 : Batch size for embedding generation}';

    protected $description = 'Generate embedding vectors for all registered sources, tables, aliases, and columns';

    protected VectorEmbeddingService $embeddingService;

    public function __construct(VectorEmbeddingService $embeddingService)
    {
        parent::__construct();
        $this->embeddingService = $embeddingService;
    }

    public function handle(): int
    {
        $type  = $this->option('type');
        $force = $this->option('force');
        $batch = (int) $this->option('batch');

        $this->info('🚀 Starting embedding generation...');
        $startTime = microtime(true);

        $totalGenerated = 0;

        // ── 1. Database Embeddings ──
        if (!$type || $type === 'database') {
            $totalGenerated += $this->generateDatabaseEmbeddings($force);
        }

        // ── 2. Table Embeddings ──
        if (!$type || $type === 'table') {
            $totalGenerated += $this->generateTableEmbeddings($force, $batch);
        }

        // ── 3. Alias Embeddings ──
        if (!$type || $type === 'alias') {
            $totalGenerated += $this->generateAliasEmbeddings($force, $batch);
        }

        // ── 4. Query Type Embeddings ──
        if (!$type || $type === 'query_type') {
            $totalGenerated += $this->generateQueryTypeEmbeddings($force);
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("✅ Done! Generated {$totalGenerated} embeddings in {$elapsed}s.");

        return Command::SUCCESS;
    }

    protected function generateDatabaseEmbeddings(bool $force): int
    {
        $this->info("\n📦 Generating database embeddings...");
        $sources = config('chatbot.sources', []);
        $count = 0;

        foreach ($sources as $sourceId => $config) {
            if (!$force && Embedding::ofType('database')->where('entity_key', $sourceId)->exists()) {
                $this->line("   ⏭  Skipped {$sourceId} (already exists)");
                continue;
            }

            $description = $config['description'] ?? '';
            $tables = implode(', ', $config['tables'] ?? []);
            $embeddingText = "{$description}. Tables: {$tables}. Source: {$sourceId}.";
            $columns = $this->getAllColumnsForSource($sourceId);
            if (!empty($columns)) {
                $embeddingText .= " Columns: " . implode(', ', $columns) . ".";
            }

            $this->line("   🔄 Embedding: {$sourceId}...");
            $vector = $this->embeddingService->generateEmbedding($embeddingText);

            Embedding::updateOrCreate(
                ['entity_type' => 'database', 'entity_key' => $sourceId],
                [
                    'entity_name' => $sourceId,
                    'source_id'   => $sourceId,
                    'embedding'   => $vector,
                    'metadata'    => [
                        'description' => $description,
                        'tables'      => $config['tables'] ?? [],
                    ],
                ]
            );

            $count++;
            $this->line("   ✅ {$sourceId} embedded (384 dims)");
        }

        return $count;
    }

    protected function generateTableEmbeddings(bool $force, int $batch): int
    {
        $this->info("\n📊 Generating table embeddings...");
        $tables = SourceTable::all();
        $count = 0;

        $texts = [];
        $records = [];

        foreach ($tables as $table) {
            if (!$force && Embedding::ofType('table')->where('entity_key', $table->table_name)->exists()) {
                $this->line("   ⏭  Skipped table {$table->table_name} (already exists)");
                continue;
            }

            // Gather column info
            $columns = SourceTableColumn::where('table_name', $table->table_name)->pluck('column_name')->toArray();
            $aliases = array_filter(array_map('trim', explode(',', $table->alias ?? '')));

            $descParts = ["Table {$table->table_name}"];
            if (!empty($aliases)) {
                $descParts[] = "also known as: " . implode(', ', $aliases);
            }
            if (!empty($columns)) {
                $descParts[] = "columns: " . implode(', ', $columns);
            }

            $text = implode('. ', $descParts) . '.';
            $texts[] = $text;
            $records[] = [
                'table' => $table,
                'columns' => $columns,
                'aliases' => $aliases,
            ];
        }

        // Batch-generate embeddings
        foreach (array_chunk($texts, $batch, true) as $chunkIndices => $chunkTexts) {
            $this->line("   🔄 Generating batch of " . count($chunkTexts) . " table embeddings...");
            $vectors = $this->embeddingService->generateEmbeddings($chunkTexts);

            foreach ($vectors as $i => $vector) {
                $idx = ($chunkIndices * $batch) + $i;
                $record = $records[$idx] ?? null;
                if (!$record) continue;

                Embedding::updateOrCreate(
                    ['entity_type' => 'table', 'entity_key' => $record['table']->table_name],
                    [
                        'entity_name' => $record['table']->table_name,
                        'source_id'   => $record['table']->source_id,
                        'embedding'   => $vector,
                        'metadata'    => [
                            'columns' => $record['columns'],
                            'aliases' => $record['aliases'],
                        ],
                    ]
                );
                $count++;
            }

            $this->line("   ✅ Batch complete ({$count} total table embeddings)");
        }

        return $count;
    }

    protected function generateAliasEmbeddings(bool $force, int $batch): int
    {
        $this->info("\n🔤 Generating alias embeddings...");
        $tables = SourceTable::all();
        $count = 0;

        $texts = [];
        $records = [];

        foreach ($tables as $table) {
            $aliases = array_filter(array_map('trim', explode(',', $table->alias ?? '')));
            if (empty($aliases)) continue;

            foreach ($aliases as $alias) {
                $aliasKey = "{$table->table_name}:alias:{$alias}";

                if (!$force && Embedding::ofType('alias')->where('entity_key', $aliasKey)->exists()) {
                    continue;
                }

                $text = "{$alias} refers to the {$table->table_name} table in {$table->source_id}. It contains data about {$table->table_name}.";
                $texts[] = $text;
                $records[] = [
                    'alias'    => $alias,
                    'table'    => $table,
                    'aliasKey' => $aliasKey,
                ];
            }
        }

        foreach (array_chunk($texts, $batch, true) as $chunkIndices => $chunkTexts) {
            $this->line("   🔄 Generating batch of " . count($chunkTexts) . " alias embeddings...");
            $vectors = $this->embeddingService->generateEmbeddings($chunkTexts);

            foreach ($vectors as $i => $vector) {
                $idx = ($chunkIndices * $batch) + $i;
                $record = $records[$idx] ?? null;
                if (!$record) continue;

                Embedding::updateOrCreate(
                    ['entity_type' => 'alias', 'entity_key' => $record['aliasKey']],
                    [
                        'entity_name' => $record['alias'],
                        'source_id'   => $record['table']->source_id,
                        'embedding'   => $vector,
                        'metadata'    => [
                            'alias'      => $record['alias'],
                            'table_name' => $record['table']->table_name,
                        ],
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    protected function generateQueryTypeEmbeddings(bool $force): int
    {
        $this->info("\n🔍 Generating query type embeddings...");
        $types = config('chatbot.query_types', []);
        $count = 0;

        foreach ($types as $type) {
            if (!$force && Embedding::ofType('query_type')->where('entity_key', $type['name'])->exists()) {
                $this->line("   ⏭  Skipped {$type['name']} (already exists)");
                continue;
            }

            $this->line("   🔄 Embedding: {$type['name']}...");
            $vector = $this->embeddingService->generateEmbedding($type['description']);

            Embedding::updateOrCreate(
                ['entity_type' => 'query_type', 'entity_key' => $type['name']],
                [
                    'entity_name' => $type['name'],
                    'embedding'   => $vector,
                    'metadata'    => ['description' => $type['description']],
                ]
            );

            $count++;
            $this->line("   ✅ {$type['name']} embedded");
        }

        return $count;
    }

    /**
     * Collect all column names across all tables in a source.
     */
    protected function getAllColumnsForSource(string $sourceId): array
    {
        $tables = SourceTable::where('source_id', $sourceId)->pluck('table_name');
        $columns = [];

        foreach ($tables as $table) {
            $cols = SourceTableColumn::where('table_name', $table)->pluck('column_name')->toArray();
            $columns = array_merge($columns, $cols);
        }

        return array_unique($columns);
    }
}
