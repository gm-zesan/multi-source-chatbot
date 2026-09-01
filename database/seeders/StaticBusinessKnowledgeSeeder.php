<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StaticBusinessKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first() ?? Workspace::create([
            'name' => 'Default Workspace',
            'slug' => 'default',
        ]);

        $workspaceId = $workspace->id;

        // Ensure Core Categories Exist
        $catPolicy = FAQCategory::firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => 'corporate-policies'],
            ['name' => 'Corporate Policies', 'description' => 'Official store policies, terms, and conditions', 'order_column' => 1]
        );

        $catOrders = FAQCategory::firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => 'orders-shipping'],
            ['name' => 'Orders & Shipping', 'description' => 'Delivery timeframes, rates, and tracking', 'order_column' => 2]
        );

        $catPayment = FAQCategory::firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => 'payment-billing'],
            ['name' => 'Payment & Billing', 'description' => 'bKash, Cards, COD, and invoice queries', 'order_column' => 3]
        );

        $catProducts = FAQCategory::firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => 'products-sizing'],
            ['name' => 'Products & Sizing', 'description' => 'Size charts, fabric care, and custom orders', 'order_column' => 4]
        );

        $documents = [
            // ── 1. About Us ──────────────────────────────────────────────────
            [
                'document_type' => 'about_us',
                'category_id'   => $catPolicy->id,
                'question'      => 'About Us — Company Story & Mission / আমাদের সম্পর্কে',
                'answer'        => 'Entrepreneurs Automation is a leading omni-channel retail commerce brand based in Dhaka, Bangladesh. Founded in 2022, we specialize in premium ethnic apparel, contemporary fashion, and lifestyle accessories. Our headquarters and flagship experience center are located at Road 11, Banani, Dhaka-1213. We are committed to ethical manufacturing, sustainable fabrics, and customer-first service across online and social channels.',
                'priority'      => 100,
            ],

            // ── 2. Terms & Conditions ─────────────────────────────────────────
            [
                'document_type' => 'terms',
                'category_id'   => $catPolicy->id,
                'question'      => 'Terms and Conditions of Service / সেবা ও ক্রয়ের শর্তাবলী',
                'answer'        => 'By placing an order on our website or social channels, you agree to our terms. All prices are listed in Bangladeshi Taka (BDT) inclusive of applicable VAT. Orders are subject to stock verification and dispatch clearance. We reserve the right to cancel any order involving suspected fraud or pricing discrepancies. All commercial disputes are governed under the jurisdiction of Dhaka, Bangladesh courts.',
                'priority'      => 95,
            ],

            // ── 3. Privacy Policy ─────────────────────────────────────────────
            [
                'document_type' => 'privacy_policy',
                'category_id'   => $catPolicy->id,
                'question'      => 'Privacy & Data Protection Policy / গোপনীয়তা ও তথ্য সুরক্ষা নীতি',
                'answer'        => 'We respect your personal privacy. We collect customer names, phone numbers, and delivery addresses strictly for order fulfillment, courier dispatch, and service communication. We do not sell or share customer data with third parties. Sensitive payment credentials (credit card numbers, PINs, OTPs) are processed directly by licensed banking gateways (SSLCommerz, bKash) and are never stored on our servers.',
                'priority'      => 95,
            ],

            // ── 4. Refund Policy ──────────────────────────────────────────────
            [
                'document_type' => 'refund_policy',
                'category_id'   => $catPolicy->id,
                'question'      => 'Official Refund Policy & Processing Time / রিফান্ড পলিসি ও টাকা ফেরত পাওয়ার নিয়ম',
                'answer'        => 'Our official refund policy guarantees refunds within 7 to 10 business days once a returned item is received and inspected at our central hub. If an order is cancelled prior to dispatch, prepaid amounts are refunded within 72 hours. Refunds are issued strictly via the original payment channel (bKash/Nagad/Credit Card). Cash refunds are not offered for digital transactions. Delivery fees are non-refundable unless the return is due to a product defect or fulfillment error.',
                'priority'      => 100,
            ],

            // ── 5. Return Policy ──────────────────────────────────────────────
            [
                'document_type' => 'return_policy',
                'category_id'   => $catPolicy->id,
                'question'      => 'Official Return Policy & Eligibility / পণ্য রিটার্ন করার নিয়ম ও শর্তাবলী',
                'answer'        => 'Items can be returned within 7 days of delivery. To be eligible for a return, products must be unworn, unwashed, in their original packaging, and with all manufacturer tags intact. Defective, damaged, or wrongly shipped items are eligible for 100% free return courier pickup. Innerwear, discounted final-sale clearance items, and customized tailored garments cannot be returned.',
                'priority'      => 100,
            ],

            // ── 6. Exchange Policy ────────────────────────────────────────────
            [
                'document_type' => 'exchange_policy',
                'category_id'   => $catPolicy->id,
                'question'      => 'Product Exchange Policy (Size & Color) / সাইজ ও কালার এক্সচেঞ্জ পলিসি',
                'answer'        => 'If you need a different size or color of your purchased item, you can request an exchange within 5 days of delivery. Exchanges depend on current stock availability. A nominal courier delivery fee (70 BDT inside Dhaka, 130 BDT outside Dhaka) applies for preference-based size swaps. If your desired replacement size is out of stock, store credit or a full refund will be provided.',
                'priority'      => 95,
            ],

            // ── 7. Delivery Policy ────────────────────────────────────────────
            [
                'document_type' => 'delivery_policy',
                'category_id'   => $catOrders->id,
                'question'      => 'Delivery & Shipping Rates, Timeframes & Coverage / ডেলিভারি চার্জ ও সময়সীমা',
                'answer'        => 'We deliver across all 64 districts of Bangladesh via Pathao Express, Paperfly, and Steadfast Courier. Inside Dhaka delivery takes 24 to 48 hours at a standard charge of 70 BDT. Outside Dhaka delivery takes 3 to 5 business days at 130 BDT. Express same-day delivery inside Dhaka is available for orders confirmed before 11:00 AM at 150 BDT. Customers receive an SMS with live courier tracking upon dispatch.',
                'priority'      => 100,
            ],

            // ── 8. Payment Policy ─────────────────────────────────────────────
            [
                'document_type' => 'payment_policy',
                'category_id'   => $catPayment->id,
                'question'      => 'Accepted Payment Methods & Gateway Security / পেমেন্ট মাধ্যম ও নিয়মাবলী',
                'answer'        => 'We support multiple secure payment methods: Cash on Delivery (COD), bKash Merchant Gateway, Nagad, Rocket, and Visa/Mastercard/Amex through SSLCommerz. For COD orders outside Dhaka, an advance courier commitment fee of 150 BDT may be required to prevent fake bookings. We do not charge any extra merchant gateway transaction fees to customers.',
                'priority'      => 100,
            ],

            // ── 9. Cancellation Policy ────────────────────────────────────────
            [
                'document_type' => 'cancellation_policy',
                'category_id'   => $catOrders->id,
                'question'      => 'Order Cancellation Policy / অর্ডার বাতিল করার নিয়ম',
                'answer'        => 'Customers can cancel an order free of charge at any time before it has been dispatched from our central warehouse. Once an order is handed over to the courier partner and a tracking ID is generated, it cannot be cancelled mid-transit and must be handled under our return policy upon arrival. To cancel before dispatch, message our page or call our hotline with your Order ID.',
                'priority'      => 90,
            ],

            // ── 10. Warranty Policy ───────────────────────────────────────────
            [
                'document_type' => 'warranty_policy',
                'category_id'   => $catProducts->id,
                'question'      => 'Product Warranty & Quality Guarantee / প্রডাক্ট ওয়ারেন্টি ও গ্যারান্টি পলিসি',
                'answer'        => 'Smart accessories, watches, and electronics carry a 6-month to 1-year brand service warranty as specified on the product page. Apparel and leather shoes carry a 30-day manufacturing defect guarantee (stitching, sole bonding, zipper). Warranty claims require proof of purchase (invoice or order number). Physical damage, water damage, and normal wear-and-tear are excluded.',
                'priority'      => 90,
            ],

            // ── 11. Contact Information ───────────────────────────────────────
            [
                'document_type' => 'contact',
                'category_id'   => $catPolicy->id,
                'question'      => 'Contact Information, Hotline & Store Address / যোগাযোগের ঠিকানা ও হটলাইন',
                'answer'        => 'Reach our team directly: Flagship Store & Headquarters: House 42, Road 11, Block D, Banani, Dhaka-1213. Customer Hotline: +880-9612-345678 (9 AM – 10 PM daily). Official WhatsApp: +880-1712-345678. Support Email: support@entrepreneursautomation.com. Official Website: https://entrepreneursautomation.com.',
                'priority'      => 100,
            ],

            // ── 12. Customer Support Policy ───────────────────────────────────
            [
                'document_type' => 'customer_support',
                'category_id'   => $catPolicy->id,
                'question'      => 'Customer Support Hours & Helpdesk Operations / কাস্টমার কেয়ার ও হেল্পডেস্ক সময়সূচী',
                'answer'        => 'Our AI Support Assistant is available 24/7 for instant order lookups, parcel tracking, and store policies. Human support specialists are available 7 days a week from 9:00 AM to 10:00 PM BST. Inquiries received after 10:00 PM are queued and answered first thing the next morning.',
                'priority'      => 95,
            ],

            // ── 13. Social / F-Commerce Policy ────────────────────────────────
            [
                'document_type' => 'social_media_policy',
                'category_id'   => $catPolicy->id,
                'question'      => 'Official Social Channels & F-Commerce Safety / সোশ্যাল মিডিয়া ও ফেসবুক পেজ সংক্রান্ত তথ্য',
                'answer'        => 'Our official Facebook Page is "Entrepreneurs Automation Official" (look for the verified blue badge). We take and confirm orders via Messenger, Instagram DM, and WhatsApp. Please beware of impostor accounts; we will never ask for your bKash PIN, OTP, or passwords on social media chats.',
                'priority'      => 85,
            ],

            // ── 14. Overlapping Policy: Return vs Refund Disambiguation ───────
            [
                'document_type' => 'faq',
                'category_id'   => $catPolicy->id,
                'question'      => 'If I return an item, do I get a cash refund? / প্রোডাক্ট ফেরত দিলে টাকা ফেরত পাবো কি?',
                'answer'        => 'Yes! If you return a product in accordance with our Return Policy (within 7 days, unworn with original tags intact), your refund will be issued under our Refund Policy within 7 to 10 business days via your original digital payment channel (bKash/Nagad/Card). Cash on Delivery orders are refunded via customer bKash or Nagad wallet.',
                'priority'      => 80,
            ],

            // ── Additional Commerce FAQs ─────────────────────────────────────
            [
                'document_type' => 'faq',
                'category_id'   => $catProducts->id,
                'question'      => 'How do I choose the correct size for Panjabi and Shirts? / সাইজ কীভাবে নির্বাচন করব?',
                'answer'        => 'Each product page includes a detailed size guide measuring chest, length, and shoulder in inches. Standard sizing: M (Chest 38-40"), L (Chest 40-42"), XL (Chest 42-44"), XXL (Chest 44-46"). For regular fit Panjabi, we suggest ordering your exact chest size. For slim fit shirts, check chest and collar dimensions.',
                'priority'      => 80,
            ],
            [
                'document_type' => 'faq',
                'category_id'   => $catOrders->id,
                'question'      => 'Can I open and check the parcel before paying the courier (COD)? / ডেলিভারিম্যানের সামনে দেখে নেওয়া যাবে কি?',
                'answer'        => 'Yes! We allow parcel opening and inspection in front of the courier delivery representative for all Cash on Delivery orders. If the item is damaged or incorrect, you can return it to the delivery agent immediately without paying.',
                'priority'      => 85,
            ],
            [
                'document_type' => 'faq',
                'category_id'   => $catPayment->id,
                'question'      => 'How do I apply a discount coupon or voucher code? / কুপন বা ডিসকাউন্ট কোড ব্যবহারের নিয়ম কী?',
                'answer'        => 'On the checkout page, enter your promo code in the "Apply Coupon" field and click Apply. The discount will instantly be deducted from your cart total. Only one coupon code can be applied per order. Coupons cannot be combined with clearance sale discounts.',
                'priority'      => 75,
            ],
            [
                'document_type' => 'faq',
                'category_id'   => $catOrders->id,
                'question'      => 'What should I do if my parcel delivery is delayed? / ডেলিভারি পেতে দেরি হলে কী করব?',
                'answer'        => 'If your order exceeds the expected timeframe (48 hours for Dhaka, 5 days outside Dhaka), please message us with your Order ID or tracking code. Our logistics escalation team will contact the courier hub and resolve the transit delay within 4 hours.',
                'priority'      => 80,
            ],
            [
                'document_type' => 'faq',
                'category_id'   => $catProducts->id,
                'question'      => 'How should I wash and care for silk and linen apparel? / ফেব্রিক ওয়াশ ও কেয়ার গাইড',
                'answer'        => 'For Silk and Georgette items, dry cleaning is strongly recommended. For 100% Linen and Cotton casual shirts, hand wash or gentle machine wash in cold water using mild detergent. Avoid bleach and dry in the shade to prevent color fading.',
                'priority'      => 70,
            ],
        ];

        foreach ($documents as $doc) {
            FAQ::updateOrCreate(
                [
                    'workspace_id'  => $workspaceId,
                    'question'      => $doc['question'],
                ],
                [
                    'category_id'   => $doc['category_id'],
                    'document_type' => $doc['document_type'],
                    'answer'        => $doc['answer'],
                    'priority'      => $doc['priority'],
                    'is_active'     => true,
                ]
            );
        }

        $count = FAQ::where('workspace_id', $workspaceId)->count();
        echo "Successfully seeded Static Business Knowledge! Total FAQs/Docs in Workspace: {$count}\n";
    }
}
