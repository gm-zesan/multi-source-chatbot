<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

use App\Services\AI\Enums\AnswerabilityStatus;
use Illuminate\Database\Eloquent\Collection;

/**
 * Value Object representing the output of the Semantic Answerability Gate.
 *
 * NOTE: confidenceScore represents composite answerability confidence,
 * which is strictly separated from raw retrieval similarity scores.
 */
class AnswerabilityDecision
{
    /**
     * @param AnswerabilityStatus $status
     * @param Collection          $groundedHits Strictly validated hits authorized for answering
     * @param float|null          $confidenceScore Composite answerability confidence (0.0 - 1.0)
     * @param array<string, mixed> $reasons Auditable decision diagnostics
     */
    public function __construct(
        public readonly AnswerabilityStatus $status,
        public readonly Collection $groundedHits,
        public readonly ?float $confidenceScore = null,
        public readonly array $reasons = [],
    ) {}

    public function isConfident(): bool
    {
        return $this->status === AnswerabilityStatus::CONFIDENT;
    }

    public function isAmbiguous(): bool
    {
        return $this->status === AnswerabilityStatus::AMBIGUOUS;
    }

    public function isUnanswerable(): bool
    {
        return $this->status === AnswerabilityStatus::UNANSWERABLE;
    }

    public function hasGroundedEvidence(): bool
    {
        return $this->groundedHits->isNotEmpty();
    }

    public function topHit(): ?object
    {
        return $this->groundedHits->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status'           => $this->status->value,
            'is_confident'     => $this->isConfident(),
            'is_ambiguous'     => $this->isAmbiguous(),
            'is_unanswerable'  => $this->isUnanswerable(),
            'confidence_score' => $this->confidenceScore,
            'grounded_count'   => $this->groundedHits->count(),
            'reasons'          => $this->reasons,
        ];
    }
}
