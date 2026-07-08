<?php

namespace App\Services\Chat;

use App\Models\Conversation;

class FAQConversationResult
{
    /**
     * @param Conversation $conversation The conversation the message belongs to.
     * @param bool         $answered     Whether the FAQ engine found an answer.
     * @param string|null  $answerText   The answer sent back to the user.
     * @param float        $confidence   FAQ engine confidence score (0–100).
     * @param string|null  $faqId        The matched FAQ ID.
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly bool $answered = false,
        public readonly ?string $answerText = null,
        public readonly float $confidence = 0.0,
        public readonly ?string $faqId = null,
    ) {}

    /**
     * Whether the human workflow should continue.
     */
    public function shouldContinueHumanWorkflow(): bool
    {
        return ! $this->answered;
    }
}
