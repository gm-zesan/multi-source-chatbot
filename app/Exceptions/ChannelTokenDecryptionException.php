<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ChannelTokenDecryptionException extends RuntimeException
{
    public function __construct(
        string $message = 'Failed to decrypt channel account token: payload is invalid, corrupted, or APP_KEY does not match.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
