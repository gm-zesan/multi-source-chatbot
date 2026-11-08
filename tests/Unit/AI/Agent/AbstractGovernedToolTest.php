<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Agent;

use App\Models\User;
use App\AI\Contracts\AgentInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\AgentResponse;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\MissingAgentContextException;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Tools\AbstractGovernedTool;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Closure;
use Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;
use Tests\TestCase;

class AbstractGovernedToolTest extends TestCase
{
    private function createAuthenticatedContext(int $workspaceId = 10, int $userId = 1, string $role = 'owner'): AgentContext
    {
        $scc = new ServerControlledContext(
            workspaceId: $workspaceId,
            userId: $userId,
            userRole: $role,
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );

        $user = new User(['id' => $userId, 'workspace_id' => $workspaceId]);
        $user->id = $userId;

        return AgentContext::fromServerControlledContext($scc, user: $user);
    }

    public function test_handle_throws_missing_agent_context_exception_when_no_ambient_context(): void
    {
        $tool = new DummyGovernedTool();
        $request = new Request(['param' => 'value']);

        $this->expectException(MissingAgentContextException::class);
        $this->expectExceptionMessage('No active AgentContext in current execution scope');

        $tool->handle($request);
    }

    public function test_handle_executes_successfully_under_ambient_context(): void
    {
        $tool = new DummyGovernedTool(name: 'calculator', isReadOnly: true);
        $agent = new DummyGovernedAgent(allowedTools: ['calculator']);

        $context = $this->createAuthenticatedContext()->withAgent($agent);
        $request = new Request(['a' => 5, 'b' => 10]);

        $result = AgentContext::withContext($context, fn() => $tool->handle($request));

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertInstanceOf(Stringable::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame(['received' => ['a' => 5, 'b' => 10]], $result->data);
    }

    public function test_handle_throws_unauthorized_tool_exception_when_tool_not_whitelisted_on_agent(): void
    {
        $tool = new DummyGovernedTool(name: 'unauthorized_tool');
        $agent = new DummyGovernedAgent(allowedTools: ['allowed_tool_only']);

        $context = $this->createAuthenticatedContext()->withAgent($agent);
        $request = new Request();

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Tool 'unauthorized_tool' is not registered on agent 'test-governed-agent'.");

        AgentContext::withContext($context, fn() => $tool->handle($request));
    }

    public function test_handle_throws_unauthorized_tool_exception_when_customer_invokes_mutating_tool(): void
    {
        $mutatingTool = new DummyGovernedTool(name: 'order_canceler', isReadOnly: false);
        $agent = new DummyGovernedAgent(allowedTools: ['order_canceler']);

        $context = AgentContext::forCustomer(
            workspaceId: 5,
            channel: ServerControlledContext::CHANNEL_CUSTOMER_WEB,
        )->withAgent($agent);

        $request = new Request(['order_id' => 123]);

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Customer channels are not authorized to execute mutating tool 'order_canceler'.");

        AgentContext::withContext($context, fn() => $mutatingTool->handle($request));
    }

    public function test_handle_traps_unexpected_exceptions_and_returns_sanitized_failure(): void
    {
        $faultyTool = new DummyGovernedTool(
            name: 'faulty_tool',
            executionCallback: function (array $args, AgentContext $ctx): ToolResult {
                throw new Exception('Database connection leak with secret credentials DB_PASSWORD=supersecret');
            }
        );
        $agent = new DummyGovernedAgent(allowedTools: ['faulty_tool']);

        $context = $this->createAuthenticatedContext()->withAgent($agent);
        $request = new Request();

        $result = AgentContext::withContext($context, fn() => $faultyTool->handle($request));

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertFalse($result->success);
        $this->assertSame('An internal error occurred while processing this tool request.', $result->error);
        $this->assertStringNotContainsString('supersecret', (string) $result);
        $this->assertSame('Exception', $result->metadata['exception_class']);
    }

    public function test_handle_rethrows_unauthorized_tool_exception_thrown_inside_execute(): void
    {
        $tool = new DummyGovernedTool(
            name: 'security_check_tool',
            executionCallback: function (array $args, AgentContext $ctx): ToolResult {
                throw new UnauthorizedToolException(
                    message: 'Dynamic permission check failed',
                    toolName: 'security_check_tool',
                );
            }
        );
        $agent = new DummyGovernedAgent(allowedTools: ['security_check_tool']);

        $context = $this->createAuthenticatedContext()->withAgent($agent);
        $request = new Request();

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage('Dynamic permission check failed');

        AgentContext::withContext($context, fn() => $tool->handle($request));
    }

    public function test_get_name_and_default_permissions(): void
    {
        $tool = new DummyGovernedTool(name: 'test_named_tool');

        $this->assertSame('test_named_tool', $tool->getName());
        $this->assertSame('test_named_tool', $tool->name());
        $this->assertSame([], $tool->getRequiredPermissions());
    }

    public function test_handle_automatically_projects_and_redacts_cost_price_for_staff(): void
    {
        $tool = new DummyGovernedTool(
            name: 'catalog_search',
            isReadOnly: true,
            executionCallback: function (array $args, AgentContext $ctx): ToolResult {
                return ToolResult::success([
                    'item'       => 'Keyboard',
                    'price'      => 45.00,
                    'cost_price' => 20.00, // Sensitive
                    'cogs'       => 20.00, // Sensitive
                ]);
            }
        );
        $agent = new DummyGovernedAgent(allowedTools: ['catalog_search']);

        // Member without analytics.view_margins
        $context = $this->createAuthenticatedContext(role: 'member')->withAgent($agent);
        $request = new Request();

        $result = AgentContext::withContext($context, fn() => $tool->handle($request));

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('Keyboard', $result->data['item']);
        $this->assertSame(45.00, $result->data['price']);
        $this->assertArrayNotHasKey('cost_price', $result->data);
        $this->assertArrayNotHasKey('cogs', $result->data);
        $this->assertStringNotContainsString('20.00', (string) $result);
    }

    public function test_handle_preserves_cost_price_for_owner(): void
    {
        $tool = new DummyGovernedTool(
            name: 'catalog_search',
            isReadOnly: true,
            executionCallback: function (array $args, AgentContext $ctx): ToolResult {
                return ToolResult::success([
                    'item'       => 'Keyboard',
                    'price'      => 45.00,
                    'cost_price' => 20.00,
                ]);
            }
        );
        $agent = new DummyGovernedAgent(allowedTools: ['catalog_search']);

        // Owner
        $context = $this->createAuthenticatedContext(role: 'owner')->withAgent($agent);
        $request = new Request();

        $result = AgentContext::withContext($context, fn() => $tool->handle($request));

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame(20.00, $result->data['cost_price']);
    }
}

/**
 * Concrete test tool implementing AbstractGovernedTool.
 */
class DummyGovernedTool extends AbstractGovernedTool
{
    public function __construct(
        private readonly string $name = 'dummy_tool',
        private readonly bool $isReadOnly = true,
        private readonly array $requiredPermissions = [],
        private readonly ?Closure $executionCallback = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return 'Dummy governed tool description.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function isReadOnly(): bool
    {
        return $this->isReadOnly;
    }

    public function getRequiredPermissions(): array
    {
        return $this->requiredPermissions;
    }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        if ($this->executionCallback !== null) {
            return ($this->executionCallback)($arguments, $context);
        }

        return ToolResult::success(['received' => $arguments]);
    }
}

/**
 * Dummy agent for testing.
 */
class DummyGovernedAgent implements AgentInterface
{
    public function __construct(
        private readonly string $id = 'test-governed-agent',
        private readonly array $allowedTools = ['dummy_tool'],
    ) {}

    public function getAgentId(): string
    {
        return $this->id;
    }

    public function getRoleName(): string
    {
        return 'test_governed_role';
    }

    public function getSystemPrompt(AgentContext $context): string
    {
        return 'Governed system prompt';
    }

    public function getRegisteredTools(): array
    {
        return array_map(function (string $name) {
            return new DummyGovernedTool(name: $name);
        }, $this->allowedTools);
    }

    public function maxSteps(): int
    {
        return 3;
    }

    public function onTurnStart(AgentContext $context): void {}

    public function onTurnEnd(AgentContext $context, AgentResponse $response): void {}
}
