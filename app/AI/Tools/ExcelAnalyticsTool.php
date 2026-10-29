<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Services\Analytics\AnalyticsClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Excel Spreadsheet Virtual Database Analytics Tool.
 *
 * Pipeline Flow: HybridRouter -> ExcelAnalyticsTool -> AnalyticsClient -> Python Excel Virtual DB Engine
 */
class ExcelAnalyticsTool implements Tool
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
        return 'Query uploaded Excel spreadsheets (.xlsx, .xls, .csv) across multiple sheets/tabs (such as sales data, expenses, employees, payroll) using natural language.';
    }

    /**
     * Standard execution method for direct routing execution.
     *
     * @param string $question Natural language question about the uploaded spreadsheet
     * @param int $workspaceId Authenticated workspace context
     * @param string|null $fileId Optional file ID
     * @param array<int, array<string, string>>|null $history Optional conversation history
     * @return array<string, mixed>
     */
    public function execute(
        string $question,
        int $workspaceId,
        ?string $fileId = null,
        ?array $history = null,
    ): array {
        if ($workspaceId <= 0) {
            \Illuminate\Support\Facades\Log::warning('[ExcelAnalyticsTool] execute() called with invalid workspaceId: ' . $workspaceId);
            return [
                'success' => false,
                'status' => 'error',
                'report' => 'Invalid or missing workspace context.',
            ];
        }

        return $this->analyticsClient->queryExcel(
            question: $question,
            workspaceId: $workspaceId,
            fileId: $fileId,
            history: $history,
        );
    }

    /**
     * Execute the tool via Laravel AI Request.
     */
    public function handle(Request $request): Stringable|string
    {
        if ($this->workspaceId === null || $this->workspaceId <= 0) {
            \Illuminate\Support\Facades\Log::warning('[ExcelAnalyticsTool] Invocation rejected: tool has missing or invalid runtime workspace context.');
            return 'Workspace context is missing or invalid.';
        }

        $question = trim((string) ($request['question'] ?? ''));
        if ($question === '') {
            return 'No question provided for Excel spreadsheet analysis.';
        }

        $fileId = isset($request['file_id']) ? (string) $request['file_id'] : null;
        $history = isset($request['history']) && is_array($request['history']) ? $request['history'] : null;

        // Strictly enforce runtime $this->workspaceId; LLM-supplied workspace arguments are never trusted.
        $result = $this->execute(
            question: $question,
            workspaceId: $this->workspaceId,
            fileId: $fileId,
            history: $history,
        );

        return $result['report'] ?? 'Could not retrieve Excel analysis data.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->description('The natural language question about specific tabs or sheets in the uploaded Excel file.'),
            'file_id' => $schema->string()->description('Optional specific file ID of the uploaded spreadsheet (leave null for the latest file).'),
        ];
    }
}
