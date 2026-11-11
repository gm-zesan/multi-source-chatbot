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

            // Merge products with deduplication across catalog, structured records, and file databases
            $mergedProducts = [];
            foreach (array_merge($catalogProds, $structuredProds, $fileProds) as $p) {
                $k = mb_strtolower(trim($p['name']));
                if (!isset($mergedProducts[$k])) {
                    $mergedProducts[$k] = $p;
                } else {
                    if (empty($mergedProducts[$k]['price']) && !empty($p['price'])) {
                        $mergedProducts[$k]['price'] = $p['price'];
                    }
                    if (empty($mergedProducts[$k]['stock']) && !empty($p['stock'])) {
                        $mergedProducts[$k]['stock'] = $p['stock'];
                    }
                    if (empty($mergedProducts[$k]['specs']) && !empty($p['specs'])) {
                        $mergedProducts[$k]['specs'] = $p['specs'];
                    }
                    if (empty($mergedProducts[$k]['description']) && !empty($p['description'])) {
                        $mergedProducts[$k]['description'] = $p['description'];
                    }
                    if (($mergedProducts[$k]['category'] ?? 'General') === 'General' && !empty($p['category']) && $p['category'] !== 'General') {
                        $mergedProducts[$k]['category'] = $p['category'];
                    }
                }
            }
            $allProducts = array_values($mergedProducts);

            $qLower = mb_strtolower($query);
            $matchedAny = false;
            $matchedProduct = null;

            // Direct name and category/keyword match across all sources
            foreach ($allProducts as $product) {
                $pNameLower = mb_strtolower($product['name']);
                $pCatLower = mb_strtolower($product['category'] ?? '');

                $isDirectMatch = str_contains($qLower, $pNameLower) || (strlen($pNameLower) > 3 && str_contains($qLower, str_replace(' ', '', $pNameLower)));

                // Also check substantive product noun/keyword and category dynamically
                $isKeywordMatch = false;
                if (!$isDirectMatch) {
                    $prodKeywords = $this->extractProductKeywords($product['name'], $product['category'] ?? null);

                    foreach ($prodKeywords as $kw) {
                        if (preg_match('/(?:^|[^\p{L}\p{N}])' . preg_quote($kw, '/') . '(?:$|[^\p{L}\p{N}])/ui', $qLower)) {
                            $isKeywordMatch = true;
                            break;
                        }
                    }
                }

                if ($isDirectMatch || $isKeywordMatch) {
                    $formattedPrice = isset($product['price']) && (float) $product['price'] > 0
                        ? '৳' . number_format((float) $product['price'], 2)
                        : 'Price on inquiry';
                    $stockText = isset($product['stock']) && $product['stock'] !== null ? "  * Stock/Qty: {$product['stock']} units\n" : '';
                    $specsText = !empty($product['specs']) ? "  * Configuration & Specs: {$product['specs']}\n" : '';
                    $descText = !empty($product['description']) ? "  * Description: {$product['description']}\n" : '';

                    $sections[] = "- Live Product Catalog Record (Authoritative Truth):\n" .
                        "  * Product: {$product['name']}\n" .
                        "  * Category: {$product['category']}\n" .
                        "  * Price: {$formattedPrice}\n" .
                        $stockText .
                        $specsText .
                        $descText .
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
                    'id'          => $matchedProduct['id'] ?? null,
                    'name'        => $matchedProduct['name'],
                    'category'    => $matchedProduct['category'] ?? 'General',
                    'unit_price'  => (float) ($matchedProduct['price'] ?? 0),
                    'stock'       => $matchedProduct['stock'] ?? null,
                    'specs'       => $matchedProduct['specs'] ?? null,
                    'description' => $matchedProduct['description'] ?? null,
                    'source'      => $matchedProduct['source'] ?? 'Catalog',
                    'updated_at'  => now()->toIso8601String(),
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
                    $stockLine = isset($activeProduct['stock']) && $activeProduct['stock'] !== null ? "  * Stock/Qty: {$activeProduct['stock']} units\n" : '';
                    $specsLine = !empty($activeProduct['specs'])
                        ? "  * Configuration & Specs: {$activeProduct['specs']}\n"
                        : "  * Configuration / Specification Note: Detailed specifications beyond model name and price are not specified in the database. State what is known (model name and price) and politely mention that for further configuration details, the customer can contact our support specialist.\n";
                    $descLine = !empty($activeProduct['description']) ? "  * Description: {$activeProduct['description']}\n" : '';

                    $sections[] = "- Live Product Catalog Record for Active Discussion ({$activeProduct['name']}):\n" .
                        "  * Product: {$activeProduct['name']}\n" .
                        "  * Category: " . ($activeProduct['category'] ?? 'General') . "\n" .
                        "  * Price: ৳{$formattedPrice}\n" .
                        $stockLine .
                        $specsLine .
                        $descLine .
                        "  * Catalog Status: Active in catalog\n" .
                        "  * Instruction to Assistant: Answer the customer's question directly using the verified specifications, price, and details above. Do not claim information is missing if it is listed above.";
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

                $sheetItems = [];
                foreach ($allProducts as $fp) {
                    $pStr = isset($fp['price']) && $fp['price'] ? '৳' . number_format((float) $fp['price'], 2) : '';
                    $stStr = isset($fp['stock']) && $fp['stock'] ? " (Stock: {$fp['stock']})" : '';
                    $catStr = !empty($fp['category']) ? " [{$fp['category']}]" : "";
                    $sheetItems[] = "{$fp['name']}{$catStr}" . ($pStr ? " — {$pStr}" : "") . $stStr;
                }

                if (!empty($sheetItems)) {
                    $lines[] = "  * Available Products in Store & Uploaded Documents:";
                    foreach (array_slice($sheetItems, 0, 12) as $itemText) {
                        $lines[] = "    - {$itemText}";
                    }
                }

                if (!empty($allDocNames)) {
                    $lines[] = "  * Active Uploaded Documents: " . implode(', ', $allDocNames);
                }

                $sections[] = "- Authoritative Store & Uploaded Document Inventory (Live Truth):\n" . implode("\n", $lines) . "\n" .
                    "  * Instruction to Assistant: When answering what products are in store or in uploaded files, summarize the actual categories and items truthfully. Do NOT invent items not listed here.";
            }

            // Negative Product/Category Verification: If customer asks about an item or category NOT present in our store or files
            // Dynamically detects the inquired item/category from the inquiry sentence
            $inquiredItem = !$matchedAny ? $this->detectInquiredItemOrCategory($query) : null;
            if ($inquiredItem !== null) {
                $targetTerm = $inquiredItem;
                $docsChecked = !empty($allDocNames) ? implode(', ', $allDocNames) : 'uploaded documents and product catalog';

                $availCats = !empty($allCategories)
                    ? implode(', ', $allCategories)
                    : (!empty($allProducts) ? implode(', ', array_unique(array_filter(array_column($allProducts, 'category')))) : 'catalog items');

                $availSummary = [];
                foreach (array_slice($allProducts, 0, 8) as $ap) {
                    $catSuffix = !empty($ap['category']) && $ap['category'] !== 'General' ? " ({$ap['category']})" : "";
                    $availSummary[] = $ap['name'] . $catSuffix;
                }
                $availList = !empty($availSummary) ? implode(', ', $availSummary) : 'currently available products';

                $sections[] = "- Authoritative Product Availability Truth (Live Truth):\n" .
                    "  * Requested Item/Category: '{$targetTerm}'\n" .
                    "  * Status: UNAVAILABLE / NOT FOUND in product catalog or any uploaded document.\n" .
                    "  * Sources Inspected: Live Product Catalog, Structured Records, and all uploaded files ({$docsChecked}).\n" .
                    "  * Fact: Our store and uploaded documents do NOT have {$targetTerm}.\n" .
                    "  * Available Categories: {$availCats} (e.g. {$availList}).\n" .
                    "  * Instruction to Assistant: Truthfully and politely inform the customer that we do not have {$targetTerm} in our store or uploaded files. Mention the actual available categories/products ({$availCats}) and ask how else you can assist.";
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
                    ->get(['document_id', 'canonical_entity_name', 'canonical_category', 'canonical_amount', 'canonical_quantity', 'mapped_attributes', 'semantic_projections']);
                $seenSr = [];
                foreach ($srRecords as $sr) {
                    $name = trim((string) $sr->canonical_entity_name);
                    if ($name === '' || isset($seenSr[mb_strtolower($name)])) {
                        continue;
                    }
                    $seenSr[mb_strtolower($name)] = true;

                    $price = $sr->canonical_amount !== null && (float) $sr->canonical_amount > 0 ? (float) $sr->canonical_amount : null;
                    $stock = $sr->canonical_quantity !== null ? (int) $sr->canonical_quantity : null;

                    $specs = null;
                    $desc = null;
                    $mapped = is_array($sr->mapped_attributes) ? $sr->mapped_attributes : (json_decode($sr->mapped_attributes ?? '[]', true) ?: []);
                    $projections = is_array($sr->semantic_projections) ? $sr->semantic_projections : (json_decode($sr->semantic_projections ?? '[]', true) ?: []);

                    foreach ($mapped as $colKey => $colData) {
                        if (!is_array($colData)) continue;
                        $hdr = mb_strtolower($colData['header'] ?? '');
                        $val = $colData['value'] ?? $colData['raw_value'] ?? null;
                        if ($val === null || trim((string) $val) === '') continue;

                        if (str_contains($hdr, 'config') || str_contains($hdr, 'spec')) {
                            $specs = (string) $val;
                        } elseif ($hdr === 'description' || (str_contains($hdr, 'desc') && !$specs)) {
                            $desc = (string) $val;
                        }
                    }
                    if (!$specs && !empty($projections['description_col_005'])) {
                        $specs = (string) $projections['description_col_005'];
                    }
                    if (!$desc && !empty($projections['description_col_006'])) {
                        $desc = (string) $projections['description_col_006'];
                    }

                    $structuredProducts[] = [
                        'name'        => $name,
                        'category'    => $sr->canonical_category ?? 'General',
                        'price'       => $price,
                        'stock'       => $stock,
                        'specs'       => $specs,
                        'description' => $desc,
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
                $activeDocIds = [];
                foreach ($docs as $d) {
                    $fn = $d->original_filename ?: $d->title;
                    $docMap[$d->id] = $fn;
                    $docNames[] = $fn;
                    $activeDocIds[] = (string) $d->id;
                }

                $sqliteDir = realpath(base_path('../python-ai-service/storage/excel_databases'));
                if ($sqliteDir && is_dir($sqliteDir)) {
                    $dbs = glob("{$sqliteDir}/ws_{$workspaceId}_*.sqlite");
                    $seenFileProd = [];
                    foreach ($dbs as $db) {
                        try {
                            $base = basename($db);
                            // Only load databases that belong to active KnowledgeDocuments in this workspace
                            $isActive = false;
                            foreach ($activeDocIds as $docId) {
                                if (str_contains($base, $docId)) {
                                    $isActive = true;
                                    break;
                                }
                            }
                            if (!$isActive && !empty($activeDocIds)) {
                                continue;
                            }

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
                                foreach (['unit_price_bdt', 'price_bdt', 'unit_price', 'selling_price', 'price', 'unit_cost_price', 'total_net', 'mrp', 'rate'] as $candPrice) {
                                    if (in_array($candPrice, $cols)) {
                                        $priceCol = $candPrice;
                                        break;
                                    }
                                }
                                $priceSql = $priceCol ? "\"{$priceCol}\"" : "NULL";

                                $stockCol = null;
                                foreach (['stock_qty', 'stock_quantity', 'stock', 'quantity', 'units', 'qty', 'stock_count'] as $candStock) {
                                    if (in_array($candStock, $cols)) {
                                        $stockCol = $candStock;
                                        break;
                                    }
                                }
                                $stockSql = $stockCol ? "\"{$stockCol}\"" : "NULL";

                                $specsCol = null;
                                foreach (['configuration_specs', 'specs', 'specification', 'specifications', 'config', 'hardware_specs'] as $candSpec) {
                                    if (in_array($candSpec, $cols)) {
                                        $specsCol = $candSpec;
                                        break;
                                    }
                                }
                                $specsSql = $specsCol ? "\"{$specsCol}\"" : "NULL";

                                $descCol = null;
                                foreach (['description', 'desc', 'details', 'product_description'] as $candDesc) {
                                    if (in_array($candDesc, $cols)) {
                                        $descCol = $candDesc;
                                        break;
                                    }
                                }
                                $descSql = $descCol ? "\"{$descCol}\"" : "NULL";

                                $stmt = $pdo->query("SELECT DISTINCT \"{$prodCol}\" as p_name, {$catCol} as p_cat, {$priceSql} as p_price, {$stockSql} as p_stock, {$specsSql} as p_specs, {$descSql} as p_desc FROM \"{$tbl}\" WHERE \"{$prodCol}\" IS NOT NULL AND TRIM(\"{$prodCol}\") != '' LIMIT 50");
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
                                                'price'       => $r['p_price'] !== null ? (float) $r['p_price'] : null,
                                                'stock'       => $r['p_stock'] !== null ? (int) $r['p_stock'] : null,
                                                'specs'       => $r['p_specs'] ? (string) $r['p_specs'] : null,
                                                'description' => $r['p_desc'] ? (string) $r['p_desc'] : null,
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

    /**
     * Dynamically detect if a query is inquiring about the availability of an item or category.
     * Scaleable and domain-agnostic for any business.
     */
    private function detectInquiredItemOrCategory(string $query): ?string
    {
        $q = trim($query);

        // Pattern 1: Inquiries with "ki ki" or "kono" e.g., "ki ki phone ache?", "kono sharee ache kina?"
        if (preg_match('/(?:^|[^\p{L}\p{N}])(?:ki\s*ki|কি\s*কি|kono|কোনো|কোন)\s+([\p{L}\p{N}\-_]+(?:\s+[\p{L}\p{N}\-_]+){0,2})\s*(?:ache|pawa\s*jabe|available|আছে|পাওয়া\s*যাবে|পাব)/ui', $q, $m)) {
            $term = trim($m[1]);
            if (!$this->isGenericFillerTerm($term)) {
                return $term;
            }
        }

        // Pattern 2: Availability suffixes: "<item> ache?", "<item> ache kina?", "<item> pawa jabe?", "<item> কি পাওয়া যায়?"
        if (preg_match('/(?:^|[^\p{L}\p{N}])([\p{L}\p{N}\-_]+(?:\s+[\p{L}\p{N}\-_]+){0,2})\s+(?:ache\s*kina|pawa\s*jabe|pawa\s*jay|available\s*ache|ache|আছে\s*কিনা|পাওয়া\s*যাবে|পাওয়া\s*যায়|আছে\s*কি|আছে)(?:$|[^\p{L}\p{N}])/ui', $q, $m)) {
            $term = trim($m[1]);
            if (!$this->isGenericFillerTerm($term)) {
                return $term;
            }
        }

        // Pattern 3: English availability inquiries: "do you have (any)? <item>?", "is <item> available?", "tell me about <item>"
        if (preg_match('/(?:do\s+you\s+have|is\s+there\s+any|are\s+there\s+any|tell\s+me\s+about)\s+(?:a|an|any)?\s*([\p{L}\p{N}\-_]+(?:\s+[\p{L}\p{N}\-_]+){0,2})/ui', $q, $m)) {
            $term = trim($m[1]);
            if (!$this->isGenericFillerTerm($term)) {
                return $term;
            }
        }

        return null;
    }

    /**
     * Check if an extracted token is just generic filler or stop words rather than a product or category name.
     */
    private function isGenericFillerTerm(string $term): bool
    {
        $lower = mb_strtolower(trim($term));
        $genericTerms = [
            'product', 'products', 'item', 'items', 'file', 'files', 'sheet', 'sheets',
            'store', 'shop', 'kono', 'kichu', 'shob', 'eta', 'ota', 'ei', 'oi',
            'apnader', 'amader', 'amar', 'tomar', 'kache', 'details', 'info',
            'দাম', 'প্রোডাক্ট', 'পণ্য', 'আইটেম', 'ফাইল', 'নথি', 'দোকান', 'স্টোর',
            'কিছু', 'সব', 'আমাদের', 'আপনার', 'কাছে', 'তথ্য',
        ];

        return mb_strlen($lower) < 2 || in_array($lower, $genericTerms, true);
    }

    /**
     * Extract substantive keywords and tokens dynamically from a product name and category.
     * Scalable and domain-agnostic for any business vertical (apparel, electronics, food, medicine, services, etc.)
     *
     * @return string[]
     */
    private function extractProductKeywords(string $productName, ?string $category = null): array
    {
        $keywords = [];

        // Common multi-lingual phonetic cross-references (domain-neutral transliterations)
        static $crossLingualMap = [
            'laptop'     => ['ল্যাপটপ'],
            'headphone'  => ['হেডফোন', 'ইয়ারফোন'],
            'earphone'   => ['ইয়ারফোন', 'হেডফোন'],
            'keyboard'   => ['কীবোর্ড', 'কিবোর্ড'],
            'mouse'      => ['মাউস'],
            'chair'      => ['চেয়ার', 'চেয়ার'],
            'furniture'  => ['আসবাবপত্র', 'ফার্নিচার'],
            'watch'      => ['ঘড়ি', 'ঘড়ি', 'স্মার্টওয়াচ'],
            'smartwatch' => ['স্মার্টওয়াচ', 'ঘড়ি', 'ঘড়ি'],
            'shirt'      => ['শার্ট'],
            'pant'       => ['প্যান্ট'],
            'jeans'      => ['জিন্স'],
            'shoe'       => ['জুতো', 'জুতা'],
            'shoes'      => ['জুতো', 'জুতা'],
            'bag'        => ['ব্যাগ'],
            'food'       => ['খাবার'],
            'burger'     => ['বার্গার'],
            'medicine'   => ['ওষুধ', 'ঔষধ'],
            'tablet'     => ['ট্যাবলেট'],
            'hub'        => ['হাব'],
            'phone'      => ['ফোন', 'মোবাইল'],
            'mobile'     => ['মোবাইল', 'ফোন'],
        ];

        // 1. Process category tokens if present and not 'General'
        if ($category && mb_strtolower($category) !== 'general') {
            $catLower = mb_strtolower(trim($category));
            $keywords[] = $catLower;
            $catWords = preg_split('/[\s\-_,\/]+/u', $catLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($catWords as $cw) {
                if (mb_strlen($cw) >= 3) {
                    $keywords[] = $cw;
                    if (isset($crossLingualMap[$cw])) {
                        $keywords = array_merge($keywords, $crossLingualMap[$cw]);
                    }
                }
            }
        }

        // 2. Extract substantive tokens from product name
        $nameLower = mb_strtolower(trim($productName));
        $tokens = preg_split('/[\s\-_,\/]+/u', $nameLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Generic stop/noise tokens to ignore as standalone keywords
        $noiseTokens = [
            'the', 'and', 'for', 'with', 'from', 'pro', 'max', 'plus', 'mini', 'ultra',
            'edition', 'series', 'gen', 'version', 'pack', 'set', 'combo', 'v1', 'v2', 'v3',
            'new', 'all', 'one', 'two', 'original', 'official', 'premium', 'deluxe',
            'সেরা', 'নতুন', 'ভালো', 'প্যাক', 'সেট',
        ];

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                continue;
            }
            if (mb_strlen($token) < 3 || in_array($token, $noiseTokens, true)) {
                continue;
            }

            $keywords[] = $token;

            // Add cross-lingual synonyms if available
            if (isset($crossLingualMap[$token])) {
                $keywords = array_merge($keywords, $crossLingualMap[$token]);
            }
        }

        return array_values(array_unique($keywords));
    }
}
