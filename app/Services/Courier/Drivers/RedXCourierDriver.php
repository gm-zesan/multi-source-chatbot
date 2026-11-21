<?php

declare(strict_types=1);

namespace App\Services\Courier\Drivers;

use App\Models\WorkspaceOrder;
use App\Services\Courier\Contracts\CourierDriverInterface;
use App\Services\Courier\DTO\CourierBookingResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RedXCourierDriver implements CourierDriverInterface
{
    protected string $baseUrl = 'https://openapi.redx.com.bd/v1.0.0-beta';

    public function getProviderSlug(): string
    {
        return 'redx';
    }

    public function getProviderTitle(): string
    {
        return 'RedX Logistics';
    }

    public function sendOrder(WorkspaceOrder $order, array $credentials): CourierBookingResult
    {
        $token = trim((string) ($credentials['token'] ?? ''));
        $pickupStoreId = (int) ($credentials['pickup_store_id'] ?? 0);

        if (empty($token)) {
            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: 'RedX API Access Token is not configured in workspace settings.'
            );
        }

        if ($pickupStoreId <= 0) {
            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: 'RedX Pickup Store ID is missing or invalid in workspace settings.'
            );
        }

        $payload = [
            'customer_name'          => $order->customer_name ?: 'Valued Customer',
            'customer_phone'         => $order->getFormatted11DigitPhone(),
            'customer_address'       => $order->delivery_address ?: 'Dhaka, Bangladesh',
            'merchant_invoice_id'    => $order->order_number,
            'cash_collection_amount' => (float) $order->order_amount,
            'parcel_weight'          => 500, // In grams
            'pickup_store_id'        => $pickupStoreId,
            'value'                  => max(100.0, (float) $order->order_amount),
            'instruction'            => $order->customer_notes ?: $order->admin_notes ?: 'Call before delivery',
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'API-ACCESS-TOKEN' => 'Bearer ' . $token,
                    'Content-Type'     => 'application/json',
                    'Accept'           => 'application/json',
                ])
                ->post("{$this->baseUrl}/parcel", $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['tracking_id'])) {
                $trackingId = (string) $data['tracking_id'];

                return CourierBookingResult::success(
                    provider: $this->getProviderSlug(),
                    consignmentId: $trackingId,
                    trackingCode: $trackingId,
                    status: 'pending',
                    rawResponse: $data
                );
            }

            $errorMessage = $data['message'] ?? $data['error'] ?? 'Failed to create parcel on RedX.';
            if (is_array($errorMessage)) {
                $errorMessage = json_encode($errorMessage);
            }

            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: (string) $errorMessage,
                rawResponse: $data ?? []
            );
        } catch (\Throwable $e) {
            Log::error('RedX courier dispatch exception', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: 'RedX API request failed: ' . $e->getMessage()
            );
        }
    }

    public function ping(array $credentials): array
    {
        $token = trim((string) ($credentials['token'] ?? ''));
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Please provide the RedX API Access Token.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'API-ACCESS-TOKEN' => 'Bearer ' . $token,
                    'Accept'           => 'application/json',
                ])
                ->get("{$this->baseUrl}/store");

            $data = $response->json();

            if ($response->successful()) {
                $stores = $data['stores'] ?? [];
                $storeCount = count($stores);
                return [
                    'success'       => true,
                    'message'       => "Successfully authenticated with RedX! ({$storeCount} pickup stores found)",
                    'provider_name' => $this->getProviderTitle(),
                    'stores'        => $stores,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Invalid RedX API token or unauthorized.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Network error connecting to RedX: ' . $e->getMessage(),
            ];
        }
    }
}
