<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Models\AnalyticsOrder;
use App\Models\AnalyticsProduct;
use App\Models\Conversation;
use App\Models\CRMContact;
use App\Models\KnowledgeDocument;
use App\Models\StructuredRecord;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
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
        $cleanId = ltrim((string) $orderId, '#');

        $order = null;

        // 1. In 'testing' environment, check mock test fixtures first
        if (app()->environment('testing') && isset(self::$mockOrderStore[$cleanId])) {
            $order = self::$mockOrderStore[$cleanId];
        }

        // 2. Query Authoritative Live Order Database (WorkspaceOrder / AnalyticsOrder)
        if ($order === null) {
            $dbOrder = WorkspaceOrder::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($cleanId) {
                    $q->where('order_number', $cleanId)
                        ->orWhere('order_number', '#' . $cleanId)
                        ->orWhere('id', is_numeric($cleanId) ? (int) $cleanId : 0);
                })
                ->first();

            if ($dbOrder !== null) {
                $order = [
                    'order_id'           => $dbOrder->order_number ?: (string) $dbOrder->id,
                    'workspace_id'       => (int) $dbOrder->workspace_id,
                    'conversation_id'    => $dbOrder->conversation_id,
                    'external_user_id'   => $dbOrder->conversation?->external_user_id,
                    'customer_name'      => $dbOrder->customer_name,
                    'customer_phones'    => array_values(array_filter([$dbOrder->customer_phone])),
                    'status'             => $dbOrder->status ?: 'Pending',
                    'carrier'            => $dbOrder->courier_provider ?: 'Standard Courier',
                    'tracking_code'      => $dbOrder->courier_tracking_code ?: ($dbOrder->courier_consignment_id ?: 'N/A'),
                    'estimated_delivery' => $dbOrder->courier_status ?: 'Processing',
                    'total_bdt'          => (float) $dbOrder->order_amount,
                    'items'              => $dbOrder->product_summary ?: 'Order Items',
                    'payment_status'     => 'Cash on Delivery',
                ];
            } else {
                $analyticsOrder = AnalyticsOrder::where('workspace_id', $workspaceId)
                    ->where(function ($q) use ($cleanId) {
                        $q->where('order_number', $cleanId)
                            ->orWhere('order_number', '#' . $cleanId)
                            ->orWhere('id', is_numeric($cleanId) ? (int) $cleanId : 0);
                    })
                    ->with(['customer', 'items.product'])
                    ->first();

                if ($analyticsOrder !== null) {
                    $itemNames = $analyticsOrder->items->map(fn ($item) => $item->product?->name)->filter()->implode(', ');
                    $order = [
                        'order_id'           => $analyticsOrder->order_number ?: (string) $analyticsOrder->id,
                        'workspace_id'       => (int) $analyticsOrder->workspace_id,
                        'customer_id'        => (string) $analyticsOrder->customer_id,
                        'customer_name'      => $analyticsOrder->customer?->name ?? 'Customer',
                        'customer_phones'    => array_values(array_filter([$analyticsOrder->customer?->phone])),
                        'status'             => $analyticsOrder->status ?: 'Completed',
                        'carrier'            => 'Standard Delivery',
                        'tracking_code'      => 'N/A',
                        'estimated_delivery' => 'Standard',
                        'total_bdt'          => (float) $analyticsOrder->net_amount,
                        'items'              => $itemNames ?: 'Ordered Products',
                        'payment_status'     => 'Completed',
                    ];
                }
            }
        }

        if ($order === null) {
            return null;
        }

        // 3. Enforce Workspace Scoping
        if (isset($order['workspace_id']) && (int) $order['workspace_id'] !== (int) $workspaceId) {
            Log::warning('[BusinessSourceOfTruth] Cross-workspace order access attempt blocked', [
                'order_id'            => $cleanId,
                'requested_workspace' => $workspaceId,
                'order_workspace'     => $order['workspace_id'],
            ]);
            return null;
        }

        // 4. Enforce Customer Ownership Authorization (fail-closed)
        if ($conversation === null || !$this->isAuthorizedCustomerForOrder($conversation, $order)) {
            Log::warning('[BusinessSourceOfTruth] Unauthorized order access attempt blocked (missing or invalid customer ownership)', [
                'order_id'         => $cleanId,
                'conversation_id'  => $conversation?->id,
                'external_user_id' => $conversation?->external_user_id,
            ]);
            return null;
        }

        Log::info('[BusinessSourceOfTruth] Order resolved from authoritative source', [
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
        // 0. Same Conversation Check (Orders captured or discussed in this conversation)
        if (!empty($order['conversation_id']) && (int) $order['conversation_id'] === (int) $conversation->id) {
            return true;
        }

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
            // On-Demand SQLite Search: ensure large catalogs (e.g. 10k rows) have query-matching products injected
            try {
                $onDemandProds = $this->searchWorkspaceProductsOnDemand($workspaceId, $query);
                foreach ($onDemandProds as $dp) {
                    $k = mb_strtolower(trim($dp['name']));
                    if (!isset($mergedProducts[$k])) {
                        $mergedProducts[$k] = $dp;
                    }
                }
            } catch (\Throwable) {}

            $allProducts = array_values($mergedProducts);

            $qLower = mb_strtolower($query);
            $matchedAny = false;
            $matchedProduct = null;

            $matchedCatalogItems = [];

            // Extreme Price Queries (Most expensive, cheapest)
            $isMostExpensiveQuery = (bool) preg_match('/(?:সব\s*চে[^\s]*\s*দামি|সর্বোচ্চ\s*দাম|most\s*expensive|highest\s*pric)/ui', $qLower);
            $isCheapestQuery = (bool) preg_match('/(?:সব\s*চে[^\s]*\s*কম|সর্বনিম্ন\s*দাম|cheapest|lowest\s*pric)/ui', $qLower);
            if ($isMostExpensiveQuery || $isCheapestQuery) {
                $pricedProducts = array_filter($allProducts, fn ($p) => isset($p['price']) && (float) $p['price'] > 0);
                if (preg_match('/(?:clothing|পোশাক|জামা|শার্ট|sharee|panjabi)/ui', $qLower)) {
                    $pricedProducts = array_filter($pricedProducts, fn ($p) => strtolower($p['category'] ?? '') === 'clothing');
                }
                if (!empty($pricedProducts)) {
                    usort($pricedProducts, fn ($a, $b) => ((float) $b['price']) <=> ((float) $a['price']));
                    $targetProd = $isMostExpensiveQuery ? $pricedProducts[0] : end($pricedProducts);
                    $matchedCatalogItems[] = ['product' => $targetProd, 'score' => 100, 'isDirect' => true];
                }
            }
            $isPolicyOrShippingInquiry = (bool) preg_match('/(?:সারচার্জ|surcharge|আন্তর্জাতিক|international|uk\b|usa\b|uae\b|যুক্তরাজ্য|কুরিয়ার\s*খরচ|খরচ\s*কে|কে\s*দেয়|courier\s*cost)/ui', $qLower);
            if (!$isPolicyOrShippingInquiry) {
                foreach ($allProducts as $product) {
                $pNameLower = mb_strtolower($product['name']);
                $pCatLower = mb_strtolower($product['category'] ?? '');

                $isDirectMatch = str_contains($qLower, $pNameLower) || (strlen($pNameLower) > 3 && str_contains($qLower, str_replace(' ', '', $pNameLower)));

                // Also check substantive product noun/keyword and category dynamically with Bengali noun inflections
                $isKeywordMatch = false;
                $prodKeywords = $this->extractProductKeywords($product['name'], $product['category'] ?? null, $workspaceId);
                if (!$isDirectMatch) {
                    $stemPattern = '(?:গুলোর|গুলোতে|গুলো|গুলা|টির|টার|টিতে|টাতে|য়ের|ের|য়ে|তে|ে|য়|র|কে|টা|টি)?';
                    foreach ($prodKeywords as $kw) {
                        if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])' . preg_quote($kw, '/') . $stemPattern . '(?:$|[^\p{L}\p{M}\p{N}])/ui', $qLower)) {
                            $isKeywordMatch = true;
                            break;
                        }
                    }
                }

                if ($isDirectMatch || $isKeywordMatch) {
                    $matchScore = $isDirectMatch ? 10 : 5;
                    $pFullText = mb_strtolower($product['name'] . ' ' . ($product['specs'] ?? '') . ' ' . ($product['category'] ?? ''));
                    foreach (preg_split('/[\s\-_,\/]+/u', $qLower, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $tok) {
                        if (mb_strlen($tok) >= 2) {
                            if (str_contains($pFullText, $tok)) {
                                $matchScore += 2;
                            } else {
                                foreach ($prodKeywords as $pkw) {
                                    if (str_contains($tok, $pkw) || str_contains($pkw, $tok)) {
                                        $matchScore += 1;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    if (($product['category'] ?? '') === 'Variant' && !empty($product['specs'])) {
                        $specLower = mb_strtolower($product['specs']);
                        if (preg_match('/size:\s*([^,\|]+)/i', $specLower, $mSz)) {
                            $szVal = trim($mSz[1]);
                            if ($szVal !== '' && (bool) preg_match('/(?:^|[^\p{L}\p{N}])' . preg_quote($szVal, '/') . '(?:$|[^\p{L}\p{N}])/ui', $qLower)) {
                                $matchScore += 15;
                            }
                        }
                        if (preg_match('/color:\s*([^,\|]+)/i', $specLower, $mCol)) {
                            $colVal = trim($mCol[1]);
                            if ($colVal !== '' && (bool) preg_match('/(?:^|[^\p{L}\p{N}])' . preg_quote($colVal, '/') . '(?:$|[^\p{L}\p{N}])/ui', $qLower)) {
                                $matchScore += 15;
                            }
                        }
                    }
                    $matchedCatalogItems[] = ['product' => $product, 'score' => $matchScore, 'isDirect' => $isDirectMatch];
                }
                }
            }

            if (!empty($matchedCatalogItems)) {
                usort($matchedCatalogItems, function ($a, $b) {
                    if ($a['score'] !== $b['score']) {
                        return $b['score'] <=> $a['score'];
                    }
                    return ($b['isDirect'] ? 1 : 0) <=> ($a['isDirect'] ? 1 : 0);
                });

                foreach ($matchedCatalogItems as $item) {
                    $product = $item['product'];
                    $formattedPrice = isset($product['price']) && (float) $product['price'] > 0
                        ? '৳' . number_format((float) $product['price'], 2)
                        : 'Price on inquiry';
                    if (isset($product['stock']) && $product['stock'] !== null) {
                        $stockText = (int) $product['stock'] <= 0
                            ? "  * Stock/Qty: 0 units (OUT OF STOCK / স্টক আউট)\n"
                            : "  * Stock/Qty: {$product['stock']} units\n";
                    } else {
                        $stockText = '';
                    }
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
                    if ($matchedProduct === null) {
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

            $inquiredItem = $this->detectInquiredItemOrCategory($query);
            $isSameProductAsActive = $activeProduct && $inquiredItem !== null && (mb_stripos($activeProduct['name'], $inquiredItem) !== false || mb_stripos($inquiredItem, $activeProduct['name']) !== false);

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
            } elseif (!$matchedAny && $activeProduct && !empty($activeProduct['name']) && ($inquiredItem === null || $isSameProductAsActive)) {
                $isPolicyOrAfterSalesQuery = (bool) preg_match('/(?:ফেরত|রিটার্ন|রিফান্ড|return|refund|exchange|বদল|ক্যানসেল|বাতিল|নষ্ট|ড্যামেজ|damage|ক্ষতিগ্রস্ত|ত্রুটি|ট্যাগ|tag|policy|পলিসি|নিয়ম|রিস্টকিং|restocking)/ui', $query);
                if (!$isPolicyOrAfterSalesQuery && preg_match('/(দাম\s*কত|কত\s*দাম|dam\s*koto|price\s*koto|how\s*much|স্টকে\s*আছে|available\s*ache|stock\s*ache|stock|স্টক|koyta|কয়টা|কয়টা|details|বিস্তারিত|বিবরণ|জানাও|বলো|বলুন|bolo|bolen|tell\s*me|specification|specs?|configuration|features?|বৈশিষ্ট্য|তথ্য|info|fabric|material|ফেব্রিক|মেটেরিয়াল|উপাদান|কাপড়|warranty|guarantee|ওয়ারেন্টি|ওয়ারেন্টি|গ্যারান্টি|origin|অরিজিন|কোথায়\s*তৈরি|তৈরি\s*কোথায়)/ui', $query)) {
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
            $isGeneralProductInquiry = (bool) preg_match('/(product|প্রোডাক্ট|পণ্য|আইটেম|item|list|তালিকা|কালেকশন|collection|kache\s*ki\s*ki\s*ache|ki\s*ki\s*ache|ki\s*ache|what\s*(do\s*you\s*have|items|products)|sale|sell|সেল|বিক্রি|sale\s*koren|বিক্রি\s*করেন|ki\s*ki\s*pawa\s*jay|কি\s*কি\s*পাওয়া\s*যায়|upload|file|sheet|excel|kono\s*file|কোন\s*ফাইল|ফাইলে|ফাইল\s*এ)/ui', $query);

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
            $inquiredItem = !$matchedAny ? ($inquiredItem ?? $this->detectInquiredItemOrCategory($query)) : null;
            if ($inquiredItem !== null) {
                // If the inquired item is present in the workspace's published FAQs, do not mark as unavailable
                $hasFaqMatch = \App\Models\FAQ::where('workspace_id', $workspaceId)
                    ->where('is_published', true)
                    ->where(function ($q) use ($inquiredItem) {
                        $q->where('question', 'LIKE', '%' . $inquiredItem . '%')
                          ->orWhere('answer', 'LIKE', '%' . $inquiredItem . '%');
                    })->exists();

                if ($hasFaqMatch) {
                    $inquiredItem = null;
                }
            }
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
                    ->get(['id', 'name', 'category', 'unit_price', 'sku', 'stock_quantity']);
                foreach ($prods as $p) {
                    $catalogProds[] = [
                        'id'          => $p->id,
                        'name'        => $p->name,
                        'category'    => $p->category ?? 'General',
                        'price'       => (float) $p->unit_price,
                        'stock'       => $p->stock_quantity !== null ? (int) $p->stock_quantity : null,
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
                    ->get(['id', 'document_id', 'canonical_entity_name', 'canonical_category', 'canonical_amount', 'canonical_quantity', 'canonical_status', 'mapped_attributes', 'semantic_projections']);
                $seenSr = [];
                foreach ($srRecords as $sr) {
                    $name = trim((string) $sr->canonical_entity_name);
                    if ($name === '' || isset($seenSr[mb_strtolower($name)])) {
                        continue;
                    }
                    $seenSr[mb_strtolower($name)] = true;

                    $price = $sr->canonical_amount !== null && (float) $sr->canonical_amount > 0 ? (float) $sr->canonical_amount : null;
                    $stock = $sr->canonical_quantity !== null ? (int) $sr->canonical_quantity : null;
                    if ($sr->canonical_status === 'out_of_stock') {
                        $stock = 0;
                    }

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
                        'id'          => $sr->id,
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

                $dbs = [];

                // 1. Shared excel_databases directory (strictly scoped to ws_{$workspaceId}_*.sqlite)
                $sharedDir = realpath(base_path('../python-ai-service/storage/excel_databases'));
                if ($sharedDir && is_dir($sharedDir)) {
                    $dbs = array_merge($dbs, glob("{$sharedDir}/ws_{$workspaceId}_*.sqlite") ?: []);
                }

                // 2. Workspace-isolated local storage directories
                $localDirs = [
                    storage_path("app/analytics/{$workspaceId}"),
                    storage_path("app/spreadsheets/{$workspaceId}"),
                ];
                foreach ($localDirs as $lDir) {
                    if (is_dir($lDir)) {
                        $dbs = array_merge($dbs, glob("{$lDir}/*.sqlite") ?: []);
                    }
                }
                $dbs = array_unique($dbs);

                if (!empty($dbs)) {
                    $seenFileProd = [];
                    foreach ($dbs as $db) {
                        try {
                            $base = basename($db);
                            // Databases located in the workspace-isolated directory belong to this workspace
                            $isLocalWorkspaceDir = str_contains($db, "/app/analytics/{$workspaceId}/") || str_contains($db, "/app/spreadsheets/{$workspaceId}/");
                            if (!$isLocalWorkspaceDir && !empty($activeDocIds)) {
                                $isActive = false;
                                foreach ($activeDocIds as $docId) {
                                    if (str_contains($base, (string) $docId)) {
                                        $isActive = true;
                                        break;
                                    }
                                }
                                if (!$isActive) {
                                    continue;
                                }
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
                                $tblLower = mb_strtolower((string) $tbl);
                                // Relational tables holding orders, sales, salespersons, or customers are never sellable product catalogs
                                if (in_array($tblLower, ['sales', 'salespersons', 'customers', 'orders', 'transactions', 'users'], true)) {
                                    continue;
                                }

                                $colsStmt = $pdo->query("PRAGMA table_info(\"{$tbl}\")");
                                $cols = $colsStmt ? array_map(fn ($c) => mb_strtolower((string) $c), array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'name')) : [];

                                $prodCol = null;
                                $prodCandidates = [
                                    'product_name', 'product', 'item_name', 'item', 'product_name_description', 'title',
                                    'পণ্যের_নাম', 'পণ্য', 'আইটেম', 'আইটেমের_নাম', 'প্রোডাক্ট', 'প্রোডাক্টের_নাম', 'কোর্স', 'কোর্সের_নাম', 'সেবা', 'সার্ভিস',
                                    'product_title', 'name', 'model', 'package_name', 'service_name', 'course_name',
                                ];
                                foreach ($prodCandidates as $cand) {
                                    if (in_array($cand, $cols, true)) {
                                        $prodCol = $cand;
                                        break;
                                    }
                                }
                                if (!$prodCol) {
                                    foreach ($cols as $colName) {
                                        if (preg_match('/(?:customer|client|buyer|salesperson|employee|seller|user|agent|admin|ক্রেতা|গ্রাহক|বিক্রেতা|কর্মী)/ui', $colName)) {
                                            continue;
                                        }
                                        if (str_contains($colName, 'name') || str_contains($colName, 'title') || str_contains($colName, 'item') || str_contains($colName, 'নাম') || str_contains($colName, 'পণ্য')) {
                                            $prodCol = $colName;
                                            break;
                                        }
                                    }
                                }
                                if (!$prodCol) continue;

                                $catCol = 'NULL';
                                foreach (['category', 'product_category', 'item_category', 'cat', 'type', 'ক্যাটাগরি', 'ধরন', 'বিভাগ', 'শ্রেণী'] as $candCat) {
                                    if (in_array($candCat, $cols, true)) {
                                        $catCol = "\"{$candCat}\"";
                                        break;
                                    }
                                }

                                $priceCol = null;
                                $priceCandidates = [
                                    'unit_price_bdt', 'price_bdt', 'unit_price', 'selling_price', 'price', 'unit_cost_price', 'total_net', 'mrp', 'rate',
                                    'মূল্য', 'দাম', 'টাকা', 'দর', 'রেট', 'ফি', 'খরচ', 'ফি_টাকা',
                                ];
                                foreach ($priceCandidates as $candPrice) {
                                    if (in_array($candPrice, $cols, true)) {
                                        $priceCol = $candPrice;
                                        break;
                                    }
                                }
                                $priceSql = $priceCol ? "\"{$priceCol}\"" : "NULL";

                                $stockCol = null;
                                $stockCandidates = [
                                    'stock_qty', 'stock_quantity', 'stock', 'quantity', 'units', 'qty', 'stock_count', 'available_stock',
                                    'স্টক', 'পরিমাণ', 'মজুদ',
                                ];
                                foreach ($stockCandidates as $candStock) {
                                    if (in_array($candStock, $cols, true)) {
                                        $stockCol = $candStock;
                                        break;
                                    }
                                }
                                $stockSql = $stockCol ? "\"{$stockCol}\"" : "NULL";

                                $specsCol = null;
                                foreach (['configuration_specs', 'specs', 'specification', 'specifications', 'config', 'hardware_specs'] as $candSpec) {
                                    if (in_array($candSpec, $cols, true)) {
                                        $specsCol = $candSpec;
                                        break;
                                    }
                                }

                                $descCol = null;
                                foreach (['description', 'desc', 'details', 'product_description', 'বিবরণ', 'বর্ণনা', 'বিস্তারিত'] as $candDesc) {
                                    if (in_array($candDesc, $cols, true)) {
                                        $descCol = $candDesc;
                                        break;
                                    }
                                }
                                $descSql = $descCol ? "\"{$descCol}\"" : "NULL";

                                // Discover all remaining non-standard custom columns (Fabric, Warranty, Origin, Brand, etc.)
                                $handledCols = array_filter([$prodCol, $priceCol, $stockCol, $descCol, $specsCol]);
                                if ($catCol !== 'NULL') {
                                    $handledCols[] = trim($catCol, '"');
                                }
                                $ignoreCols = ['id', 'created_at', 'updated_at', 'deleted_at', 'row_id', '_row', 'workspace_id', 'sheet', 'file', 'source_id'];

                                $extraCols = [];
                                foreach ($cols as $colName) {
                                    if (!in_array($colName, $handledCols, true) && !in_array($colName, $ignoreCols, true)) {
                                        $extraCols[] = $colName;
                                    }
                                }

                                if (!empty($extraCols)) {
                                    $extraParts = implode(" || ' | ' || ", array_map(fn ($c) => "'{$c}: ' || COALESCE(\"{$c}\", '')", $extraCols));
                                    $specsSql = $specsCol
                                        ? "TRIM(COALESCE(\"{$specsCol}\", '') || ' | ' || {$extraParts})"
                                        : "TRIM({$extraParts})";
                                } elseif ($specsCol) {
                                    $specsSql = "\"{$specsCol}\"";
                                } else {
                                    $specsSql = "NULL";
                                }

                                $stmt = $pdo->query("SELECT DISTINCT \"{$prodCol}\" as p_name, {$catCol} as p_cat, {$priceSql} as p_price, {$stockSql} as p_stock, {$specsSql} as p_specs, {$descSql} as p_desc FROM \"{$tbl}\" WHERE \"{$prodCol}\" IS NOT NULL AND TRIM(\"{$prodCol}\") != '' LIMIT 2000");
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

                            // Check for cross-sheet variant joins if multiple tables exist in the spreadsheet
                            if (count($tables) >= 2) {
                                $parentTable = null;
                                $parentIdCol = null;
                                $parentNameCol = null;

                                foreach ($tables as $tName) {
                                    $colsStmt = $pdo->query("PRAGMA table_info(\"{$tName}\")");
                                    $tCols = $colsStmt ? array_map(fn ($c) => mb_strtolower((string) $c), array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'name')) : [];
                                    $idCands = ['id', 'product_id', 'item_id', 'sku', 'আইডি', 'কোড'];
                                    $nameCands = ['product_name', 'name', 'item_name', 'title', 'পণ্যের_নাম', 'পণ্য', 'নাম'];
                                    $fId = null;
                                    $fName = null;
                                    foreach ($idCands as $c) { if (in_array($c, $tCols, true)) { $fId = $c; break; } }
                                    foreach ($nameCands as $c) { if (in_array($c, $tCols, true)) { $fName = $c; break; } }
                                    if ($fId && $fName) {
                                        $parentTable = $tName;
                                        $parentIdCol = $fId;
                                        $parentNameCol = $fName;
                                        break;
                                    }
                                }

                                if ($parentTable) {
                                    foreach ($tables as $childTable) {
                                        if ($childTable === $parentTable) continue;
                                        $colsStmt = $pdo->query("PRAGMA table_info(\"{$childTable}\")");
                                        $cCols = $colsStmt ? array_map(fn ($c) => mb_strtolower((string) $c), array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'name')) : [];

                                        $fkCol = null;
                                        foreach (['product_id', 'item_id', 'id', 'sku', 'আইডি', 'কোড'] as $c) {
                                            if (in_array($c, $cCols, true)) { $fkCol = $c; break; }
                                        }

                                        $vCols = [];
                                        foreach (['size', 'color', 'variant', 'stock', 'stock_qty', 'price', 'unit_price', 'সাইজ', 'রং', 'কালার'] as $c) {
                                            if (in_array($c, $cCols, true)) {
                                                $vCols[] = $c;
                                            }
                                        }

                                        if ($fkCol && !empty($vCols)) {
                                            $vParts = implode(" || ', ' || ", array_map(fn ($c) => "'{$c}: ' || COALESCE(C.\"{$c}\", '')", $vCols));
                                            $joinSql = "SELECT P.\"{$parentNameCol}\" as p_name, {$vParts} as p_specs FROM \"{$parentTable}\" P JOIN \"{$childTable}\" C ON P.\"{$parentIdCol}\" = C.\"{$fkCol}\" LIMIT 2000";
                                            $jStmt = $pdo->query($joinSql);
                                            if ($jStmt) {
                                                while ($jr = $jStmt->fetch(PDO::FETCH_ASSOC)) {
                                                    $jNm = trim((string) $jr['p_name']);
                                                    $jSpecs = trim((string) $jr['p_specs']);
                                                    if ($jNm === '' || $jSpecs === '') continue;
                                                    $fileProducts[] = [
                                                        'name'        => "{$jNm} ({$jSpecs})",
                                                        'category'    => 'Variant',
                                                        'price'       => null,
                                                        'stock'       => null,
                                                        'specs'       => $jSpecs,
                                                        'description' => null,
                                                        'source'      => "Uploaded File: {$inferredFile} ({$parentTable} + {$childTable})",
                                                        'source_file' => $inferredFile,
                                                        'source_type' => 'uploaded_file',
                                                    ];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable) {}
                    }

                    // Cross-File Virtual Database Query Federation (GAP-XLS-04)
                    if (count($dbs) >= 2) {
                        $this->federateCrossFileDatabases($dbs, $docMap, $seenFileProd, $fileProducts);
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

        if (preg_match('/(?:showroom|শোরুম|ধানমন্ডি|outlet|swap|exchange|এক্সচেঞ্জ|বদল|size|সাইজ|rocket|nagad|bkash|নগদ|বিকাশ|রকেট|charge|চার্জ|refund|রিফান্ড|return|ফেরত|delivery|ডেলিভারি|shipping|শিপিং|policy|পলিসি|ওয়ারেন্টি|warranty|সারচার্জ|surcharge|কয়টা\s*বাকি|koyta\s*baki|stock\s*e)/ui', $q)) {
            return null;
        }

        // Pattern 1: Inquiries with "ki ki" or "kono" e.g., "ki ki phone ache?", "kono sharee ache kina?"
        if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])(?:ki\s*ki|কি\s*কি|kono|কোনো|কোন)\s+([\p{L}\p{M}\p{N}\-_]+(?:\s+[\p{L}\p{M}\p{N}\-_]+){0,3})\s*(?:ache|pawa\s*jabe|available|আছে|পাওয়া\s*যাবে|পাব)/ui', $q, $m)) {
            $term = trim($m[1]);
            if (!$this->isGenericFillerTerm($term)) {
                return $term;
            }
        }

        // Pattern 2: Availability suffixes: "<item> ache?", "<item> ache kina?", "<item> pawa jabe?", "<item> কি পাওয়া যায়?"
        if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])([\p{L}\p{M}\p{N}\-_]+(?:\s+[\p{L}\p{M}\p{N}\-_]+){0,3})\s+(?:ache\s*kina|pawa\s*jabe|pawa\s*jay|available\s*ache|ache|আছে\s*কিনা|পাওয়া\s*যাবে|পাওয়া\s*যায়|আছে\s*কি|আছে)(?:$|[^\p{L}\p{M}\p{N}])/ui', $q, $m)) {
            $term = trim($m[1]);
            if (!$this->isGenericFillerTerm($term)) {
                return $term;
            }
        }

        // Pattern 3: English availability inquiries: "do you have (any)? <item>?", "do you sell <item>?", "is <item> available?", "tell me about <item>"
        if (preg_match('/(?:do\s+you\s+(?:have|sell)|is\s+there\s+any|are\s+there\s+any|tell\s+me\s+about)\s+(?:a|an|any)?\s*([\p{L}\p{M}\p{N}\-_]+(?:\s+[\p{L}\p{M}\p{N}\-_]+){0,3})/ui', $q, $m)) {
            $term = trim($m[1]);
            if (!$this->isGenericFillerTerm($term)) {
                return $term;
            }
        }

        // Pattern 4: Purchase desire: "ami ekta <item> kinte cai/chai", "want to buy <item>", "looking for <item>", "একটি <আইটেম> কিনতে চাই"
        if (preg_match('/(?:ami\s+(?:ekta\s+|akta\s+)?|i\s+(?:want\s+to\s+buy|am\s+looking\s+for|need)\s+(?:a|an)?\s*|আমি\s+(?:একটি\s+|একটা\s+)?)([\p{L}\p{M}\p{N}\-_]+(?:\s+[\p{L}\p{M}\p{N}\-_]+){0,3})\s*(?:kinte\s*cai|kinte\s*chai|kinbo|kinte\s*cassi|kinte\s*chassi|কিনতে\s*চাই|কিনবো|কিনব)?(?:$|[^\p{L}\p{M}\p{N}])/ui', $q, $m)) {
            $term = trim($m[1]);
            if (!$this->isGenericFillerTerm($term)) {
                return $term;
            }
        }

        // Pattern 5: Price inquiry for specific item: "<item> er (dam|price) koto?", "<item> কত দাম?", "<item> এর দাম কত?"
        if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])([\p{L}\p{M}\p{N}\-_]+(?:\s+[\p{L}\p{M}\p{N}\-_]+){0,4})\s*(?:er|এর)?\s*(?:dam|daam|price|দাম|মূল্য)\s*(?:koto|কত|how\s*much)(?:$|[^\p{L}\p{M}\p{N}])/ui', $q, $m) ||
            preg_match('/(?:^|[^\p{L}\p{M}\p{N}])([\p{L}\p{M}\p{N}\-_]+(?:\s+[\p{L}\p{M}\p{N}\-_]+){0,4})\s+(?:কত|koto)\s+(?:দাম|dam|price)(?:$|[^\p{L}\p{M}\p{N}])/ui', $q, $m) ||
            preg_match('/(?:how\s+much\s+(?:is|for))\s+(?:the\s+)?([\p{L}\p{M}\p{N}\-_]+(?:\s+[\p{L}\p{M}\p{N}\-_]+){0,4})/ui', $q, $m)) {
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
        if (mb_strlen($lower) < 2) {
            return true;
        }

        $policyServiceSubstrings = [
            'ওয়ারেন্টি', 'ওয়ারেন্টি', 'warranty', 'guarantee', 'গ্যারান্টি',
            'রিফান্ড', 'refund', 'রিটার্ন', 'return', 'ferot',
            'এক্সচেঞ্জ', 'exchange', 'রিপ্লেসমেন্ট', 'replacement',
            'ডেলিভারি', 'ডেলিভারী', 'delivery', 'শিপিং', 'shipping', 'কুরিয়ার', 'courier',
            'চার্জ', 'charge', 'ফি', 'fee', 'খরচ', 'cost',
            'ক্যাশ অন ডেলিভারি', 'cod', 'পেমেন্ট', 'payment', 'বিকাশ', 'bkash', 'নগদ', 'nagad',
            'ডিসকাউন্ট', 'discount', 'অফার', 'offer', 'ছাড়', 'ভাউচার', 'কুপন',
            'ইনভয়েস', 'invoice', 'রসিদ', 'receipt', 'সার্ভিস', 'service',
            'পানি', 'পানিতে', 'water', 'আগুন', 'fire', 'ড্যামেজ', 'damage', 'ক্ষতিগ্রস্ত', 'ত্রুটি', 'defective',
            'সাহায্য', 'help', 'সাপোর্ট', 'support', 'কাস্টমার কেয়ার', 'helpline', 'হেল্পলাইন',
            'শোরুম', 'showroom', 'আউটলেট', 'outlet', 'ঠিকানা', 'address', 'সময়', 'time', 'hours', 'খোলা', 'বন্ধ',
            'swap', 'বদল', 'check', 'chance', 'size', 'সাইজ', 'rocket', 'রকেট', 'baki', 'বাকি', 'koyta', 'কয়টা', 'কয়টা', 'stock', 'স্টক',
        ];
        foreach ($policyServiceSubstrings as $pss) {
            if (str_contains($lower, $pss)) {
                return true;
            }
        }

        $genericTerms = [
            'product', 'products', 'item', 'items', 'file', 'files', 'sheet', 'sheets',
            'store', 'shop', 'kono', 'kichu', 'shob', 'eta', 'ota', 'ei', 'oi',
            'apnader', 'amader', 'amar', 'tomar', 'kache', 'details', 'info',
            'delivery charge', 'shipping charge', 'courier charge', 'delivery fee', 'shipping fee',
            'delivery', 'shipping', 'courier', 'charge', 'fee',
            'দাম', 'প্রোডাক্ট', 'পণ্য', 'আইটেম', 'ফাইল', 'নথি', 'দোকান', 'স্টোর',
            'কিছু', 'সব', 'আমাদের', 'আপনার', 'কাছে', 'তথ্য',
            'ডেলিভারি চার্জ', 'কুরিয়ার চার্জ', 'ডেলিভারি', 'ডেলিভারী', 'চার্জ', 'কুরিয়ার', 'খরচ', 'ফি',
        ];

        return in_array($lower, $genericTerms, true);
    }

    /**
     * Retrieve merchant-configured custom synonyms from workspace settings.
     *
     * @return array<string, string[]>
     */
    protected function getMerchantCustomSynonyms(int $workspaceId): array
    {
        if ($workspaceId <= 0) {
            return [];
        }

        return Cache::remember("ws_{$workspaceId}_custom_synonyms", 300, function () use ($workspaceId) {
            $ws = Workspace::find($workspaceId);
            $raw = $ws?->settings['custom_synonyms'] ?? [];
            if (!is_array($raw)) {
                return [];
            }

            $normalized = [];
            foreach ($raw as $key => $values) {
                $cleanKey = mb_strtolower(trim((string) $key));
                if ($cleanKey === '') {
                    continue;
                }
                if (is_string($values)) {
                    $values = array_filter(array_map('trim', explode(',', $values)));
                }
                if (is_array($values)) {
                    $normalized[$cleanKey] = array_values(array_unique(array_filter(array_map(fn ($v) => mb_strtolower(trim((string) $v)), $values))));
                }
            }

            return $normalized;
        });
    }

    /**
     * Extract substantive keywords and tokens dynamically from a product name and category.
     * Scalable and domain-agnostic for any business vertical (apparel, electronics, food, medicine, services, etc.)
     *
     * @return string[]
     */
    private function extractProductKeywords(string $productName, ?string $category = null, int $workspaceId = 1): array
    {
        $keywords = [];

        // Common multi-lingual phonetic cross-references (domain-neutral transliterations)
        static $crossLingualMap = [
            'laptop'     => ['ল্যাপটপ'],
            'headphone'  => ['হেডফোন', 'ইয়ারফোন'],
            'headphones' => ['হেডফোন', 'ইয়ারফোন'],
            'earphone'   => ['ইয়ারফোন', 'হেডফোন'],
            'earphones'  => ['ইয়ারফোন', 'হেডফোন'],
            'keyboard'   => ['কীবোর্ড', 'কিবোর্ড'],
            'keyboards'  => ['কীবোর্ড', 'কিবোর্ড'],
            'mouse'      => ['মাউস'],
            'chair'      => ['চেয়ার', 'চেয়ার'],
            'furniture'  => ['আসবাবপত্র', 'ফার্নিচার'],
            'watch'      => ['ঘড়ি', 'ঘড়ি', 'স্মার্টওয়াচ'],
            'smartwatch' => ['স্মার্টওয়াচ', 'ঘড়ি', 'ঘড়ি'],
            'shirt'      => ['শার্ট'],
            'shirts'     => ['শার্ট'],
            'panjabi'    => ['পাঞ্জাবি', 'পাঞ্জাবী'],
            'panjabis'   => ['পাঞ্জাবি', 'পাঞ্জাবী'],
            'sharee'     => ['শাড়ি', 'শাড়ী'],
            'sharees'    => ['শাড়ি', 'শাড়ী'],
            'saree'      => ['শাড়ি', 'শাড়ী'],
            'saris'      => ['শাড়ি', 'শাড়ী'],
            'pant'       => ['প্যান্ট'],
            'pants'      => ['প্যান্ট'],
            'jeans'      => ['জিন্স'],
            'shoe'       => ['জুতো', 'জুতা'],
            'shoes'      => ['জুতো', 'জুতা'],
            'bag'        => ['ব্যাগ'],
            'bags'       => ['ব্যাগ'],
            'food'       => ['খাবার'],
            'burger'     => ['বার্গার'],
            'medicine'   => ['ওষুধ', 'ঔষধ'],
            'tablet'     => ['ট্যাবলেট'],
            'tablets'    => ['ট্যাবলেট'],
            'hub'        => ['হাব'],
            'phone'      => ['ফোন', 'মোবাইল'],
            'phones'     => ['ফোন', 'মোবাইল'],
            'mobile'     => ['মোবাইল', 'ফোন'],
        ];

        $customMap = $this->getMerchantCustomSynonyms($workspaceId);
        $effectiveCrossMap = !empty($customMap) ? array_merge($crossLingualMap, $customMap) : $crossLingualMap;

        // 1. Process category tokens if present and not 'General'
        if ($category && mb_strtolower($category) !== 'general') {
            $catLower = mb_strtolower(trim($category));
            $keywords[] = $catLower;
            $catWords = preg_split('/[\s\-_,\/]+/u', $catLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($catWords as $cw) {
                if (mb_strlen($cw) >= 3) {
                    $keywords[] = $cw;
                    if (isset($effectiveCrossMap[$cw])) {
                        $keywords = array_merge($keywords, $effectiveCrossMap[$cw]);
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
            if (isset($effectiveCrossMap[$token])) {
                $keywords = array_merge($keywords, $effectiveCrossMap[$token]);
            }
        }

        // Bi-directional and phrase-level synonym expansion against full product name
        if (!empty($effectiveCrossMap)) {
            foreach ($effectiveCrossMap as $k => $syns) {
                if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])' . preg_quote((string) $k, '/') . '(?:$|[^\p{L}\p{M}\p{N}])/ui', $nameLower)) {
                    $keywords[] = (string) $k;
                    $keywords = array_merge($keywords, (array) $syns);
                }
                foreach ((array) $syns as $syn) {
                    if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])' . preg_quote((string) $syn, '/') . '(?:$|[^\p{L}\p{M}\p{N}])/ui', $nameLower)) {
                        $keywords[] = (string) $k;
                        $keywords = array_merge($keywords, (array) $syns);
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Cross-File Virtual Database Query Federation (GAP-XLS-04).
     * Connects multiple separate SQLite databases within the same workspace using SQLite ATTACH DATABASE.
     * Discovers entity foreign-key relationships across independent database files (e.g. products.sqlite + inventory.sqlite)
     * and joins them in read-only mode to enrich catalog items and extract variant/stock relationships.
     *
     * @param string[] $dbs
     * @param array<string, string> $docMap
     * @param array<string, bool> $seenFileProd
     * @param array<int, array<string, mixed>> $fileProducts
     */
    private function federateCrossFileDatabases(
        array $dbs,
        array $docMap,
        array &$seenFileProd,
        array &$fileProducts
    ): void {
        if (count($dbs) < 2) {
            return;
        }

        $schemas = [];
        foreach ($dbs as $db) {
            try {
                $p = new PDO("sqlite:" . $db);
                $tablesStmt = $p->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
                $tableCols = [];
                foreach ($tables as $t) {
                    $cStmt = $p->query("PRAGMA table_info(\"{$t}\")");
                    $cols = $cStmt ? array_map(fn ($c) => mb_strtolower((string) $c), array_column($cStmt->fetchAll(PDO::FETCH_ASSOC), 'name')) : [];
                    $tableCols[$t] = $cols;
                }
                $schemas[$db] = $tableCols;
            } catch (\Throwable) {}
        }

        $idPatterns = ['id', 'product_id', 'item_id', 'sku', 'code', 'আইডি', 'কোড'];
        $namePatterns = [
            'product_name', 'product', 'item_name', 'item', 'title', 'name', 'model',
            'পণ্যের_নাম', 'পণ্য', 'আইটেম', 'আইটেমের_নাম', 'প্রোডাক্ট', 'প্রোডাক্টের_নাম', 'কোর্স', 'সেবা',
        ];
        $stockCandidates = ['stock_qty', 'stock_quantity', 'stock', 'quantity', 'units', 'qty', 'স্টক', 'পরিমাণ', 'মজুদ'];
        $priceCandidates = ['unit_price_bdt', 'price_bdt', 'unit_price', 'selling_price', 'price', 'মূল্য', 'দাম', 'টাকা', 'দর'];
        $variantCandidates = ['size', 'color', 'variant', 'warehouse', 'সাইজ', 'রং', 'কালার'];

        // Iterate through all pairs of distinct databases in the workspace
        $dbList = array_values($dbs);
        for ($i = 0; $i < count($dbList); $i++) {
            $dbA = $dbList[$i];
            if (!isset($schemas[$dbA])) continue;

            for ($j = 0; $j < count($dbList); $j++) {
                if ($i === $j) continue;
                $dbB = $dbList[$j];
                if (!isset($schemas[$dbB])) continue;

                // Look for parent table in dbA with ID and Name
                foreach ($schemas[$dbA] as $tblA => $colsA) {
                    $idColA = null;
                    $nameColA = null;
                    $priceColA = null;
                    $stockColA = null;
                    foreach ($idPatterns as $c) { if (in_array($c, $colsA, true)) { $idColA = $c; break; } }
                    foreach ($namePatterns as $c) { if (in_array($c, $colsA, true)) { $nameColA = $c; break; } }
                    foreach ($priceCandidates as $c) { if (in_array($c, $colsA, true)) { $priceColA = $c; break; } }
                    foreach ($stockCandidates as $c) { if (in_array($c, $colsA, true)) { $stockColA = $c; break; } }

                    if (!$idColA || !$nameColA) continue;

                    // Look for child/extension table in dbB with FK matching idColA
                    foreach ($schemas[$dbB] as $tblB => $colsB) {
                        $fkColB = null;
                        foreach ($idPatterns as $c) { if (in_array($c, $colsB, true)) { $fkColB = $c; break; } }
                        if (!$fkColB) continue;

                        // Check if dbB table has stock, price, or variant columns
                        $stockColB = null;
                        foreach ($stockCandidates as $c) { if (in_array($c, $colsB, true)) { $stockColB = $c; break; } }

                        $priceColB = null;
                        foreach ($priceCandidates as $c) { if (in_array($c, $colsB, true)) { $priceColB = $c; break; } }

                        $vColsB = [];
                        foreach ($variantCandidates as $c) { if (in_array($c, $colsB, true)) { $vColsB[] = $c; } }

                        if (!$stockColB && !$priceColB && empty($vColsB)) continue;

                        try {
                            $pdoA = new PDO("sqlite:" . $dbA);
                            $pdoA->exec("ATTACH DATABASE " . $pdoA->quote($dbB) . " AS ext_b");

                            $stockSql = $stockColB ? "B.\"{$stockColB}\"" : ($stockColA ? "A.\"{$stockColA}\"" : "NULL");
                            $priceSql = $priceColB ? "B.\"{$priceColB}\"" : ($priceColA ? "A.\"{$priceColA}\"" : "NULL");

                            $vParts = !empty($vColsB)
                                ? implode(" || ', ' || ", array_map(fn ($c) => "'{$c}: ' || COALESCE(B.\"{$c}\", '')", $vColsB))
                                : "NULL";

                            $joinSql = "SELECT DISTINCT A.\"{$nameColA}\" as p_name, {$priceSql} as p_price, {$stockSql} as p_stock, {$vParts} as p_specs "
                                . "FROM \"{$tblA}\" A JOIN ext_b.\"{$tblB}\" B ON A.\"{$idColA}\" = B.\"{$fkColB}\" "
                                . "WHERE A.\"{$nameColA}\" IS NOT NULL AND TRIM(A.\"{$nameColA}\") != '' LIMIT 2000";

                            $jStmt = $pdoA->query($joinSql);
                            if ($jStmt) {
                                $fileA = basename($dbA);
                                foreach ($docMap as $uuid => $fn) { if (str_contains($fileA, $uuid)) { $fileA = $fn; break; } }
                                $fileB = basename($dbB);
                                foreach ($docMap as $uuid => $fn) { if (str_contains($fileB, $uuid)) { $fileB = $fn; break; } }

                                while ($row = $jStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $pName = trim((string) $row['p_name']);
                                    if ($pName === '') continue;

                                    $pStock = $row['p_stock'] !== null ? (int) $row['p_stock'] : null;
                                    $pPrice = $row['p_price'] !== null ? (float) $row['p_price'] : null;
                                    $pSpecs = !empty($row['p_specs']) && $row['p_specs'] !== 'NULL' ? (string) $row['p_specs'] : null;

                                    // 1. Enrich existing product record if stock or price was previously missing
                                    $k = mb_strtolower($pName);
                                    $enriched = false;
                                    foreach ($fileProducts as &$existingProd) {
                                        if (mb_strtolower($existingProd['name']) === $k) {
                                            if ($existingProd['stock'] === null && $pStock !== null) {
                                                $existingProd['stock'] = $pStock;
                                                $enriched = true;
                                            }
                                            if ($existingProd['price'] === null && $pPrice !== null) {
                                                $existingProd['price'] = $pPrice;
                                                $enriched = true;
                                            }
                                            if ($pSpecs !== null) {
                                                $existingProd['specs'] = trim(($existingProd['specs'] ?? '') . ' | ' . $pSpecs, ' |');
                                                $enriched = true;
                                            }
                                            if ($enriched) {
                                                $existingProd['source'] = "Cross-File Federated: {$existingProd['source_file']} + {$fileB}";
                                            }
                                            break;
                                        }
                                    }
                                    unset($existingProd);

                                    // 2. If it is a variant with specs, add as distinct variant entry
                                    if ($pSpecs !== null) {
                                        $variantName = "{$pName} ({$pSpecs})";
                                        $vk = mb_strtolower($variantName);
                                        if (!isset($seenFileProd[$vk])) {
                                            $seenFileProd[$vk] = true;
                                            $fileProducts[] = [
                                                'name'        => $variantName,
                                                'category'    => 'Variant',
                                                'price'       => $pPrice,
                                                'stock'       => $pStock,
                                                'specs'       => $pSpecs,
                                                'description' => null,
                                                'source'      => "Cross-File Federated: {$fileA} ({$tblA}) + {$fileB} ({$tblB})",
                                                'source_file' => "{$fileA} + {$fileB}",
                                                'source_type' => 'uploaded_file',
                                            ];
                                        }
                                    }
                                }
                            }

                            $pdoA->exec("DETACH DATABASE ext_b");
                        } catch (\Throwable $ex) {
                            Log::debug('[BusinessSourceOfTruth] Cross-file federation query exception: ' . $ex->getMessage());
                        }
                    }
                }
            }
        }
    }

    /**
     * Dynamically search workspace product catalogs on-demand directly in SQLite files (GAP-XLS-01 / Row Limit Fix).
     * Bypasses in-memory cache limits (e.g. 2000+ items) to find specific products, brands, or attributes directly via SQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchWorkspaceProductsOnDemand(int $workspaceId, string $query, int $limit = 25): array
    {
        $cleanQuery = trim($query);
        if ($cleanQuery === '' || mb_strlen($cleanQuery) < 2) {
            return [];
        }

        $rawTokens = array_values(array_filter(preg_split('/\s+/u', mb_strtolower($cleanQuery)), fn ($t) => mb_strlen($t) >= 2));
        if (empty($rawTokens)) {
            return [];
        }

        $suffixes = ['গুলোতে', 'গুলোর', 'টিতে', 'টাতে', 'টার', 'টির', 'গুলো', 'গুলা', 'য়ের', 'ের', 'য়ে', 'তে', 'ে', 'য়', 'র', 'কে', 'টা', 'টি'];
        $crossMap = [
            'মাউস' => ['mouse'],
            'মাউসের' => ['mouse'],
            'মাউসটির' => ['mouse'],
            'কীবোর্ড' => ['keyboard'],
            'কিবোর্ড' => ['keyboard'],
            'কীবোর্ডের' => ['keyboard'],
            'মেকানিক্যাল' => ['mechanical'],
            'গেমিং' => ['gaming'],
            'হেডফোন' => ['headphone', 'earphone'],
            'হেডফোনের' => ['headphone', 'earphone'],
            'হেডফোনটি' => ['headphone', 'earphone'],
            'পাঞ্জাবি' => ['panjabi', 'punjabi'],
            'পাঞ্জাবির' => ['panjabi'],
            'পাঞ্জাবী' => ['panjabi'],
            'শাড়ি' => ['sharee', 'saree'],
            'শাড়ী' => ['sharee', 'saree'],
            'শাড়ির' => ['sharee', 'saree'],
            'জামদানি' => ['jamdani'],
            'লিনেন' => ['linen'],
            'সিল্ক' => ['silk'],
            'কটন' => ['cotton'],
            'শার্ট' => ['shirt'],
            'ল্যাপটপ' => ['laptop'],
        ];

        $customMap = $this->getMerchantCustomSynonyms($workspaceId);
        if (!empty($customMap)) {
            $cleanLower = mb_strtolower($cleanQuery);
            foreach ($customMap as $k => $syns) {
                if (isset($crossMap[$k])) {
                    $crossMap[$k] = array_values(array_unique(array_merge($crossMap[$k], $syns)));
                } else {
                    $crossMap[$k] = $syns;
                }

                // Multi-word phrase containment and bi-directional expansion
                if (str_contains($cleanLower, (string) $k)) {
                    foreach ($syns as $syn) {
                        $rawTokens = array_merge($rawTokens, preg_split('/[\s\-_,\/]+/u', (string) $syn, -1, PREG_SPLIT_NO_EMPTY) ?: []);
                    }
                }
                foreach ($syns as $syn) {
                    if (str_contains($cleanLower, (string) $syn)) {
                        $rawTokens = array_merge($rawTokens, preg_split('/[\s\-_,\/]+/u', (string) $k, -1, PREG_SPLIT_NO_EMPTY) ?: []);
                    }
                }
            }
        }

        $tokens = $rawTokens;
        foreach ($rawTokens as $tok) {
            foreach ($suffixes as $suf) {
                if (str_ends_with($tok, $suf)) {
                    $stem = mb_substr($tok, 0, mb_strlen($tok) - mb_strlen($suf));
                    if (mb_strlen($stem) >= 3) {
                        $tokens[] = $stem;
                    }
                }
            }
            if (isset($crossMap[$tok])) {
                $tokens = array_merge($tokens, $crossMap[$tok]);
            }
        }
        $tokens = array_values(array_unique($tokens));

        $dbs = [];
        $sharedDir = realpath(base_path('../python-ai-service/storage/excel_databases'));
        if ($sharedDir && is_dir($sharedDir)) {
            $dbs = array_merge($dbs, glob("{$sharedDir}/ws_{$workspaceId}_*.sqlite") ?: []);
        }
        $localDirs = [
            storage_path("app/analytics/{$workspaceId}"),
            storage_path("app/spreadsheets/{$workspaceId}"),
        ];
        foreach ($localDirs as $lDir) {
            if (is_dir($lDir)) {
                $dbs = array_merge($dbs, glob("{$lDir}/*.sqlite") ?: []);
            }
        }
        $dbs = array_unique($dbs);
        if (empty($dbs)) {
            return [];
        }

        $results = [];
        $seen = [];

        foreach ($dbs as $db) {
            try {
                $pdo = new PDO("sqlite:" . $db);
                $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];

                foreach ($tables as $tbl) {
                    $tblLower = mb_strtolower((string) $tbl);
                    if (in_array($tblLower, ['sales', 'salespersons', 'customers', 'orders', 'transactions', 'users', 'analytics_orders', 'analytics_salespersons'], true)) {
                        continue;
                    }
                    if (preg_match('/(?:salesperson|customer|employee|user|transaction|invoice|order)/i', $tblLower)) {
                        continue;
                    }

                    $colsStmt = $pdo->query("PRAGMA table_info(\"{$tbl}\")");
                    $cols = $colsStmt ? array_map(fn ($c) => mb_strtolower((string) $c), array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'name')) : [];

                    $hasPersonalEntityCol = false;
                    foreach (['customer_name', 'salesperson_id', 'customer_id', 'employee_code', 'phone', 'email', 'salary'] as $pc) {
                        if (in_array($pc, $cols, true)) {
                            $hasPersonalEntityCol = true;
                            break;
                        }
                    }
                    if ($hasPersonalEntityCol) {
                        continue;
                    }

                    $prodCol = null;
                    foreach (['product_name', 'product', 'item_name', 'item', 'title', 'name', 'model', 'পণ্যের_নাম', 'পণ্য', 'আইটেম'] as $c) {
                        if (in_array($c, $cols, true)) { $prodCol = $c; break; }
                    }
                    if (!$prodCol) continue;

                    $whereClauses = [];
                    $params = [];
                    foreach ($tokens as $idx => $token) {
                        $pName = ":tok_{$idx}";
                        $whereClauses[] = "\"{$prodCol}\" LIKE {$pName}";
                        $params[$pName] = "%{$token}%";
                    }

                    $sql = "SELECT * FROM \"{$tbl}\" WHERE (" . implode(" OR ", $whereClauses) . ") LIMIT " . (int) $limit;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $pName = trim((string) ($r[$prodCol] ?? ''));
                        $k = mb_strtolower($pName);
                        if ($pName === '' || isset($seen[$k])) continue;
                        $seen[$k] = true;

                        // Find price
                        $price = null;
                        foreach (['unit_price_bdt', 'price_bdt', 'unit_price', 'price', 'মূল্য', 'দাম', 'টাকা'] as $pc) {
                            if (isset($r[$pc]) && is_numeric($r[$pc])) { $price = (float) $r[$pc]; break; }
                        }

                        // Find stock
                        $stock = null;
                        foreach (['stock_qty', 'stock_quantity', 'stock', 'quantity', 'qty', 'স্টক', 'পরিমাণ'] as $sc) {
                            if (isset($r[$sc]) && is_numeric($r[$sc])) { $stock = (int) $r[$sc]; break; }
                        }

                        // Find category
                        $category = 'General';
                        foreach (['category', 'cat', 'type', 'ক্যাটাগরি', 'ধরন'] as $cc) {
                            if (!empty($r[$cc])) { $category = (string) $r[$cc]; break; }
                        }

                        // All remaining columns into specs
                        $extraSpecs = [];
                        foreach ($r as $colName => $colVal) {
                            $cLower = mb_strtolower($colName);
                            if (in_array($cLower, [$prodCol, 'id', 'created_at', 'updated_at', 'deleted_at'], true)) continue;
                            if ($colVal !== null && trim((string) $colVal) !== '') {
                                $extraSpecs[] = "{$colName}: {$colVal}";
                            }
                        }

                        $results[] = [
                            'name'        => $pName,
                            'category'    => $category,
                            'price'       => $price,
                            'stock'       => $stock,
                            'specs'       => !empty($extraSpecs) ? implode(' | ', $extraSpecs) : null,
                            'description' => null,
                            'source'      => "On-Demand SQLite Search: " . basename($db) . " ({$tbl})",
                            'source_file' => basename($db),
                            'source_type' => 'uploaded_file',
                        ];
                    }
                }
            } catch (\Throwable) {}
        }

        return $results;
    }
}
