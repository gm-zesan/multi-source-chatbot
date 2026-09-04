<?php

declare(strict_types=1);

namespace App\Services\FAQ;

class FaqLexiconValidator
{
    /**
     * Maximum allowed words in an individual search term/alias.
     */
    private const MAX_WORDS_PER_TERM = 6;

    /**
     * Maximum number of terms allowed per category.
     */
    private const MAX_TERMS_PER_CATEGORY = 15;

    /**
     * Validate and sanitize raw LLM lexicon output against commerce guardrails.
     *
     * @param array<string, mixed> $rawPayload
     * @return array{
     *     domain: string,
     *     intent: string,
     *     canonical_terms: string[],
     *     bangla_terms: string[],
     *     commerce_terms: string[],
     *     is_valid: bool
     * }
     */
    public function validateAndSanitize(array $rawPayload): array
    {
        $rawDomain = (string) ($rawPayload['domain'] ?? CommerceOntology::DOMAIN_PLATFORM_UI_ISSUES);
        $domain = CommerceOntology::normalizeDomain($rawDomain);

        $rawIntent = (string) ($rawPayload['intent'] ?? 'general_inquiry');
        $intent = $this->sanitizeIntent($rawIntent);

        $canonical = $this->sanitizeTermList($rawPayload['canonical_terms'] ?? []);
        $bangla = $this->sanitizeTermList($rawPayload['bangla_terms'] ?? []);
        $commerce = $this->sanitizeTermList($rawPayload['commerce_terms'] ?? []);

        $isValid = !empty($canonical) || !empty($bangla) || !empty($commerce);

        return [
            'domain'          => $domain,
            'intent'          => $intent,
            'canonical_terms' => array_slice($canonical, 0, self::MAX_TERMS_PER_CATEGORY),
            'bangla_terms'    => array_slice($bangla, 0, self::MAX_TERMS_PER_CATEGORY),
            'commerce_terms'  => array_slice($commerce, 0, self::MAX_TERMS_PER_CATEGORY),
            'is_valid'        => $isValid,
        ];
    }

    /**
     * Common standalone stopwords that should never exist as isolated single-word search terms.
     */
    private const GENERIC_STOPWORDS = [
        'the', 'how', 'do', 'i', 'is', 'a', 'an', 'what', 'why', 'are', 'my', 'can', 'does',
        'of', 'to', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'up', 'about', 'into',
        'over', 'after', 'where', 'your', 'and', 'or', 'we', 'you', 'should', 'if', 'it',
    ];

    /**
     * Recognized valid acronyms that are allowed despite being short (<= 3 chars).
     */
    private const ALLOWED_SHORT_ACRONYMS = [
        '2fa', 'cod', 'api', 'otp', 'sms', 'app', 'qr', 'nid', 'ssl', 'tls', 'pos', 'eta', 'faq',
    ];

    /**
     * Sanitize and filter a list of terms.
     *
     * @param mixed $terms
     * @return string[]
     */
    private function sanitizeTermList(mixed $terms): array
    {
        if (!is_array($terms)) {
            return [];
        }

        $sanitized = [];
        foreach ($terms as $term) {
            if (!is_string($term)) {
                continue;
            }

            $t = trim($term, " \t\n\r\0\x0B.,;\"'`-");
            if ($t === '') {
                continue;
            }

            $tLower = mb_strtolower($t);

            // Filter standalone stopwords
            if (in_array($tLower, self::GENERIC_STOPWORDS, true)) {
                continue;
            }

            // Filter ultra-short non-acronyms (< 3 chars)
            if (mb_strlen($t) < 3 && !in_array($tLower, self::ALLOWED_SHORT_ACRONYMS, true)) {
                continue;
            }

            // Reject full sentences or paragraph answers
            if (mb_strlen($t) > 80 || $this->isSentence($t)) {
                continue;
            }

            // Word count constraint (<= 6 words)
            $wordCount = count(preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            if ($wordCount > self::MAX_WORDS_PER_TERM) {
                continue;
            }

            $sanitized[] = $t;
        }

        return array_values(array_unique($sanitized));
    }

    /**
     * Check if a text looks like a full descriptive sentence or answer text.
     */
    private function isSentence(string $text): bool
    {
        $hasSentenceEnding = preg_match('/[.!?|]$/u', trim($text));
        $hasAnswerPhrases = preg_match('/\b(you can|we offer|our team will|please follow|click on the)\b/i', $text);

        return (bool) ($hasSentenceEnding && $hasAnswerPhrases);
    }

    /**
     * Sanitize intent slug.
     */
    private function sanitizeIntent(string $intent): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', mb_strtolower(trim($intent)));
        return trim($clean ?: 'general_inquiry', '_');
    }
}
