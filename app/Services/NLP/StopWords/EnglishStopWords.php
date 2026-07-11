<?php

declare(strict_types=1);

namespace App\Services\NLP\StopWords;

class EnglishStopWords
{
    /**
     * Get the full list of English stop words.
     *
     * @return string[]
     */
    public static function get(): array
    {
        return [
            'a', 'an', 'the', 'and', 'or', 'but', 'if', 'because', 'as', 'until',
            'while', 'of', 'at', 'by', 'for', 'with', 'about', 'against', 'between',
            'into', 'through', 'during', 'before', 'after', 'above', 'below', 'to',
            'from', 'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under', 'again',
            'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how',
            'all', 'each', 'every', 'both', 'few', 'more', 'most', 'some', 'any', 'no',
            'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'also', 'now',
            'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has',
            'had', 'having', 'do', 'does', 'did', 'doing', 'will', 'would', 'can', 'could',
            'shall', 'should', 'may', 'might', 'must', 'need', 'dare', 'ought', 'used',
            'it', 'its', 'itself', 'i', 'me', 'my', 'myself', 'we', 'our', 'ours',
            'ourselves', 'you', 'your', 'yours', 'yourself', 'yourselves', 'he', 'him',
            'his', 'himself', 'she', 'her', 'hers', 'herself', 'they', 'them', 'their',
            'theirs', 'themselves', 'what', 'which', 'who', 'whom', 'this', 'that',
            'these', 'those', 'not', 'n\'t', 'don\'t', 'doesn\'t', 'didn\'t', 'won\'t',
            'wouldn\'t', 'can\'t', 'couldn\'t', 'shouldn\'t', 'isn\'t', 'aren\'t',
            'wasn\'t', 'weren\'t', 'hasn\'t', 'haven\'t', 'hadn\'t', 'ain\'t',
        ];
    }
}
