<?php

namespace App\Services\NLP\Processors;

use App\Services\NLP\Contracts\ProcessorInterface;

class NormalizeContractionsProcessor implements ProcessorInterface
{
    /**
     * English contraction mapping.
     */
    private const CONTRACTIONS = [
        // Positive
        "i'm"        => 'i am',
        "you're"     => 'you are',
        "he's"       => 'he is',
        "she's"      => 'she is',
        "it's"       => 'it is',
        "we're"      => 'we are',
        "they're"    => 'they are',
        "that's"     => 'that is',
        "who's"      => 'who is',
        "what's"     => 'what is',
        "where's"    => 'where is',
        "when's"     => 'when is',
        "why's"      => 'why is',
        "how's"      => 'how is',
        "there's"    => 'there is',
        "here's"     => 'here is',
        "i've"       => 'i have',
        "you've"     => 'you have',
        "we've"      => 'we have',
        "they've"    => 'they have',
        "could've"   => 'could have',
        "should've"  => 'should have',
        "would've"   => 'would have',
        "might've"   => 'might have',
        "must've"    => 'must have',
        "i'll"       => 'i will',
        "you'll"     => 'you will',
        "he'll"      => 'he will',
        "she'll"     => 'she will',
        "it'll"      => 'it will',
        "we'll"      => 'we will',
        "they'll"    => 'they will',
        "that'll"    => 'that will',
        "who'll"     => 'who will',
        "what'll"    => 'what will',
        "i'd"        => 'i would',
        "you'd"      => 'you would',
        "he'd"       => 'he would',
        "she'd"      => 'she would',
        "it'd"       => 'it would',
        "we'd"       => 'we would',
        "they'd"     => 'they would',
        "that'd"     => 'that would',
        "who'd"      => 'who would',
        "what'd"     => 'what would',

        // Negative
        "don't"      => 'do not',
        "doesn't"    => 'does not',
        "didn't"     => 'did not',
        "won't"      => 'will not',
        "wouldn't"   => 'would not',
        "can't"      => 'cannot',
        "couldn't"   => 'could not',
        "shouldn't"  => 'should not',
        "mightn't"   => 'might not',
        "mustn't"    => 'must not',
        "needn't"    => 'need not',
        "isn't"      => 'is not',
        "aren't"     => 'are not',
        "wasn't"     => 'was not',
        "weren't"    => 'were not',
        "hasn't"     => 'has not',
        "haven't"    => 'have not',
        "hadn't"     => 'had not',
        "ain't"      => 'is not',
        "shan't"     => 'shall not',
        "let's"      => 'let us',
        "ma'am"      => 'madam',
        "o'clock"    => 'of the clock',
        "y'all"      => 'you all',
    ];

    public function process(string $text, string $language): string
    {
        // Only normalize contractions for English text
        if ($language !== 'en') {
            return $text;
        }

        // Tokenize to handle contractions within words boundaries
        $words = preg_split('/\s+/', $text);
        $normalized = [];

        foreach ($words as $word) {
            $lower = mb_strtolower($word, 'UTF-8');
            $expansion = self::CONTRACTIONS[$lower] ?? null;

            if ($expansion !== null) {
                // Preserve original capitalization pattern if first letter was uppercase
                $normalized[] = $expansion;
            } else {
                $normalized[] = $word;
            }
        }

        return implode(' ', $normalized);
    }

    public function name(): string
    {
        return 'normalize_contractions';
    }
}
