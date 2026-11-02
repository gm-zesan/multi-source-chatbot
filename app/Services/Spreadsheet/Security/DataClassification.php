<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Security;

/**
 * Data Classification Specification.
 *
 * Enforces field sensitivity tiers and fail-closed privacy classification.
 * All unclassified, custom, or ambiguous fields default to RESTRICTED.
 */
final class DataClassification
{
    public const PUBLIC = 'public';
    public const INTERNAL = 'internal';
    public const RESTRICTED = 'restricted';

    /**
     * Vetted public domain roles permitted for customer and search exposure.
     *
     * @var array<int, string>
     */
    public const PUBLIC_ROLES = [
        'selling_price',
        'unit_price',
        'mrp',
        'retail_price',
        'public_price',
        'price',
        'product_name',
        'item_name',
        'entity_name',
        'title',
        'description',
        'product_description',
        'details',
        'specs',
        'sku',
        'barcode',
        'item_code',
        'product_code',
        'category',
        'department',
        'brand',
        'quantity',
        'order_quantity',
        'stock_quantity',
        'booking_charge',
        'service_fee',
        'consultation_fee',
        'order_status',
        'booking_status',
        'delivery_status',
        'faq_question',
        'faq_answer',
        'public_catalog',
    ];

    /**
     * Operational internal roles accessible to authenticated workspace members.
     *
     * @var array<int, string>
     */
    public const INTERNAL_ROLES = [
        'doctor',
        'physician',
        'salesperson',
        'employee',
        'staff',
        'donor',
        'guest',
        'customer',
        'buyer',
        'order_date',
        'booking_date',
        'check_in_date',
        'check_out_date',
        'delivery_date',
        'room_number',
        'tax',
        'vat',
        'discount',
        'record_identifier',
    ];

    /**
     * Highly sensitive financial, workforce, clinical, and PII roles strictly restricted.
     *
     * @var array<int, string>
     */
    public const RESTRICTED_ROLES = [
        'salary',
        'remuneration',
        'stipend',
        'wage',
        'payroll',
        'bonus',
        'incentive',
        'commission',
        'payout',
        'purchase_cost',
        'unit_cost',
        'cogs',
        'supplier_price',
        'procurement_cost',
        'cost',
        'margin',
        'profit',
        'markup',
        'revenue',
        'turnover',
        'internal_target',
        'target',
        'due_balance',
        'outstanding_balance',
        'patient',
        'clinical_notes',
        'diagnosis',
        'symptom',
        'medical_record',
        'prescription',
        'treatment',
        'beneficiary',
        'confidential',
        'secret',
    ];

    /**
     * Regex pattern catching sensitive substrings in semantic roles or headers.
     */
    public const SENSITIVE_SUBSTRING_PATTERN = '/\b(salary|wage|payroll|remuneration|stipend|bonus|incentive|commission|payment|payout|cost|cogs|procurement|supplier|margin|profit|markup|revenue|turnover|target|performance|patient|diagnosis|symptom|clinical|medical_record|prescription|treatment|confidential|secret|internal|due_balance)\b|বেতন|কমিশন|খরচ|লাভ|মুনাফা|রোগী|পেশেন্ট|পাওনা|দেনা/ui';

    /**
     * Return all valid classifications.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::PUBLIC,
            self::INTERNAL,
            self::RESTRICTED,
        ];
    }

    /**
     * Check if a classification string is valid.
     */
    public static function isValid(string $classification): bool
    {
        return in_array(strtolower(trim($classification)), self::all(), true);
    }

    /**
     * Classify a column based on its semantic role and original header.
     * Defaults to RESTRICTED (Fail-Closed).
     */
    public static function classifyRole(string $semanticRole, ?string $originalHeader = null): string
    {
        $role = strtolower(trim($semanticRole));
        $header = $originalHeader !== null ? strtolower(trim($originalHeader)) : '';

        // 1. Prohibited sensitive patterns are immediately classified as RESTRICTED
        if (preg_match(self::SENSITIVE_SUBSTRING_PATTERN, $role) || ($header !== '' && preg_match(self::SENSITIVE_SUBSTRING_PATTERN, $header))) {
            return self::RESTRICTED;
        }

        // 2. Explicit restricted roles
        if (in_array($role, self::RESTRICTED_ROLES, true)) {
            return self::RESTRICTED;
        }

        // 3. Vetted public roles
        if (in_array($role, self::PUBLIC_ROLES, true)) {
            return self::PUBLIC;
        }

        // 4. Operational internal roles
        if (in_array($role, self::INTERNAL_ROLES, true)) {
            return self::INTERNAL;
        }

        // 5. Fail-Closed Default: Any unrecognized, custom, or ambiguous role is RESTRICTED
        return self::RESTRICTED;
    }

    /**
     * Sanitize and normalize sensitivity string. Defaults to RESTRICTED if invalid.
     */
    public static function normalize(?string $sensitivity): string
    {
        if ($sensitivity === null) {
            return self::RESTRICTED;
        }

        $clean = strtolower(trim($sensitivity));
        return in_array($clean, self::all(), true) ? $clean : self::RESTRICTED;
    }
}

