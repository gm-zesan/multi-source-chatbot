<?php

declare(strict_types=1);

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;

class RemovePunctuationProcessor implements ProcessorInterface
{
    /**
     * Process text: remove punctuation while preserving placeholders.
     *
     * Uses a Unicode-safe regex approach instead of byte-based str_split
     * to avoid corrupting multi-byte characters (e.g. Bangla, Arabic).
     */
    public function process(string $text, string $language): string
    {
        // Preserve placeholders like {{EMAIL_0}}, {{URL_1}}, {{PHONE_2}}
        $placeholders = [];
        $text = preg_replace_callback('/\{\{[A-Z_]+\d*\}\}/', function ($m) use (&$placeholders) {
            $key = "\x01PLACEHOLDER\x01" . count($placeholders) . "\x01";
            $placeholders[$key] = $m[0];
            return $key;
        }, $text);

        // Remove common ASCII punctuation (Unicode-safe regex character class)
        $text = preg_replace('/[!-\/\-:?@\[-\]_`{-~]+/u', ' ', $text);

        // Remove common Unicode punctuation marks
        $text = preg_replace('/[\x{00A1}\x{00BF}\x{0964}\x{0965}'  // Inverted marks, Bengali danda
            . '\x{2010}-\x{203D}\x{2046}-\x{205E}'
            . '\x{207A}-\x{207E}\x{208A}-\x{208E}'
            . '\x{20A0}-\x{20CF}'  // Currency symbols (keep numbers, remove symbols)
            . '\x{2100}-\x{27BF}'  // Letterlike symbols, arrows, math operators
            . '\x{2B00}-\x{2BFF}'  // Miscellaneous symbols and arrows
            . '\x{3000}-\x{303F}'  // CJK punctuation
            . '\x{FE30}-\x{FE4F}'  // CJK compatibility forms
            . '\x{FE50}-\x{FE6F}'  // Small form variants
            . '\x{FF00}-\x{FFEF}'  // Fullwidth forms
            . ']/u', ' ', $text);

        // Restore placeholders
        foreach ($placeholders as $key => $value) {
            $text = str_replace($key, $value, $text);
        }

        return $text;
    }

    public function name(): string
    {
        return 'remove_punctuation';
    }
}
