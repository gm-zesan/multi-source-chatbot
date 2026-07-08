<?php

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;

class NormalizeWhitespaceProcessor implements ProcessorInterface
{
    public function process(string $text, string $language): string
    {
        // Replace tabs, newlines, carriage returns with space
        $text = preg_replace('/[\t\n\r]+/u', ' ', $text);

        // Collapse multiple spaces into one
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    public function name(): string
    {
        return 'normalize_whitespace';
    }
}
