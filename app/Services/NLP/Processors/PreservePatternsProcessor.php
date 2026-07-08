<?php

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;

class PreservePatternsProcessor implements ProcessorInterface
{
    /**
     * Patterns to preserve and their placeholder prefix.
     */
    private const PATTERNS = [
        'EMAIL' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        'URL'   => '#https?://[^\s<>"\'{}|\\^`[\]]+#i',
        'PHONE' => '/\+?\d{1,3}[-.\s]?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,4}[-.\s]?\d{1,4}/',
    ];

    /**
     * Placeholder storage.
     *
     * @var array<string, string>
     */
    private array $placeholders = [];

    /**
     * Process text: replace known patterns with placeholders.
     */
    public function process(string $text, string $language): string
    {
        $this->placeholders = [];

        foreach (self::PATTERNS as $type => $pattern) {
            $index = 0;
            $text = preg_replace_callback($pattern, function ($match) use ($type, &$index) {
                $key = '{{' . $type . '_' . $index . '}}';
                $this->placeholders[$key] = $match[0];
                $index++;

                return $key;
            }, $text);
        }

        return $text;
    }

    /**
     * Get the map of placeholders → original values.
     *
     * @return array<string, string>
     */
    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    /**
     * Restore placeholders back to original values.
     */
    public function restore(string $text): string
    {
        return str_replace(
            array_keys($this->placeholders),
            array_values($this->placeholders),
            $text
        );
    }

    public function name(): string
    {
        return 'preserve_patterns';
    }
}
