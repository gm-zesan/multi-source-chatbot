<?php

declare(strict_types=1);

namespace App\Services\NLP;

class ProcessedText
{
    /**
     * @param string   $original   The raw input text.
     * @param string   $normalized The fully processed text.
     * @param string[] $tokens     The tokenized words.
     * @param string   $keyword    Space-separated keywords for search/matching.
     * @param string   $language   Detected language (en, bn, banglish).
     */
    public function __construct(
        public readonly string $original,
        public readonly string $normalized,
        public readonly array $tokens,
        public readonly string $keyword,
        public readonly string $language,
    ) {}

    /**
     * Convert the result to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original'   => $this->original,
            'normalized' => $this->normalized,
            'tokens'     => $this->tokens,
            'keyword'    => $this->keyword,
            'language'   => $this->language,
        ];
    }
}
