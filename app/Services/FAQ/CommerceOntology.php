<?php

declare(strict_types=1);

namespace App\Services\FAQ;

class CommerceOntology
{
    /**
     * 11 Canonical E-Commerce & F-Commerce Domains
     */
    public const DOMAIN_ACCOUNT_CUSTOMER         = 'Account & Customer';
    public const DOMAIN_PRODUCT_CATALOG          = 'Product & Catalog';
    public const DOMAIN_ORDER_MANAGEMENT         = 'Order Management';
    public const DOMAIN_DELIVERY_SHIPPING        = 'Delivery & Shipping';
    public const DOMAIN_PAYMENT                  = 'Payment';
    public const DOMAIN_RETURN_REFUND_EXCHANGE   = 'Return / Refund / Exchange';
    public const DOMAIN_PROMOTION_PRICING        = 'Promotion & Pricing';
    public const DOMAIN_FCOMMERCE_SOCIAL         = 'F-Commerce & Social Channels';
    public const DOMAIN_SELLER_MERCHANT          = 'Seller / Merchant Operations';
    public const DOMAIN_TECHNICAL_INTEGRATIONS   = 'Technical & Integrations';
    public const DOMAIN_PLATFORM_UI_ISSUES       = 'Platform & UI Issues';

    /**
     * All recognized commerce domains list.
     *
     * @var string[]
     */
    public const ALL_DOMAINS = [
        self::DOMAIN_ACCOUNT_CUSTOMER,
        self::DOMAIN_PRODUCT_CATALOG,
        self::DOMAIN_ORDER_MANAGEMENT,
        self::DOMAIN_DELIVERY_SHIPPING,
        self::DOMAIN_PAYMENT,
        self::DOMAIN_RETURN_REFUND_EXCHANGE,
        self::DOMAIN_PROMOTION_PRICING,
        self::DOMAIN_FCOMMERCE_SOCIAL,
        self::DOMAIN_SELLER_MERCHANT,
        self::DOMAIN_TECHNICAL_INTEGRATIONS,
        self::DOMAIN_PLATFORM_UI_ISSUES,
    ];

    /**
     * Check if a domain name is a recognized valid commerce domain.
     */
    public static function isValidDomain(string $domain): bool
    {
        return in_array(trim($domain), self::ALL_DOMAINS, true);
    }

    /**
     * Normalize fuzzy domain name to exact canonical domain.
     */
    public static function normalizeDomain(string $rawDomain): string
    {
        $d = mb_strtolower(trim($rawDomain));

        if (str_contains($d, 'account') || str_contains($d, 'customer') || str_contains($d, 'auth') || str_contains($d, 'login')) {
            return self::DOMAIN_ACCOUNT_CUSTOMER;
        }
        if (str_contains($d, 'product') || str_contains($d, 'catalog') || str_contains($d, 'stock') || str_contains($d, 'variant')) {
            return self::DOMAIN_PRODUCT_CATALOG;
        }
        if (str_contains($d, 'order') || str_contains($d, 'cancel') || str_contains($d, 'placement')) {
            return self::DOMAIN_ORDER_MANAGEMENT;
        }
        if (str_contains($d, 'delivery') || str_contains($d, 'shipping') || str_contains($d, 'courier') || str_contains($d, 'track')) {
            return self::DOMAIN_DELIVERY_SHIPPING;
        }
        if (str_contains($d, 'payment') || str_contains($d, 'billing') || str_contains($d, 'invoice') || str_contains($d, 'card') || str_contains($d, 'bkash')) {
            return self::DOMAIN_PAYMENT;
        }
        if (str_contains($d, 'refund') || str_contains($d, 'return') || str_contains($d, 'exchange') || str_contains($d, 'damage')) {
            return self::DOMAIN_RETURN_REFUND_EXCHANGE;
        }
        if (str_contains($d, 'promo') || str_contains($d, 'discount') || str_contains($d, 'coupon') || str_contains($d, 'price') || str_contains($d, 'plan')) {
            return self::DOMAIN_PROMOTION_PRICING;
        }
        if (str_contains($d, 'social') || str_contains($d, 'f-commerce') || str_contains($d, 'facebook') || str_contains($d, 'messenger') || str_contains($d, 'whatsapp') || str_contains($d, 'channel')) {
            return self::DOMAIN_FCOMMERCE_SOCIAL;
        }
        if (str_contains($d, 'seller') || str_contains($d, 'merchant') || str_contains($d, 'settlement')) {
            return self::DOMAIN_SELLER_MERCHANT;
        }
        if (str_contains($d, 'api') || str_contains($d, 'webhook') || str_contains($d, 'tech') || str_contains($d, 'integration')) {
            return self::DOMAIN_TECHNICAL_INTEGRATIONS;
        }

        return self::DOMAIN_PLATFORM_UI_ISSUES;
    }
}
