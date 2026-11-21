<?php

namespace App\Enums;

/**
 * RoleEnum — Single source of truth for all role names.
 *
 * Usage:
 *   RoleEnum::SUPERADMIN->value  → 'superadmin'  (for Spatie)
 *   RoleEnum::from('admin')      → RoleEnum::ADMIN
 *
 * To add a new role, add a case here and define its permissions in RoleSeeder.
 */
enum RoleEnum: string
{
    case SUPERADMIN = 'superadmin';
    case PLATFORM_SUPPORT = 'platform_support';
    case ADMIN = 'admin';
    case WORKSPACE_MANAGER = 'workspace_manager';
    case WORKSPACE_AGENT = 'workspace_agent';

    /**
     * Get all role values as an array.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Super Admin',
            self::PLATFORM_SUPPORT => 'Platform Operations Support',
            self::ADMIN => 'Business Owner (Admin)',
            self::WORKSPACE_MANAGER => 'Store Manager',
            self::WORKSPACE_AGENT => 'Support & Sales Agent',
        };
    }

    /**
     * Roles assignable by a Business Admin (Businessman).
     * Strictly restricted to Manager and Agent. Cannot create another admin or superadmin.
     */
    public static function assignableByBusinessAdmin(): array
    {
        return [
            self::WORKSPACE_MANAGER->value => 'Store Manager',
            self::WORKSPACE_AGENT->value   => 'Support & Sales Agent',
        ];
    }

    /**
     * Get the roles that should be hidden from non-superadmin users.
     *
     * @return array<int, string>
     */
    public static function hiddenFromNonSuperadmin(): array
    {
        return [self::SUPERADMIN->value, self::PLATFORM_SUPPORT->value];
    }
}
