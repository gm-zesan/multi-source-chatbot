<?php

declare(strict_types=1);

namespace App\Services\Analytics\DTOs\Contract3;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Stage B Authorization Grant: Cryptographically signed single-use grant
 * issued by Laravel after AST authorization, column sensitivity verification,
 * and confirmed relationship edge verification.
 */
class ExecutionAuthorizationGrant
{
    /**
     * @param string $grantId Unique UUID for this authorization grant
     * @param int $workspaceId Authenticated workspace context
     * @param string $queryFingerprint SHA-256 fingerprint of the authorized parameterized SQL and params
     * @param string $generationId Dataset generation UUID
     * @param string $authorizedAt ISO8601 timestamp
     * @param string $expiresAt ISO8601 timestamp (typically authorizedAt + 60s)
     * @param string $nonce Single-use random hex token to prevent replay attacks
     * @param string $signature HMAC-SHA256 signature
     */
    public function __construct(
        public readonly string $grantId,
        public readonly int $workspaceId,
        public readonly string $queryFingerprint,
        public readonly string $generationId,
        public readonly string $authorizedAt,
        public readonly string $expiresAt,
        public readonly string $nonce,
        public readonly string $signature,
    ) {}

    /**
     * Verify cryptographic signature against secret key.
     */
    public function verifySignature(string $secret): bool
    {
        return GrantSigner::verify(
            signature: $this->signature,
            workspaceId: $this->workspaceId,
            queryFingerprint: $this->queryFingerprint,
            generationId: $this->generationId,
            expiresAt: $this->expiresAt,
            nonce: $this->nonce,
            secret: $secret,
        );
    }

    /**
     * Check if the authorization grant has expired.
     */
    public function isExpired(): bool
    {
        try {
            $exp = new DateTimeImmutable($this->expiresAt);
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            return $now > $exp;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Convert to array representation matching Python contract.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'grant_id' => $this->grantId,
            'workspace_id' => $this->workspaceId,
            'query_fingerprint' => $this->queryFingerprint,
            'generation_id' => $this->generationId,
            'authorized_at' => $this->authorizedAt,
            'expires_at' => $this->expiresAt,
            'nonce' => $this->nonce,
            'signature' => $this->signature,
        ];
    }

    /**
     * Construct instance from associative array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            grantId: (string) ($data['grant_id'] ?? ''),
            workspaceId: (int) ($data['workspace_id'] ?? 0),
            queryFingerprint: (string) ($data['query_fingerprint'] ?? ''),
            generationId: (string) ($data['generation_id'] ?? ''),
            authorizedAt: (string) ($data['authorized_at'] ?? ''),
            expiresAt: (string) ($data['expires_at'] ?? ''),
            nonce: (string) ($data['nonce'] ?? ''),
            signature: (string) ($data['signature'] ?? ''),
        );
    }
}

