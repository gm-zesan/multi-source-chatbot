<?php

namespace App\Services;

use App\Models\Embedding;
use App\Models\SourceTable;
use App\Models\SourceTableColumn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RegistryService
 *
 * Central source of truth for table and column metadata across all
 * connected databases. Provides cached lookups for table names,
 * column listings, alias resolution, and embedding-based semantic
 * resolution.
 *
 * All methods are cached to avoid repeated DB queries.
 * Call refreshCache() after any registry changes.
 */
class RegistryService
{
    /**
     * Cache key prefix for registry data.
     */
    protected const CACHE_PREFIX = 'registry:';

    /**
     * Cache TTL for registry data (1 hour).
     */
    protected const CACHE_TTL = 3600;

    /**
     * Get table metadata by table name or alias.
     *
     * Checks cache first, then queries source_tables.
     * Supports resolution by exact table_name or alias.
     *
     * @param  string     $tableName The table name or alias to look up
     * @return array|null {source_id: string, table_name: string, alias: string|null} or null
     */
    public function getTable(string $tableName): ?array
    {
        $cacheKey = self::CACHE_PREFIX . 'table:' . md5(strtolower($tableName));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tableName) {
            // Try exact table_name match first
            $record = DB::table('source_tables')
                ->where('table_name', $tableName)
                ->first();

            if ($record) {
                return [
                    'source_id'  => $record->source_id,
                    'table_name' => $record->table_name,
                    'alias'      => $record->alias,
                ];
            }

            // Fallback: match against alias column
            // Uses LIKE with comma-padding for cross-database compatibility
            $searchTerm = strtolower($tableName);
            $record = DB::table('source_tables')
                ->where('alias', 'LIKE', "%{$searchTerm}%")
                ->orWhere(DB::raw("LOWER(alias)"), 'LIKE', "%{$searchTerm}%")
                ->orderBy('id')
                ->first();

            if ($record) {
                return [
                    'source_id'  => $record->source_id,
                    'table_name' => $record->table_name,
                    'alias'      => $record->alias,
                ];
            }

            return null;
        });
    }

    /**
     * Get all column names for a given table.
     *
     * First checks source_table_columns registry, then falls back
     * to reading the actual database schema via INFORMATION_SCHEMA.
     *
     * @param  string $tableName The canonical table name
     * @return array<int, string> List of column names
     */
    public function getColumns(string $tableName): array
    {
        $cacheKey = self::CACHE_PREFIX . 'columns:' . md5(strtolower($tableName));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tableName) {
            // 1. Try the registry table
            $columns = SourceTableColumn::where('table_name', $tableName)
                ->pluck('column_name')
                ->toArray();

            if (!empty($columns)) {
                return array_map('strtolower', $columns);
            }

            // 2. Fallback: read from actual database schema
            try {
                $tableInfo = $this->getTable($tableName);
                if ($tableInfo && isset($tableInfo['source_id'])) {
                    $schemaColumns = DB::connection($tableInfo['source_id'])
                        ->getSchemaBuilder()
                        ->getColumnListing($tableName);

                    if (!empty($schemaColumns)) {
                        // Cache back to registry for future lookups
                        foreach ($schemaColumns as $col) {
                            SourceTableColumn::firstOrCreate([
                                'table_name'  => $tableName,
                                'column_name' => $col,
                            ]);
                        }

                        return array_map('strtolower', $schemaColumns);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch columns from schema', [
                    'table' => $tableName,
                    'error' => $e->getMessage(),
                ]);
            }

            return [];
        });
    }

    /**
     * Resolve a table name or alias to its canonical table and source,
     * using embedding-based semantic matching as the primary strategy.
     *
     * Resolution order:
     *   1. Exact match in source_tables (table_name or alias)
     *   2. Semantic match using embeddings (via ContextRouter)
     *   3. Keyword/substring fallback
     *
     * @param  string          $alias        The user-provided table reference
     * @param  ContextRouter   $router       Optional router for semantic matching
     * @return array{source_id: string, table_name: string, confidence: float}|null
     */
    public function resolveTable(string $alias, ?ContextRouter $router = null): ?array
    {
        // 1. Exact or alias match (fast path)
        $exact = $this->getTable($alias);
        if ($exact !== null) {
            return [
                'source_id'  => $exact['source_id'],
                'table_name' => $exact['table_name'],
                'confidence' => 1.0,
            ];
        }

        // 2. Semantic match via embeddings
        if ($router !== null) {
            try {
                $result = $router->route($alias);
                if ($result->isActionable(0.5) && $result->table !== null) {
                    return [
                        'source_id'  => $result->sourceId ?? $this->inferSourceId($result->table),
                        'table_name' => $result->table,
                        'confidence' => $result->confidence,
                    ];
                }
            } catch (\Throwable $e) {
                Log::debug('Semantic table resolution failed', [
                    'alias' => $alias,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. Keyword/substring fallback across all source_tables
        $aliasLower = strtolower(trim($alias));
        $tables = SourceTable::select('table_name', 'source_id', 'alias')->get();

        $best = null;
        $bestScore = 0;

        foreach ($tables as $table) {
            $names = array_map('trim', explode(',', strtolower($table->alias ?? '')));
            $names[] = strtolower($table->table_name);
            $names = array_unique($names);

            foreach ($names as $name) {
                if ($name === '') continue;
                // Prefer exact substring match
                if (str_contains($aliasLower, $name) || str_contains($name, $aliasLower)) {
                    $score = strlen($name) / max(strlen($aliasLower), 1);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = [
                            'source_id'  => $table->source_id,
                            'table_name' => $table->table_name,
                            'confidence' => round(min(0.9, $score), 2),
                        ];
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Infer the source_id for a table name when no explicit source is available.
     *
     * @param  string $tableName
     * @return string|null
     */
    public function inferSourceId(string $tableName): ?string
    {
        $record = DB::table('source_tables')
            ->where('table_name', $tableName)
            ->value('source_id');

        return $record ?: null;
    }

    /**
     * Register a new table in the registry.
     *
     * @param  string $sourceId  Database connection name
     * @param  string $tableName Canonical table name
     * @param  string $aliases   Comma-separated aliases (optional)
     * @return bool
     */
    public function registerTable(string $sourceId, string $tableName, string $aliases = ''): bool
    {
        try {
            SourceTable::updateOrCreate(
                ['source_id' => $sourceId, 'table_name' => $tableName],
                ['alias' => $aliases]
            );

            $this->refreshCache();
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to register table', [
                'source' => $sourceId,
                'table'  => $tableName,
                'error'  => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Register columns for a table in the registry.
     *
     * @param  string   $tableName  Canonical table name
     * @param  string[] $columnNames
     * @return bool
     */
    public function registerColumns(string $tableName, array $columnNames): bool
    {
        try {
            foreach ($columnNames as $columnName) {
                SourceTableColumn::firstOrCreate([
                    'table_name'  => $tableName,
                    'column_name' => $columnName,
                ]);
            }

            $this->refreshCache();
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to register columns', [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clear all registry caches.
     *
     * Call this after any changes to source_tables or source_table_columns
     * to ensure subsequent lookups return fresh data.
     */
    public function refreshCache(): void
    {
        Cache::getStore()->flush(); // Simple but effective for registry data
        Log::info('Registry cache cleared');
    }

    /**
     * Check if a table exists in the registry.
     *
     * @param  string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        return $this->getTable($tableName) !== null;
    }

    /**
     * Get all registered tables grouped by source.
     *
     * @return array<string, array<int, string>> source_id => [table_name, ...]
     */
    public function getAllTablesGrouped(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'all_tables_grouped';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $tables = SourceTable::select('source_id', 'table_name')
                ->orderBy('source_id')
                ->orderBy('table_name')
                ->get();

            $grouped = [];
            foreach ($tables as $table) {
                $grouped[$table->source_id][] = $table->table_name;
            }

            // Deduplicate table names per source
            foreach ($grouped as $source => $names) {
                $grouped[$source] = array_values(array_unique($names));
            }

            return $grouped;
        });
    }
}
