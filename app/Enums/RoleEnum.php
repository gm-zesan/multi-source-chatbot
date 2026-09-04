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
    case ADMIN = 'admin';

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
            self::ADMIN => 'Admin',
        };
    }

    /**
     * Get the roles that should be hidden from non-superadmin users.
     *
     * @return array<int, string>
     */
    public static function hiddenFromNonSuperadmin(): array
    {
        return [self::SUPERADMIN->value];
    }
}
