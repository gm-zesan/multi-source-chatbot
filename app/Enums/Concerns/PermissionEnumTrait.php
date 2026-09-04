<?php

namespace App\Enums\Concerns;

/**
 * Shared trait for all module-based permission enums.
 *
 * Provides:
 * - values()       — all permission string values
 * - displayName()  — human-readable label from the enum value
 * - module()       — the module/group name (derived from value's prefix)
 * - seederData()   — array for database seeding
 *
 * @template T of \BackedEnum
 * @method static T[] cases()
 * @property string $value
 */
trait PermissionEnumTrait
{
    /**
     * Get all permission values as an array.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Derive the module/group name from the enum value prefix.
     * e.g. 'user.view' → 'user', 'workspace-user.view' → 'workspace-user'
     */
    public static function module(): string
    {
        // Use the first case's value prefix as the module name
        $firstValue = self::cases()[0]->value;

        return explode('.', $firstValue)[0];
    }

    /**
     * Human-readable display name.
     * e.g. 'user.view' → 'User view'
     */
    public function displayName(): string
    {
        $parts = explode('.', $this->value);
        $resource = ucfirst(str_replace('-', ' ', $parts[0]));
        $action = ucfirst($parts[1] ?? '');

        return "{$resource} {$action}";
    }

    /**
     * Get seeder-compatible data array for all cases.
     *
     * @return array<int, array{name: string, display_name: string, module: string}>
     */
    public static function seederData(): array
    {
        return array_map(fn (self $case) => [
            'name' => $case->value,
            'display_name' => $case->displayName(),
            'module' => self::module(),
        ], self::cases());
    }
}
