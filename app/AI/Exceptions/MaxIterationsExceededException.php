<?php

declare(strict_types=1);

namespace App\AI\Exceptions;

use RuntimeException;

class MaxIterationsExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $maxIterations = 3,
        public readonly int $attemptedIterations = 3,
        int $code = 429,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

