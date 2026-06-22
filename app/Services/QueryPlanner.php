<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Services\RegistryService;
use InvalidArgumentException;

class QueryPlanner
{
    /**
     * Create an execution plan from an intent array.
     *
     * @param array $intent
     * @return array
     */
    public function planFromIntent(array $intent): array
    {
        return [
            'connection' => $intent['source'] ?? ($intent['connection'] ?? 'db_01'),
            'table'      => $intent['table'] ?? null,
            'columns'    => $intent['columns'] ?? ['*'],
            'filters'    => $intent['filters'] ?? [],
            'sort'       => $intent['sort'] ?? null,
            'limit'      => $intent['limit'] ?? null,
        ];
    }

    /**
     * Build and return the query builder for inspection or further modification.
     *
     * @param array $plan
     * @return mixed
     */
    public function build(array $plan)
    {
        // Validate and normalize plan (ensures allowed table/columns)
        $plan = $this->normalizePlan($plan);

        $connection = $plan['connection'] ?? ($plan['source'] ?? 'db_01');
        $table = $plan['table'];
        $columns = $plan['columns'] ?? ['*'];
        $filters = $plan['filters'] ?? [];
        $sort = $plan['sort'] ?? null;
        $limit = $plan['limit'] ?? null;

        $query = DB::connection($connection)->table($table);

        if (!empty($columns) && $columns[0] !== '*') {
            $query = $query->select($columns);
        }

        foreach ($filters as $filter) {
            $col = $filter['column'] ?? null;
            $op  = $filter['operator'] ?? '=';
            $val = $filter['value'] ?? null;
            if ($col === null) {
                continue;
            }
            $query = $query->where($col, $op, $val);
        }

        if (!empty($sort) && !empty($sort['column'])) {
            $allowedColumns = RegistryService::getColumns($plan['table']);
            if (!in_array(strtolower($sort['column']), $allowedColumns, true)) {
                throw new InvalidArgumentException('Invalid sort column: ' . $sort['column']);
            }
            $dir = strtolower($sort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $query = $query->orderBy($sort['column'], $dir);
        }

        if (!empty($limit) && is_numeric($limit) && (int)$limit > 0) {
            $query = $query->limit((int)$limit);
        }

        return $query;
    }

    /**
     * Normalize and validate a plan against registry tables.
     * Ensures the table is registered and requested columns exist in registry.
     * Expands '*' to the registered allowed columns.
     *
     * @param array $plan
     * @return array
     */
    private function normalizePlan(array $plan): array
    {
        $table = $plan['table'] ?? null;
        if (empty($table)) {
            throw new InvalidArgumentException('Plan must include a table name.');
        }

        // Validate table exists in source_tables
        $exists = DB::table('source_tables')->where('table_name', $table)->exists();
        if (!$exists) {
            throw new InvalidArgumentException('Invalid table: ' . $table);
        }

        // Get allowed columns for this table from registry
        $allowedColumns = RegistryService::getColumns($table);

        $columns = $plan['columns'] ?? ['*'];
        // If wildcard, expand to allowed columns
        if (!empty($columns) && $columns[0] === '*') {
            $plan['columns'] = $allowedColumns;
        } else {
            // Validate each requested column
            $normalized = [];
            foreach ($columns as $col) {
                $colLower = strtolower($col);
                if (!in_array($colLower, $allowedColumns, true)) {
                    throw new InvalidArgumentException('Invalid column: ' . $col);
                }
                $normalized[] = $col;
            }
            $plan['columns'] = $normalized;
        }

        return $plan;
    }
}
