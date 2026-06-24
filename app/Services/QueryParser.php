<?php

namespace App\Services;

use App\Models\SourceTable;
use App\Models\SourceTableColumn;

class QueryParser
{
    public function parse(string $query): array
    {
        $query = strtolower(trim($query));

        $intent = [
            'action' => null,
            'table'  => null,
            'columns'=> [],
            'limit'  => null,
            'filters'=> [],
            'sort'   => null,
            'confidence' => 0,
            'aggregate' => null,
            'aggregate_column' => null,
        ];

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
                $intent['confidence'] += 10;
                break;
            }
        }

        // Table detection using the source_tables registry
        $tables = SourceTable::select('table_name', 'alias')->get();

        foreach ($tables as $table) {

            $aliases = [];

            if (!empty($table->alias)) {
                $aliases = array_map(
                    'trim',
                    explode(',', strtolower($table->alias))
                );
            }

            $aliases[] = strtolower($table->table_name);

            foreach ($aliases as $alias) {

                if (
                    preg_match(
                        '/\b' . preg_quote($alias, '/') . '\b/i',
                        $query
                    )
                )if (str_contains($query, $alias)) {
                    $intent['table'] = $table->table_name;
                    $intent['confidence'] += 20;
                    break 2;
                }
            }
        }


        $columnQuery = preg_split('/\s+where\s+|\s+order\s+by\s+|\s+limit\s+|\s+top\s+\d+/i', $query)[0];

        // Column detection using the source_table_columns registry
        if ($intent['table']) {
            $columnKeywords = SourceTableColumn::where(
                'table_name',
                $intent['table']
            )->get();

            foreach ($columnKeywords as $column) {

                $keywords = [];

                if (!empty($column->alias)) {
                    $keywords = explode(',', strtolower($column->alias));
                }

                $keywords[] = strtolower($column->column_name);

                foreach ($keywords as $keyword) {

                    if (
                        preg_match(
                            '/\b' . preg_quote(trim($keyword), '/') . '\b/i',
                            $columnQuery
                        )
                    )if (str_contains($columnQuery, trim($keyword))) {

                        if (!in_array($column->column_name, $intent['columns'])) {
                            $intent['columns'][] = $column->column_name;
                        }

                        $intent['confidence'] += 5;

                        break;
                    }
                }
            }
        }


        // Aggregate function detection
        $aggregateMap = [
            'count'   => 'COUNT',
            'total'   => 'SUM',
            'sum'     => 'SUM',
            'average' => 'AVG',
            'avg'     => 'AVG',
            'minimum' => 'MIN',
            'min'     => 'MIN',
            'maximum' => 'MAX',
            'max'     => 'MAX',
        ];

        $intent['aggregate_column'] = null;

        foreach ($aggregateMap as $keyword => $function) {

            if (
                preg_match(
                    '/\b' . preg_quote($keyword, '/') . '\b/i',
                    $columnQuery
                )
            ) {

                $intent['aggregate'] = $function;
                $intent['action'] = 'select';
                $intent['confidence'] += 10;

                // COUNT usually works on *
                if ($function === 'COUNT') {

                    $intent['aggregate_column'] = '*';
                }
                // SUM, AVG, MIN, MAX need a target column
                elseif (!empty($intent['columns'])) {

                    $intent['aggregate_column'] = $intent['columns'][0];
                }

                break;
            }
        }

        // Default columns only when no aggregate detected
        if (
            empty($intent['columns']) &&
            empty($intent['aggregate'])
        ) {
            $intent['columns'] = ['*'];
        }

        // Detect TOP n (e.g. "top 10")
        if (preg_match('/top\s+(\d+)/i', $query, $matches)) {
            $intent['limit'] = (int)$matches[1];
            $intent['confidence'] += 5;
        }

        // Detect LIMIT n
        if (preg_match('/limit\s+(\d+)/i', $query, $matches)) {
            $intent['limit'] = (int)$matches[1];
            $intent['confidence'] += 5;
        }

        // Detect WHERE clauses (allow multi-word values, with or without quotes)
        // Captures until next clause (order by, limit, top) or end of string
        if (preg_match('/where\s+(.+)/i', $query, $match)) {
            $wherePart = $match[1];
            $wherePart = preg_split(
                '/\s+order\s+by|\s+limit|\s+top\s+/i',
                $wherePart
            )[0];
            $conditions = preg_split(
                '/\s+(and|or)\s+/i',
                $wherePart
            );

            foreach ($conditions as $condition) {
                if (
                    preg_match(
                        '/(\w+)\s*(=|!=|>=|<=|>|<)\s*(.+)/',
                        trim($condition),
                        $m
                    )
                ) {
                    $intent['filters'][] = [
                        'column'   => trim($m[1]),
                        'operator' => trim($m[2]),
                        'value'    => trim($m[3], '"\' ')
                    ];
                    $intent['confidence'] += 5;
                }
            }
        }

        // Detect ORDER BY column direction
        if (
            preg_match(
                '/order by\s+(\w+)(?:\s+(asc|desc))?/i',
                $query,
                $matches
            )
        ) {

            $intent['sort'] = [
                'column' => $matches[1],
                'direction' => $matches[2] ?? 'asc'
            ];
            $intent['confidence'] += 5;
        }

        $intent['confidence'] = min(100,$intent['confidence']);

        if (!$intent['table']) {
            return [
                'success' => false,
                'message' => 'No table detected',
                'intent' => $intent
            ];
        }

        return $intent;
    }
}
