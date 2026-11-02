<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Security\Exceptions;

use RuntimeException;

class UnauthorizedDataAccessException extends RuntimeException
{
    public function __construct(
        string $message = 'Unauthorized field-level access attempt.',
        public readonly string $field = '',
        public readonly string $role = '',
        public readonly string $sensitivity = '',
        public readonly string $purpose = '',
        int $code = 403,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

