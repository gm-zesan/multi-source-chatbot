<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

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
        if ($this->allowed && !empty($this->followUpText)) {
            $trimmedAnswer = trim($this->answer);
            $trimmedFollowUp = trim($this->followUpText);
            return $trimmedAnswer . "\n\n" . $trimmedFollowUp;
        }

        return trim($this->answer);
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
