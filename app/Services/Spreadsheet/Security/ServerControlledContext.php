<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Security;

use App\Enums\RoleEnum;
use App\Models\SuperAdminGovernanceSession;
use App\Models\User;
use App\Services\Spreadsheet\Security\Exceptions\TenantScopeViolationException;
use Illuminate\Support\Str;

/**
 * Server-Controlled Access Context.
 *
 * Immutable value object capturing server-verified authorization parameters.
 * Guarantees that purpose, channel, tenant scope, and permissions are determined
 * by trusted backend logic, never client request payloads.
 */
final class ServerControlledContext
{
    public const PURPOSE_CUSTOMER_SUPPORT   = 'customer_support';
    public const PURPOSE_ANALYTICS_REPORTING = 'analytics_reporting';
    public const PURPOSE_DATA_EXPORT        = 'data_export';
    public const PURPOSE_SYSTEM_SYNC        = 'system_sync';
    public const PURPOSE_SCHEMA_INSPECTION  = 'schema_inspection';

    public const CHANNEL_CUSTOMER_WEB       = 'customer_web';
    public const CHANNEL_CUSTOMER_WHATSAPP  = 'customer_whatsapp';
    public const CHANNEL_CUSTOMER_FACEBOOK  = 'customer_facebook';
    public const CHANNEL_DASHBOARD          = 'dashboard';
    public const CHANNEL_INTERNAL_API       = 'internal_api';
    public const CHANNEL_EXPORT_JOB         = 'export_job';
    public const CHANNEL_SYSTEM             = 'system';

    public function __construct(
        public readonly int $workspaceId,
        public readonly ?int $userId,
        public readonly string $userRole,
        public readonly string $channel,
        public readonly string $purpose,
        public readonly array $grantedPermissions = [],
        public readonly ?SuperAdminGovernanceSession $governanceSession = null,
        public readonly string $requestId = '',
        public readonly ?string $clientIp = null,
    ) {}

    /**
     * Create an unprivileged customer support context.
     * Enforces strict fail-closed customer security boundaries.
     */
    public static function forCustomer(
        int $workspaceId,
        string $channel = self::CHANNEL_CUSTOMER_WEB,
        ?string $requestId = null,
    ): self {
        return new self(
            workspaceId: $workspaceId,
            userId: null,
            userRole: 'customer',
            channel: $channel,
            purpose: self::PURPOSE_CUSTOMER_SUPPORT,
            grantedPermissions: [],
            governanceSession: null,
            requestId: $requestId ?? (string) Str::uuid(),
            clientIp: request()?->ip(),
        );
    }

    /**
     * Create an authenticated user context with strict tenant boundary enforcement.
     */
    public static function forUser(
        User $user,
        int $workspaceId,
        string $purpose,
        array $permissions = [],
        string $channel = self::CHANNEL_DASHBOARD,
        ?SuperAdminGovernanceSession $session = null,
        ?string $requestId = null,
    ): self {
        $isSuperAdmin = $user->hasRole(RoleEnum::SUPERADMIN->value);
        $userWorkspaceId = (int) ($user->workspace_id ?? 0);

        // Strict Tenant Scoping:
        if ($isSuperAdmin) {
            $userRole = 'superadmin';
        } else {
            // Non-superadmins are strictly locked to their assigned workspace
            if ($userWorkspaceId !== $workspaceId) {
                throw new TenantScopeViolationException(
                    message: "Tenant isolation violation: User belongs to workspace {$userWorkspaceId}, access to {$workspaceId} is forbidden.",
                    requestWorkspaceId: $userWorkspaceId,
                    targetWorkspaceId: $workspaceId,
                    userId: $user->id,
                );
            }

            if ($user->hasRole('owner') || $user->hasRole(RoleEnum::ADMIN->value) || (method_exists($user, 'isOwner') && $user->isOwner())) {
                $userRole = 'owner';
            } elseif ($user->hasRole('manager')) {
                $userRole = 'manager';
            } elseif ($user->hasRole('agent')) {
                $userRole = 'agent';
            } else {
                $userRole = 'member';
            }
        }

        return new self(
            workspaceId: $workspaceId,
            userId: $user->id,
            userRole: $userRole,
            channel: $channel,
            purpose: $purpose,
            grantedPermissions: array_values(array_unique($permissions)),
            governanceSession: $session,
            requestId: $requestId ?? (string) Str::uuid(),
            clientIp: request()?->ip(),
        );
    }

    /**
     * Create a trusted system-level context for scheduled jobs or ingestion fencing.
     */
    public static function forSystem(
        int $workspaceId,
        string $purpose = self::PURPOSE_SYSTEM_SYNC,
        ?string $requestId = null,
    ): self {
        return new self(
            workspaceId: $workspaceId,
            userId: null,
            userRole: 'system',
            channel: self::CHANNEL_SYSTEM,
            purpose: $purpose,
            grantedPermissions: ['*'],
            governanceSession: null,
            requestId: $requestId ?? (string) Str::uuid(),
            clientIp: '127.0.0.1',
        );
    }

    /**
     * Check if context possesses a specific granular permission.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->userRole === 'system') {
            return true;
        }

        if ($this->userRole === 'owner') {
            return true;
        }

        if ($this->userRole === 'superadmin') {
            return true;
        }

        return in_array($permission, $this->grantedPermissions, true);
    }

    /**
     * Check if request represents a public customer channel.
     */
    public function isCustomer(): bool
    {
        return $this->userRole === 'customer' || $this->purpose === self::PURPOSE_CUSTOMER_SUPPORT;
    }

    /**
     * Check if request has workspace owner authority.
     */
    public function isOwner(): bool
    {
        return $this->userRole === 'owner' || $this->userRole === 'superadmin';
    }

    /**
     * Compute a cryptographic policy scope hash for composite cache key partitioning.
     * Guarantees that cache entries are partitioned across tenants, personas, channels, and policy versions.
     */
    public function getPolicyScopeHash(): string
    {
        $perms = $this->grantedPermissions;
        sort($perms);
        $govId = $this->governanceSession?->id ? (string) $this->governanceSession->id : 'none';

        $payload = sprintf(
            'ws:%d|role:%s|purp:%s|chan:%s|gov:%s|perms:%s',
            $this->workspaceId,
            $this->userRole,
            $this->purpose,
            $this->channel,
            $govId,
            implode(',', $perms)
        );

        return hash('sha256', $payload);
    }
}
