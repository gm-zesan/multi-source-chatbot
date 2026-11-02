<?php

declare(strict_types=1);

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

/**
 * Granular Analytics Permissions.
 *
 * Enforces role-based and attribute-based access controls for structured analytics,
 * virtual database queries, AST validation, and report projections.
 */
enum AnalyticsPermission: string
{
    use PermissionEnumTrait;

    case VIEW_BASIC      = 'analytics.view_basic';
    case VIEW_FINANCIAL  = 'analytics.view_financial';
    case VIEW_MARGINS    = 'analytics.view_margins';
    case VIEW_PAYROLL    = 'analytics.view_payroll';
    case VIEW_HEALTH_PHI = 'analytics.view_health_phi';
}

