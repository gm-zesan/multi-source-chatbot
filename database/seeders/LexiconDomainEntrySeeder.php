<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LexiconDomainEntry;
use Illuminate\Database\Seeder;

/**
 * Seeds lexicon_domain_entries from retrieval_engine.py LOCAL_DOMAIN_LEXICON.
 * All entries are GLOBAL (workspace_id=0) and ACTIVE.
 * Concept keys derived from comment group headings in the original Python file.
 */
class LexiconDomainEntrySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Detect language from pattern
        // bn = contains Bangla Unicode, banglish = Banglish/transliterated, en = pure English
        $entries = [

            // ── Concept: ACCOUNT_ONBOARDING ──────────────────────────────────
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'notun akaunt',    'expansion' => 'create account register sign up', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'akaunt khulbo',   'expansion' => 'create account register', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'akaunt kulbo',    'expansion' => 'create account register', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'account khulbo',  'expansion' => 'create account register', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'account kulbo',   'expansion' => 'create account register', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'accaunt kulbo',   'expansion' => 'create account register sign up', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'accaunt khulbo',  'expansion' => 'create account register sign up', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'acount kulbo',    'expansion' => 'create account register sign up', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'acount khulbo',   'expansion' => 'create account register sign up', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'notun account',   'expansion' => 'create account register sign up', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'নতুন একাউন্ট',     'expansion' => 'create account register sign up', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'নতুন অ্যাকাউন্ট',   'expansion' => 'create account register sign up', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'একাউন্ট খুলব',     'expansion' => 'create account register', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'অ্যাকাউন্ট খুলব',   'expansion' => 'create account register', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'একাউন্ট তৈরি',     'expansion' => 'create account register', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'অ্যাকাউন্ট তৈরি',   'expansion' => 'create account register', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'কীভাবে একাউন্ট',    'expansion' => 'create account register', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'কীভাবে অ্যাকাউন্ট',  'expansion' => 'create account register', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'login korbo',     'expansion' => 'login sign in access account', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'dhukbo',          'expansion' => 'login sign in', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'লগইন',            'expansion' => 'login sign in', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'পাসওয়ার্ড ভুলে',   'expansion' => 'forgot reset password create account', 'language' => 'bn'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'password vule',   'expansion' => 'forgot reset password create account', 'language' => 'banglish'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'reset password',  'expansion' => 'forgot reset password create account', 'language' => 'en'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'first time login','expansion' => 'what do I do after logging in workspace profile onboarding', 'language' => 'en'],
            ['concept_key' => 'ACCOUNT_ONBOARDING', 'pattern' => 'prothom login',   'expansion' => 'what do I do after logging in workspace profile onboarding', 'language' => 'banglish'],

            // ── Concept: TWO_FACTOR_AUTH ────────────────────────────────────
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => '2 step',              'expansion' => 'two factor authentication 2fa enable security authenticator app', 'language' => 'en'],
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => '2-step',              'expansion' => 'two factor authentication 2fa enable security authenticator app', 'language' => 'en'],
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => '2fa',                 'expansion' => 'two factor authentication 2fa enable security authenticator app', 'language' => 'en'],
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => 'two factor',          'expansion' => 'two factor authentication 2fa enable security authenticator app', 'language' => 'en'],
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => 'authenticator code',  'expansion' => 'two factor authentication 2fa enable security authenticator app', 'language' => 'en'],
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => 'authenticator app',   'expansion' => 'two factor authentication 2fa enable security', 'language' => 'en'],
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => 'security code',       'expansion' => 'two factor authentication 2fa enable security', 'language' => 'en'],
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => 'টু-স্টেপ',            'expansion' => 'two factor authentication 2fa enable security authenticator app', 'language' => 'bn'],
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => 'টু স্টেপ',            'expansion' => 'two factor authentication 2fa enable security authenticator app', 'language' => 'bn'],
            ['concept_key' => 'TWO_FACTOR_AUTH', 'pattern' => 'ভেরিফিকেশন',          'expansion' => 'two factor authentication 2fa enable security authenticator app', 'language' => 'bn'],

            // ── Concept: DATA_SECURITY ──────────────────────────────────────
            ['concept_key' => 'DATA_SECURITY', 'pattern' => 'unreadable to unauthorized', 'expansion' => 'how is my data encrypted AES-256 TLS security data protection', 'language' => 'en'],
            ['concept_key' => 'DATA_SECURITY', 'pattern' => 'customer records unreadable', 'expansion' => 'how is my data encrypted AES-256 TLS security data protection', 'language' => 'en'],
            ['concept_key' => 'DATA_SECURITY', 'pattern' => 'data encrypted',             'expansion' => 'how is my data encrypted AES-256 TLS security data protection', 'language' => 'en'],
            ['concept_key' => 'DATA_SECURITY', 'pattern' => 'data security',              'expansion' => 'how is my data encrypted AES-256 TLS security data protection', 'language' => 'en'],
            ['concept_key' => 'DATA_SECURITY', 'pattern' => 'ডেটা এনক্রিপশন',            'expansion' => 'how is my data encrypted AES-256 TLS security data protection', 'language' => 'bn'],
            ['concept_key' => 'DATA_SECURITY', 'pattern' => 'এনক্রিপ্ট',                 'expansion' => 'how is my data encrypted AES-256 TLS security data protection', 'language' => 'bn'],
            ['concept_key' => 'DATA_SECURITY', 'pattern' => 'এনক্রিপশন',                 'expansion' => 'how is my data encrypted AES-256 TLS security data protection', 'language' => 'bn'],
            ['concept_key' => 'DATA_SECURITY', 'pattern' => 'gdpr',                       'expansion' => 'does platform comply with GDPR privacy compliance data processing', 'language' => 'en'],
            ['concept_key' => 'DATA_SECURITY', 'pattern' => 'privacy regulation',         'expansion' => 'does platform comply with GDPR privacy compliance data processing', 'language' => 'en'],

            // ── Concept: BILLING_INVOICES ───────────────────────────────────
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'purono bill',            'expansion' => 'view invoices receipt history download', 'language' => 'banglish'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'purono invoice',         'expansion' => 'view invoices receipt history download', 'language' => 'banglish'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'আগের ইনভয়েস',          'expansion' => 'view invoices receipt history download', 'language' => 'bn'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'ইনভয়েস ডাউনলোড',      'expansion' => 'view invoices receipt history download', 'language' => 'bn'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'রসিদ দেখতে',           'expansion' => 'view invoices receipt history download', 'language' => 'bn'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'tax invoice',           'expansion' => 'view invoices receipt history download', 'language' => 'en'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'download invoice',      'expansion' => 'view invoices receipt history download', 'language' => 'en'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'tax documentation',     'expansion' => 'view invoices receipt history download corporate expenses', 'language' => 'en'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'tax receipts',          'expansion' => 'view invoices receipt history download corporate expenses', 'language' => 'en'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'auditors inspect',      'expansion' => 'view invoices receipt history download corporate expenses', 'language' => 'en'],
            ['concept_key' => 'BILLING_INVOICES', 'pattern' => 'billing history',       'expansion' => 'view invoices receipt history download', 'language' => 'en'],

            // ── Concept: WARRANTY_POLICY (retail) ───────────────────────────
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'ক্লেইম',               'expansion' => 'warranty quality guarantee proof of purchase replacement claim', 'language' => 'bn'],
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'ওয়ারেন্টি',             'expansion' => 'product warranty quality guarantee brand service warranty', 'language' => 'bn'],
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'সেলাই ছুটে',           'expansion' => 'product warranty apparel 30 day manufacturing defect guarantee stitching', 'language' => 'bn'],
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'warranty',             'expansion' => 'product warranty 6 month 1 year electronics 30 day apparel defect', 'language' => 'en'],
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'warranty claim',       'expansion' => 'product warranty 6 month 1 year electronics 30 day apparel defect', 'language' => 'en'],
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'claim',                'expansion' => 'product warranty 6 month 1 year electronics 30 day apparel defect', 'language' => 'en'],
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'গ্যারান্টি',            'expansion' => 'product warranty 6 month 1 year electronics 30 day apparel defect', 'language' => 'bn'],
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'selai khule',          'expansion' => 'product warranty 30 day manufacturing defect stitching zipper', 'language' => 'banglish'],
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'venge gele',           'expansion' => 'product warranty manufacturing defect guarantee', 'language' => 'banglish'],
            ['concept_key' => 'WARRANTY_POLICY', 'pattern' => 'সার্ভিস চার্জ',         'expansion' => 'product warranty 6 month 1 year electronics 30 day apparel defect', 'language' => 'bn'],

            // ── Concept: PAYMENT_METHOD ──────────────────────────────────────
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'card change',          'expansion' => 'update payment method credit card paypal billing', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'notun card',           'expansion' => 'update payment method credit card paypal billing', 'language' => 'banglish'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'কার্ড পরিবর্তন',        'expansion' => 'update payment method credit card paypal billing', 'language' => 'bn'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'পেমেন্ট মেথড',          'expansion' => 'update payment method credit card paypal billing', 'language' => 'bn'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'credit card update',   'expansion' => 'update payment method credit card paypal billing', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'expired mastercard',   'expansion' => 'update payment method credit card paypal billing', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'swap out my expired',  'expansion' => 'update payment method credit card paypal billing', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'corporate credit card','expansion' => 'update payment method credit card paypal billing', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'taka ferot',           'expansion' => 'refund payment reversal money back policy update payment method', 'language' => 'banglish'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'money back',           'expansion' => 'refund payment reversal policy update payment method', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'payment reversal',     'expansion' => 'refund payment reversal policy update payment method', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'টাকা ফেরত',            'expansion' => 'refund payment reversal money back policy update payment method', 'language' => 'bn'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'cash on delivery',     'expansion' => 'payment methods Cash on Delivery COD bKash Nagad card SSLCommerz', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'cod',                  'expansion' => 'payment methods Cash on Delivery COD bKash Nagad card SSLCommerz', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'ক্যাশ অন ডেলিভারি',    'expansion' => 'payment methods Cash on Delivery COD bKash Nagad card SSLCommerz', 'language' => 'bn'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'bkash payment',        'expansion' => 'payment methods bKash merchant gateway Nagad Rocket SSLCommerz', 'language' => 'en'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'bikash',               'expansion' => 'payment methods bKash merchant gateway Nagad Rocket SSLCommerz', 'language' => 'banglish'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'bks',                  'expansion' => 'payment methods bKash merchant gateway Nagad Rocket SSLCommerz', 'language' => 'banglish'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'পেমেন্ট মাধ্যম',        'expansion' => 'payment methods bKash Nagad card COD', 'language' => 'bn'],
            ['concept_key' => 'PAYMENT_METHOD', 'pattern' => 'পেমেন্ট গ্রহণ',         'expansion' => 'accepted payment methods bkash nagad cod cash on delivery', 'language' => 'bn'],

            // ── Concept: SUBSCRIPTION_PLANS ─────────────────────────────────
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'plan change',          'expansion' => 'change my plan subscription upgrade downgrade', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'plan switch',          'expansion' => 'change my plan subscription upgrade downgrade', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'প্ল্যান পরিবর্তন',      'expansion' => 'change my plan subscription upgrade downgrade', 'language' => 'bn'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'upgrade plan',         'expansion' => 'change my plan subscription upgrade monthly to annual prepaid', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'annual billing',       'expansion' => 'change my plan subscription upgrade monthly to annual prepaid', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'annual prepaid',       'expansion' => 'change my plan subscription upgrade monthly to annual prepaid', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'terminate membership', 'expansion' => 'change my plan cancel subscription downgrade', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'switch from monthly',  'expansion' => 'change my plan subscription upgrade monthly to annual', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'charity foundation',   'expansion' => 'do you offer discounts for non-profits verified 50% discount', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'non-profit',           'expansion' => 'do you offer discounts for non-profits verified 50% discount', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'free trial',           'expansion' => 'is there a free trial 14-day pro plan no credit card', 'language' => 'en'],
            ['concept_key' => 'SUBSCRIPTION_PLANS', 'pattern' => 'test without paying',  'expansion' => 'is there a free trial 14-day pro plan no credit card', 'language' => 'en'],

            // ── Concept: MULTI_CHANNEL ───────────────────────────────────────
            ['concept_key' => 'MULTI_CHANNEL', 'pattern' => 'duto eksathe',                'expansion' => 'can I use multiple channels simultaneously connect whatsapp telegram', 'language' => 'banglish'],
            ['concept_key' => 'MULTI_CHANNEL', 'pattern' => 'ekshathe',                    'expansion' => 'can I use multiple channels simultaneously connect whatsapp telegram', 'language' => 'banglish'],
            ['concept_key' => 'MULTI_CHANNEL', 'pattern' => 'একই সাথে',                    'expansion' => 'can I use multiple channels simultaneously connect whatsapp telegram', 'language' => 'bn'],
            ['concept_key' => 'MULTI_CHANNEL', 'pattern' => 'একসাথে',                      'expansion' => 'can I use multiple channels simultaneously connect whatsapp telegram', 'language' => 'bn'],
            ['concept_key' => 'MULTI_CHANNEL', 'pattern' => 'multiple channels',           'expansion' => 'can I use multiple channels simultaneously connect whatsapp telegram', 'language' => 'en'],
            ['concept_key' => 'MULTI_CHANNEL', 'pattern' => 'whatsapp connect',            'expansion' => 'how do I connect WhatsApp QR code integration', 'language' => 'en'],
            ['concept_key' => 'MULTI_CHANNEL', 'pattern' => 'whatsapp business cloud api', 'expansion' => 'how do I connect WhatsApp QR code integration', 'language' => 'en'],
            ['concept_key' => 'MULTI_CHANNEL', 'pattern' => 'telegram connect',            'expansion' => 'how do I connect Telegram bot authorization', 'language' => 'en'],

            // ── Concept: AGENT_TROUBLESHOOTING ──────────────────────────────
            ['concept_key' => 'AGENT_TROUBLESHOOTING', 'pattern' => 'chatbot not responding',      'expansion' => 'why is my chatbot not responding active service', 'language' => 'en'],
            ['concept_key' => 'AGENT_TROUBLESHOOTING', 'pattern' => 'bot silent',                  'expansion' => 'why is my chatbot not responding active service', 'language' => 'en'],
            ['concept_key' => 'AGENT_TROUBLESHOOTING', 'pattern' => 'agent is sitting idle',       'expansion' => 'why is my chatbot not responding active service', 'language' => 'en'],
            ['concept_key' => 'AGENT_TROUBLESHOOTING', 'pattern' => 'unresponsive',                'expansion' => 'why is my chatbot not responding active service', 'language' => 'en'],
            ['concept_key' => 'AGENT_TROUBLESHOOTING', 'pattern' => 'messages not being delivered','expansion' => 'why are my messages not being delivered network recipient restrictions', 'language' => 'en'],
            ['concept_key' => 'AGENT_TROUBLESHOOTING', 'pattern' => 'encounter an error',          'expansion' => 'what should I do if I encounter an error screenshot refresh', 'language' => 'en'],

            // ── Concept: API_DEVELOPER ───────────────────────────────────────
            ['concept_key' => 'API_DEVELOPER', 'pattern' => 'api key',          'expansion' => 'how do I get my API key generate manage keys', 'language' => 'en'],
            ['concept_key' => 'API_DEVELOPER', 'pattern' => 'rate limits',      'expansion' => 'what are the API rate limits requests per minute', 'language' => 'en'],
            ['concept_key' => 'API_DEVELOPER', 'pattern' => 'authenticate api', 'expansion' => 'how do I authenticate API requests bearer token authorization header', 'language' => 'en'],

            // ── Concept: RETURN_POLICY ───────────────────────────────────────
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'return policy',           'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'en'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'return timeframe',        'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'en'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'official return',         'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'en'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'return korar rules',      'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'banglish'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'ferot pathabo',           'expansion' => 'return policy eligibility unworn tags intact 7 days send back', 'language' => 'banglish'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'jama ferot',              'expansion' => 'return policy eligibility unworn tags 7 days', 'language' => 'banglish'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'product ferot',           'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'banglish'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'retrn',                   'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'banglish'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'পণ্য ফেরত',               'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'bn'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'জামা ফেরত',               'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'bn'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'রিটার্ন নীতি',            'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'bn'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'রিটার্ন পলিসি',           'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'bn'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'রিটার্ন',                 'expansion' => 'return policy eligibility unworn tags intact 7 days', 'language' => 'bn'],
            ['concept_key' => 'RETURN_POLICY', 'pattern' => 'নন-রিটার্নেবল',           'expansion' => 'official return policy non-returnable items', 'language' => 'bn'],

            // ── Concept: REFUND_POLICY ───────────────────────────────────────
            ['concept_key' => 'REFUND_POLICY', 'pattern' => 'refund pete koto din', 'expansion' => 'refund policy processing time 7 to 10 days bKash gateway', 'language' => 'banglish'],
            ['concept_key' => 'REFUND_POLICY', 'pattern' => 'taka refund',          'expansion' => 'refund policy processing time 7 to 10 days bKash card', 'language' => 'banglish'],
            ['concept_key' => 'REFUND_POLICY', 'pattern' => 'টাকা ফেরত',            'expansion' => 'refund policy processing time 7 to 10 days bKash card', 'language' => 'bn'],
            ['concept_key' => 'REFUND_POLICY', 'pattern' => 'রিফান্ড',              'expansion' => 'refund policy processing time 7 to 10 days bKash card', 'language' => 'bn'],
            ['concept_key' => 'REFUND_POLICY', 'pattern' => 'rfnd',                 'expansion' => 'refund policy processing time 7 to 10 days', 'language' => 'banglish'],
            ['concept_key' => 'REFUND_POLICY', 'pattern' => 'reversal',             'expansion' => 'refund policy turnaround 72 hours prepaid', 'language' => 'en'],

            // ── Concept: EXCHANGE_POLICY ─────────────────────────────────────
            ['concept_key' => 'EXCHANGE_POLICY', 'pattern' => 'exchange',          'expansion' => 'product exchange policy size color swap replacement 5 days', 'language' => 'en'],
            ['concept_key' => 'EXCHANGE_POLICY', 'pattern' => 'সাইজ বদলানো',       'expansion' => 'product exchange policy size color swap replacement 5 days', 'language' => 'bn'],
            ['concept_key' => 'EXCHANGE_POLICY', 'pattern' => 'এক্সচেঞ্জ',          'expansion' => 'product exchange policy size color swap replacement 5 days', 'language' => 'bn'],
            ['concept_key' => 'EXCHANGE_POLICY', 'pattern' => 'size na mille',     'expansion' => 'product exchange policy size color swap replacement 5 days', 'language' => 'banglish'],
            ['concept_key' => 'EXCHANGE_POLICY', 'pattern' => 'choto hoise',       'expansion' => 'product exchange policy size color swap replacement 5 days', 'language' => 'banglish'],
            ['concept_key' => 'EXCHANGE_POLICY', 'pattern' => 'boro hoise',        'expansion' => 'product exchange policy size color swap replacement 5 days', 'language' => 'banglish'],
            ['concept_key' => 'EXCHANGE_POLICY', 'pattern' => 'l size er poriborte','expansion' => 'product exchange policy size color swap replacement 5 days', 'language' => 'banglish'],
            ['concept_key' => 'EXCHANGE_POLICY', 'pattern' => 'swap',              'expansion' => 'product exchange policy size color swap replacement 5 days', 'language' => 'en'],

            // ── Concept: DELIVERY_POLICY ─────────────────────────────────────
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'delivery charge',    'expansion' => 'delivery shipping rates 70 BDT inside Dhaka 130 outside', 'language' => 'en'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'delivry',            'expansion' => 'delivery shipping rates 70 BDT inside Dhaka 130 outside', 'language' => 'banglish'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'ডেলিভারি চার্জ',    'expansion' => 'delivery shipping rates 70 BDT inside Dhaka 130 outside', 'language' => 'bn'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'ডেলিভারি খরচ',      'expansion' => 'delivery shipping rates 70 BDT inside Dhaka 130 outside', 'language' => 'bn'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'shipping rate',      'expansion' => 'delivery shipping rates 70 BDT inside Dhaka 130 outside', 'language' => 'en'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'same day delivery',  'expansion' => 'express same day delivery inside Dhaka 150 BDT', 'language' => 'en'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'koto din lagbe',     'expansion' => 'delivery timeframes 24 to 48 hours Dhaka 3 to 5 days outside', 'language' => 'banglish'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'ডেলিভারি সময়',      'expansion' => 'delivery timeframes 24 to 48 hours Dhaka 3 to 5 days outside', 'language' => 'bn'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'সেইম ডে',           'expansion' => 'express same day delivery inside Dhaka 150 BDT', 'language' => 'bn'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'ডেলিভারি ফি',       'expansion' => 'delivery shipping rates 70 BDT inside Dhaka 130 outside', 'language' => 'bn'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'ডেলিভারির ফি',      'expansion' => 'delivery shipping rates 70 BDT inside Dhaka 130 outside', 'language' => 'bn'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'delivery tracking',  'expansion' => 'delivery timeframes 24 to 48 hours Dhaka 3 to 5 days courier tracking', 'language' => 'en'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'delivery timeframe', 'expansion' => 'delivery timeframes 24 to 48 hours Dhaka 3 to 5 days courier tracking', 'language' => 'en'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'পার্সেল ট্র্যাক',   'expansion' => 'delivery shipping tracking live courier sms tracking code', 'language' => 'bn'],
            ['concept_key' => 'DELIVERY_POLICY', 'pattern' => 'কুরিয়ার ট্র্যাকিং', 'expansion' => 'delivery shipping tracking live courier sms tracking code', 'language' => 'bn'],

            // ── Concept: CANCELLATION_POLICY ────────────────────────────────
            ['concept_key' => 'CANCELLATION_POLICY', 'pattern' => 'order cancel', 'expansion' => 'order cancellation policy before dispatch central warehouse', 'language' => 'en'],
            ['concept_key' => 'CANCELLATION_POLICY', 'pattern' => 'ক্যানসেল',     'expansion' => 'order cancellation policy before dispatch central warehouse', 'language' => 'bn'],
            ['concept_key' => 'CANCELLATION_POLICY', 'pattern' => 'বাতিল',        'expansion' => 'order cancellation policy before dispatch central warehouse', 'language' => 'bn'],

            // ── Concept: STORE_LOCATION ──────────────────────────────────────
            ['concept_key' => 'STORE_LOCATION', 'pattern' => 'showroom', 'expansion' => 'contact store address Banani Road 11 Block D Dhaka flagship store', 'language' => 'en'],
            ['concept_key' => 'STORE_LOCATION', 'pattern' => 'thikana',  'expansion' => 'contact store address Banani Road 11 Block D Dhaka flagship store', 'language' => 'banglish'],
            ['concept_key' => 'STORE_LOCATION', 'pattern' => 'ঠিকানা',   'expansion' => 'contact store address Banani Road 11 Block D Dhaka flagship store', 'language' => 'bn'],
            ['concept_key' => 'STORE_LOCATION', 'pattern' => 'শোরুম',    'expansion' => 'contact store address Banani Road 11 Block D Dhaka flagship store', 'language' => 'bn'],
            ['concept_key' => 'STORE_LOCATION', 'pattern' => 'office',   'expansion' => 'contact store address Banani Road 11 Block D Dhaka flagship store', 'language' => 'en'],
            ['concept_key' => 'STORE_LOCATION', 'pattern' => 'দোকান',    'expansion' => 'contact store address Banani Road 11 Block D Dhaka flagship store', 'language' => 'bn'],

            // ── Concept: CONTACT_SUPPORT ─────────────────────────────────────
            ['concept_key' => 'CONTACT_SUPPORT', 'pattern' => 'কাস্টমার কেয়ার', 'expansion' => 'customer support hours 9am 10pm helpline', 'language' => 'bn'],
        ];

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = array_merge($entry, [
                'workspace_id' => 0,
                'status'       => 'ACTIVE',
                'version'      => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // Insert in chunks, ignore duplicates
        foreach (array_chunk($rows, 100) as $chunk) {
            LexiconDomainEntry::insertOrIgnore($chunk);
        }

        $total = LexiconDomainEntry::count();
        $this->command->info("[LexiconDomainEntrySeeder] Seeded {$total} domain entries from LOCAL_DOMAIN_LEXICON.");
    }
}
