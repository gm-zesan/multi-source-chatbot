<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Security;

/**
 * Data Projection Policy (Policy Decision Point - PDP).
 *
 * Evaluates whether a particular field, role, or column in a StructuredRecord
 * is authorized for egress based on the ServerControlledContext.
 */
class DataProjectionPolicy
{
    public const POLICY_VERSION = '2.2.0';

    /**
     * Financial cost and margin roles requiring explicit margin permission.
     *
     * @var array<int, string>
     */
    public const FINANCIAL_COST_ROLES = [
        'purchase_cost',
        'unit_cost',
        'cogs',
        'supplier_price',
        'procurement_cost',
        'cost',
        'margin',
        'profit',
        'markup',
        'internal_target',
        'due_balance',
        'outstanding_balance',
    ];

    /**
     * Workforce compensation roles requiring explicit salary permission.
     *
     * @var array<int, string>
     */
    public const WORKFORCE_COMPENSATION_ROLES = [
        'salary',
        'remuneration',
        'stipend',
        'wage',
        'payroll',
        'bonus',
        'incentive',
        'commission',
        'payout',
    ];

    /**
     * Clinical healthcare roles requiring explicit PHI permission.
     *
     * @var array<int, string>
     */
    public const CLINICAL_PHI_ROLES = [
        'patient',
        'clinical_notes',
        'diagnosis',
        'symptom',
        'medical_record',
        'prescription',
        'treatment',
        'beneficiary',
    ];

    /**
     * Formal 4-tuple evaluation: evaluate(DataClassification $classification, ServerControlledContext $context): bool.
     */
    public function evaluate(\App\Enums\DataClassification|string $classification, ServerControlledContext $context): bool
    {
        $classEnum = $classification instanceof \App\Enums\DataClassification
            ? $classification
            : (\App\Enums\DataClassification::tryFrom($classification) ?? \App\Enums\DataClassification::fromLegacySensitivity($classification));

        if ($context->isCustomer()) {
            return $classEnum->isPublic();
        }

        if ($context->purpose === ServerControlledContext::PURPOSE_SYSTEM_SYNC) {
            return true;
        }

        if ($context->isOwner()) {
            return true;
        }

        if (in_array($context->purpose, [
            ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            ServerControlledContext::PURPOSE_DATA_EXPORT,
        ], true)) {
            if ($classEnum === \App\Enums\DataClassification::PUBLIC_CATALOG) {
                return true;
            }

            if ($classEnum === \App\Enums\DataClassification::OPERATIONAL_INTERNAL) {
                return true;
            }

            if ($classEnum === \App\Enums\DataClassification::CONFIDENTIAL_FINANCIAL) {
                return $context->hasPermission('analytics.view_margins') || $context->hasPermission(\App\Enums\Permissions\AnalyticsPermission::VIEW_MARGINS->value);
            }

            if ($classEnum === \App\Enums\DataClassification::WORKFORCE_COMPENSATION) {
                return $context->hasPermission('hr.view_salaries') || $context->hasPermission(\App\Enums\Permissions\AnalyticsPermission::VIEW_PAYROLL->value);
            }

            if ($classEnum === \App\Enums\DataClassification::PATIENT_HEALTH_PHI) {
                return $context->hasPermission('medical.view_phi') || $context->hasPermission(\App\Enums\Permissions\AnalyticsPermission::VIEW_HEALTH_PHI->value);
            }

            return false;
        }

        return false;
    }

    /**
     * Evaluate whether a specific field can be projected to the requester.
     */
    public function canProjectField(
        string $sourceColId,
        string $semanticRole,
        string $sensitivity,
        ServerControlledContext $context,
        ?string $originalHeader = null,
    ): bool {
        $normalizedSensitivity = DataClassification::normalize($sensitivity);
        $role = strtolower(trim($semanticRole));

        // ── 1. Customer Security Barrier (Zero-Token / Public Catalog Only) ──
        if ($context->isCustomer()) {
            // Under customer context, ONLY fields classified as PUBLIC are authorized.
            if ($normalizedSensitivity !== DataClassification::PUBLIC) {
                return false;
            }

            // Defense in depth: Verify semantic role is not classified as RESTRICTED
            if (DataClassification::classifyRole($role, $originalHeader) === DataClassification::RESTRICTED) {
                return false;
            }

            return true;
        }

        // ── 2. System Sync Context ───────────────────────────────────────────
        if ($context->purpose === ServerControlledContext::PURPOSE_SYSTEM_SYNC) {
            return true;
        }

        // ── 3. Workspace Owner & Active SuperAdmin Governance Session ─────────
        if ($context->isOwner()) {
            return true;
        }

        // ── 4. Analytics Reporting & Data Export ──────────────────────────────
        if (in_array($context->purpose, [
            ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            ServerControlledContext::PURPOSE_DATA_EXPORT,
        ], true)) {
            // Public fields are unconditionally authorized for workspace members
            if ($normalizedSensitivity === DataClassification::PUBLIC) {
                return true;
            }

            // Internal operational fields are authorized for workspace members
            if ($normalizedSensitivity === DataClassification::INTERNAL) {
                return true;
            }

            // Restricted fields mandate specific granular permissions:
            if ($normalizedSensitivity === DataClassification::RESTRICTED) {
                if ($this->isFinancialCostRole($role) && $context->hasPermission('analytics.view_margins')) {
                    return true;
                }

                if ($this->isWorkforceCompensationRole($role) && $context->hasPermission('hr.view_salaries')) {
                    return true;
                }

                if ($this->isClinicalPhiRole($role) && $context->hasPermission('medical.view_phi')) {
                    return true;
                }

                return false;
            }
        }

        // ── 5. Schema Inspection Context ─────────────────────────────────────
        if ($context->purpose === ServerControlledContext::PURPOSE_SCHEMA_INSPECTION) {
            return $normalizedSensitivity !== DataClassification::RESTRICTED || $context->isOwner();
        }

        // Fail-Closed Default
        return false;
    }

    /**
     * Determine allowed semantic roles for a given collection of column descriptors.
     *
     * @param array<string, array<string, mixed>> $mappedAttributes
     * @return array<int, string>
     */
    public function determineAllowedRoles(array $mappedAttributes, ServerControlledContext $context): array
    {
        $allowed = [];

        foreach ($mappedAttributes as $colId => $desc) {
            if (! is_array($desc)) {
                continue;
            }

            $role = (string) ($desc['semantic_role'] ?? 'custom_attribute');
            $sensitivity = (string) ($desc['sensitivity'] ?? DataClassification::RESTRICTED);
            $header = (string) ($desc['header'] ?? $desc['original_header'] ?? '');

            if ($this->canProjectField($colId, $role, $sensitivity, $context, $header)) {
                $allowed[] = $role;
            }
        }

        return array_values(array_unique($allowed));
    }

    public function isFinancialCostRole(string $role): bool
    {
        return in_array(strtolower(trim($role)), self::FINANCIAL_COST_ROLES, true);
    }

    public function isWorkforceCompensationRole(string $role): bool
    {
        return in_array(strtolower(trim($role)), self::WORKFORCE_COMPENSATION_ROLES, true);
    }

    public function isClinicalPhiRole(string $role): bool
    {
        return in_array(strtolower(trim($role)), self::CLINICAL_PHI_ROLES, true);
    }
}

