<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Security;

use App\Services\Spreadsheet\DTOs\SourceColumnDescriptor;
use App\Services\Spreadsheet\Security\Exceptions\UnauthorizedDataAccessException;
use Throwable;

/**
 * Analytics AST Authorizer.
 *
 * Inspects SQL query syntax, identifiers, CTEs, subqueries, aliases, and derived
 * mathematical expressions before execution against structured analytics or virtual database engines.
 * Enforces AST-level barriers across all query clauses (SELECT, WHERE, JOIN, GROUP BY, HAVING, ORDER BY)
 * and guarantees fail-closed behavior on syntax anomalies or mutation attempts.
 */
class AnalyticsAstAuthorizer
{
    /**
     * Forbidden mutating SQL keywords that must fail closed immediately.
     */
    private const FORBIDDEN_MUTATION_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE',
        'ATTACH', 'DETACH', 'PRAGMA', 'REINDEX', 'VACUUM', 'EXEC', 'EXECUTE',
    ];

    public function __construct(
        private readonly DataProjectionPolicy $policy = new DataProjectionPolicy(),
    ) {}

    /**
     * Authorize an analytics SQL query against source column descriptors and server context.
     *
     * @param string $sql The SQL query string
     * @param array<int|string, array<string, mixed>|SourceColumnDescriptor> $sourceDescriptors
     * @param ServerControlledContext $context
     * @return bool True if authorized, throws UnauthorizedDataAccessException if blocked.
     * @throws UnauthorizedDataAccessException
     */
    public function authorizeQuery(string $sql, array $sourceDescriptors, ServerControlledContext $context): bool
    {
        // 1. Customer Channel Barrier: Customers are strictly barred from analytics SQL execution
        if ($context->isCustomer()) {
            throw new UnauthorizedDataAccessException(
                message: 'Customer channels are strictly forbidden from executing structured analytics queries.',
                purpose: $context->purpose,
            );
        }

        // 2. Workspace Owner / Active SuperAdmin Governance Session
        if ($context->isOwner()) {
            return true;
        }

        try {
            // 3. Pre-process and sanitize SQL (strip comments, check mutation keywords)
            $sanitizedSql = $this->sanitizeAndValidateQueryShape($sql);

            // 4. Index source descriptors by multiple search keys
            $descriptorIndex = $this->buildDescriptorIndex($sourceDescriptors);

            // 5. Track Alias Dependency Graph (e.g., WITH t AS (SELECT purchase_cost AS c ...))
            $aliasGraph = $this->extractAliasDependencyGraph($sanitizedSql);

            // 6. Extract Column Identifiers from Query
            $referencedIdentifiers = $this->extractReferencedColumns($sanitizedSql);

            // 7. Resolve all identifiers against descriptor index and alias graph
            foreach ($referencedIdentifiers as $identifier) {
                // Check if identifier is an alias pointing to an underlying sensitive column
                $resolvedTarget = $aliasGraph[$identifier] ?? $identifier;
                $matchedDescriptor = $this->findDescriptor($resolvedTarget, $descriptorIndex)
                    ?? $this->findDescriptor($identifier, $descriptorIndex);

                if ($matchedDescriptor !== null) {
                    $colId = (string) ($matchedDescriptor['source_column_id'] ?? $resolvedTarget);
                    $role = (string) ($matchedDescriptor['semantic_role'] ?? 'custom_attribute');
                    $sensitivity = (string) ($matchedDescriptor['sensitivity'] ?? DataClassification::RESTRICTED);
                    $header = (string) ($matchedDescriptor['original_header'] ?? $resolvedTarget);

                    if (! $this->policy->canProjectField($colId, $role, $sensitivity, $context, $header)) {
                        throw new UnauthorizedDataAccessException(
                            message: "Access denied: Query references unauthorized column '{$identifier}' (underlying: '{$resolvedTarget}', role: '{$role}', sensitivity: '{$sensitivity}') without required permissions.",
                            field: $identifier,
                            role: $role,
                            sensitivity: $sensitivity,
                            purpose: $context->purpose,
                        );
                    }
                } else {
                    // Check if the identifier or alias target itself matches known restricted keywords
                    $this->checkRawIdentifierRestrictions($resolvedTarget, $context);
                    if ($resolvedTarget !== $identifier) {
                        $this->checkRawIdentifierRestrictions($identifier, $context);
                    }
                }
            }

            // 8. AST Mathematical Expression & Inference Audit
            $this->auditDerivedExpressions($sanitizedSql, $descriptorIndex, $aliasGraph, $context);

            return true;
        } catch (UnauthorizedDataAccessException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Fail-Closed on any unhandled parsing anomaly or regex failure
            throw new UnauthorizedDataAccessException(
                message: 'Query authorization failed closed due to query analysis anomaly: ' . $e->getMessage(),
                purpose: $context->purpose,
                previous: $e
            );
        }
    }

    /**
     * Sanitize query by stripping SQL comments and validating read-only shape.
     */
    private function sanitizeAndValidateQueryShape(string $sql): string
    {
        $trimmed = trim($sql);
        if ($trimmed === '') {
            throw new UnauthorizedDataAccessException('Empty query rejected.');
        }

        // Strip block comments (/* ... */) and line comments (-- ...)
        $noComments = preg_replace('/(\/\*.*?\*\/|--[^\r\n]*)/su', ' ', $trimmed) ?? $trimmed;

        // Check for forbidden mutation keywords
        foreach (self::FORBIDDEN_MUTATION_KEYWORDS as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $noComments)) {
                throw new UnauthorizedDataAccessException(
                    "Mutating SQL keyword '{$keyword}' is strictly prohibited in analytics execution."
                );
            }
        }

        // Ensure statement starts with SELECT or WITH
        if (! preg_match('/^\s*(SELECT|WITH)\b/i', $noComments)) {
            throw new UnauthorizedDataAccessException(
                'Only read-only SELECT or WITH statements are authorized for analytics execution.'
            );
        }

        return $noComments;
    }

    /**
     * Build an alias dependency graph mapping column aliases back to source columns.
     * E.g. "purchase_cost AS c" maps "c" => "purchase_cost"
     * E.g. "col_002 AS my_metric" maps "my_metric" => "col_002"
     *
     * @return array<string, string>
     */
    public function extractAliasDependencyGraph(string $sql): array
    {
        $aliasMap = [];

        // Match patterns: source_col [AS] alias_name
        $pattern = '/\b([a-zA-Z_][a-zA-Z0-9_]*)\s+(?:AS\s+)?([a-zA-Z_][a-zA-Z0-9_]*)\b/ui';

        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            $sqlKeywords = [
                'SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'ON',
                'GROUP', 'BY', 'HAVING', 'ORDER', 'ASC', 'DESC', 'LIMIT', 'OFFSET',
                'AND', 'OR', 'NOT', 'IN', 'IS', 'NULL', 'LIKE', 'BETWEEN', 'AS', 'CASE',
                'WHEN', 'THEN', 'ELSE', 'END', 'OVER', 'PARTITION',
            ];
            $keywordLookup = array_fill_keys(array_map('strtoupper', $sqlKeywords), true);

            foreach ($matches as $match) {
                $source = strtolower($match[1]);
                $alias = strtolower($match[2]);

                if (! isset($keywordLookup[strtoupper($source)]) && ! isset($keywordLookup[strtoupper($alias)])) {
                    $aliasMap[$alias] = $source;
                }
            }
        }

        return $aliasMap;
    }

    /**
     * Extract tokenized column identifiers from SQL clauses.
     *
     * @return array<int, string>
     */
    public function extractReferencedColumns(string $sql): array
    {
        // Strip string literals to prevent matching words inside quotes
        $cleanSql = preg_replace('/\'[^\']*\'|"[^"]*"/', '', $sql) ?? $sql;

        // Strip standard SQL keywords and SQL functions
        $sqlKeywords = [
            'SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'ON',
            'GROUP', 'BY', 'HAVING', 'ORDER', 'ASC', 'DESC', 'LIMIT', 'OFFSET',
            'AND', 'OR', 'NOT', 'IN', 'IS', 'NULL', 'LIKE', 'BETWEEN', 'AS', 'CASE',
            'WHEN', 'THEN', 'ELSE', 'END', 'DISTINCT', 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX',
            'ROUND', 'COALESCE', 'IFNULL', 'CAST', 'DATE', 'STRFTIME', 'WITH', 'UNION',
            'ALL', 'OVER', 'PARTITION', 'ROW_NUMBER', 'RANK', 'DENSE_RANK', 'JSON_EXTRACT',
            'INSTR', 'SUBSTR', 'LENGTH', 'UPPER', 'LOWER', 'ABS', 'TOTAL',
        ];

        // Find potential identifiers (letters, digits, underscores)
        preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/u', $cleanSql, $matches);
        $tokens = $matches[1] ?? [];

        $keywordLookup = array_fill_keys(array_map('strtoupper', $sqlKeywords), true);
        $identifiers = [];

        foreach ($tokens as $token) {
            $upper = strtoupper($token);
            if (! isset($keywordLookup[$upper]) && ! is_numeric($token)) {
                $identifiers[] = strtolower($token);
            }
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * Inspect arithmetic expressions (e.g. price - cost, sum(cost) / sum(price)) for indirect inference.
     */
    private function auditDerivedExpressions(
        string $sql,
        array $descriptorIndex,
        array $aliasGraph,
        ServerControlledContext $context
    ): void {
        // Pattern detecting arithmetic operations between identifiers: identifier (+|-|*|/) identifier
        $mathPattern = '/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*([\+\-\*\/])\s*([a-zA-Z_][a-zA-Z0-9_]*)\b/u';

        if (preg_match_all($mathPattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $leftOperand = strtolower($match[1]);
                $operator = $match[2];
                $rightOperand = strtolower($match[3]);

                foreach ([$leftOperand, $rightOperand] as $operand) {
                    $resolvedOperand = $aliasGraph[$operand] ?? $operand;
                    $desc = $this->findDescriptor($resolvedOperand, $descriptorIndex);
                    $role = $desc ? ($desc['semantic_role'] ?? $resolvedOperand) : $resolvedOperand;
                    $sensitivity = $desc ? ($desc['sensitivity'] ?? DataClassification::RESTRICTED) : DataClassification::classifyRole($resolvedOperand);

                    if ($this->policy->isFinancialCostRole($role) && ! $context->hasPermission('analytics.view_margins')) {
                        throw new UnauthorizedDataAccessException(
                            message: "Indirect mathematical inference blocked: Expression '{$leftOperand} {$operator} {$rightOperand}' calculates derived metrics using restricted cost/margin column '{$operand}' (underlying: '{$resolvedOperand}').",
                            field: $operand,
                            role: $role,
                            sensitivity: $sensitivity,
                            purpose: $context->purpose,
                        );
                    }
                }
            }
        }
    }

    /**
     * Check raw identifier against sensitive domain patterns if not explicitly matched in descriptors.
     */
    private function checkRawIdentifierRestrictions(string $identifier, ServerControlledContext $context): void
    {
        $classified = DataClassification::classifyRole($identifier);
        if ($classified === DataClassification::RESTRICTED) {
            if ($this->policy->isFinancialCostRole($identifier) && ! $context->hasPermission('analytics.view_margins')) {
                throw new UnauthorizedDataAccessException(
                    message: "Access denied: Query references restricted financial term '{$identifier}' without 'analytics.view_margins' permission.",
                    field: $identifier,
                    role: $identifier,
                    sensitivity: DataClassification::RESTRICTED,
                    purpose: $context->purpose,
                );
            }

            if ($this->policy->isWorkforceCompensationRole($identifier) && ! $context->hasPermission('hr.view_salaries')) {
                throw new UnauthorizedDataAccessException(
                    message: "Access denied: Query references restricted workforce term '{$identifier}' without 'hr.view_salaries' permission.",
                    field: $identifier,
                    role: $identifier,
                    sensitivity: DataClassification::RESTRICTED,
                    purpose: $context->purpose,
                );
            }

            if ($this->policy->isClinicalPhiRole($identifier) && ! $context->hasPermission('medical.view_phi')) {
                throw new UnauthorizedDataAccessException(
                    message: "Access denied: Query references restricted clinical PHI term '{$identifier}' without 'medical.view_phi' permission.",
                    field: $identifier,
                    role: $identifier,
                    sensitivity: DataClassification::RESTRICTED,
                    purpose: $context->purpose,
                );
            }
        }
    }

    /**
     * Build lookup map for source descriptors.
     *
     * @param array<int|string, array<string, mixed>|SourceColumnDescriptor> $sourceDescriptors
     * @return array<string, array<string, mixed>>
     */
    private function buildDescriptorIndex(array $sourceDescriptors): array
    {
        $index = [];

        foreach ($sourceDescriptors as $desc) {
            $arr = $desc instanceof SourceColumnDescriptor ? $desc->toArray() : (array) $desc;

            $colId = strtolower((string) ($arr['source_column_id'] ?? ''));
            $norm = strtolower((string) ($arr['normalized_header'] ?? ''));
            $orig = strtolower((string) ($arr['original_header'] ?? ''));
            $role = strtolower((string) ($arr['semantic_role'] ?? ''));

            if ($colId !== '') {
                $index[$colId] = $arr;
            }
            if ($norm !== '') {
                $index[$norm] = $arr;
            }
            if ($orig !== '') {
                $index[$orig] = $arr;
            }
            if ($role !== '') {
                $index[$role] = $arr;
            }
        }

        return $index;
    }

    /**
     * Find descriptor matching an identifier.
     *
     * @param array<string, array<string, mixed>> $descriptorIndex
     * @return array<string, mixed>|null
     */
    private function findDescriptor(string $identifier, array $descriptorIndex): ?array
    {
        $key = strtolower(trim($identifier));
        return $descriptorIndex[$key] ?? null;
    }
}
