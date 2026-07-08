<?php

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;

class TokenizerProcessor implements ProcessorInterface
{
    /**
     * @param int $minTokenLength Minimum character length to keep a token.
     */
    public function __construct(
        private readonly int $minTokenLength = 1,
    ) {}

    /**
     * This processor doesn't modify the text — it's used externally for tokenization.
     * The process method is a no-op.
     */
    public function process(string $text, string $language): string
    {
        return $text;
    }

    /**
     * Tokenize the normalized text into words.
     *
     * Handles English, Bangla, and mixed Banglish text.
     *
     * @return string[]
     */
    public function tokenize(string $text, string $language): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        // Split on whitespace
        $words = preg_split('/\s+/', $text);
        $tokens = [];

        foreach ($words as $word) {
            $word = trim($word);

            // Keep preserved placeholders as single tokens
            if (preg_match('/^\{\{[A-Z_]+\d*\}\}$/', $word)) {
                $tokens[] = $word;
                continue;
            }

            // For Bangla: split on punctuation that may have survived
            if ($language === 'bn') {
                $word = preg_replace('/[^\p{Bengali}\p{Latin}\d\{\}_]+/u', ' ', $word);
            }

            // Further split on internal hyphenation for English
            if ($language === 'en') {
                $parts = preg_split('/[-–—]/', $word);
            } else {
                $parts = [$word];
            }

            foreach ($parts as $part) {
                $part = trim($part);
                $len = mb_strlen($part, 'UTF-8');

                if ($len >= $this->minTokenLength) {
                    $tokens[] = $part;
                }
            }
        }

        return $tokens;
    }

    /**
     * Build a keyword string from tokens (deduplicated, sorted).
     */
    public function buildKeywordString(array $tokens, string $language): string
    {
        // Remove duplicates while preserving order
        $seen = [];
        $unique = [];

        foreach ($tokens as $token) {
            $lower = mb_strtolower($token, 'UTF-8');

            if (! isset($seen[$lower])) {
                $seen[$lower] = true;
                $unique[] = $token;
            }
        }

        return implode(' ', $unique);
    }

    public function name(): string
    {
        return 'tokenizer';
    }
}
