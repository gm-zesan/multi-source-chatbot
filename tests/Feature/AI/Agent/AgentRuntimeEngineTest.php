<?php

declare(strict_types=1);

namespace Tests\Feature\AI\Agent;

use App\Models\User;
use App\AI\AgentRuntimeEngine;
use App\AI\Contracts\AgentInterface;
use App\AI\Contracts\ToolInterface;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\AgentResponse;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\AI\Exceptions\UnauthorizedToolException;
use App\AI\Tools\AbstractGovernedTool;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Contracts\Agent as LaravelAiAgent;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\Request;
use Stringable;
use Tests\TestCase;

class AgentRuntimeEngineTest extends TestCase
{
    private AgentRuntimeEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new AgentRuntimeEngine();
    }

    private function createAuthenticatedContext(
        int $workspaceId = 1,
        int $userId = 42,
        string $role = 'owner',
        array $permissions = ['analytics.view_margins'],
        float $timeoutSeconds = 4.0,
        int $maxIterations = 3,
    ): AgentContext {
        $scc = new ServerControlledContext(
            workspaceId: $workspaceId,
            userId: $userId,
            userRole: $role,
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: $permissions,
        );

        $user = new User(['id' => $userId, 'workspace_id' => $workspaceId]);
        $user->id = $userId;

        return AgentContext::fromServerControlledContext(
            scc: $scc,
            user: $user,
            maxIterations: $maxIterations,
            timeoutSeconds: $timeoutSeconds,
        );
    }

    public function test_run_successful_turn_with_governed_agent(): void
    {
        $agent = new FeatureTestAgent(agentId: 'bi_assistant', roleName: 'Business Intelligence Assistant');
        $context = $this->createAuthenticatedContext();

        $response = $this->engine->run(
            agent: $agent,
            userPrompt: 'What was our total revenue last month?',
            context: $context,
        );

        $this->assertTrue($response->isSuccess());
        $this->assertSame(AgentResponse::STATUS_SUCCESS, $response->status);
        $this->assertStringContainsString('Processed inquiry for Business Intelligence Assistant', $response->content);
        $this->assertSame(1, $response->telemetry['iterations']);
        $this->assertArrayHasKey('elapsed_ms', $response->telemetry);
        $this->assertSame($context->correlationId, $response->telemetry['correlation_id']);

        // Assert lifecycle hooks fired
        $this->assertTrue($agent->turnStarted);
        $this->assertTrue($agent->turnEnded);
        $this->assertSame($response, $agent->lastResponse);

        // Assert ambient context was popped cleanly
        $this->assertFalse(AgentContext::hasCurrent());
    }

    public function test_run_halts_with_timeout_when_sla_budget_is_pre_expired(): void
    {
        $agent = new FeatureTestAgent();
        $context = $this->createAuthenticatedContext(timeoutSeconds: 0.001);
        usleep(3000); // 3ms, exceeding 1ms SLA

        $response = $this->engine->run(
            agent: $agent,
            userPrompt: 'Quick question',
            context: $context,
        );

        $this->assertTrue($response->isTimedOut());
        $this->assertSame(AgentResponse::STATUS_TIMEOUT, $response->status);
        $this->assertStringContainsString('Execution deadline expired before starting turn', $response->error);
        $this->assertTrue($agent->turnStarted);
        $this->assertTrue($agent->turnEnded);
        $this->assertFalse(AgentContext::hasCurrent());
    }

    public function test_execute_tool_success_under_governed_policy(): void
    {
        $tool = new FeatureTestTool(name: 'revenue_query', isReadOnly: true);
        $agent = new FeatureTestAgent(agentId: 'analyst', registeredTools: [$tool]);
        $context = $this->createAuthenticatedContext();

        $result = $this->engine->executeTool(
            tool: $tool,
            arguments: ['month' => '2026-10'],
            context: $context,
            agent: $agent,
        );

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame(['received' => ['month' => '2026-10']], $result->data);
    }

    public function test_execute_tool_applies_central_output_projection(): void
    {
        // Tool returning cost_price
        $tool = new FeatureProjectionTestTool();
        $agent = new FeatureTestAgent(agentId: 'analyst', registeredTools: [$tool]);

        // Unauthorized member context (no analytics.view_margins)
        $context = $this->createAuthenticatedContext(
            role: 'member',
            permissions: [],
        );

        $result = $this->engine->executeTool(
            tool: $tool,
            arguments: [],
            context: $context,
            agent: $agent,
        );

        $this->assertTrue($result->success);
        $this->assertSame('Widget', $result->data['name']);
        $this->assertSame(99.0, $result->data['selling_price']);
        // Assert cost_price and margin were scrubbed by DataProjectionPolicy
        $this->assertArrayNotHasKey('cost_price', $result->data);
        $this->assertArrayNotHasKey('margin', $result->data);
    }

    public function test_execute_tool_separates_financial_and_hr_projection(): void
    {
        $tool = new FeatureDualCategoryProjectionTool();
        $agent = new FeatureTestAgent(registeredTools: [$tool]);

        // 1. Manager with margins only
        $contextMargins = $this->createAuthenticatedContext(role: 'manager', permissions: ['analytics.view_margins']);
        $resultMargins = $this->engine->executeTool($tool, [], $contextMargins, $agent);
        $this->assertSame(0.35, $resultMargins->data['gross_margin']);
        $this->assertArrayNotHasKey('base_salary', $resultMargins->data);
        $this->assertStringContainsString('Gross Margin: 35%', $resultMargins->data['report']);
        $this->assertStringNotContainsString('Base Salary: $4500', $resultMargins->data['report']);
        $this->assertStringContainsString('[REDACTED]', $resultMargins->data['report']);

        // 2. Manager with HR only
        $contextHr = $this->createAuthenticatedContext(role: 'manager', permissions: ['hr.view_salaries']);
        $resultHr = $this->engine->executeTool($tool, [], $contextHr, $agent);
        $this->assertArrayNotHasKey('gross_margin', $resultHr->data);
        $this->assertSame(4500, $resultHr->data['base_salary']);
        $this->assertStringNotContainsString('Gross Margin: 35%', $resultHr->data['report']);
        $this->assertStringContainsString('Base Salary: $4500', $resultHr->data['report']);
        $this->assertStringContainsString('[REDACTED]', $resultHr->data['report']);
    }

    public function test_real_sdk_tool_dispatch_separates_financial_and_hr_projection(): void
    {
        $tool = new FeatureDualCategoryProjectionTool();
        $agent = new RealSdkFeatureAgent(tools: [$tool]);

        RealSdkFeatureAgent::fake([
            new \Laravel\Ai\Responses\Data\ToolCall('tc-1', 'dual_projection_tool', []),
            'Summary complete',
        ]);

        $context = $this->createAuthenticatedContext(role: 'manager', permissions: ['analytics.view_margins']);

        $response = $this->engine->run($agent, 'Query financials and HR', $context);
        $this->assertTrue($response->isSuccess());
        $this->assertSame('Summary complete', $response->content);
    }

    public function test_execute_tool_enforces_conjunctive_permissions_for_dual_domain_fields(): void
    {
        $tool = new FeatureConjunctiveProjectionTool();
        $agent = new FeatureTestAgent(registeredTools: [$tool]);

        // 1. Unprivileged staff: all restricted
        $staffContext = $this->createAuthenticatedContext(role: 'staff', permissions: []);
        $staffResult = $this->engine->executeTool($tool, [], $staffContext, $agent);
        $this->assertSame(10000, $staffResult->data['revenue']);
        $this->assertArrayNotHasKey('gross_margin', $staffResult->data);
        $this->assertArrayNotHasKey('base_salary', $staffResult->data);
        $this->assertArrayNotHasKey('employee_cost', $staffResult->data);
        $this->assertStringNotContainsString('Gross Margin', $staffResult->data['report']);
        $this->assertStringNotContainsString('Base Salary', $staffResult->data['report']);
        $this->assertStringNotContainsString('Employee Cost', $staffResult->data['report']);

        // 2. Manager with margins only: gross_margin allowed, employee_cost denied!
        $marginsContext = $this->createAuthenticatedContext(role: 'manager', permissions: ['analytics.view_margins']);
        $marginsResult = $this->engine->executeTool($tool, [], $marginsContext, $agent);
        $this->assertSame(0.35, $marginsResult->data['gross_margin']);
        $this->assertArrayNotHasKey('base_salary', $marginsResult->data);
        $this->assertArrayNotHasKey('employee_cost', $marginsResult->data);

        // 3. Manager with HR only: base_salary allowed, employee_cost denied!
        $hrContext = $this->createAuthenticatedContext(role: 'manager', permissions: ['hr.view_salaries']);
        $hrResult = $this->engine->executeTool($tool, [], $hrContext, $agent);
        $this->assertArrayNotHasKey('gross_margin', $hrResult->data);
        $this->assertSame(4500, $hrResult->data['base_salary']);
        $this->assertArrayNotHasKey('employee_cost', $hrResult->data);

        // 4. Manager with BOTH permissions: all allowed!
        $bothContext = $this->createAuthenticatedContext(role: 'manager', permissions: ['analytics.view_margins', 'hr.view_salaries']);
        $bothResult = $this->engine->executeTool($tool, [], $bothContext, $agent);
        $this->assertSame(0.35, $bothResult->data['gross_margin']);
        $this->assertSame(4500, $bothResult->data['base_salary']);
        $this->assertSame(5200, $bothResult->data['employee_cost']);
        $this->assertSame(10000, $bothResult->data['revenue']);
    }

    public function test_real_sdk_tool_dispatch_enforces_conjunctive_dual_permissions(): void
    {
        $tool = new FeatureConjunctiveProjectionTool();
        $agent = new RealSdkFeatureAgent(tools: [$tool]);

        RealSdkFeatureAgent::fake([
            new \Laravel\Ai\Responses\Data\ToolCall('tc-conjunctive', 'conjunctive_projection_tool', []),
            'Conjunctive projection processed',
        ]);

        $context = $this->createAuthenticatedContext(
            role: 'manager',
            permissions: ['analytics.view_margins', 'hr.view_salaries']
        );

        $response = $this->engine->run($agent, 'Query full commercial workforce data', $context);
        $this->assertTrue($response->isSuccess());
        $this->assertSame('Conjunctive projection processed', $response->content);
    }

    public function test_execute_tool_fails_closed_when_sla_deadline_is_exhausted(): void
    {
        $tool = new FeatureTestTool(name: 'slow_query');
        $context = $this->createAuthenticatedContext(timeoutSeconds: 0.001);
        usleep(3000);

        $this->expectException(AgentExecutionTimeoutException::class);
        $this->expectExceptionMessage("Cannot invoke tool 'slow_query': SLA budget of 0.001s exhausted.");

        $this->engine->executeTool(
            tool: $tool,
            arguments: [],
            context: $context,
        );
    }

    public function test_execute_tool_fails_closed_when_agent_does_not_whitelist_tool(): void
    {
        $registeredTool = new FeatureTestTool(name: 'safe_tool');
        $unregisteredTool = new FeatureTestTool(name: 'unauthorized_tool');

        $agent = new FeatureTestAgent(registeredTools: [$registeredTool]);
        $context = $this->createAuthenticatedContext();

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Tool 'unauthorized_tool' is not registered on agent 'feature_agent'");

        $this->engine->executeTool(
            tool: $unregisteredTool,
            arguments: [],
            context: $context,
            agent: $agent,
        );
    }

    public function test_execute_tool_denies_financial_tool_for_staff_without_permissions(): void
    {
        $financialTool = new FeatureTestTool(
            name: 'view_margins',
            isReadOnly: true,
            permissions: ['analytics.view_margins'],
        );

        $context = $this->createAuthenticatedContext(
            role: 'sales_staff',
            permissions: [],
        );

        $this->expectException(UnauthorizedToolException::class);
        $this->expectExceptionMessage("Missing required permission 'analytics.view_margins' for tool 'view_margins'");

        $this->engine->executeTool(
            tool: $financialTool,
            arguments: [],
            context: $context,
        );
    }

    public function test_run_native_laravel_ai_agent_integration(): void
    {
        $nativeAgent = new FeatureNativeAiAgent();
        FeatureNativeAiAgent::fake(['Gross profit increased by 14.2% quarter-over-quarter.']);

        $context = $this->createAuthenticatedContext();

        $response = $this->engine->run(
            agent: $nativeAgent,
            userPrompt: 'Summarize profitability',
            context: $context,
        );

        $this->assertTrue($response->isSuccess());
        $this->assertSame(AgentResponse::STATUS_SUCCESS, $response->status);
        $this->assertSame('Gross profit increased by 14.2% quarter-over-quarter.', $response->content);
        $this->assertSame(1, $response->telemetry['iterations']);
        $this->assertSame($context->correlationId, $response->telemetry['correlation_id']);
        $this->assertTrue($nativeAgent->turnStarted);
        $this->assertTrue($nativeAgent->turnEnded);
    }

    public function test_run_propagates_remaining_sla_timeout_to_native_agent(): void
    {
        $nativeAgent = new TimeoutCapturingNativeAgent();
        $context = $this->createAuthenticatedContext(timeoutSeconds: 3.5);

        $response = $this->engine->run(
            agent: $nativeAgent,
            userPrompt: 'Test timeout propagation',
            context: $context,
        );

        $this->assertTrue($response->isSuccess());
        // Timeout ceiling should be propagated to prompt() as an integer
        $this->assertNotNull($nativeAgent->capturedTimeout);
        $this->assertGreaterThanOrEqual(1, $nativeAgent->capturedTimeout);
        $this->assertLessThanOrEqual(4, $nativeAgent->capturedTimeout);
    }

    public function test_run_rejects_fourth_step_in_native_agent_via_event_listener(): void
    {
        $nativeAgent = new StepEventEmittingNativeAgent();
        $context = $this->createAuthenticatedContext(maxIterations: 3);

        $response = $this->engine->run(
            agent: $nativeAgent,
            userPrompt: 'Trigger 4 steps',
            context: $context,
        );

        $this->assertSame(AgentResponse::STATUS_MAX_ITERATIONS_EXCEEDED, $response->status);
        $this->assertStringContainsString('step count 4 exceeds maximum allowed iterations (3)', $response->error);
    }

    public function test_non_native_agent_bounded_execution_and_fourth_step_rejection(): void
    {
        // Non-native agent configured to request 4 steps
        $multiStepAgent = new MultiStepTestAgent(stepsToRun: 4);
        $context = $this->createAuthenticatedContext(maxIterations: 3);

        $response = $this->engine->run(
            agent: $multiStepAgent,
            userPrompt: 'Run 4 non-native steps',
            context: $context,
        );

        $this->assertSame(AgentResponse::STATUS_MAX_ITERATIONS_EXCEEDED, $response->status);
        $this->assertStringContainsString('requested steps exceeding maximum allowed iterations (3)', $response->error);
    }

    public function test_non_native_agent_terminates_cleanly_within_three_steps(): void
    {
        // Non-native agent configured to request exactly 2 steps
        $multiStepAgent = new MultiStepTestAgent(stepsToRun: 2);
        $context = $this->createAuthenticatedContext(maxIterations: 3);

        $response = $this->engine->run(
            agent: $multiStepAgent,
            userPrompt: 'Run 2 non-native steps',
            context: $context,
        );

        $this->assertTrue($response->isSuccess());
        $this->assertSame(2, $response->telemetry['iterations']);
        $this->assertCount(2, $response->steps);
        $this->assertSame('Step 1 complete; Step 2 complete; ', $response->content);
    }

    public function test_run_handles_native_laravel_ai_unauthorized_tool_denial_gracefully(): void
    {
        $nativeAgent = new SecurityThrowingNativeAgent();
        $context = $this->createAuthenticatedContext();

        $response = $this->engine->run(
            agent: $nativeAgent,
            userPrompt: 'Trigger forbidden action',
            context: $context,
        );

        $this->assertTrue($response->isDenied());
        $this->assertSame(AgentResponse::STATUS_AUTHORIZATION_DENIED, $response->status);
        $this->assertSame('Agent attempted unauthorized tool execution.', $response->error);
        $this->assertTrue($nativeAgent->turnStarted);
        $this->assertTrue($nativeAgent->turnEnded);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Test Doubles
// ─────────────────────────────────────────────────────────────────────────────

class FeatureTestAgent implements AgentInterface
{
    public bool $turnStarted = false;
    public bool $turnEnded = false;
    public ?AgentResponse $lastResponse = null;

    /**
     * @param array<int, ToolInterface> $registeredTools
     */
    public function __construct(
        private readonly string $agentId = 'feature_agent',
        private readonly string $roleName = 'Feature Test Agent',
        private readonly array $registeredTools = [],
    ) {}

    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getRoleName(): string
    {
        return $this->roleName;
    }

    public function getSystemPrompt(AgentContext $context): string
    {
        return 'System prompt for feature agent';
    }

    public function getRegisteredTools(): array
    {
        return $this->registeredTools;
    }

    public function maxSteps(): int
    {
        return 3;
    }

    public function onTurnStart(AgentContext $context): void
    {
        $this->turnStarted = true;
    }

    public function onTurnEnd(AgentContext $context, AgentResponse $response): void
    {
        $this->turnEnded = true;
        $this->lastResponse = $response;
    }
}

class MultiStepTestAgent implements AgentInterface
{
    public bool $turnStarted = false;
    public bool $turnEnded = false;
    private int $currentStep = 0;

    public function __construct(
        private readonly int $stepsToRun = 2,
    ) {}

    public function getAgentId(): string
    {
        return 'multistep_agent';
    }

    public function getRoleName(): string
    {
        return 'Multi-Step Test Agent';
    }

    public function getSystemPrompt(AgentContext $context): string
    {
        return 'Prompt';
    }

    public function getRegisteredTools(): array
    {
        return [];
    }

    public function maxSteps(): int
    {
        return 3;
    }

    public function onTurnStart(AgentContext $context): void
    {
        $this->turnStarted = true;
    }

    public function onTurnEnd(AgentContext $context, AgentResponse $response): void
    {
        $this->turnEnded = true;
    }

    public function executeStep(int $step, string $prompt, AgentContext $context): ?string
    {
        $this->currentStep = $step;
        return "Step {$step} complete; ";
    }

    public function hasMoreSteps(): bool
    {
        return $this->currentStep < $this->stepsToRun;
    }
}

class FeatureTestTool extends AbstractGovernedTool
{
    public function __construct(
        private readonly string $name = 'feature_tool',
        private readonly bool $isReadOnly = true,
        private readonly array $permissions = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): Stringable|string
    {
        return 'Feature test tool';
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
        return $this->permissions;
    }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        return ToolResult::success(['received' => $arguments]);
    }
}

class FeatureProjectionTestTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'projection_tool';
    }

    public function description(): Stringable|string
    {
        return 'Projection tool';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRequiredPermissions(): array
    {
        return [];
    }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        return ToolResult::success([
            'name'          => 'Widget',
            'selling_price' => 99.0,
            'cost_price'    => 40.0,
            'margin'        => 59.0,
        ]);
    }
}

class FeatureDualCategoryProjectionTool extends AbstractGovernedTool
{
    public function name(): string { return 'dual_projection_tool'; }
    public function description(): Stringable|string { return 'Dual projection tool'; }
    public function schema(JsonSchema $schema): array { return []; }
    public function isReadOnly(): bool { return true; }
    public function getRequiredPermissions(): array { return []; }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        return ToolResult::success([
            'revenue'      => 10000,
            'gross_margin' => 0.35,
            'base_salary'  => 4500,
            'report'       => "Revenue: $10000\nGross Margin: 35%\nBase Salary: $4500\n",
        ]);
    }
}

class FeatureConjunctiveProjectionTool extends AbstractGovernedTool
{
    public function name(): string { return 'conjunctive_projection_tool'; }
    public function description(): Stringable|string { return 'Conjunctive projection tool'; }
    public function schema(JsonSchema $schema): array { return []; }
    public function isReadOnly(): bool { return true; }
    public function getRequiredPermissions(): array { return []; }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        return ToolResult::success([
            'revenue'       => 10000,
            'gross_margin'  => 0.35,
            'base_salary'   => 4500,
            'employee_cost' => 5200,
            'report'        => "Revenue: $10000\nGross Margin: 35%\nBase Salary: $4500\nEmployee Cost: $5200\n",
        ]);
    }
}

class RealSdkFeatureAgent implements AgentInterface, LaravelAiAgent, \Laravel\Ai\Contracts\HasTools
{
    use Promptable;

    public function __construct(
        private readonly array $tools = []
    ) {}

    public function instructions(): string { return 'Real SDK Agent Instructions'; }
    public function messages(): iterable { return []; }
    public function tools(): iterable { return $this->tools; }
    public function getAgentId(): string { return 'real_sdk_feature_agent'; }
    public function getRoleName(): string { return 'Real SDK Feature Agent'; }
    public function getSystemPrompt(AgentContext $context): string { return $this->instructions(); }
    public function getRegisteredTools(): array { return $this->tools; }
    public function maxSteps(): int { return 3; }
    public function onTurnStart(AgentContext $context): void {}
    public function onTurnEnd(AgentContext $context, AgentResponse $response): void {}
}

class FeatureNativeAiAgent implements AgentInterface, LaravelAiAgent
{
    use Promptable;

    public bool $turnStarted = false;
    public bool $turnEnded = false;

    public function instructions(): string
    {
        return 'Native AI Agent Instructions';
    }

    public function getAgentId(): string
    {
        return 'native_ai_agent';
    }

    public function getRoleName(): string
    {
        return 'Native Laravel AI Agent';
    }

    public function getSystemPrompt(AgentContext $context): string
    {
        return $this->instructions();
    }

    public function getRegisteredTools(): array
    {
        return [];
    }

    public function maxSteps(): int
    {
        return 3;
    }

    public function onTurnStart(AgentContext $context): void
    {
        $this->turnStarted = true;
    }

    public function onTurnEnd(AgentContext $context, AgentResponse $response): void
    {
        $this->turnEnded = true;
    }
}

class TimeoutCapturingNativeAgent implements AgentInterface, LaravelAiAgent
{
    use Promptable;

    public ?int $capturedTimeout = null;

    public function instructions(): string
    {
        return 'Instructions';
    }

    public function getAgentId(): string
    {
        return 'timeout_agent';
    }

    public function getRoleName(): string
    {
        return 'Timeout Agent';
    }

    public function getSystemPrompt(AgentContext $context): string
    {
        return 'Prompt';
    }

    public function getRegisteredTools(): array
    {
        return [];
    }

    public function maxSteps(): int
    {
        return 3;
    }

    public function onTurnStart(AgentContext $context): void {}

    public function onTurnEnd(AgentContext $context, AgentResponse $response): void {}

    public function prompt(
        \Laravel\Ai\Approvals\Decisions|string $prompt,
        array $attachments = [],
        \Laravel\Ai\Enums\Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null
    ): \Laravel\Ai\Responses\AgentResponse {
        $this->capturedTimeout = $timeout;

        return new \Laravel\Ai\Responses\AgentResponse(
            invocationId: 'inv-1',
            text: 'Timeout captured successfully.',
            usage: new \Laravel\Ai\Responses\Data\Usage(),
            meta: new \Laravel\Ai\Responses\Data\Meta('test_provider', 'test_model'),
        );
    }
}

class StepEventEmittingNativeAgent implements AgentInterface, LaravelAiAgent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Instructions';
    }

    public function getAgentId(): string
    {
        return 'step_agent';
    }

    public function getRoleName(): string
    {
        return 'Step Agent';
    }

    public function getSystemPrompt(AgentContext $context): string
    {
        return 'Prompt';
    }

    public function getRegisteredTools(): array
    {
        return [];
    }

    public function maxSteps(): int
    {
        return 3;
    }

    public function onTurnStart(AgentContext $context): void {}

    public function onTurnEnd(AgentContext $context, AgentResponse $response): void {}

    public function prompt(
        \Laravel\Ai\Approvals\Decisions|string $prompt,
        array $attachments = [],
        \Laravel\Ai\Enums\Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null
    ): \Laravel\Ai\Responses\AgentResponse {
        $dummyProvider = \Mockery::mock(\Laravel\Ai\Contracts\Providers\TextProvider::class);

        // Simulate 4 successive steps
        for ($step = 0; $step < 4; $step++) {
            Event::dispatch(new StartingStep(
                invocationId: 'inv-step',
                stepNumber: $step,
                agent: $this,
                provider: $dummyProvider,
                model: 'dummy-model',
                isFinalStep: $step >= 2,
                messages: [],
                options: null,
            ));
        }

        return new \Laravel\Ai\Responses\AgentResponse(
            invocationId: 'inv-step',
            text: 'Completed',
            usage: new \Laravel\Ai\Responses\Data\Usage(),
            meta: new \Laravel\Ai\Responses\Data\Meta('dummy', 'dummy'),
        );
    }
}

class SecurityThrowingNativeAgent implements AgentInterface, LaravelAiAgent
{
    use Promptable;

    public bool $turnStarted = false;
    public bool $turnEnded = false;

    public function instructions(): string
    {
        return 'Security Throwing Native Agent';
    }

    public function getAgentId(): string
    {
        return 'security_throwing_agent';
    }

    public function getRoleName(): string
    {
        return 'Security Throwing Native Agent';
    }

    public function getSystemPrompt(AgentContext $context): string
    {
        return $this->instructions();
    }

    public function getRegisteredTools(): array
    {
        return [];
    }

    public function maxSteps(): int
    {
        return 3;
    }

    public function onTurnStart(AgentContext $context): void
    {
        $this->turnStarted = true;
    }

    public function onTurnEnd(AgentContext $context, AgentResponse $response): void
    {
        $this->turnEnded = true;
    }

    public function prompt(
        \Laravel\Ai\Approvals\Decisions|string $prompt,
        array $attachments = [],
        \Laravel\Ai\Enums\Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null
    ): \Laravel\Ai\Responses\AgentResponse {
        throw new UnauthorizedToolException(
            message: 'Agent attempted unauthorized tool execution.',
            toolName: 'forbidden_tool',
            agentId: 'security_throwing_agent',
        );
    }
}
