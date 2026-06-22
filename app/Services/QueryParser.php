<?php

namespace App\Services;

use App\Models\SourceTable;
use App\Services\RegistryService;

class QueryParser
{
    public function parse(string $query): array
    {
        $query = strtolower(trim($query));

        $intent = [
            'action' => null,
            'table'  => null,
            'source' => 'db_01',
            'limit'  => null,
            'filters'=> [],
            'columns'=> [],
            'sort'   => null,
        ];

        // Attempt to resolve table & source from SourceTable aliases (if model/db available)
        try {
            $sources = SourceTable::all();
            foreach ($sources as $item) {
                if (str_contains($query, strtolower($item->alias))) {
                    $intent['table']  = $item->table_name;
                    $intent['source'] = $item->source_id;
                    break;
                }
            }
        } catch (\Throwable $e) {
            // If SourceTable or DB isn't available, ignore and fall back to simple checks
        }

        // Actions detection using a small action list
        $actions = [
            'show',
            'list',
            'get',
            'fetch',
            'display'
        ];

        foreach ($actions as $action) {
            if (str_contains($query, $action)) {
                $intent['action'] = 'select';
                break;
            }
        }

        // Fallback table detection for common keywords
        if (empty($intent['table'])) {
            if (str_contains($query, 'customer') || str_contains($query, 'customers')) {
                $intent['table'] = 'customers';
            }
        }

        // Detect TOP n (e.g. "top 10")
        if (preg_match('/top\s+(\d+)/', $query, $matches)) {
            $intent['limit'] = (int)$matches[1];
        }

        // Detect LIMIT n
        if (preg_match('/limit\s+(\d+)/', $query, $matches)) {
            $intent['limit'] = (int)$matches[1];
        }

        // Detect WHERE clauses (allow multi-word values, with or without quotes)
        // Captures until next clause (order by, limit, top) or end of string
        if (preg_match_all('/where\s+(\w+)\s*=\s*(.+?)(?=(\s+order\s+by|\s+limit|\s+top\s+\d+|$))/i', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $col = $m[1];
                $val = trim($m[2]);
                // strip surrounding quotes if present
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                $val = trim($val);

                $intent['filters'][] = [
                    'column'   => $col,
                    'operator' => '=',
                    'value'    => $val,
                ];
            }
        }

        // Detect selected columns using registry as single source-of-truth
        $availableColumns = [];
        if (!empty($intent['table'])) {
            try {
                $availableColumns = RegistryService::getColumns($intent['table']);
                $availableColumns = array_map('strtolower', (array) $availableColumns);
            } catch (\Throwable $e) {
                $availableColumns = [];
            }
        }

        // fallback small whitelist when registry is not available
        if (empty($availableColumns)) {
            $availableColumns = RegistryService::getColumns($intent['table'] ?? '') ?: ['id', 'name', 'email', 'phone'];
        }

        foreach ($availableColumns as $column) {
            if (str_contains($query, $column)) {
                if (!in_array($column, $intent['columns'], true)) {
                    $intent['columns'][] = $column;
                }
            }
        }

        if (empty($intent['columns'])) {
            $intent['columns'] = ['*'];
        }

        // Detect ORDER BY column direction
        if (preg_match('/order by\s+(\w+)\s+(asc|desc)/', $query, $matches)) {
            $intent['sort'] = [
                'column'    => $matches[1],
                'direction' => $matches[2],
            ];
        }

        return $intent;
    }
}