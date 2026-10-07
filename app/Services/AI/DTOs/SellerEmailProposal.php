<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

use InvalidArgumentException;

/**
 * Immutable DTO representing a validated proposal for sending an email to a seller.
 * This represents intent/proposal state, NOT execution.
 */
class SellerEmailProposal
{
    public function __construct(
        public readonly int $sellerId,
        public readonly string $recipientEmail,
        public readonly string $subject,
        public readonly string $message,
        public readonly ?string $sellerName = null,
        public readonly ?string $fingerprint = null,
        public readonly string $status = 'awaiting_confirmation',
    ) {
        $this->validate();
    }

    /**
     * Validate proposal constraints.
     */
    private function validate(): void
    {
        if ($this->sellerId <= 0) {
            throw new InvalidArgumentException('sellerId must be a positive integer');
        }

        if (trim($this->recipientEmail) === '' || !filter_var($this->recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('recipientEmail must be a valid email address');
        }

        if (trim($this->subject) === '') {
            throw new InvalidArgumentException('subject cannot be empty');
        }

        if (trim($this->message) === '') {
            throw new InvalidArgumentException('message cannot be empty');
        }
    }

    /**
     * Compute a deterministic SHA-256 fingerprint for canonical action authorization.
     */
    public function generateFingerprint(int $workspaceId): string
    {
        $payload = implode('|', [
            (string) $workspaceId,
            (string) $this->sellerId,
            trim(strtolower($this->recipientEmail)),
            trim($this->subject),
            trim($this->message),
        ]);

        return hash('sha256', $payload);
    }

    /**
     * Create an instance with a computed fingerprint.
     */
    public function withFingerprint(int $workspaceId): self
    {
        return new self(
            sellerId: $this->sellerId,
            recipientEmail: $this->recipientEmail,
            subject: $this->subject,
            message: $this->message,
            sellerName: $this->sellerName,
            fingerprint: $this->generateFingerprint($workspaceId),
            status: $this->status,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'seller_id'       => $this->sellerId,
            'recipient_email' => $this->recipientEmail,
            'subject'         => $this->subject,
            'message'         => $this->message,
            'seller_name'     => $this->sellerName,
            'fingerprint'     => $this->fingerprint,
            'status'          => $this->status,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sellerId: (int) ($data['seller_id'] ?? 0),
            recipientEmail: (string) ($data['recipient_email'] ?? ''),
            subject: (string) ($data['subject'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            sellerName: isset($data['seller_name']) ? (string) $data['seller_name'] : null,
            fingerprint: isset($data['fingerprint']) ? (string) $data['fingerprint'] : null,
            status: (string) ($data['status'] ?? 'awaiting_confirmation'),
        );
    }
}
