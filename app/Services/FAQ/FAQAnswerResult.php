<?php

namespace App\Services\FAQ;

use App\Models\FAQ;
use Illuminate\Contracts\Support\Arrayable;

class FAQAnswerResult implements Arrayable
{
    /**
     * @param FAQ|null  $faq           The matched FAQ (null if no answer found).
     * @param float     $confidence    Final confidence score 0–100.
     * @param bool      $answered      Whether a satisfactory answer was found.
     * @param float     $keywordScore  Keyword relevance score.
     * @param float     $semanticScore Semantic similarity score.
     * @param string    $matchType     'keyword', 'semantic', 'hybrid', or 'none'.
     * @param float     $responseTimeMs Time taken for the full pipeline.
     */
    public function __construct(
        public readonly ?FAQ $faq,
        public readonly float $confidence = 0.0,
        public readonly bool $answered = false,
        public readonly float $keywordScore = 0.0,
        public readonly float $semanticScore = 0.0,
        public readonly string $matchType = 'none',
        public readonly float $responseTimeMs = 0.0,
    ) {}

    /**
     * Get the answer text if available.
     */
    public function getAnswer(): ?string
    {
        return $this->faq?->answer;
    }

    /**
     * Get the matched question text if available.
     */
    public function getQuestion(): ?string
    {
        return $this->faq?->question;
    }

    public function toArray(): array
    {
        return [
            'answered'       => $this->answered,
            'confidence'     => round($this->confidence, 2),
            'faq_id'         => $this->faq?->id,
            'question'       => $this->getQuestion(),
            'answer'         => $this->getAnswer(),
            'keyword_score'  => round($this->keywordScore, 4),
            'semantic_score' => round($this->semanticScore, 4),
            'match_type'     => $this->matchType,
            'response_time_ms' => round($this->responseTimeMs, 2),
        ];
    }
}
