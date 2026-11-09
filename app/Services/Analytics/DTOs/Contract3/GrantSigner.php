<?php

declare(strict_types=1);

namespace App\Services\Analytics\DTOs\Contract3;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;

/**
 * Cryptographic signer and validator for Stage B ExecutionAuthorizationGrants.
 * Produces byte-identical HMAC-SHA256 signatures with Python execution_gateway.
 */
class GrantSigner
{
    /**
     * Compute HMAC-SHA256 signature over grant metadata.
     * Message format: "{workspace_id}:{query_fingerprint}:{generation_id}:{expires_at}:{nonce}"
     */
    public static function sign(
        int $workspaceId,
        string $queryFingerprint,
        string $generationId,
        string $expiresAt,
        string $nonce,
        string $secret,
    ): string {
        $message = sprintf(
            '%d:%s:%s:%s:%s',
            $workspaceId,
            trim($queryFingerprint),
            trim($generationId),
            trim($expiresAt),
            trim($nonce)
        );

        return hash_hmac('sha256', $message, $secret);
    }

    /**
     * Constant-time verification of HMAC-SHA256 signature.
     */
    public static function verify(
        string $signature,
        int $workspaceId,
        string $queryFingerprint,
        string $generationId,
        string $expiresAt,
        string $nonce,
        string $secret,
    ): bool {
        $expected = self::sign(
            workspaceId: $workspaceId,
            queryFingerprint: $queryFingerprint,
            generationId: $generationId,
            expiresAt: $expiresAt,
            nonce: $nonce,
            secret: $secret,
        );

        return hash_equals($expected, $signature);
    }

    /**
     * Issue a new signed ExecutionAuthorizationGrant.
     *
     * @param int $workspaceId
     * @param string $queryFingerprint
     * @param string $generationId
     * @param string|null $secret
     * @param int $ttlSeconds Default 60 seconds
     * @return ExecutionAuthorizationGrant
     */
    public static function issue(
        int $workspaceId,
        string $queryFingerprint,
        string $generationId,
        ?string $secret = null,
        int $ttlSeconds = 60,
    ): ExecutionAuthorizationGrant {
        $secretKey = $secret ?? (string) config('analytics.secret_key', 'fallback_analytics_secret');
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $authorizedAt = $now->format('Y-m-d\TH:i:s\Z');
        $expiresAt = $now->modify("+{$ttlSeconds} seconds")->format('Y-m-d\TH:i:s\Z');
        $nonce = Str::random(32);
        $grantId = (string) Str::uuid();

        $signature = self::sign(
            workspaceId: $workspaceId,
            queryFingerprint: $queryFingerprint,
            generationId: $generationId,
            expiresAt: $expiresAt,
            nonce: $nonce,
            secret: $secretKey,
        );

        return new ExecutionAuthorizationGrant(
            grantId: $grantId,
            workspaceId: $workspaceId,
            queryFingerprint: $queryFingerprint,
            generationId: $generationId,
            authorizedAt: $authorizedAt,
            expiresAt: $expiresAt,
            nonce: $nonce,
            signature: $signature,
        );
    }
}

