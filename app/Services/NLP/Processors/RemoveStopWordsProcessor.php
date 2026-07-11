<?php

declare(strict_types=1);

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;
use App\Services\NLP\StopWords\BanglaStopWords;
use App\Services\NLP\StopWords\EnglishStopWords;

class RemoveStopWordsProcessor implements ProcessorInterface
{
    /**
     * Cached stop word lists per language.
     *
     * @var array<string, array<int, string>>
     */
    private static array $stopWords = [];

    /**
     * @param bool $removeTokens Whether to strip stop words from the full text (true) or only for keyword extraction (false).
     */
    public function __construct(
        private readonly bool $removeTokens = true,
    ) {}

    public function process(string $text, string $language): string
    {
        if (! $this->removeTokens) {
            return $text;
        }

        $stopWords = $this->getStopWords($language);

        if (empty($stopWords)) {
            return $text;
        }

        $words = preg_split('/\s+/', $text);
        $filtered = [];

        foreach ($words as $word) {
            $clean = trim($word);

            if ($clean === '' || $clean === ' ') {
                continue;
            }

            // Skip if it's a preserved placeholder
            if (preg_match('/^\{\{[A-Z_]+\d*\}\}$/', $clean)) {
                $filtered[] = $clean;
                continue;
            }

            $lower = mb_strtolower($clean, 'UTF-8');

            if (! in_array($lower, $stopWords, true)) {
                $filtered[] = $clean;
            }
        }

        return implode(' ', $filtered);
    }

    /**
     * Filter tokens by removing stop words.
     *
     * @param string[] $tokens
     * @param string   $language
     * @return string[]
     */
    public function filterTokens(array $tokens, string $language): array
    {
        $stopWords = $this->getStopWords($language);

        if (empty($stopWords)) {
            return $tokens;
        }

        return array_values(array_filter($tokens, function (string $token) use ($stopWords) {
            $lower = mb_strtolower($token, 'UTF-8');
            return ! in_array($lower, $stopWords, true);
        }));
    }

    /**
     * Get stop words for the given language.
     *
     * @return string[]
     */
    private function getStopWords(string $language): array
    {
        if (! isset(self::$stopWords[$language])) {
            self::$stopWords[$language] = match ($language) {
                'en', 'banglish' => EnglishStopWords::get(),
                'bn'             => BanglaStopWords::get(),
                default          => [],
            };
        }

        return self::$stopWords[$language];
    }

    public function name(): string
    {
        return 'remove_stop_words';
    }
}
