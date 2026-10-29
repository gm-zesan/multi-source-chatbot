<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Services\Analytics\AnalyticsClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Business Database Analytics & BI Tool (Phase 3.x Semantic Engine)
 *
 * Pipeline Flow: HybridRouter (ANALYTICS Route) -> BusinessAnalyticsTool -> AnalyticsClient -> Python AI Engine
 */
class BusinessAnalyticsTool implements Tool
{
    public function __construct(
        private readonly AnalyticsClient $analyticsClient,
        private readonly ?int $workspaceId = null,
    ) {}

    /**
     * The description of what the tool does.
     */
    public function description(): Stringable|string
    {
        return 'Execute business analytics and database queries to calculate sales amount, order counts, cash collections, outstanding dues, customer records, product price catalog, and salesperson performance.';
    }

    /**
     * Standard execution method for direct routing execution.
     *
     * @param string $query Natural language business intelligence question
     * @param int $workspaceId Authenticated runtime workspace context
     * @param array<int, array{role: string, content: string}> $history Conversation turns
     * @return array<string, mixed>
     */
    public function execute(string $query, int $workspaceId, array $history = []): array
    {
        if ($workspaceId <= 0) {
            \Illuminate\Support\Facades\Log::warning('[BusinessAnalyticsTool] execute() called with invalid workspaceId: ' . $workspaceId);
            return [
                'success' => false,
                'status' => 'error',
                'report' => 'Invalid or missing workspace context.',
            ];
        }

        return $this->analyticsClient->query(
            query: $query,
            workspaceId: $workspaceId,
            history: $history,
        );
    }

    /**
     * Execute the tool via Laravel AI Request.
     */
    public function handle(Request $request): Stringable|string
    {
        if ($this->workspaceId === null || $this->workspaceId <= 0) {
            \Illuminate\Support\Facades\Log::warning('[BusinessAnalyticsTool] Invocation rejected: tool has missing or invalid runtime workspace context.');
            return 'Workspace context is missing or invalid.';
        }

        $query = trim((string) ($request['query'] ?? ''));
        if ($query === '') {
            return 'No analytics query provided.';
        }

        // Strictly enforce runtime $this->workspaceId; LLM-supplied workspace arguments are never trusted.
        $result = $this->execute(query: $query, workspaceId: $this->workspaceId);

        return $result['report'] ?? 'Could not retrieve analytics data.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('The natural language business intelligence or database question in Bengali, Banglish, or English.'),
        ];
    }
}
