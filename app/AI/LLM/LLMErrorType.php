<?php

declare(strict_types=1);

namespace App\AI\LLM;

use Throwable;

enum LLMErrorType: string
{
    case RATE_LIMIT = 'rate_limit';           // HTTP 429
    case TIMEOUT = 'timeout';                 // Connection/Read Timeout
    case SERVER_ERROR = 'server_error';       // HTTP 500, 502, 503, 504
    case AUTH_ERROR = 'auth_error';           // HTTP 401, 403 (Invalid Key/Unauthorized)
    case INVALID_REQUEST = 'invalid_request'; // HTTP 400, 422 (Malformed Request/Payload)
    case UNKNOWN = 'unknown';

    /**
     * Determine error category from exception.
     */
    public static function classify(Throwable $e): self
    {
        $msg = strtolower($e->getMessage());

        if (str_contains($msg, '429') || str_contains($msg, 'rate limit') || str_contains($msg, 'quota')) {
            return self::RATE_LIMIT;
        }

        if (str_contains($msg, 'timeout') || str_contains($msg, 'timed out') || str_contains($msg, 'curl error 28')) {
            return self::TIMEOUT;
        }

        if (str_contains($msg, '500') || str_contains($msg, '502') || str_contains($msg, '503') || str_contains($msg, '504') || str_contains($msg, 'server error') || str_contains($msg, 'service unavailable')) {
            return self::SERVER_ERROR;
        }

        if (str_contains($msg, '401') || str_contains($msg, '403') || str_contains($msg, 'unauthorized') || str_contains($msg, 'invalid api key') || str_contains($msg, 'authentication')) {
            return self::AUTH_ERROR;
        }

        if (str_contains($msg, '400') || str_contains($msg, '422') || str_contains($msg, 'bad request') || str_contains($msg, 'invalid payload')) {
            return self::INVALID_REQUEST;
        }

        return self::UNKNOWN;
    }

    /**
     * Check if error is transient and eligible for generic provider fallback.
     */
    public function isFallbackEligible(): bool
    {
        return match ($this) {
            self::RATE_LIMIT, self::TIMEOUT, self::SERVER_ERROR, self::UNKNOWN => true,
            self::AUTH_ERROR, self::INVALID_REQUEST => false,
        };
    }
}
