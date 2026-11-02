<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Security;

use App\Enums\DataClassification;

/**
 * Data Classification Registry.
 *
 * Provides an in-memory extensible classification registry mapping custom semantic roles
 * to DataClassification enum tiers.
 */
class DataClassificationRegistry
{
    /**
     * @var array<string, DataClassification>
     */
    private static array $customRegistry = [];

    public static function register(string $role, DataClassification $classification): void
    {
        self::$customRegistry[strtolower(trim($role))] = $classification;
    }

    public static function get(string $role): ?DataClassification
    {
        return self::$customRegistry[strtolower(trim($role))] ?? null;
    }

    public static function reset(): void
    {
        self::$customRegistry = [];
    }
}

