<?php

declare(strict_types=1);

namespace App\AI\DTOs;

use App\Models\Conversation;
use App\Models\User;
use App\AI\Contracts\AgentInterface;
use App\AI\Exceptions\MissingAgentContextException;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use App\Services\Spreadsheet\Security\SuperAdminGovernanceSession;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Immutable execution context for governed AI agents and tools.
 * Wraps ServerControlledContext to guarantee strict multi-tenant isolation,
 * monotonic SLA execution budgets, and bounded iteration limits.
 */
class AgentContext
{
    /**
     * Ambient execution context storage for the current execution thread.
     * Note: Uses static property storage. In single-threaded PHP-FPM and CLI worker processes,
     * context is isolated per request/job. It does not provide Fiber-local storage across interleaved
     * PHP Fibers; concurrent Fibers within the same process must not interleave ambient context
     * scopes without serial execution.
     */
    private static ?self $current = null;

    /**
     * Tracked count of model / tool execution steps taken in this turn.
     */
    private int $stepCount = 0;

    /**
     * Monotonic start time in seconds (float).
     */
    public readonly float $startedAt;

    /**
     * Unique correlation ID for distributed tracing and observability.
     */
    public readonly string $correlationId;

    /**
     * Cached User model reference if constructed via forUser().
     */
    private ?User $user = null;

    /**
     * Current agent instance bound to this turn, if assigned.
     */
    private ?AgentInterface $agent = null;

    /**
     * @param ServerControlledContext $serverControlledContext Authoritative tenant and security context
     * @param int $maxIterations Maximum allowed tool execution iterations (hard capped at 3)
     * @param float $timeoutSeconds Hard wall-clock SLA deadline (defaults to 4.0 seconds)
     * @param Conversation|null $conversation Optional conversation model
     * @param string|null $correlationId
     */
    public function __construct(
        public readonly ServerControlledContext $serverControlledContext,
        public readonly int $maxIterations = 3,
        public readonly float $timeoutSeconds = 4.0,
        public readonly ?Conversation $conversation = null,
        ?string $correlationId = null,
    ) {
        $this->startedAt = hrtime(true) / 1e9;
        $this->correlationId = $correlationId ?? (string) Str::uuid();

        // Enforce fail-closed boundary on construction: workspace must be positive integer
        if ($this->serverControlledContext->workspaceId <= 0) {
            throw new InvalidArgumentException(
                "AgentContext requires a valid, positive workspaceId. Received: {$this->serverControlledContext->workspaceId}"
            );
        }
    }

    /**
     * Factory for creating an AgentContext for an authenticated User.
     *
     * @param User $user Authenticated user principal
     * @param int $workspaceId Target workspace ID (must match user workspace or valid SuperAdmin session)
     * @param string $purpose Operational purpose (e.g. PURPOSE_ANALYTICS_REPORTING)
     * @param array<int, string> $permissions Explicitly granted permissions
     * @param string $channel Ingress channel (e.g. CHANNEL_DASHBOARD)
     * @param SuperAdminGovernanceSession|null $session
     * @param Conversation|null $conversation
     * @param int $maxIterations Hard cap <= 3
     * @param float $timeoutSeconds Hard SLA ceiling <= 4.0s
     */
    public static function forUser(
        User $user,
        int $workspaceId,
        string $purpose = ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
        array $permissions = [],
        string $channel = ServerControlledContext::CHANNEL_DASHBOARD,
        ?SuperAdminGovernanceSession $session = null,
        ?Conversation $conversation = null,
        int $maxIterations = 3,
        float $timeoutSeconds = 4.0,
    ): self {
        $scc = ServerControlledContext::forUser(
            user: $user,
            workspaceId: $workspaceId,
            purpose: $purpose,
            permissions: $permissions,
            channel: $channel,
            session: $session,
        );

        $instance = new self(
            serverControlledContext: $scc,
            maxIterations: min(max(1, $maxIterations), 3),
            timeoutSeconds: min(max(0.001, $timeoutSeconds), 4.0),
            conversation: $conversation,
        );
        $instance->user = $user;

        return $instance;
    }

    /**
     * Factory for creating an AgentContext for a public Customer channel.
     *
     * @param int $workspaceId
     * @param string $channel
     * @param string|null $requestId
     * @param Conversation|null $conversation
     * @param int $maxIterations
     * @param float $timeoutSeconds
     */
    public static function forCustomer(
        int $workspaceId,
        string $channel = ServerControlledContext::CHANNEL_CUSTOMER_WEB,
        ?string $requestId = null,
        ?Conversation $conversation = null,
        int $maxIterations = 1,
        float $timeoutSeconds = 3.0,
    ): self {
        $scc = ServerControlledContext::forCustomer(
            workspaceId: $workspaceId,
            channel: $channel,
            requestId: $requestId,
        );

        return new self(
            serverControlledContext: $scc,
            maxIterations: min(max(1, $maxIterations), 3),
            timeoutSeconds: min(max(0.001, $timeoutSeconds), 4.0),
            conversation: $conversation,
            correlationId: $requestId,
        );
    }

    /**
     * Factory wrapping an existing ServerControlledContext.
     */
    public static function fromServerControlledContext(
        ServerControlledContext $scc,
        ?Conversation $conversation = null,
        int $maxIterations = 3,
        float $timeoutSeconds = 4.0,
        ?User $user = null,
    ): self {
        $instance = new self(
            serverControlledContext: $scc,
            maxIterations: min(max(1, $maxIterations), 3),
            timeoutSeconds: min(max(0.001, $timeoutSeconds), 4.0),
            conversation: $conversation,
        );
        $instance->user = $user;

        return $instance;
    }

    // ─── AMBIENT CONTEXT MANAGEMENT (FAIL-CLOSED) ─────────────────────────────

    /**
     * Retrieve the ambient AgentContext for the current execution scope.
     *
     * @throws MissingAgentContextException If no context has been set (Fail-Closed).
     */
    public static function current(): self
    {
        if (self::$current === null) {
            throw new MissingAgentContextException(
                'No active AgentContext in current execution scope. Governed tools must be invoked within an authorized runtime.'
            );
        }

        return self::$current;
    }

    /**
     * Check if an ambient AgentContext is currently active.
     */
    public static function hasCurrent(): bool
    {
        return self::$current !== null;
    }

    /**
     * Set the current ambient context explicitly.
     */
    public static function setCurrent(?self $context): void
    {
        self::$current = $context;
    }

    /**
     * Clear the ambient context.
     */
    public static function clear(): void
    {
        self::$current = null;
    }

    /**
     * Execute a callback within an ambient AgentContext scope, guaranteeing cleanup.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public static function withContext(self $context, callable $callback): mixed
    {
        $previous = self::$current;
        self::$current = $context;

        try {
            return $callback($context);
        } finally {
            self::$current = $previous;
        }
    }

    // ─── EXECUTION STEP TRACKING ─────────────────────────────────────────────

    /**
     * Get current execution step count.
     */
    public function getStepCount(): int
    {
        return $this->stepCount;
    }

    /**
     * Increment and record an execution step.
     */
    public function recordStep(): int
    {
        $this->stepCount++;
        return $this->stepCount;
    }

    // ─── MONOTONIC SLA BUDGET & TIMEOUT ───────────────────────────────────────

    /**
     * Get remaining wall-clock seconds before SLA deadline.
     */
    public function getRemainingSeconds(): float
    {
        $elapsed = (hrtime(true) / 1e9) - $this->startedAt;
        return max(0.0, $this->timeoutSeconds - $elapsed);
    }

    /**
     * Alias for getRemainingSeconds().
     */
    public function getRemainingTimeout(): float
    {
        return $this->getRemainingSeconds();
    }

    /**
     * Check if the execution has exceeded its wall-clock SLA budget.
     */
    public function isExpired(): bool
    {
        $elapsed = (hrtime(true) / 1e9) - $this->startedAt;
        return $elapsed >= $this->timeoutSeconds;
    }

    /**
     * Get elapsed time in milliseconds.
     */
    public function getElapsedMs(): float
    {
        $elapsed = (hrtime(true) / 1e9) - $this->startedAt;
        return round($elapsed * 1000, 2);
    }

    // ─── CONTEXT ACCESSORS DELEGATING TO TRUSTED BACKEND ─────────────────────

    public function getWorkspaceId(): int
    {
        return $this->serverControlledContext->workspaceId;
    }

    public function getUserId(): ?int
    {
        return $this->serverControlledContext->userId;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getUserRole(): string
    {
        return $this->serverControlledContext->userRole;
    }

    public function isOwner(): bool
    {
        return $this->serverControlledContext->isOwner();
    }

    public function isCustomer(): bool
    {
        return $this->serverControlledContext->isCustomer();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->serverControlledContext->hasPermission($permission);
    }

    public function getPurpose(): string
    {
        return $this->serverControlledContext->purpose;
    }

    public function getChannel(): string
    {
        return $this->serverControlledContext->channel;
    }

    public function getAgent(): ?AgentInterface
    {
        return $this->agent;
    }

    public function withAgent(AgentInterface $agent): self
    {
        $clone = clone $this;
        $clone->agent = $agent;
        return $clone;
    }
}
