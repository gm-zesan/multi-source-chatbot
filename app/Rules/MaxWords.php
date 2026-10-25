<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxWords implements ValidationRule
{
    /**
     * @param int $maxWords Maximum allowable words (default 250 words for MPNet embedding window)
     */
    public function __construct(
        protected int $maxWords = 250
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        // Split by Unicode whitespace to support English, Bengali, and all UTF-8 text accurately
        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        $count = is_array($words) ? count($words) : 0;

        if ($count > $this->maxWords) {
            $formattedField = ucfirst(str_replace('_', ' ', $attribute));
            $fail(sprintf(
                'The %s must not exceed %s words (currently %s words). For optimal AI retrieval with our paraphrase-multilingual-mpnet model, keep answers within 1–2 specific paragraphs. For large policies, break them down into separate focused entries (e.g. Return, Delivery, or Warranty).',
                $formattedField,
                number_format($this->maxWords),
                number_format($count)
            ));
        }
    }
}
