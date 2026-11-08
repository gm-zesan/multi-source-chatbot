<?php

declare(strict_types=1);

namespace App\AI\Exceptions;

use RuntimeException;

class MissingAgentContextException extends RuntimeException
{
    public function __construct(
        string $message = 'No active AgentContext in current execution scope.',
        int $code = 401,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

