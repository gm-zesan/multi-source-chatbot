<?php

declare(strict_types=1);

namespace App\Services\Courier\Drivers;

use App\Models\WorkspaceOrder;
use App\Services\Courier\Contracts\CourierDriverInterface;
use App\Services\Courier\DTO\CourierBookingResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteadfastCourierDriver implements CourierDriverInterface
{
    protected string $baseUrl = 'https://portal.packzy.com/api/v1';

    public function getProviderSlug(): string
    {
        return 'steadfast';
    }

    public function getProviderTitle(): string
    {
        return 'Steadfast Courier';
    }

    public function sendOrder(WorkspaceOrder $order, array $credentials): CourierBookingResult
    {
        $apiKey    = trim((string) ($credentials['api_key'] ?? ''));
        $secretKey = trim((string) ($credentials['secret_key'] ?? ''));

        if (empty($apiKey) || empty($secretKey)) {
            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: 'Steadfast API Key or Secret Key is not configured in workspace settings.'
            );
        }

        $recipientPhone = $order->getFormatted11DigitPhone();
        if (empty($recipientPhone)) {
            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: 'Valid recipient phone number is required to book Steadfast consignment.'
            );
        }

        $payload = [
            'invoice'           => $order->order_number,
            'recipient_name'    => $order->customer_name ?: 'Valued Customer',
            'recipient_phone'   => $recipientPhone,
            'recipient_address' => $order->delivery_address ?: 'Dhaka, Bangladesh',
            'cod_amount'        => (float) $order->order_amount,
            'note'              => $order->customer_notes ?: $order->admin_notes ?: 'Delivery handover',
            'item_description'  => $order->product_summary ?: 'E-commerce Merchandise',
            'total_lot'         => 1,
            'delivery_type'     => 0, // 0 = Home Delivery
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Api-Key'      => $apiKey,
                    'Secret-Key'   => $secretKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post("{$this->baseUrl}/create_order", $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && (int) $data['status'] === 200) {
                $consignment   = $data['consignment'] ?? [];
                $consignmentId = (string) ($consignment['consignment_id'] ?? $consignment['id'] ?? '');
                $trackingCode  = (string) ($consignment['tracking_code'] ?? $consignmentId);
                $status        = (string) ($consignment['status'] ?? 'in_review');

                return CourierBookingResult::success(
                    provider: $this->getProviderSlug(),
                    consignmentId: $consignmentId,
                    trackingCode: $trackingCode,
                    status: $status,
                    rawResponse: $data
                );
            }

            $errorMessage = $data['message'] ?? $data['errors'] ?? 'Failed to book consignment on Steadfast.';
            if (is_array($errorMessage)) {
                $errorMessage = implode(', ', array_map(fn($v) => is_array($v) ? implode(' ', $v) : $v, $errorMessage));
            }

            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: (string) $errorMessage,
                rawResponse: $data ?? []
            );
        } catch (\Throwable $e) {
            Log::error('Steadfast courier dispatch exception', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: 'Connection to Steadfast server timed out or failed: ' . $e->getMessage()
            );
        }
    }

    public function ping(array $credentials): array
    {
        $apiKey    = trim((string) ($credentials['api_key'] ?? ''));
        $secretKey = trim((string) ($credentials['secret_key'] ?? ''));

        if (empty($apiKey) || empty($secretKey)) {
            return [
                'success' => false,
                'message' => 'Please provide both API Key and Secret Key.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Api-Key'      => $apiKey,
                    'Secret-Key'   => $secretKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->get("{$this->baseUrl}/get_balance");

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && (int) $data['status'] === 200) {
                return [
                    'success'       => true,
                    'message'       => 'Successfully authenticated with Steadfast Courier! Current Balance: ৳ ' . number_format((float) ($data['current_balance'] ?? 0), 2),
                    'balance'       => (float) ($data['current_balance'] ?? 0),
                    'provider_name' => $this->getProviderTitle(),
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Invalid API Key or Secret Key for Steadfast.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Network error connecting to Steadfast: ' . $e->getMessage(),
            ];
        }
    }
}
