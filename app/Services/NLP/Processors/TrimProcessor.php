<?php

declare(strict_types=1);

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;

class TrimProcessor implements ProcessorInterface
{
    public function process(string $text, string $language): string
    {
        return trim($text);
    }

    public function name(): string
    {
        return 'trim';
    }
}
