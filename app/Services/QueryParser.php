<?php

namespace App\Services;

use App\DTO\RoutingResult;
use App\Models\SourceTable;
use App\Models\SourceTableColumn;
use Illuminate\Support\Facades\Log;

class QueryParser
{
    /**
     * Minimum confidence threshold for routing to be considered "semantic".
     * Below this, keyword fallback logic is used.
     */
    protected const FALLBACK_THRESHOLD = 0.15;

    protected ContextRouter $router;
    protected ?RoutingResult $lastRoutingResult = null;

    /**
     * Inject ContextRouter via constructor (Laravel auto-resolves).
     */
    public function __construct(ContextRouter $router)
    {
        $this->router = $router;
    }

    /**
     * Get the last routing result (for logging/audit in ChatController).
     */
    public function getLastRoutingResult(): ?RoutingResult
    {
        return $this->lastRoutingResult;
    }

    public function parse(string $query): array
    {
        $query = strtolower(trim($query));

        $intent = [
            'action'           => null,
            'table'            => null,
            'source'           => null,
            'columns'          => [],
            'limit'            => null,
            'filters'          => [],
            'sort'             => null,
            'confidence'       => 0,
            'aggregate'        => null,
            'aggregate_column' => null,
            // Routing metadata
            'routing_source'   => null,
            'routing_confidence' => null,
            'routing_method'   => null,
        ];

        // ── Phase 1: Context Router (semantic + keyword fallback) ──
        try {
            $routingResult = $this->router->route($query);
            $this->lastRoutingResult = $routingResult;

            // Store routing metadata in intent
            $intent['routing_confidence'] = $routingResult->confidence;
            $intent['routing_method'] = $routingResult->usedFallback ? 'keyword_fallback' : 'semantic';

            if ($routingResult->isActionable(self::FALLBACK_THRESHOLD)) {
                $intent['table']     = $routingResult->table;
                $intent['source']    = $routingResult->sourceId;
                $intent['confidence'] = (int) round($routingResult->confidence * 100);
                $intent['routing_source'] = $routingResult->sourceId;

                Log::debug('QueryParser: context routing succeeded', [
                    'query'    => mb_substr($query, 0, 100),
                    'table'    => $routingResult->table,
                    'source'   => $routingResult->sourceId,
                    'method'   => $intent['routing_method'],
                    'confidence' => $routingResult->confidence,
                ]);

                // Skip Phase 2 keyword detection since router resolved the table
                $skipKeywordPhase = true;
            } else {
                Log::debug('QueryParser: routing confidence too low, falling back to keywords', [
                    'confidence' => $routingResult->confidence,
                    'threshold'  => self::FALLBACK_THRESHOLD,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('QueryParser: context router threw exception, using keyword fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        // Actions detection using a small action list
        $actions = [
            'show',
            'list',
            'get',
            'fetch',
            'display',
            'top',
        ];

        foreach ($actions as $action) {
            if (str_contains($query, $action)) {
                $intent['action'] = 'select';
                $intent['confidence'] += 10;
                break;
            }
        }

        // ── Phase 2: Keyword-based table detection (only if semantic routing didn't resolve it) ──
        if (empty($intent['table'])) {
            $tables = SourceTable::select('table_name', 'alias', 'source_id')->get();

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

                    if (preg_match(
                        '/\b' . preg_quote($alias, '/') . '\b/i',
                        $query
                    )) {
                        $intent['table'] = $table->table_name;
                        if (empty($intent['source'])) {
                            $intent['source'] = $table->source_id;
                        }
                        $intent['confidence'] += 20;
                        break 2;
                    }
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

                    if (preg_match(
                        '/\b' . preg_quote(trim($keyword), '/') . '\b/i',
                        $columnQuery
                    )) {

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
                '/\s+order\s+by|\s+limit\s+top\s+/i',
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

        // Detect implicit comparison operators without WHERE keyword
        // Pattern: "table column over/under/above/below value"
        $implicitOps = [
            'over'         => '>',
            'under'        => '<',
            'above'        => '>',
            'below'        => '<',
            'greater than' => '>',
            'less than'    => '<',
            'more than'    => '>',
            'at least'     => '>=',
            'at most'      => '<=',
        ];

        $tableColumns = [];
        if ($intent['table']) {
            $tableColumns = \App\Models\SourceTableColumn::where('table_name', $intent['table'])
                ->pluck('column_name')
                ->toArray();
        }

        foreach ($implicitOps as $word => $operator) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\s+(\d+(?:\.\d+)?)\b/i', $query, $m)) {
                // Try to find which column this compares against
                $value = $m[1];
                $matchedColumn = null;

                // Look for a column name before the operator word
                $beforeOp = substr($query, 0, strpos($query, $m[0]));
                $words = preg_split('/\s+/', trim($beforeOp));
                $lastWord = end($words);

                if ($lastWord && in_array($lastWord, $tableColumns)) {
                    $matchedColumn = $lastWord;
                } elseif (!empty($intent['columns']) && $intent['columns'][0] !== '*') {
                    $matchedColumn = $intent['columns'][0];
                } elseif (!empty($tableColumns)) {
                    // Pick most relevant numeric column (preferred order first)
                    $preferredOrder = ['total_amount', 'amount', 'price', 'salary', 'stock', 'customer_id', 'id', 'phone'];
                    foreach ($preferredOrder as $preferred) {
                        if (in_array($preferred, $tableColumns, true)) {
                            $matchedColumn = $preferred;
                            break;
                        }
                    }
                }

                if ($matchedColumn) {
                    $intent['filters'][] = [
                        'column'   => $matchedColumn,
                        'operator' => $operator,
                        'value'    => $value,
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

        $intent['confidence'] = min(100, $intent['confidence']);

        // Default action to 'select' if a table was resolved but no keyword matched
        if (empty($intent['action']) && !empty($intent['table'])) {
            $intent['action'] = 'select';
        }

        // Ensure routing_method is set even if router wasn't used
        if (empty($intent['routing_method'])) {
            $intent['routing_method'] = $intent['source'] ? 'keyword_fallback' : 'none';
        }

        if (!$intent['table']) {
            return [
                'success' => false,
                'message' => 'No table detected',
                'intent'  => $intent,
            ];
        }

        return $intent;
    }
}
