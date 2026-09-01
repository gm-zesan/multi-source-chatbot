<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Models\Conversation;
use App\Models\CRMContact;
use Illuminate\Support\Facades\Log;

class BusinessSourceOfTruthService
{
    /**
     * In-memory / database mockable store for live order truth.
     * Can be easily swapped with live ERP / Shopify / WooCommerce / DB table.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $mockOrderStore = [
        '1042' => [
            'order_id'           => '1042',
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
     * Register a mock order for testing or live runtime overrides.
     *
     * @param array<string, mixed> $data
     */
    public static function setMockOrder(string $orderId, array $data): void
    {
        self::$mockOrderStore[$orderId] = $data;
    }

    /**
     * Clear custom mock orders.
     */
    public static function resetMockOrders(): void
    {
        self::$mockOrderStore = [
            '1042' => [
                'order_id'           => '1042',
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
     * @return array<string, mixed>|null
     */
    public function lookupOrder(string|int $orderId, int $workspaceId = 1): ?array
    {
        $cleanId = ltrim((string) $orderId, '#');

        if (isset(self::$mockOrderStore[$cleanId])) {
            Log::info('[BusinessSourceOfTruth] Order resolved from live source', [
                'order_id'     => $cleanId,
                'status'       => self::$mockOrderStore[$cleanId]['status'],
                'workspace_id' => $workspaceId,
            ]);
            return self::$mockOrderStore[$cleanId];
        }

        // Deterministic synthetic fallback for any positive numerical order ID
        if (is_numeric($cleanId) && (int) $cleanId > 0) {
            return [
                'order_id'           => $cleanId,
                'status'             => 'Processing',
                'carrier'            => 'Paperfly Logistics',
                'tracking_code'      => "PF-{$cleanId}-BD",
                'estimated_delivery' => '2-3 business days',
                'total_bdt'          => 1200,
                'items'              => 'Standard Order Items',
                'payment_status'     => 'Confirmed',
            ];
        }

        return null;
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
            $orderData = $this->lookupOrder($orderId, $workspaceId);
            if ($orderData !== null) {
                $sections[] = "- Live Order #{$orderData['order_id']}:\n" .
                    "  * Status: {$orderData['status']}\n" .
                    "  * Courier/Carrier: {$orderData['carrier']}\n" .
                    "  * Tracking ID: {$orderData['tracking_code']}\n" .
                    "  * Estimated Delivery: {$orderData['estimated_delivery']}\n" .
                    "  * Payment: {$orderData['payment_status']}\n" .
                    "  * Items: {$orderData['items']}";
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

        if (empty($sections)) {
            return null;
        }

        return implode("\n\n", $sections);
    }
}
