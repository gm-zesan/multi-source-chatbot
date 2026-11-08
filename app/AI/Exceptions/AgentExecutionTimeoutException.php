<?php

declare(strict_types=1);

namespace App\AI\Exceptions;

use RuntimeException;

class AgentExecutionTimeoutException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly float $timeoutSeconds = 4.0,
        public readonly float $elapsedSeconds = 0.0,
        int $code = 408,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

