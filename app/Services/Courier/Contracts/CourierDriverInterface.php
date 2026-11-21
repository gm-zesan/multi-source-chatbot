<?php

declare(strict_types=1);

namespace App\Services\Courier\Contracts;

use App\Models\WorkspaceOrder;
use App\Services\Courier\DTO\CourierBookingResult;

interface CourierDriverInterface
{
    /**
     * Get unique slug for provider (e.g. steadfast, pathao, redx).
     */
    public function getProviderSlug(): string;

    /**
     * Get human-readable title.
     */
    public function getProviderTitle(): string;

    /**
     * Book/send a parcel order to the courier service.
     */
    public function sendOrder(WorkspaceOrder $order, array $credentials): CourierBookingResult;

    /**
     * Test credentials / Ping API.
     */
    public function ping(array $credentials): array;
}
