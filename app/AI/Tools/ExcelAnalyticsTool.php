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
     * @return array<string, mixed>
     */
    public function execute(string $question, int $workspaceId, ?string $fileId = null): array
    {
        return $this->analyticsClient->queryExcel(
            question: $question,
            workspaceId: $workspaceId,
            fileId: $fileId,
        );
    }

    /**
     * Execute the tool via Laravel AI Request.
     */
    public function handle(Request $request): Stringable|string
    {
        $question = trim((string) ($request['question'] ?? ''));
        $workspaceId = (int) ($request['workspace_id'] ?? $this->workspaceId ?? 1);
        $fileId = isset($request['file_id']) ? (string) $request['file_id'] : null;

        if ($question === '') {
            return 'No question provided for Excel spreadsheet analysis.';
        }

        $result = $this->execute(
            question: $question,
            workspaceId: $workspaceId,
            fileId: $fileId,
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
            'workspace_id' => $schema->integer()->description('The authenticated workspace ID.')->default(1),
            'file_id' => $schema->string()->description('Optional specific file ID of the uploaded spreadsheet (leave null for the latest file).'),
        ];
    }
}
