<?php

declare(strict_types=1);

namespace App\Services\NLP\Contracts;

interface ProcessorInterface
{
    /**
     * Process the text and return the modified version.
     */
    public function process(string $text, string $language): string;

    /**
     * A unique name for this processor step.
     */
    public function name(): string;
}
