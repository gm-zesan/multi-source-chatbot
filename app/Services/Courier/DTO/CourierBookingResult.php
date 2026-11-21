<?php

declare(strict_types=1);

namespace App\Services\Courier\DTO;

class CourierBookingResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $provider,
        public readonly ?string $consignmentId = null,
        public readonly ?string $trackingCode = null,
        public readonly ?string $status = null,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = [],
    ) {}

    public static function success(
        string $provider,
        string $consignmentId,
        ?string $trackingCode = null,
        string $status = 'in_review',
        array $rawResponse = []
    ): self {
        return new self(
            success: true,
            provider: $provider,
            consignmentId: $consignmentId,
            trackingCode: $trackingCode ?: $consignmentId,
            status: $status,
            errorMessage: null,
            rawResponse: $rawResponse
        );
    }

    public static function failure(
        string $provider,
        string $errorMessage,
        array $rawResponse = []
    ): self {
        return new self(
            success: false,
            provider: $provider,
            consignmentId: null,
            trackingCode: null,
            status: 'failed',
            errorMessage: $errorMessage,
            rawResponse: $rawResponse
        );
    }

    public function toArray(): array
    {
        return [
            'success'        => $this->success,
            'provider'       => $this->provider,
            'consignment_id' => $this->consignmentId,
            'tracking_code'  => $this->trackingCode,
            'status'         => $this->status,
            'error_message'  => $this->errorMessage,
            'raw_response'   => $this->rawResponse,
        ];
    }
}
