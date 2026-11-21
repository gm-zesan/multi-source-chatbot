<?php

declare(strict_types=1);

namespace App\Services\Courier;

use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Courier\Contracts\CourierDriverInterface;
use App\Services\Courier\Drivers\PathaoCourierDriver;
use App\Services\Courier\Drivers\RedXCourierDriver;
use App\Services\Courier\Drivers\SteadfastCourierDriver;
use App\Services\Courier\DTO\CourierBookingResult;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CourierManager
{
    /** @var array<string, CourierDriverInterface> */
    protected array $drivers = [];

    public function __construct()
    {
        $this->registerDriver(new SteadfastCourierDriver());
        $this->registerDriver(new PathaoCourierDriver());
        $this->registerDriver(new RedXCourierDriver());
    }

    public function registerDriver(CourierDriverInterface $driver): void
    {
        $this->drivers[$driver->getProviderSlug()] = $driver;
    }

    public function getDriver(string $provider): CourierDriverInterface
    {
        $provider = strtolower(trim($provider));
        if (! isset($this->drivers[$provider])) {
            throw new InvalidArgumentException("Unsupported courier provider [{$provider}]. Supported: " . implode(', ', array_keys($this->drivers)));
        }

        return $this->drivers[$provider];
    }

    /**
     * Get list of all available courier providers with metadata.
     */
    public function getAvailableProviders(): array
    {
        return [
            'steadfast' => [
                'name'        => 'Steadfast Courier',
                'description' => 'Fast COD dispatch with direct tracking and low COD rates across Bangladesh.',
                'icon'        => 'ri-truck-line',
                'color'       => 'emerald',
            ],
            'pathao' => [
                'name'        => 'Pathao Courier',
                'description' => 'Reliable 48-hour delivery with automatic district & zone mapping.',
                'icon'        => 'ri-e-bike-2-line',
                'color'       => 'rose',
            ],
            'redx' => [
                'name'        => 'RedX Logistics',
                'description' => 'Nationwide coverage with integrated warehouse pickup and doorstep collection.',
                'icon'        => 'ri-map-pin-range-line',
                'color'       => 'red',
            ],
        ];
    }

    /**
     * Get stored courier settings for a workspace.
     */
    public function getCourierSettings(Workspace $workspace): array
    {
        $settings = $workspace->settings ?? [];
        $courier = $settings['courier'] ?? [];

        return [
            'default_provider' => $courier['default_provider'] ?? 'steadfast',
            'steadfast'        => [
                'api_key'    => $courier['steadfast']['api_key'] ?? '',
                'secret_key' => $courier['steadfast']['secret_key'] ?? '',
                'is_active'  => (bool) ($courier['steadfast']['is_active'] ?? false),
            ],
            'pathao'           => [
                'client_id'     => $courier['pathao']['client_id'] ?? '',
                'client_secret' => $courier['pathao']['client_secret'] ?? '',
                'username'      => $courier['pathao']['username'] ?? '',
                'password'      => $courier['pathao']['password'] ?? '',
                'store_id'      => $courier['pathao']['store_id'] ?? '',
                'is_active'     => (bool) ($courier['pathao']['is_active'] ?? false),
            ],
            'redx'             => [
                'token'           => $courier['redx']['token'] ?? '',
                'pickup_store_id' => $courier['redx']['pickup_store_id'] ?? '',
                'is_active'       => (bool) ($courier['redx']['is_active'] ?? false),
            ],
        ];
    }

    /**
     * Save courier settings for a workspace.
     */
    public function saveCourierSettings(Workspace $workspace, array $data): bool
    {
        $settings = $workspace->settings ?? [];
        $existing = $settings['courier'] ?? [];

        $courier = [
            'default_provider' => $data['default_provider'] ?? ($existing['default_provider'] ?? 'steadfast'),
            'steadfast'        => [
                'api_key'    => trim((string) ($data['steadfast']['api_key'] ?? $existing['steadfast']['api_key'] ?? '')),
                'secret_key' => trim((string) ($data['steadfast']['secret_key'] ?? $existing['steadfast']['secret_key'] ?? '')),
                'is_active'  => isset($data['steadfast']['is_active']) ? (bool) $data['steadfast']['is_active'] : (!empty($data['steadfast']['api_key'])),
            ],
            'pathao'           => [
                'client_id'     => trim((string) ($data['pathao']['client_id'] ?? $existing['pathao']['client_id'] ?? '')),
                'client_secret' => trim((string) ($data['pathao']['client_secret'] ?? $existing['pathao']['client_secret'] ?? '')),
                'username'      => trim((string) ($data['pathao']['username'] ?? $existing['pathao']['username'] ?? '')),
                'password'      => trim((string) ($data['pathao']['password'] ?? $existing['pathao']['password'] ?? '')),
                'store_id'      => trim((string) ($data['pathao']['store_id'] ?? $existing['pathao']['store_id'] ?? '')),
                'is_active'     => isset($data['pathao']['is_active']) ? (bool) $data['pathao']['is_active'] : (!empty($data['pathao']['client_id'])),
            ],
            'redx'             => [
                'token'           => trim((string) ($data['redx']['token'] ?? $existing['redx']['token'] ?? '')),
                'pickup_store_id' => trim((string) ($data['redx']['pickup_store_id'] ?? $existing['redx']['pickup_store_id'] ?? '')),
                'is_active'       => isset($data['redx']['is_active']) ? (bool) $data['redx']['is_active'] : (!empty($data['redx']['token'])),
            ],
        ];

        $settings['courier'] = $courier;
        $workspace->settings = $settings;

        return $workspace->save();
    }

    /**
     * Dispatch a single order to courier.
     */
    public function dispatchOrder(WorkspaceOrder $order, ?string $provider = null): CourierBookingResult
    {
        $workspace = $order->workspace;
        if (! $workspace) {
            return CourierBookingResult::failure('none', 'Order has no associated workspace.');
        }

        $settings = $this->getCourierSettings($workspace);
        $provider = strtolower(trim($provider ?: $settings['default_provider'] ?: 'steadfast'));

        $driver = $this->getDriver($provider);
        $credentials = $settings[$provider] ?? [];

        $result = $driver->sendOrder($order, $credentials);

        if ($result->success) {
            $order->update([
                'courier_provider'       => $provider,
                'courier_consignment_id' => $result->consignmentId,
                'courier_tracking_code'  => $result->trackingCode,
                'courier_status'         => $result->status ?: 'in_review',
                'courier_response'      => $result->rawResponse,
                'courier_dispatched_at' => now(),
                'status'                 => $order->status === 'delivered' ? 'delivered' : 'shipped',
            ]);
        }

        return $result;
    }

    /**
     * Bulk dispatch multiple orders to courier.
     *
     * @param Collection<int, WorkspaceOrder>|array<int, WorkspaceOrder> $orders
     */
    public function bulkDispatch(Collection|array $orders, ?string $provider = null): array
    {
        $total = count($orders);
        $successful = 0;
        $failed = 0;
        $results = [];

        foreach ($orders as $order) {
            // If already booked, skip to prevent double bookings
            if ($order->isDispatchedToCourier()) {
                $results[] = [
                    'order_id'      => $order->id,
                    'order_number'  => $order->order_number,
                    'success'       => true,
                    'already_sent'  => true,
                    'tracking_code' => $order->courier_tracking_code ?: $order->courier_consignment_id,
                    'message'       => "Already dispatched ({$order->courier_provider}: {$order->courier_tracking_code}).",
                ];
                $successful++;
                continue;
            }

            $booking = $this->dispatchOrder($order, $provider);

            if ($booking->success) {
                $successful++;
                $results[] = [
                    'order_id'      => $order->id,
                    'order_number'  => $order->order_number,
                    'success'       => true,
                    'already_sent'  => false,
                    'tracking_code' => $booking->trackingCode,
                    'message'       => "Successfully booked with {$booking->provider} (ID: {$booking->trackingCode}).",
                ];
            } else {
                $failed++;
                $results[] = [
                    'order_id'      => $order->id,
                    'order_number'  => $order->order_number,
                    'success'       => false,
                    'already_sent'  => false,
                    'tracking_code' => null,
                    'message'       => $booking->errorMessage ?: 'Booking failed.',
                ];
            }
        }

        return [
            'total'       => $total,
            'successful'  => $successful,
            'failed'      => $failed,
            'results'     => $results,
        ];
    }
}
