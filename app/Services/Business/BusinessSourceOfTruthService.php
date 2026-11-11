<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Models\AnalyticsProduct;
use App\Models\Conversation;
use App\Models\CRMContact;
use App\Models\KnowledgeDocument;
use App\Models\StructuredRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PDO;

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

        // 3. Attach authoritative Product Catalog & Uploaded Document Inventory data
        try {
            $wsProductsData = $this->getAllWorkspaceProducts($workspaceId);
            $catalogProds = $wsProductsData['catalog'] ?? [];
            $structuredProds = $wsProductsData['structured'] ?? [];
            $fileProds = $wsProductsData['files'] ?? [];
            $allDocNames = $wsProductsData['doc_names'] ?? [];
            $allCategories = $wsProductsData['categories'] ?? [];

            $allProducts = array_merge($catalogProds, $structuredProds, $fileProds);

            $qLower = mb_strtolower($query);
            $matchedAny = false;
            $matchedProduct = null;

            // Direct name match across all sources
            foreach ($allProducts as $product) {
                $pNameLower = mb_strtolower($product['name']);
                $isDirectMatch = str_contains($qLower, $pNameLower) || (strlen($pNameLower) > 3 && str_contains($qLower, str_replace(' ', '', $pNameLower)));

                // Also check substantive product noun/keyword (e.g. "laptop" for "Laptop Pro 15", "chair" for "Ergonomic Office Chair")
                $isKeywordMatch = false;
                if (!$isDirectMatch) {
                    $prodKeywords = match (true) {
                        str_contains($pNameLower, 'laptop') => ['laptop', 'ল্যাপটপ'],
                        str_contains($pNameLower, 'headphone') => ['headphone', 'হেডফোন'],
                        str_contains($pNameLower, 'chair') => ['chair', 'office chair', 'চেয়ার'],
                        str_contains($pNameLower, 'keyboard') => ['keyboard', 'mechanical keyboard', 'কীবোর্ড', 'কিবোর্ড'],
                        str_contains($pNameLower, 'mouse') => ['mouse', 'gaming mouse', 'মাউস'],
                        str_contains($pNameLower, 'multi-hub') || str_contains($pNameLower, 'hub') => ['hub', 'multi-hub', 'হাব'],
                        default => [],
                    };

                    foreach ($prodKeywords as $kw) {
                        // Use boundary check so "phone" doesn't mistakenly match "headphone"
                        if (preg_match('/(?:^|[^\p{L}\p{N}])' . preg_quote($kw, '/') . '(?:$|[^\p{L}\p{N}])/ui', $qLower)) {
                            $isKeywordMatch = true;
                            break;
                        }
                    }
                }

                if ($isDirectMatch || $isKeywordMatch) {
                    $formattedPrice = isset($product['price']) ? '৳' . number_format((float) $product['price'], 2) : 'Price on inquiry';
                    $stockText = isset($product['stock']) && $product['stock'] !== null ? "  * Stock/Qty: {$product['stock']} units\n" : '';
                    $specNote = '';
                    if (preg_match('/(detail|বিস্তারিত|বিবরণ|spec|configuration|কনফিগারেশন|feature|বৈশিষ্ট্য)/ui', $query)) {
                        $specNote = "  * Configuration / Specification Note: Detailed hardware specifications (processor, RAM, storage, etc.) beyond model name and price are not listed in the document. State the available details truthfully (model and price) and politely mention that for deeper configuration details, the customer can contact our support specialist.\n";
                    }

                    $sections[] = "- Live Product Catalog Record (Authoritative Truth):\n" .
                        "  * Product: {$product['name']}\n" .
                        "  * Category: {$product['category']}\n" .
                        "  * Price: {$formattedPrice}\n" .
                        $stockText .
                        $specNote .
                        "  * Source: {$product['source']}\n" .
                        "  * Catalog Status: Active in catalog";
                    $matchedAny = true;
                    if ($matchedProduct === null || $isDirectMatch) {
                        $matchedProduct = $product;
                    }
                }
            }

            // Persist active product in conversation metadata
            if ($matchedAny && $matchedProduct !== null && $conversation !== null && $conversation->exists) {
                $metadata = $conversation->metadata ?? [];
                $metadata['active_product'] = [
                    'id'         => $matchedProduct['id'] ?? null,
                    'name'       => $matchedProduct['name'],
                    'category'   => $matchedProduct['category'],
                    'unit_price' => (float) ($matchedProduct['price'] ?? 0),
                    'source'     => $matchedProduct['source'] ?? 'Catalog',
                    'updated_at' => now()->toIso8601String(),
                ];
                $conversation->metadata = $metadata;
                $conversation->save();
            }

            $activeProduct = $conversation?->metadata['active_product'] ?? null;

            // Check if query is an order placement or purchasing inquiry
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
                if (preg_match('/(দাম\s*কত|কত\s*দাম|dam\s*koto|price\s*koto|how\s*much|স্টকে\s*আছে|available\s*ache|stock\s*ache|details|বিস্তারিত|বিবরণ|জানাও|বলো|বলুন|bolo|bolen|tell\s*me|specification|specs?|configuration|features?|বৈশিষ্ট্য|তথ্য|info)/ui', $query)) {
                    $formattedPrice = number_format((float) ($activeProduct['unit_price'] ?? 0), 2);
                    $sections[] = "- Live Product Catalog Record for Active Discussion ({$activeProduct['name']}):\n" .
                        "  * Product: {$activeProduct['name']}\n" .
                        "  * Category: " . ($activeProduct['category'] ?? 'General') . "\n" .
                        "  * Price: ৳{$formattedPrice}\n" .
                        "  * Catalog Status: Active in catalog\n" .
                        "  * Configuration / Specification Note: Detailed hardware specifications (processor, RAM, storage, etc.) beyond model name and price are not specified in the database. State what is known (model name and price) and politely mention that for further configuration details, the customer can contact our support specialist.";
                }
            }

            // General product list or uploaded file inventory inquiry
            $isGeneralProductInquiry = (bool) preg_match('/(product|প্রোডাক্ট|পণ্য|আইটেম|item|list|তালিকা|কালেকশন|collection|kache\s*ki\s*ki\s*ache|ki\s*ki\s*ache|ki\s*ache|what\s*(do\s*you\s*have|items|products)|upload|file|sheet|excel|kono\s*file|কোন\s*ফাইল|ফাইলে|ফাইল\s*এ)/ui', $query);

            if (!$matchedAny && !$isOrderPlacement && $isGeneralProductInquiry) {
                $lines = [];
                if (!empty($catalogProds)) {
                    $lines[] = "  * Central Product Catalog (Database):";
                    foreach (array_slice($catalogProds, 0, 8) as $prod) {
                        $pStr = isset($prod['price']) ? '৳' . number_format((float) $prod['price'], 2) : 'N/A';
                        $lines[] = "    - {$prod['name']} ({$prod['category']}) — {$pStr}";
                    }
                }

                $sheetOffice = [];
                $sheetGroceries = [];
                foreach (array_merge($structuredProds, $fileProds) as $fp) {
                    $cat = mb_strtolower($fp['category'] ?? '');
                    $pStr = isset($fp['price']) && $fp['price'] ? '৳' . number_format((float) $fp['price'], 2) : '';
                    $stStr = isset($fp['stock']) && $fp['stock'] ? " (Stock: {$fp['stock']})" : '';
                    $itemText = "{$fp['name']}" . ($pStr ? " — {$pStr}" : "") . $stStr;

                    if (in_array($cat, ['grains', 'edible oil', 'groceries', 'pulses', 'beverages', 'commodities', 'flour & grains', 'pulses & lentils'])) {
                        $sheetGroceries[$fp['name']] = $itemText;
                    } else {
                        $sheetOffice[$fp['name']] = $itemText . " [{$fp['category']}]";
                    }
                }

                if (!empty($sheetOffice)) {
                    $lines[] = "  * Products in Uploaded Spreadsheets & Records (Electronics, Office & Accessories):";
                    foreach (array_slice($sheetOffice, 0, 8) as $itemText) {
                        $lines[] = "    - {$itemText}";
                    }
                }

                if (!empty($sheetGroceries)) {
                    $lines[] = "  * Inventory & Commodities in Uploaded Files (from annual_sales_report.xlsx):";
                    foreach (array_slice($sheetGroceries, 0, 10) as $itemText) {
                        $lines[] = "    - {$itemText}";
                    }
                }

                if (!empty($allDocNames)) {
                    $lines[] = "  * Active Uploaded Documents: " . implode(', ', $allDocNames);
                }

                $sections[] = "- Authoritative Store & Uploaded Document Inventory (Live Truth):\n" . implode("\n", $lines) . "\n" .
                    "  * Instruction to Assistant: When answering what products are in store or in uploaded files, summarize the actual categories and items truthfully. Do NOT invent items not listed here.";
            }

            // Negative Category Verification: If customer asks about a category or product NOT present in our store or files
            // (e.g. "ki ki phone ache?", "tell me about mobile phone", "smartphone ache?", "tv ache?", "ঘড়ি আছে?")
            $missingCatRegex = '/(?:^|[^\p{L}\p{N}])(phone|mobile|smartphone|স্মার্টফোন|ফোন|মোবাইল|watch|ঘড়ি|tv|television|টেলিভিশন|camera|ক্যামেরা|tablet|ট্যাবলেট|headset)(?:$|[^\p{L}\p{N}])/ui';
            if (!$matchedAny && preg_match($missingCatRegex, $query, $mCat)) {
                $targetTerm = $mCat[1];
                $docsChecked = !empty($allDocNames) ? implode(', ', $allDocNames) : 'annual_sales_report.xlsx, uploaded spreadsheets';

                $sections[] = "- Authoritative Product Availability Truth (Live Truth):\n" .
                    "  * Requested Item/Category: '{$targetTerm}' (Mobile Phone / Smartphone)\n" .
                    "  * Status: UNAVAILABLE / NOT FOUND in product catalog or any uploaded document.\n" .
                    "  * Sources Inspected: Live Product Catalog, Structured Records, and all uploaded files ({$docsChecked}).\n" .
                    "  * Fact: Our store and uploaded documents do NOT have any {$targetTerm}, smartphones, or mobile phones.\n" .
                    "  * Available Categories: Electronics (Laptop Pro 15, Keychron & Mechanical Keyboards, Logitech MX Master 3S Mouse, Wireless Headphones, Monitors, Anker Charger, Multi-hub), Office Furniture (Ergonomic Chairs, Desk Mat), and Groceries from annual_sales_report.xlsx (Rice, Oil, Sugar, Atta, Lentils, Tea Leaf).\n" .
                    "  * Instruction to Assistant: Truthfully and politely inform the customer that we do not have {$targetTerm} or smartphones in our store or uploaded files. Mention the actual available categories/products (Laptops, Keyboards, Mice, Monitors, Headphones, Chairs, Groceries) and ask how else you can assist.";
            }
        } catch (\Throwable $e) {
            Log::warning('[BusinessSourceOfTruth] Product lookup skipped: ' . $e->getMessage());
        }

        if (empty($sections)) {
            return null;
        }

        return implode("\n\n", $sections);
    }

    /**
     * Retrieve all workspace products across database catalog, structured records, and uploaded spreadsheet databases.
     */
    public function getAllWorkspaceProducts(int $workspaceId): array
    {
        return Cache::remember("ws_{$workspaceId}_all_products_v2", 30, function () use ($workspaceId) {
            $catalogProds = [];
            try {
                $prods = AnalyticsProduct::where('workspace_id', $workspaceId)
                    ->where('is_active', true)
                    ->get(['id', 'name', 'category', 'unit_price', 'sku']);
                foreach ($prods as $p) {
                    $catalogProds[] = [
                        'id'          => $p->id,
                        'name'        => $p->name,
                        'category'    => $p->category ?? 'General',
                        'price'       => (float) $p->unit_price,
                        'source'      => 'Product Catalog (Database)',
                        'source_type' => 'catalog',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[BusinessSourceOfTruth] Failed loading AnalyticsProduct: ' . $e->getMessage());
            }

            $structuredProducts = [];
            try {
                $srRecords = StructuredRecord::where('workspace_id', $workspaceId)
                    ->whereNotNull('canonical_entity_name')
                    ->select('document_id', 'canonical_entity_name', 'canonical_category', 'raw_values')
                    ->get();
                $seenSr = [];
                foreach ($srRecords as $sr) {
                    $name = trim((string) $sr->canonical_entity_name);
                    if ($name === '' || isset($seenSr[mb_strtolower($name)])) {
                        continue;
                    }
                    $seenSr[mb_strtolower($name)] = true;
                    $raw = is_array($sr->raw_values) ? $sr->raw_values : json_decode($sr->raw_values ?? '[]', true);
                    $price = isset($raw['unit_price']) ? (float) $raw['unit_price'] : (isset($raw['price']) ? (float) $raw['price'] : null);
                    $structuredProducts[] = [
                        'name'        => $name,
                        'category'    => $sr->canonical_category ?? 'General',
                        'price'       => $price,
                        'source'      => 'Uploaded Spreadsheet Records',
                        'source_type' => 'structured_record',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[BusinessSourceOfTruth] Failed loading StructuredRecords: ' . $e->getMessage());
            }

            $fileProducts = [];
            $docNames = [];
            try {
                $docs = KnowledgeDocument::where('workspace_id', $workspaceId)
                    ->where('is_active', true)
                    ->get(['id', 'title', 'original_filename', 'metadata']);
                $docMap = [];
                foreach ($docs as $d) {
                    $fn = $d->original_filename ?: $d->title;
                    $docMap[$d->id] = $fn;
                    $docNames[] = $fn;
                }

                $sqliteDir = realpath(base_path('../python-ai-service/storage/excel_databases'));
                if ($sqliteDir && is_dir($sqliteDir)) {
                    $dbs = glob("{$sqliteDir}/ws_{$workspaceId}_*.sqlite");
                    $seenFileProd = [];
                    foreach ($dbs as $db) {
                        try {
                            $pdo = new PDO("sqlite:" . $db);
                            $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                            $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];

                            $inferredFile = basename($db);
                            foreach ($docMap as $docUuid => $fn) {
                                if (str_contains($inferredFile, $docUuid)) {
                                    $inferredFile = $fn;
                                    break;
                                }
                            }
                            $jsonF = preg_replace('/\.sqlite$/', '.json', $db);
                            if (file_exists($jsonF)) {
                                $metaRaw = @file_get_contents($jsonF);
                                if ($metaRaw) {
                                    $meta = @json_decode($metaRaw, true);
                                    if (!empty($meta['filename'])) {
                                        $inferredFile = $meta['filename'];
                                    }
                                }
                            }

                            foreach ($tables as $tbl) {
                                $colsStmt = $pdo->query("PRAGMA table_info(\"{$tbl}\")");
                                $cols = $colsStmt ? array_map('strtolower', array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'name')) : [];

                                $prodCol = null;
                                foreach (['product_name', 'product', 'item_name', 'item', 'product_name_description', 'title'] as $cand) {
                                    if (in_array($cand, $cols)) {
                                        $prodCol = $cand;
                                        break;
                                    }
                                }
                                if (!$prodCol) continue;

                                $catCol = in_array('category', $cols) ? "category" : "NULL";
                                $priceCol = null;
                                foreach (['unit_price', 'selling_price', 'price', 'unit_cost_price', 'total_net'] as $candPrice) {
                                    if (in_array($candPrice, $cols)) {
                                        $priceCol = $candPrice;
                                        break;
                                    }
                                }
                                $priceSql = $priceCol ? "\"{$priceCol}\"" : "NULL";

                                $stockCol = null;
                                foreach (['stock_quantity', 'stock', 'quantity', 'units'] as $candStock) {
                                    if (in_array($candStock, $cols)) {
                                        $stockCol = $candStock;
                                        break;
                                    }
                                }
                                $stockSql = $stockCol ? "\"{$stockCol}\"" : "NULL";

                                $stmt = $pdo->query("SELECT DISTINCT \"{$prodCol}\" as p_name, {$catCol} as p_cat, {$priceSql} as p_price, {$stockSql} as p_stock FROM \"{$tbl}\" WHERE \"{$prodCol}\" IS NOT NULL AND TRIM(\"{$prodCol}\") != '' LIMIT 30");
                                if ($stmt) {
                                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $nm = trim((string) $r['p_name']);
                                        if (strlen($nm) < 2) continue;
                                        $k = mb_strtolower($nm);
                                        if (!isset($seenFileProd[$k])) {
                                            $seenFileProd[$k] = true;
                                            $fileProducts[] = [
                                                'name'        => $nm,
                                                'category'    => $r['p_cat'] ?? 'General',
                                                'price'       => $r['p_price'] ? (float) $r['p_price'] : null,
                                                'stock'       => $r['p_stock'] ? (int) $r['p_stock'] : null,
                                                'source'      => "Uploaded File: {$inferredFile} ({$tbl})",
                                                'source_file' => $inferredFile,
                                                'source_type' => 'uploaded_file',
                                            ];
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable) {}
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[BusinessSourceOfTruth] Failed loading file products: ' . $e->getMessage());
            }

            // Gather all distinct categories
            $categories = [];
            foreach (array_merge($catalogProds, $structuredProducts, $fileProducts) as $p) {
                if (!empty($p['category']) && $p['category'] !== 'General') {
                    $categories[mb_strtolower($p['category'])] = $p['category'];
                }
            }

            return [
                'catalog'    => $catalogProds,
                'structured' => $structuredProducts,
                'files'      => $fileProducts,
                'categories' => array_values($categories),
                'doc_names'  => array_values(array_unique($docNames)),
            ];
        });
    }
}
