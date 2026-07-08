<?php

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;

class RemoveEmojisProcessor implements ProcessorInterface
{
    public function process(string $text, string $language): string
    {
        // Remove common emoji and pictographic characters
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', ' ', $text);  // emoticons
        $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', ' ', $text);  // symbols & pictographs
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', ' ', $text);  // transport symbols
        $text = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', ' ', $text);  // flags
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', ' ', $text);    // miscellaneous symbols
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', ' ', $text);    // dingbats
        $text = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $text);     // variation selectors
        $text = preg_replace('/[\x{200D}]/u', '', $text);              // zero-width joiner

        return $text;
    }

    public function name(): string
    {
        return 'remove_emojis';
    }
}
