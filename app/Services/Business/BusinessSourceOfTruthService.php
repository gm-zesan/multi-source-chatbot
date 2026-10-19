<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Models\Conversation;
use App\Models\CRMContact;
use Illuminate\Support\Facades\Log;

class BusinessSourceOfTruthService
{
    /**
     * In-memory test fixtures for automated testing environments.
     * Strictly gated to the 'testing' environment to prevent synthetic data leakage in production.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $mockOrderStore = [
        '1042' => [
            'order_id'           => '1042',
            'workspace_id'       => 1,
            'external_user_ids'  => ['user_sot_999', 'e2e_customer_tanvir', 'e2e_trace_user_01'],
            'customer_names'     => ['Tanvir Rahman', 'Tanvir Ahmed'],
            'customer_phones'    => ['+8801712345678'],
            'status'             => 'Dispatched',
            'carrier'            => 'Pathao Express',
            'tracking_code'      => 'PATH-1042-BD',
            'estimated_delivery' => 'Tomorrow by 4:00 PM',
            'total_bdt'          => 2450,
            'items'              => 'Premium Cotton Panjabi (Size: XL, Color: Navy Blue)',
            'payment_status'     => 'Paid via bKash',
        ],
        '8899' => [
            'order_id'           => '8899',
            'workspace_id'       => 1,
            'external_user_ids'  => ['user_sot_999', 'e2e_customer_tanvir'],
            'customer_names'     => ['Tanvir Rahman', 'Tanvir Ahmed'],
            'customer_phones'    => ['+8801712345678'],
            'status'             => 'Delivered',
            'carrier'            => 'Steadfast Courier',
            'tracking_code'      => 'ST-8899-DH',
            'estimated_delivery' => 'Delivered on Aug 28',
            'total_bdt'          => 1850,
            'items'              => 'Casual Shirt (Size: L)',
            'payment_status'     => 'Cash on Delivery',
        ],
    ];

    /**
     * Register a mock order for testing overrides.
     *
     * @param array<string, mixed> $data
     */
    public static function setMockOrder(string $orderId, array $data): void
    {
        self::$mockOrderStore[$orderId] = $data;
    }

    /**
     * Clear custom mock orders and restore defaults.
     */
    public static function resetMockOrders(): void
    {
        self::$mockOrderStore = [
            '1042' => [
                'order_id'           => '1042',
                'workspace_id'       => 1,
                'external_user_ids'  => ['user_sot_999', 'e2e_customer_tanvir', 'e2e_trace_user_01'],
                'customer_names'     => ['Tanvir Rahman', 'Tanvir Ahmed'],
                'customer_phones'    => ['+8801712345678'],
                'status'             => 'Dispatched',
                'carrier'            => 'Pathao Express',
                'tracking_code'      => 'PATH-1042-BD',
                'estimated_delivery' => 'Tomorrow by 4:00 PM',
                'total_bdt'          => 2450,
                'items'              => 'Premium Cotton Panjabi (Size: XL, Color: Navy Blue)',
                'payment_status'     => 'Paid via bKash',
            ],
            '8899' => [
                'order_id'           => '8899',
                'workspace_id'       => 1,
                'external_user_ids'  => ['user_sot_999', 'e2e_customer_tanvir'],
                'customer_names'     => ['Tanvir Rahman', 'Tanvir Ahmed'],
                'customer_phones'    => ['+8801712345678'],
                'status'             => 'Delivered',
                'carrier'            => 'Steadfast Courier',
                'tracking_code'      => 'ST-8899-DH',
                'estimated_delivery' => 'Delivered on Aug 28',
                'total_bdt'          => 1850,
                'items'              => 'Casual Shirt (Size: L)',
                'payment_status'     => 'Cash on Delivery',
            ],
        ];
    }

    /**
     * Look up authoritative live order data.
     *
     * Enforces:
     * 1. Environment Guard: Mock test fixtures are only available in 'testing'.
     * 2. No Synthetic Fallback: Unknown order IDs strictly return null.
     * 3. Workspace Scoping: Cross-workspace order access is rejected.
     * 4. Customer Ownership Verification: Fails closed when ownership cannot be verified.
     *
     * @return array<string, mixed>|null
     */
    public function lookupOrder(string|int $orderId, int $workspaceId = 1, ?Conversation $conversation = null): ?array
    {
        // 1. Production safety: Mock fixtures are strictly restricted to automated tests under 'testing'.
        if (!app()->environment('testing')) {
            Log::info('[BusinessSourceOfTruth] Order lookup suppressed in non-testing environment (no authoritative source)', [
                'order_id'     => $orderId,
                'workspace_id' => $workspaceId,
            ]);
            return null;
        }

        $cleanId = ltrim((string) $orderId, '#');

        if (!isset(self::$mockOrderStore[$cleanId])) {
            return null;
        }

        $order = self::$mockOrderStore[$cleanId];

        // 2. Enforce Workspace Scoping
        if (isset($order['workspace_id']) && (int) $order['workspace_id'] !== (int) $workspaceId) {
            Log::warning('[BusinessSourceOfTruth] Cross-workspace order access attempt blocked', [
                'order_id'            => $cleanId,
                'requested_workspace' => $workspaceId,
                'order_workspace'     => $order['workspace_id'],
            ]);
            return null;
        }

        // 3. Enforce Customer Ownership Authorization (fail-closed)
        if ($conversation === null || !$this->isAuthorizedCustomerForOrder($conversation, $order)) {
            Log::warning('[BusinessSourceOfTruth] Unauthorized order access attempt blocked (missing or invalid customer ownership)', [
                'order_id'         => $cleanId,
                'conversation_id'  => $conversation?->id,
                'external_user_id' => $conversation?->external_user_id,
            ]);
            return null;
        }

        Log::info('[BusinessSourceOfTruth] Order resolved from authorized test fixture', [
            'order_id'     => $cleanId,
            'status'       => $order['status'] ?? 'Unknown',
            'workspace_id' => $workspaceId,
        ]);

        return $order;
    }

    /**
     * Verify customer ownership of the order.
     * Fails closed if ownership evidence is missing or does not match conversation customer identity.
     *
     * @param Conversation $conversation
     * @param array<string, mixed> $order
     * @return bool
     */
    private function isAuthorizedCustomerForOrder(Conversation $conversation, array $order): bool
    {
        // 1. Gather Order Owner Identifiers
        $allowedExternalIds = [];
        if (!empty($order['external_user_id'])) {
            $allowedExternalIds[] = (string) $order['external_user_id'];
        }
        if (!empty($order['customer_id'])) {
            $allowedExternalIds[] = (string) $order['customer_id'];
        }
        if (!empty($order['external_user_ids']) && is_array($order['external_user_ids'])) {
            foreach ($order['external_user_ids'] as $id) {
                $allowedExternalIds[] = (string) $id;
            }
        }

        $allowedPhones = [];
        if (!empty($order['phone'])) {
            $allowedPhones[] = preg_replace('/[^\d]/', '', (string) $order['phone']);
        }
        if (!empty($order['customer_phone'])) {
            $allowedPhones[] = preg_replace('/[^\d]/', '', (string) $order['customer_phone']);
        }
        if (!empty($order['customer_phones']) && is_array($order['customer_phones'])) {
            foreach ($order['customer_phones'] as $p) {
                $allowedPhones[] = preg_replace('/[^\d]/', '', (string) $p);
            }
        }

        $allowedEmails = [];
        if (!empty($order['email'])) {
            $allowedEmails[] = mb_strtolower(trim((string) $order['email']));
        }
        if (!empty($order['customer_email'])) {
            $allowedEmails[] = mb_strtolower(trim((string) $order['customer_email']));
        }
        if (!empty($order['customer_emails']) && is_array($order['customer_emails'])) {
            foreach ($order['customer_emails'] as $e) {
                $allowedEmails[] = mb_strtolower(trim((string) $e));
            }
        }

        // Fails closed if order has zero reliable ownership evidence (external ID, phone, email)
        if (empty($allowedExternalIds) && empty($allowedPhones) && empty($allowedEmails)) {
            return false;
        }

        // 2. Gather Conversation Customer Identifiers
        $convExternalId = (string) ($conversation->external_user_id ?? '');

        $contact = $conversation->crmContact
            ?? CRMContact::with(['phones', 'emails'])
                ->where('conversation_id', $conversation->id)
                ->orWhere('external_user_id', $conversation->external_user_id)
                ->first();

        $convPhones = [];
        if ($contact && $contact->relationLoaded('phones')) {
            foreach ($contact->phones as $phoneObj) {
                $digits = preg_replace('/[^\d]/', '', (string) $phoneObj->phone);
                if ($digits !== '') {
                    $convPhones[] = $digits;
                }
            }
        } elseif ($contact) {
            foreach ($contact->phones()->get() as $phoneObj) {
                $digits = preg_replace('/[^\d]/', '', (string) $phoneObj->phone);
                if ($digits !== '') {
                    $convPhones[] = $digits;
                }
            }
        }

        $convEmails = [];
        if ($contact && $contact->relationLoaded('emails')) {
            foreach ($contact->emails as $emailObj) {
                $emailStr = mb_strtolower(trim((string) $emailObj->email));
                if ($emailStr !== '') {
                    $convEmails[] = $emailStr;
                }
            }
        } elseif ($contact) {
            foreach ($contact->emails()->get() as $emailObj) {
                $emailStr = mb_strtolower(trim((string) $emailObj->email));
                if ($emailStr !== '') {
                    $convEmails[] = $emailStr;
                }
            }
        }

        // Fails closed if conversation has zero reliable customer identity evidence
        if ($convExternalId === '' && empty($convPhones) && empty($convEmails)) {
            return false;
        }

        // 3. Check for matching reliable identity (external ID, verified phone, verified email)
        // Names are never treated as unique identifiers or independent proof of ownership.
        if ($convExternalId !== '' && in_array($convExternalId, $allowedExternalIds, true)) {
            return true;
        }

        if (!empty(array_intersect($convPhones, array_filter($allowedPhones)))) {
            return true;
        }

        if (!empty(array_intersect($convEmails, array_filter($allowedEmails)))) {
            return true;
        }

        return false;
    }

    /**
     * Build formatted Layer 3 Live Business Context.
     */
    public function buildBusinessContext(string $query, ?Conversation $conversation, int $workspaceId = 1): ?string
    {
        $sections = [];

        // 1. Detect order ID mention in query (#1042 or 1042)
        if (preg_match('/#?([0-9]{4,8})/u', $query, $matches)) {
            $orderId = $matches[1];
            $orderData = $conversation !== null ? $this->lookupOrder($orderId, $workspaceId, $conversation) : null;
            if ($orderData !== null) {
                $sections[] = "- Live Order #{$orderData['order_id']}:\n" .
                    "  * Status: {$orderData['status']}\n" .
                    "  * Courier/Carrier: {$orderData['carrier']}\n" .
                    "  * Tracking ID: {$orderData['tracking_code']}\n" .
                    "  * Estimated Delivery: {$orderData['estimated_delivery']}\n" .
                    "  * Payment: {$orderData['payment_status']}\n" .
                    "  * Items: {$orderData['items']}";
            } else {
                $cleanId = ltrim((string) $orderId, '#');
                $isTesting = app()->environment('testing');
                $existsInFixture = $isTesting && isset(self::$mockOrderStore[$cleanId]);

                if ($isTesting && !$existsInFixture) {
                    $sections[] = "- Order #{$orderId} Inquiry Status:\n" .
                        "  * Verification Status: Order not found.\n" .
                        "  * Policy Notice: Order #{$orderId} was not found in our records.\n" .
                        "  * Instruction to Assistant: Truthfully inform the customer that order #{$orderId} was not found in records. Do NOT guess, invent, or assume any status, courier, tracking code, or delivery dates. Politely ask the customer to verify their order number.";
                } else {
                    $sections[] = "- Order #{$orderId} Inquiry Status:\n" .
                        "  * Verification Status: Order status could not be verified for this customer account.\n" .
                        "  * Policy Notice: Order #{$orderId} could not be verified in live records for this customer session.\n" .
                        "  * Instruction to Assistant: Truthfully inform the customer that order #{$orderId} cannot be verified or accessed for their account. Do NOT guess, invent, or assume any status, courier, tracking code, or delivery dates. Politely advise the customer to double-check their order number or contact support from their registered phone/email.";
                }
            }
        }

        // 2. Attach CRM Contact information if associated with conversation
        if ($conversation !== null) {
            $contact = $conversation->crmContact
                ?? CRMContact::with(['phones', 'emails'])
                    ->where('conversation_id', $conversation->id)
                    ->orWhere('external_user_id', $conversation->external_user_id)
                    ->first();

            if ($contact !== null) {
                $contact->loadMissing(['phones', 'emails']);
                $primaryPhone = $contact->phones->first()?->phone;
                $primaryEmail = $contact->emails->first()?->email;
                $crmLines = [];
                if (!empty($contact->name)) {
                    $crmLines[] = "  * Customer Name: {$contact->name}";
                }
                if (!empty($primaryPhone)) {
                    $crmLines[] = "  * Verified Phone: {$primaryPhone}";
                }
                if (!empty($primaryEmail)) {
                    $crmLines[] = "  * Registered Email: {$primaryEmail}";
                }
                if (!empty($crmLines)) {
                    $sections[] = "- Customer CRM Record:\n" . implode("\n", $crmLines);
                }
            }
        }

        // 3. Attach authoritative Product Catalog data if product mentioned in query or in conversation context
        try {
            $matchingProducts = \App\Models\AnalyticsProduct::activeCatalog()
                ->where('workspace_id', $workspaceId)
                ->get();

            $qLower = mb_strtolower($query);
            $matchedAny = false;
            $matchedProduct = null;

            foreach ($matchingProducts as $product) {
                $pNameLower = mb_strtolower($product->name);
                if (str_contains($qLower, $pNameLower) || str_contains($qLower, str_replace(' ', '', $pNameLower))) {
                    $formattedPrice = number_format((float) $product->unit_price, 2);
                    $sections[] = "- Live Product Catalog Record (Authoritative Truth):\n" .
                        "  * Product: {$product->name}\n" .
                        "  * Category: {$product->category}\n" .
                        "  * Price: ৳{$formattedPrice}\n" .
                        "  * Catalog Status: Active in catalog";
                    $matchedAny = true;
                    $matchedProduct = $product;
                }
            }

            // If a specific product was matched in this turn, persist it as the active product in conversation metadata
            if ($matchedAny && $matchedProduct !== null && $conversation !== null && $conversation->exists) {
                $metadata = $conversation->metadata ?? [];
                $metadata['active_product'] = [
                    'id'         => $matchedProduct->id,
                    'name'       => $matchedProduct->name,
                    'category'   => $matchedProduct->category,
                    'unit_price' => (float) $matchedProduct->unit_price,
                    'updated_at' => now()->toIso8601String(),
                ];
                $conversation->metadata = $metadata;
                $conversation->save();
            }

            // Retrieve active product from context if no product was explicitly matched in the current turn
            $activeProduct = $conversation?->metadata['active_product'] ?? null;

            // Check if query is an order placement or purchasing inquiry (e.g. "kivabe order korbo?", "order korte chai", "how to order?")
            $isOrderPlacement = (bool) preg_match('/(kivabe\s*order|order\s*kivabe|order\s*korte\s*chai|kivabe\s*kinbo|kivabe\s*pabo|order\s*korbo|কিভাবে\s*অর্ডার|অর্ডার\s*করব|অর্ডার\s*করবো|অর্ডার\s*কীভাবে|অর্ডার\s*করতে\s*চাই|অর্ডার\s*দিতে\s*চাই|কিনতে\s*চাই|নিতে\s*চাই|অর্ডার\s*প্রক্রিয়া|অর্ডার\s*করার\s*নিয়ম|how\s*(to|can\s*i)\s*(order|buy|purchase)|order\s*process)/ui', $query);

            if ($isOrderPlacement) {
                if ($activeProduct && !empty($activeProduct['name'])) {
                    $formattedPrice = number_format((float) ($activeProduct['unit_price'] ?? 0), 2);
                    $sections[] = "- Active Product in Discussion (Authoritative Truth):\n" .
                        "  * Product: {$activeProduct['name']}\n" .
                        "  * Category: " . ($activeProduct['category'] ?? 'General') . "\n" .
                        "  * Price: ৳{$formattedPrice}\n" .
                        "- Authoritative Order Placement Process:\n" .
                        "  * The customer is asking how to place an order for the active product ({$activeProduct['name']}).\n" .
                        "  * Instruct the customer to provide their Name, Mobile Phone Number, and complete Delivery Address here in the chat to confirm the order.\n" .
                        "  * Mention that our customer support team will verify and process the order promptly.";
                } else {
                    $sections[] = "- Authoritative Order Placement Process:\n" .
                        "  * To place an order, instruct the customer to provide the name of the desired product, their Name, Mobile Phone Number, and complete Delivery Address in the chat.";
                }
            } elseif (!$matchedAny && $activeProduct && !empty($activeProduct['name'])) {
                // If query is an elliptical inquiry about price or availability of the active product
                if (preg_match('/(দাম\s*কত|কত\s*দাম|dam\s*koto|price\s*koto|how\s*much|স্টকে\s*আছে|available\s*ache|stock\s*ache)/ui', $query)) {
                    $formattedPrice = number_format((float) ($activeProduct['unit_price'] ?? 0), 2);
                    $sections[] = "- Live Product Catalog Record for Active Discussion ({$activeProduct['name']}):\n" .
                        "  * Product: {$activeProduct['name']}\n" .
                        "  * Category: " . ($activeProduct['category'] ?? 'General') . "\n" .
                        "  * Price: ৳{$formattedPrice}\n" .
                        "  * Catalog Status: Active in catalog";
                }
            }

            // If general product list/catalog inquiry (e.g. "ki ki product ache?", "কি কি প্রোডাক্ট আছে")
            if (!$matchedAny && !$isOrderPlacement && preg_match('/(product|প্রোডাক্ট|পণ্য|আইটেম|item|list|তালিকা|কালেকশন|collection)/ui', $query) && $matchingProducts->isNotEmpty()) {
                $productListLines = [];
                foreach ($matchingProducts->take(10) as $prod) {
                    $priceStr = number_format((float) $prod->unit_price, 2);
                    $productListLines[] = "  * {$prod->name} ({$prod->category}) - ৳{$priceStr}";
                }
                $sections[] = "- Active Products in Catalog (Authoritative Truth):\n" . implode("\n", $productListLines);
            }
        } catch (\Throwable $e) {
            Log::warning('[BusinessSourceOfTruth] Product lookup skipped: ' . $e->getMessage());
        }

        if (empty($sections)) {
            return null;
        }

        return implode("\n\n", $sections);
    }
}
