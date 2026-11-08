<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Agent;

use App\Models\User;
use App\AI\DTOs\AgentContext;
use App\AI\Exceptions\MissingAgentContextException;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use InvalidArgumentException;
use Tests\TestCase;

class AgentContextTest extends TestCase
{
    public function test_from_server_controlled_context_creates_trusted_immutable_context(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 10,
            userId: 42,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );

        $context = AgentContext::fromServerControlledContext(
            scc: $scc,
            maxIterations: 3,
            timeoutSeconds: 4.0,
        );

        $this->assertSame(10, $context->getWorkspaceId());
        $this->assertSame(42, $context->getUserId());
        $this->assertSame('owner', $context->getUserRole());
        $this->assertSame(ServerControlledContext::PURPOSE_ANALYTICS_REPORTING, $context->getPurpose());
        $this->assertSame(ServerControlledContext::CHANNEL_DASHBOARD, $context->getChannel());
        $this->assertSame(3, $context->maxIterations);
        $this->assertEqualsWithDelta(4.0, $context->timeoutSeconds, 0.01);
        $this->assertNotEmpty($context->correlationId);
        $this->assertFalse($context->isCustomer());
        $this->assertTrue($context->isOwner());
        $this->assertTrue($context->hasPermission('analytics.view_margins'));
    }

    public function test_for_customer_creates_unprivileged_context(): void
    {
        $context = AgentContext::forCustomer(
            workspaceId: 15,
            channel: ServerControlledContext::CHANNEL_CUSTOMER_FACEBOOK,
        );

        $this->assertSame(15, $context->getWorkspaceId());
        $this->assertTrue($context->isCustomer());
        $this->assertFalse($context->isOwner());
        $this->assertSame(ServerControlledContext::CHANNEL_CUSTOMER_FACEBOOK, $context->getChannel());
        $this->assertSame(1, $context->maxIterations);
    }

    public function test_constructor_fails_closed_when_workspace_id_is_non_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('valid, positive workspaceId');

        $scc = new ServerControlledContext(
            workspaceId: 0,
            userId: null,
            userRole: 'customer',
            channel: ServerControlledContext::CHANNEL_CUSTOMER_WEB,
            purpose: ServerControlledContext::PURPOSE_CUSTOMER_SUPPORT,
        );

        new AgentContext(serverControlledContext: $scc);
    }

    public function test_max_iterations_is_strictly_capped_at_three(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );

        $context = AgentContext::fromServerControlledContext(
            scc: $scc,
            maxIterations: 100, // Attempt to exceed ceiling
        );

        $this->assertSame(3, $context->maxIterations);
    }

    public function test_timeout_seconds_is_strictly_capped_at_four(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 5,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );

        $context = AgentContext::fromServerControlledContext(
            scc: $scc,
            timeoutSeconds: 60.0, // Attempt to exceed SLA ceiling
        );

        $this->assertEqualsWithDelta(4.0, $context->timeoutSeconds, 0.01);
    }

    public function test_current_throws_missing_context_exception_when_ambient_is_absent(): void
    {
        AgentContext::clear();

        $this->expectException(MissingAgentContextException::class);
        $this->expectExceptionMessage('No active AgentContext');

        AgentContext::current();
    }

    public function test_with_context_sets_ambient_and_restores_previous_on_completion(): void
    {
        AgentContext::clear();
        $this->assertFalse(AgentContext::hasCurrent());

        $scc = new ServerControlledContext(
            workspaceId: 7,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $context = AgentContext::fromServerControlledContext($scc);

        $result = AgentContext::withContext($context, function (AgentContext $ctx) use ($context) {
            $this->assertTrue(AgentContext::hasCurrent());
            $this->assertSame($context, AgentContext::current());
            return 'success_val';
        });

        $this->assertSame('success_val', $result);
        $this->assertFalse(AgentContext::hasCurrent());
    }

    public function test_with_context_restores_ambient_even_if_callback_throws(): void
    {
        AgentContext::clear();

        $scc = new ServerControlledContext(
            workspaceId: 7,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $context = AgentContext::fromServerControlledContext($scc);

        try {
            AgentContext::withContext($context, function () {
                throw new \RuntimeException('Kaboom!');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Kaboom!', $e->getMessage());
        }

        $this->assertFalse(AgentContext::hasCurrent());
    }

    public function test_monotonic_timer_computes_remaining_and_expiration(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 1,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );

        // Context with very short timeout
        $context = AgentContext::fromServerControlledContext(
            scc: $scc,
            timeoutSeconds: 0.5,
        );

        $this->assertGreaterThan(0.0, $context->getRemainingSeconds());
        $this->assertFalse($context->isExpired());

        // Sleep to exhaust budget
        usleep(550000); // 0.55s

        $this->assertSame(0.0, $context->getRemainingSeconds());
        $this->assertTrue($context->isExpired());
    }

    public function test_nested_context_restoration(): void
    {
        AgentContext::clear();

        $scc1 = new ServerControlledContext(
            workspaceId: 1,
            userId: 10,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $contextA = AgentContext::fromServerControlledContext($scc1);

        $scc2 = new ServerControlledContext(
            workspaceId: 2,
            userId: 20,
            userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $contextB = AgentContext::fromServerControlledContext($scc2);

        AgentContext::withContext($contextA, function () use ($contextA, $contextB) {
            $this->assertSame($contextA, AgentContext::current());

            AgentContext::withContext($contextB, function () use ($contextB) {
                $this->assertSame($contextB, AgentContext::current());
                $this->assertSame(2, AgentContext::current()->getWorkspaceId());
            });

            // Restored to contextA
            $this->assertSame($contextA, AgentContext::current());
            $this->assertSame(1, AgentContext::current()->getWorkspaceId());
        });

        $this->assertFalse(AgentContext::hasCurrent());
    }

    public function test_step_tracking_and_increment(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 1,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $context = AgentContext::fromServerControlledContext($scc);

        $this->assertSame(0, $context->getStepCount());
        $this->assertSame(1, $context->recordStep());
        $this->assertSame(2, $context->recordStep());
        $this->assertSame(2, $context->getStepCount());
    }

    public function test_app_terminating_cleans_ambient_context(): void
    {
        $scc = new ServerControlledContext(
            workspaceId: 1,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        );
        $context = AgentContext::fromServerControlledContext($scc);

        AgentContext::setCurrent($context);
        $this->assertTrue(AgentContext::hasCurrent());

        // Simulate application termination event
        app()->terminate();

        $this->assertFalse(AgentContext::hasCurrent());
    }
}
