<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Spreadsheet\Security\DataClassificationRegistry;

/**
 * Domain-Agnostic Extensible Data Classification.
 *
 * Formalizes field-level sensitivity tiers for all tabular and document attributes.
 * Defaults fail-closed to RESTRICTED_CUSTOM for any unclassified or ambiguous fields.
 */
enum DataClassification: string
{
    case PUBLIC_CATALOG          = 'public_catalog';
    case OPERATIONAL_INTERNAL    = 'operational_internal';
    case CONFIDENTIAL_FINANCIAL  = 'confidential_financial';
    case WORKFORCE_COMPENSATION  = 'workforce_compensation';
    case PATIENT_HEALTH_PHI      = 'patient_health_phi';
    case DONOR_CONFIDENTIAL      = 'donor_confidential';
    case RESTRICTED_CUSTOM       = 'restricted_custom';

    /**
     * Regex pattern catching sensitive substrings in semantic roles or headers.
     */
    public const SENSITIVE_SUBSTRING_PATTERN = '/\b(salary|wage|payroll|remuneration|stipend|bonus|incentive|commission|payment|payout|cost|cogs|procurement|supplier|margin|profit|markup|revenue|turnover|target|performance|patient|diagnosis|symptom|clinical|medical_record|prescription|treatment|confidential|secret|internal|due_balance)\b|বেতন|কমিশন|খরচ|লাভ|মুনাফা|রোগী|পেশেন্ট|পাওনা|দেনা/ui';

    /**
     * Register a custom classification mapping for a semantic role.
     */
    public static function register(string $role, self $classification): void
    {
        DataClassificationRegistry::register($role, $classification);
    }

    /**
     * Reset the classification registry to defaults.
     */
    public static function resetRegistry(): void
    {
        DataClassificationRegistry::reset();
    }

    /**
     * Determine if this classification is safe for public customer channels.
     */
    public function isPublic(): bool
    {
        return $this === self::PUBLIC_CATALOG;
    }

    /**
     * Determine if this classification is restricted and barred from normal operational staff.
     */
    public function isRestricted(): bool
    {
        return in_array($this, [
            self::CONFIDENTIAL_FINANCIAL,
            self::WORKFORCE_COMPENSATION,
            self::PATIENT_HEALTH_PHI,
            self::DONOR_CONFIDENTIAL,
            self::RESTRICTED_CUSTOM,
        ], true);
    }

    /**
     * Map to legacy sensitivity string ('public', 'internal', 'restricted').
     */
    public function toLegacySensitivity(): string
    {
        return match ($this) {
            self::PUBLIC_CATALOG => 'public',
            self::OPERATIONAL_INTERNAL => 'internal',
            default => 'restricted',
        };
    }

    /**
     * Instantiate from legacy sensitivity string.
     */
    public static function fromLegacySensitivity(?string $sensitivity): self
    {
        return match (strtolower(trim((string) $sensitivity))) {
            'public' => self::PUBLIC_CATALOG,
            'internal' => self::OPERATIONAL_INTERNAL,
            default => self::RESTRICTED_CUSTOM,
        };
    }

    /**
     * Classify a semantic role and header fail-closed.
     */
    public static function classifyRole(string $semanticRole, ?string $originalHeader = null): self
    {
        $role = strtolower(trim($semanticRole));
        $header = $originalHeader !== null ? strtolower(trim($originalHeader)) : '';

        // 1. Check custom registry first
        $custom = DataClassificationRegistry::get($role);
        if ($custom !== null) {
            return $custom;
        }

        // 2. Sensitive pattern matching
        if (preg_match(self::SENSITIVE_SUBSTRING_PATTERN, $role) || ($header !== '' && preg_match(self::SENSITIVE_SUBSTRING_PATTERN, $header))) {
            if (self::isWorkforceRole($role, $header)) {
                return self::WORKFORCE_COMPENSATION;
            }
            if (self::isPatientRole($role, $header)) {
                return self::PATIENT_HEALTH_PHI;
            }
            return self::CONFIDENTIAL_FINANCIAL;
        }

        // 3. Vetted Public Roles Allowlist
        $publicRoles = [
            'selling_price', 'unit_price', 'mrp', 'retail_price', 'public_price', 'price',
            'product_name', 'item_name', 'entity_name', 'title',
            'description', 'product_description', 'details', 'specs',
            'sku', 'barcode', 'item_code', 'product_code',
            'category', 'department', 'brand',
            'quantity', 'order_quantity', 'stock_quantity',
            'booking_charge', 'service_fee', 'consultation_fee',
            'order_status', 'booking_status', 'delivery_status',
            'faq_question', 'faq_answer', 'public_catalog',
        ];
        if (in_array($role, $publicRoles, true)) {
            return self::PUBLIC_CATALOG;
        }

        // 4. Operational Internal Roles
        $internalRoles = [
            'doctor', 'physician', 'salesperson', 'employee', 'staff',
            'donor', 'guest', 'customer', 'buyer',
            'order_date', 'booking_date', 'check_in_date', 'check_out_date', 'delivery_date',
            'room_number', 'tax', 'vat', 'discount', 'record_identifier',
        ];
        if (in_array($role, $internalRoles, true)) {
            return self::OPERATIONAL_INTERNAL;
        }

        // 5. Explicit Workforce Roles
        $workforceRoles = [
            'salary', 'remuneration', 'stipend', 'wage', 'payroll', 'bonus', 'incentive', 'commission', 'payout',
        ];
        if (in_array($role, $workforceRoles, true)) {
            return self::WORKFORCE_COMPENSATION;
        }

        // 6. Explicit PHI / Clinical Roles
        $phiRoles = [
            'patient', 'clinical_notes', 'diagnosis', 'symptom', 'medical_record', 'prescription', 'treatment',
        ];
        if (in_array($role, $phiRoles, true)) {
            return self::PATIENT_HEALTH_PHI;
        }

        // 7. Donor Confidential
        $donorRoles = ['beneficiary', 'confidential', 'secret'];
        if (in_array($role, $donorRoles, true)) {
            return self::DONOR_CONFIDENTIAL;
        }

        // 8. Fail-Closed Default
        return self::RESTRICTED_CUSTOM;
    }

    private static function isWorkforceRole(string $role, string $header): bool
    {
        $pattern = '/\b(salary|wage|payroll|remuneration|stipend|bonus|incentive|commission|payout)\b|বেতন|কমিশন/ui';
        return preg_match($pattern, $role) === 1 || preg_match($pattern, $header) === 1;
    }

    private static function isPatientRole(string $role, string $header): bool
    {
        $pattern = '/\b(patient|diagnosis|symptom|clinical|medical_record|prescription|treatment)\b|রোগী|পেশেন্ট/ui';
        return preg_match($pattern, $role) === 1 || preg_match($pattern, $header) === 1;
    }
}

