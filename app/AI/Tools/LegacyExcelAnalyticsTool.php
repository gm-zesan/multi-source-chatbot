<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\SpreadsheetImportJob;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\AI\Exceptions\UnauthorizedToolException;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
use App\Services\Spreadsheet\Security\AnalyticsAstAuthorizer;
use App\Services\Spreadsheet\Security\Exceptions\UnauthorizedDataAccessException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stringable;
use Throwable;

/**
 * LegacyExcelAnalyticsTool (legacy_excel_analytics).
 *
 * Governed spreadsheet query tool executing exclusively through Contract 3.0.
 * Enforces:
 *   planCandidateQuery -> prepareVirtualQuery -> authorizePreparedQuery (HMAC Grant) -> executeVirtualQuery.
 * Decommissions and prohibits direct unauthenticated execution routes.
 */
class LegacyExcelAnalyticsTool extends AbstractGovernedTool
{
    public function __construct(
        private readonly ?AnalyticsClient $analyticsClient = null,
        private readonly ?AnalyticsAstAuthorizer $astAuthorizer = null,
        private readonly ?CrossSheetRelationshipService $relationshipService = null,
    ) {}

    public function name(): string
    {
        return 'legacy_excel_analytics';
    }

    public function description(): Stringable|string
    {
        return 'Executes governed analytical questions over uploaded enterprise spreadsheet datasets via the Contract 3.0 SQLite virtual execution gateway.';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRequiredPermissions(): array
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()
                ->description('Natural language question to query against the uploaded spreadsheet records.')
                ->required(),
            'dataset_id' => $schema->string()
                ->description('Optional UUID of the specific spreadsheet import job. If omitted, uses the latest active dataset.'),
        ];
    }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        // 1. SLA Deadline Guard
        $remaining = $context->getRemainingSeconds();
        if ($remaining < 0.5) {
            throw new AgentExecutionTimeoutException(
                message: "Tool [{$this->name()}] execution aborted: remaining SLA budget ({$remaining}s) is below 0.5s threshold.",
                timeoutSeconds: $context->timeoutSeconds,
                elapsedSeconds: $context->timeoutSeconds - $remaining,
            );
        }

        $workspaceId = $context->getWorkspaceId();
        $question = trim((string) ($arguments['question'] ?? $arguments['query'] ?? ''));

        if ($question === '') {
            return ToolResult::failure('Question parameter cannot be empty.');
        }

        // 2. Resolve Services
        $client = $this->analyticsClient ?? app(AnalyticsClient::class);
        $authorizer = $this->astAuthorizer ?? app(AnalyticsAstAuthorizer::class);
        $relService = $this->relationshipService ?? app(CrossSheetRelationshipService::class);

        // 3. Resolve Target Spreadsheet Dataset
        $explicitDatasetId = !empty($arguments['dataset_id']) ? trim((string) $arguments['dataset_id']) : null;

        $jobQuery = SpreadsheetImportJob::where('workspace_id', $workspaceId)
            ->whereIn('status', ['completed', 'completed_with_errors']);

        if ($explicitDatasetId !== null) {
            $jobQuery->where('id', $explicitDatasetId);
        }

        $job = $jobQuery->latest()->first();

        if ($job === null) {
            return ToolResult::success([
                'status'  => 'no_spreadsheet_found',
                'message' => 'No active spreadsheet datasets found for this workspace. Upload a spreadsheet to enable Excel analytics.',
                'rows'    => [],
            ], [
                'execution_time_ms' => $context->getElapsedMs(),
                'tool_name'         => $this->name(),
            ]);
        }

        $datasetId = (string) $job->id;
        $generationId = (string) ($job->generation_id ?: Str::uuid());
        $descriptors = is_array($job->source_descriptors) ? $job->source_descriptors : [];
        $activeGraph = $relService->loadGraph($job);

        // 4. Contract 3.0 Execution Sequence
        try {
            // Stage A: Plan Candidate Query
            $plan = $client->planCandidateQuery(
                question: $question,
                workspaceId: $workspaceId,
                datasetId: $datasetId
            );

            // Stage B: Prepare Virtual Query
            $artifact = $client->prepareVirtualQuery(
                plan: $plan,
                workspaceId: $workspaceId,
                datasetId: $datasetId,
                generationId: $generationId,
                graph: $activeGraph,
                sourceDescriptors: $descriptors
            );

            // Stage C: Authorize Query & Issue Single-Use HMAC Grant
            $grant = $authorizer->authorizePreparedQuery(
                artifact: $artifact,
                sourceDescriptors: $descriptors,
                graph: $activeGraph,
                context: $context->serverControlledContext
            );

            // Stage D: Execute Against Virtual SQLite Gateway
            $executionResponse = $client->executeVirtualQuery(
                artifact: $artifact,
                grant: $grant
            );

            return ToolResult::success([
                'status'       => 'executed',
                'dataset_id'   => $datasetId,
                'row_count'    => $executionResponse->rowCount,
                'columns'      => $executionResponse->columns,
                'rows'         => $executionResponse->rows,
                'latency_ms'   => $executionResponse->latencyMs,
            ], [
                'execution_time_ms' => $context->getElapsedMs(),
                'tool_name'         => $this->name(),
                'query_fingerprint' => $artifact->queryFingerprint,
            ]);
        } catch (UnauthorizedDataAccessException $e) {
            Log::warning("[{$this->name()}] Security rejection during AST authorization", [
                'workspace_id' => $workspaceId,
                'error'        => $e->getMessage(),
            ]);

            throw new UnauthorizedToolException(
                message: "Access denied to spreadsheet column or relationship: {$e->getMessage()}",
                toolName: $this->name(),
                workspaceId: $workspaceId
            );
        } catch (Throwable $e) {
            Log::error("[{$this->name()}] Contract 3.0 query execution failed", [
                'workspace_id' => $workspaceId,
                'error'        => $e->getMessage(),
            ]);

            return ToolResult::failure(
                error: 'An error occurred while compiling or executing the virtual spreadsheet query.',
                metadata: ['exception_class' => class_basename($e)]
            );
        }
    }
}
