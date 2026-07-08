<?php

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;

class NormalizeBanglaUnicodeProcessor implements ProcessorInterface
{
    /**
     * Unicode normalization map for Bangla characters.
     *
     * Maps visually similar or variant characters to their standard form.
     */
    private const NORMALIZATION_MAP = [
        // Bengali-Assamese script variants
        "\xe0\xa6\x9c" => "\xe0\xa6\x9c", // য় → য (if needed)
        "\xe0\xa6\xbc" => '',              // nukta removal

        // Normalize kandara (joined reph)
        // :: TODO :: Add more normalization pairs as needed
    ];

    /**
     * Bengali vowel signs that may appear in composed/decomposed forms.
     */
    private const BANGLA_VOWEL_SIGNS = [
        'া', 'ি', 'ী', 'ু', 'ূ', 'ৃ', 'ে', 'ৈ', 'ো', 'ৌ',
    ];

    public function process(string $text, string $language): string
    {
        // Only process Bangla and Banglish (Bangla portion)
        if ($language !== 'bn' && $language !== 'banglish') {
            return $text;
        }

        // 1. Normalize to Unicode NFC form
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $text = $normalized;
            }
        }

        // 2. Remove zero-width non-joiners and joiners (except within words)
        $text = preg_replace('/\x{200B}/u', '', $text); // ZWNBSP / Zero-width space
        $text = preg_replace('/\x{200C}/u', '', $text); // ZWNJ (remove, breaks rendering)
        // Preserve ZWJ within words (used in complex conjuncts)
        $text = preg_replace('/(?<!\w)\x{200D}(?!\w)/u', '', $text);

        // 3. Remove multiple consecutive vowel signs (typing artifacts)
        foreach (self::BANGLA_VOWEL_SIGNS as $sign) {
            $text = preg_replace('/' . preg_quote($sign, '/') . '{2,}/u', $sign, $text);
        }

        // 4. Remove trailing dangling vowel signs (typing artifacts)
        $text = preg_replace('/[' . preg_quote(implode('', self::BANGLA_VOWEL_SIGNS), '/') . ']+$/u', '', $text);

        // 5. Remove Bangla digits (keep English digits for number preservation)
        $text = preg_replace('/[\x{09E6}-\x{09EF}]/u', ' ', $text);

        return $text;
    }

    public function name(): string
    {
        return 'normalize_bangla_unicode';
    }
}
