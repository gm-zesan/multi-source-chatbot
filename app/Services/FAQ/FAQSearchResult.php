<?php

namespace App\Services\FAQ;

use App\Models\FAQ;
use Illuminate\Contracts\Support\Arrayable;

class FAQSearchResult implements Arrayable
{
    /**
     * @param FAQ    $faq           The matched FAQ.
     * @param float  $keywordScore  Normalized keyword match score (0-1).
     * @param float  $semanticScore Normalized vector match score (0-1).
     * @param float  $finalScore    Combined score after priority bonus.
     * @param string $matchType     'keyword', 'semantic', or 'hybrid'.
     */
    public function __construct(
        public readonly FAQ $faq,
        public readonly float $keywordScore = 0.0,
        public readonly float $semanticScore = 0.0,
        public readonly float $finalScore = 0.0,
        public readonly string $matchType = 'keyword',
    ) {}

    public function toArray(): array
    {
        return [
            'id'             => $this->faq->id,
            'question'       => $this->faq->question,
            'answer'         => $this->faq->answer,
            'category'       => $this->faq->category?->name,
            'priority'       => $this->faq->priority,
            'keyword_score'  => round($this->keywordScore, 4),
            'semantic_score' => round($this->semanticScore, 4),
            'final_score'    => round($this->finalScore, 4),
            'match_type'     => $this->matchType,
        ];
    }
}
