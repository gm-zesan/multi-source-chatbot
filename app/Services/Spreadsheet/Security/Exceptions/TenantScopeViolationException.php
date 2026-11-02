<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Security\Exceptions;

use RuntimeException;

class TenantScopeViolationException extends RuntimeException
{
    public function __construct(
        string $message = 'Cross-tenant boundary access violation.',
        public readonly int $requestWorkspaceId = 0,
        public readonly int $targetWorkspaceId = 0,
        public readonly ?int $userId = null,
        int $code = 403,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

