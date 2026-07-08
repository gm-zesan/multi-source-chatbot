<?php

namespace App\Services\NLP\Embedding;

use Exception;

class EmbeddingException extends Exception
{
    /**
     * @param string          $message  Human-readable error description.
     * @param int             $code     HTTP status code or 0 for connection errors.
     * @param \Throwable|null $previous Previous exception for chaining.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
