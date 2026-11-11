<?php

declare(strict_types=1);

namespace App\AI\DTOs;

/**
 * Immutable DTO representing a deterministic follow-up evaluation outcome.
 */
class FollowUpDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $answer,
        public readonly ?string $followUpText = null,
        public readonly ?string $followUpType = null,
        public readonly ?string $suppressionReason = null,
    ) {}

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function isSuppressed(): bool
    {
        return !$this->allowed;
    }

    public function finalReply(): string
    {
        $cleanAnswer = preg_replace('/\{[\s\S]*?"(?:proposed_follow_up|follow_up_type)"[\s\S]*?\}/u', '', $this->answer);
        $cleanAnswer = trim((string) $cleanAnswer);

        if ($this->allowed && !empty($this->followUpText)) {
            $trimmedFollowUp = preg_replace('/\{[\s\S]*?"(?:proposed_follow_up|follow_up_type)"[\s\S]*?\}/u', '', $this->followUpText);
            $trimmedFollowUp = trim((string) $trimmedFollowUp);
            if ($trimmedFollowUp !== '') {
                return $cleanAnswer !== '' ? $cleanAnswer . "\n\n" . $trimmedFollowUp : $trimmedFollowUp;
            }
        }

        return $cleanAnswer;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed'            => $this->allowed,
            'answer'             => $this->answer,
            'follow_up_text'     => $this->followUpText,
            'follow_up_type'     => $this->followUpType,
            'suppression_reason' => $this->suppressionReason,
            'final_reply'        => $this->finalReply(),
        ];
    }
}
