<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Models\FAQ;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Stringable;
use Throwable;

/**
 * KnowledgeIntelligenceTool (knowledge_intelligence).
 *
 * Governed business knowledge retrieval tool.
 * Performs semantic hybrid search across workspace FAQs and uploaded knowledge documents,
 * enforcing document-level permissions, tenant boundaries, and untrusted evidence guards.
 */
class KnowledgeIntelligenceTool extends AbstractGovernedTool
{
    public function __construct(
        private readonly ?RetrievalClient $retrievalClient = null,
    ) {}

    public function name(): string
    {
        return 'knowledge_intelligence';
    }

    public function description(): Stringable|string
    {
        return 'Searches business policies, operating procedures, product FAQs, and uploaded enterprise knowledge documents for factual evidence matching a query.';
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
            'query' => $schema->string()
                ->description('The natural language question, policy topic, or search query.')
                ->required(),
            'top_k' => $schema->integer()
                ->description('Maximum number of matching evidence items to retrieve (1-10). Defaults to 5.'),
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
        $query = trim((string) ($arguments['query'] ?? $arguments['question'] ?? ''));

        if ($query === '') {
            return ToolResult::failure('Query parameter cannot be empty.');
        }

        $topK = min(max(1, (int) ($arguments['top_k'] ?? 5)), 10);

        // 2. Resolve Retrieval Client or Bound FAQSearch
        $client = $this->retrievalClient ?? app(RetrievalClient::class);

        // 3. Execute Scoped Search with Downstream Resiliency & Strict Error Semantics
        try {
            $hits = null;
            if (app()->bound(\App\Services\FAQ\FAQSearch::class)) {
                try {
                    $faqSearch = app(\App\Services\FAQ\FAQSearch::class);
                    $hits = $faqSearch->search($query, $topK, $workspaceId, $context->conversation, null);
                } catch (Throwable) {
                    $hits = null;
                }
            }

            if ($hits === null || $hits->isEmpty()) {
                $hits = $client->search(
                    query: $query,
                    workspaceId: $workspaceId,
                    topK: $topK,
                );
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("[{$this->name()}] Downstream connection failure in knowledge retrieval", [
                'workspace_id' => $workspaceId,
                'query_sha256' => hash('sha256', $query),
                'error_code'   => 'DOWNSTREAM_UNAVAILABLE',
            ]);

            return ToolResult::failure(
                error: 'Knowledge retrieval service is temporarily unavailable. Please retry later.',
                metadata: [
                    'error_code' => 'DOWNSTREAM_UNAVAILABLE',
                    'tool_name'  => $this->name(),
                ]
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $status = $e->response?->status() ?? 500;
            Log::warning("[{$this->name()}] Downstream HTTP non-success response in knowledge retrieval", [
                'workspace_id' => $workspaceId,
                'http_status'  => $status,
                'query_sha256' => hash('sha256', $query),
                'error_code'   => 'INVALID_DOWNSTREAM_RESPONSE',
            ]);

            return ToolResult::failure(
                error: 'Knowledge retrieval service returned an unexpected downstream response.',
                metadata: [
                    'error_code'  => 'INVALID_DOWNSTREAM_RESPONSE',
                    'http_status' => $status,
                    'tool_name'   => $this->name(),
                ]
            );
        } catch (\JsonException $e) {
            Log::warning("[{$this->name()}] Downstream response decoding failure in knowledge retrieval", [
                'workspace_id' => $workspaceId,
                'query_sha256' => hash('sha256', $query),
                'error_code'   => 'INVALID_DOWNSTREAM_RESPONSE',
            ]);

            return ToolResult::failure(
                error: 'Invalid or malformed response from knowledge retrieval service.',
                metadata: [
                    'error_code' => 'INVALID_DOWNSTREAM_RESPONSE',
                    'tool_name'  => $this->name(),
                ]
            );
        } catch (Throwable $e) {
            Log::warning("[{$this->name()}] Knowledge retrieval downstream failure", [
                'workspace_id' => $workspaceId,
                'query_sha256' => hash('sha256', $query),
                'error_code'   => 'DOWNSTREAM_UNAVAILABLE',
            ]);

            return ToolResult::failure(
                error: 'Knowledge retrieval service is temporarily unavailable. Please retry later.',
                metadata: [
                    'error_code' => 'DOWNSTREAM_UNAVAILABLE',
                    'tool_name'  => $this->name(),
                ]
            );
        }

        // 4. Verify Document-Level Authorization & Format Untrusted Evidence
        $canViewDocuments = $context->isOwner()
            || $context->hasPermission(KnowledgeDocumentPermission::VIEW->value)
            || $context->hasPermission('document.view');

        $evidenceList = [];

        foreach ($hits as $hit) {
            $faq = $hit instanceof \App\Services\FAQ\FAQSearchResult ? $hit->faq : $hit;
            if (! $faq instanceof FAQ) {
                continue;
            }

            $docType = $faq->document_type ?? 'faq';

            // Document Chunk Access Control: Requires document.view or active status
            if ($docType === 'knowledge_document' && ! $canViewDocuments && ! ($faq->is_active ?? false)) {
                continue;
            }

            $evidenceList[] = [
                'source_id'   => (string) $faq->id,
                'source_type' => $docType,
                'title'       => (string) ($faq->question ?? 'Knowledge Reference'),
                // Untrusted evidence quote
                'content'     => (string) ($faq->answer ?? ''),
            ];
        }

        return ToolResult::success([
            'query_hash'     => hash('sha256', $query),
            'match_count'    => count($evidenceList),
            'evidence_items' => $evidenceList,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
            'retrieval_hits'    => $hits,
            'top_hit'           => $hits instanceof \Illuminate\Support\Collection ? $hits->first() : null,
        ]);
    }
}

