<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Agent\Tools;

use App\Models\FAQ;
use App\Models\User;
use App\Models\Workspace;
use App\AI\AgentRuntimeEngine;
use App\AI\Contracts\AgentInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Policies\ToolAuthorizationPolicy;
use App\AI\Tools\KnowledgeIntelligenceTool;
use App\Services\Retrieval\RetrievalClient;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class KnowledgeRetrievalFailureSemanticsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private AgentContext $ownerContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Knowledge WS', 'slug' => 'knowledge-ws-' . uniqid()]);
        $this->owner = User::create([
            'name'         => 'Owner User',
            'email'        => 'owner.kn.' . uniqid() . '@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);

        $serverContext = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->owner->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $this->ownerContext = AgentContext::fromServerControlledContext($serverContext, user: $this->owner, timeoutSeconds: 4.0);
    }

    /**
     * Test 1: Successful search with results -> success.
     */
    public function test_successful_search_with_results_returns_success(): void
    {
        $mockFaq = new FAQ([
            'id'            => 'faq-1',
            'document_type' => 'faq',
            'question'      => 'Return policy',
            'answer'        => 'Items can be returned within 14 days.',
        ]);

        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andReturn(new Collection([$mockFaq]));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'Return policy']));
        });

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['match_count']);
        $this->assertNotEmpty($result->data['evidence_items']);
        $this->assertNull($result->error);
    }

    /**
     * Test 2: Genuine empty search -> success with zero matches.
     */
    public function test_genuine_empty_search_returns_success_with_zero_matches(): void
    {
        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andReturn(new Collection([]));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'Nonexistent topic']));
        });

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->data['match_count']);
        $this->assertEmpty($result->data['evidence_items']);
        $this->assertNull($result->error);
    }

    /**
     * Test 3: Connection exception -> failure with DOWNSTREAM_UNAVAILABLE.
     */
    public function test_connection_exception_returns_failure(): void
    {
        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new ConnectionException('Connection refused to Qdrant/FastAPI'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'Customer question']));
        });

        $this->assertFalse($result->success);
        $this->assertSame('DOWNSTREAM_UNAVAILABLE', $result->metadata['error_code']);
        $this->assertStringContainsString('unavailable', $result->error);
    }

    /**
     * Test 4: Timeout -> failure with DOWNSTREAM_UNAVAILABLE.
     */
    public function test_timeout_returns_failure(): void
    {
        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new ConnectionException('cURL error 28: Operation timed out after 5000 milliseconds'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'Customer question']));
        });

        $this->assertFalse($result->success);
        $this->assertSame('DOWNSTREAM_UNAVAILABLE', $result->metadata['error_code']);
    }

    /**
     * Test 5: HTTP 5xx -> failure with INVALID_DOWNSTREAM_RESPONSE.
     */
    public function test_http_5xx_returns_failure(): void
    {
        $psrResponse = new \GuzzleHttp\Psr7\Response(503, [], 'Service Unavailable');
        $realResponse = new Response($psrResponse);

        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new RequestException($realResponse));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'Customer question']));
        });

        $this->assertFalse($result->success);
        $this->assertSame('INVALID_DOWNSTREAM_RESPONSE', $result->metadata['error_code']);
        $this->assertSame(503, $result->metadata['http_status']);
    }

    /**
     * Test 6: Malformed JSON -> failure with INVALID_DOWNSTREAM_RESPONSE.
     */
    public function test_malformed_json_returns_failure(): void
    {
        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new \JsonException('Syntax error in JSON response'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'Customer question']));
        });

        $this->assertFalse($result->success);
        $this->assertSame('INVALID_DOWNSTREAM_RESPONSE', $result->metadata['error_code']);
    }

    /**
     * Test 7: Invalid response schema -> failure.
     */
    public function test_invalid_response_schema_returns_failure(): void
    {
        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new \RuntimeException('Schema validation failed: missing results field'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'Customer question']));
        });

        $this->assertFalse($result->success);
        $this->assertSame('DOWNSTREAM_UNAVAILABLE', $result->metadata['error_code']);
    }

    /**
     * Test 8: Failure is preserved by AbstractGovernedTool.
     */
    public function test_failure_is_preserved_by_abstract_governed_tool(): void
    {
        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new ConnectionException('Network unreachable'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'Customer question']));
        });

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertFalse($result->success);
        $responseString = (string) $result;
        $this->assertStringContainsString('Tool execution failed:', $responseString);
    }

    /**
     * Test 9: Failure is preserved through AgentRuntimeEngine.
     */
    public function test_failure_is_preserved_through_agent_runtime_engine(): void
    {
        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new ConnectionException('Timeout'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $engine = new AgentRuntimeEngine(
            authorizationPolicy: app(ToolAuthorizationPolicy::class)
        );

        $result = $engine->executeTool($tool, ['query' => 'Search topic'], $this->ownerContext);

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertFalse($result->success);
        $this->assertSame('DOWNSTREAM_UNAVAILABLE', $result->metadata['error_code']);
    }

    /**
     * Test 10: Failure message contains no raw query or PII.
     */
    public function test_failure_message_contains_no_raw_query_or_pii(): void
    {
        $sensitiveQuery = 'User John Doe phone 01711999888 email john@example.com salary 50000';

        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new ConnectionException('Downstream failed'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool, $sensitiveQuery) {
            return $tool->handle(new Request(['query' => $sensitiveQuery]));
        });

        $this->assertStringNotContainsString('John Doe', (string) $result->error);
        $this->assertStringNotContainsString('01711999888', (string) $result->error);
        $this->assertStringNotContainsString('john@example.com', (string) $result->error);
        $this->assertStringNotContainsString('50000', (string) $result->error);
    }

    /**
     * Test 11: Logs contain no raw query or PII.
     */
    public function test_logs_contain_no_raw_query_or_pii(): void
    {
        $sensitiveQuery = 'Confidential query for patient Jane Doe 01811222333';

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use ($sensitiveQuery) {
                // Must NOT contain sensitive query text
                foreach ($context as $key => $value) {
                    if (is_string($value) && str_contains($value, 'Jane Doe')) {
                        return false;
                    }
                    if (is_string($value) && str_contains($value, '01811222333')) {
                        return false;
                    }
                }
                // Must contain hashed query fingerprint
                return isset($context['query_sha256']) && $context['query_sha256'] === hash('sha256', $sensitiveQuery);
            });

        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new ConnectionException('Service down'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        AgentContext::withContext($this->ownerContext, function () use ($tool, $sensitiveQuery) {
            return $tool->handle(new Request(['query' => $sensitiveQuery]));
        });
    }

    /**
     * Test 12: Error metadata contains no raw query or PII.
     */
    public function test_error_metadata_contains_no_raw_query_or_pii(): void
    {
        $sensitiveQuery = 'Bank account 123456789 secret pin 4321';

        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->andThrow(new ConnectionException('Downstream dropped'));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool, $sensitiveQuery) {
            return $tool->handle(new Request(['query' => $sensitiveQuery]));
        });

        foreach ($result->metadata as $k => $v) {
            $strV = is_string($v) ? $v : json_encode($v);
            $this->assertStringNotContainsString('123456789', (string) $strV);
            $this->assertStringNotContainsString('4321', (string) $strV);
        }
    }

    /**
     * Test 13: Existing workspace isolation and DLP behavior remain unchanged.
     */
    public function test_workspace_isolation_and_dlp_behavior_remain_unchanged(): void
    {
        $mockFaq = new FAQ([
            'id'            => 'faq-ws-a',
            'document_type' => 'knowledge_document',
            'question'      => 'Internal Sop',
            'answer'        => 'Restricted internal standard operating procedure.',
        ]);

        $mockClient = Mockery::mock(RetrievalClient::class);
        $mockClient->shouldReceive('search')
            ->once()
            ->with('internal sop', $this->workspace->id, 5)
            ->andReturn(new Collection([$mockFaq]));

        $tool = new KnowledgeIntelligenceTool(retrievalClient: $mockClient);

        // Staff user without document.view
        $staffUser = User::create([
            'name'         => 'Staff User',
            'email'        => 'staff.kn.' . uniqid() . '@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);
        $staffServerContext = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $staffUser->id,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [] // Lacks document.view
        );
        $staffAgentContext = AgentContext::fromServerControlledContext($staffServerContext, user: $staffUser, timeoutSeconds: 4.0);

        $result = AgentContext::withContext($staffAgentContext, function () use ($tool) {
            return $tool->handle(new Request(['query' => 'internal sop']));
        });

        $this->assertTrue($result->success);
        // Knowledge document chunk was filtered out by DLP for staff lacking document.view
        $this->assertSame(0, $result->data['match_count']);
        $this->assertEmpty($result->data['evidence_items']);
    }
}
