<?php

declare(strict_types=1);

namespace App\Services\Courier\Drivers;

use App\Models\WorkspaceOrder;
use App\Services\Courier\Contracts\CourierDriverInterface;
use App\Services\Courier\DTO\CourierBookingResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PathaoCourierDriver implements CourierDriverInterface
{
    protected string $baseUrl = 'https://api-hermes.pathao.com';

    public function getProviderSlug(): string
    {
        return 'pathao';
    }

    public function getProviderTitle(): string
    {
        return 'Pathao Courier';
    }

    public function sendOrder(WorkspaceOrder $order, array $credentials): CourierBookingResult
    {
        $token = $this->getAccessToken($credentials);
        if (! $token) {
            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: 'Failed to authenticate with Pathao API. Please verify Client ID, Secret, Username, and Password.'
            );
        }

        $storeId = (int) ($credentials['store_id'] ?? 0);
        if ($storeId <= 0) {
            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: 'Pathao Store ID is missing or invalid in workspace settings.'
            );
        }

        $payload = [
            'store_id'            => $storeId,
            'merchant_order_id'   => $order->order_number,
            'recipient_name'      => $order->customer_name ?: 'Valued Customer',
            'recipient_phone'     => $order->getFormatted11DigitPhone(),
            'recipient_address'   => $order->delivery_address ?: 'Dhaka, Bangladesh',
            'delivery_type'       => 48, // 48 Hours standard delivery
            'item_type'           => 2,  // 2 = Parcel
            'special_instruction' => $order->customer_notes ?: $order->admin_notes ?: 'Please call before delivery',
            'item_quantity'       => 1,
            'item_weight'         => '0.5',
            'item_description'    => $order->product_summary ?: 'General Merchandise',
            'amount_to_collect'   => (float) $order->order_amount,
        ];

        try {
            $response = Http::timeout(15)
                ->withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post("{$this->baseUrl}/aladdin/api/v1/orders", $payload);

            $data = $response->json();

            if ($response->successful() && (isset($data['type']) && $data['type'] === 'success' || isset($data['data']['consignment_id']))) {
                $orderData     = $data['data'] ?? [];
                $consignmentId = (string) ($orderData['consignment_id'] ?? '');
                $status        = (string) ($orderData['order_status'] ?? 'pending');

                return CourierBookingResult::success(
                    provider: $this->getProviderSlug(),
                    consignmentId: $consignmentId,
                    trackingCode: $consignmentId,
                    status: $status,
                    rawResponse: $data
                );
            }

            $errorMessage = $data['message'] ?? 'Failed to book consignment on Pathao.';
            if (isset($data['errors']) && is_array($data['errors'])) {
                $errList = [];
                foreach ($data['errors'] as $fld => $errs) {
                    $errList[] = is_array($errs) ? implode(' ', $errs) : (string) $errs;
                }
                $errorMessage .= ': ' . implode('; ', $errList);
            }

            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: (string) $errorMessage,
                rawResponse: $data ?? []
            );
        } catch (\Throwable $e) {
            Log::error('Pathao courier dispatch exception', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return CourierBookingResult::failure(
                provider: $this->getProviderSlug(),
                errorMessage: 'Pathao API request failed: ' . $e->getMessage()
            );
        }
    }

    public function ping(array $credentials): array
    {
        $token = $this->getAccessToken($credentials);
        if (! $token) {
            return [
                'success' => false,
                'message' => 'Failed to obtain access token from Pathao. Check Client ID, Client Secret, Username and Password.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->get("{$this->baseUrl}/aladdin/api/v1/stores");

            if ($response->successful()) {
                $data = $response->json();
                $storeCount = count($data['data']['data'] ?? []);
                return [
                    'success'       => true,
                    'message'       => "Successfully connected to Pathao Courier! ({$storeCount} pickup stores found)",
                    'provider_name' => $this->getProviderTitle(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Pathao token verified but failed to retrieve store list: ' . ($response->json('message') ?? 'Unknown error'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Network error connecting to Pathao: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve or cache Pathao OAuth access token.
     */
    protected function getAccessToken(array $credentials): ?string
    {
        $clientId     = trim((string) ($credentials['client_id'] ?? ''));
        $clientSecret = trim((string) ($credentials['client_secret'] ?? ''));
        $username     = trim((string) ($credentials['username'] ?? ''));
        $password     = trim((string) ($credentials['password'] ?? ''));

        if (empty($clientId) || empty($clientSecret) || empty($username) || empty($password)) {
            return null;
        }

        $cacheKey = 'pathao_token_' . md5($clientId . $username);
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            return (string) $cachedToken;
        }

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/aladdin/api/v1/issue-token", [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'username'      => $username,
                'password'      => $password,
                'grant_type'    => 'password',
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $token = $tokenData['access_token'] ?? null;
                $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

                if ($token) {
                    Cache::put($cacheKey, $token, max(60, $expiresIn - 300));
                    return (string) $token;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Pathao issue-token failed: ' . $e->getMessage());
        }

        return null;
    }
}
