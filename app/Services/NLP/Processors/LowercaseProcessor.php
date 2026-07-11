<?php

declare(strict_types=1);

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;

class LowercaseProcessor implements ProcessorInterface
{
    public function process(string $text, string $language): string
    {
        // Only lowercase English/Banglish text; Bangla has no case
        if ($language === 'en' || $language === 'banglish') {
            return mb_strtolower($text, 'UTF-8');
        }

        return $text;
    }

    public function name(): string
    {
        return 'lowercase';
    }
}
