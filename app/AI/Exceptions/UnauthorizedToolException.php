<?php

declare(strict_types=1);

namespace App\AI\Exceptions;

use RuntimeException;

class UnauthorizedToolException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $toolName = '',
        public readonly ?string $agentId = null,
        public readonly int $workspaceId = 0,
        int $code = 403,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
