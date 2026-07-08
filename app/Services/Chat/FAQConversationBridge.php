<?php

namespace App\Services\Chat;

use App\Models\ChannelAccount;
use App\Models\Message;
use App\Services\Chat\FAQConversationResult;
use App\Services\FAQ\FAQAnswerEngine;
use Illuminate\Support\Facades\Log;

class FAQConversationBridge
{
    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly FAQAnswerEngine $answerEngine,
    ) {}

    /**
     * Process an incoming message through the FAQ engine.
     *
     * 1. Save the incoming message (existing flow)
     * 2. Run FAQAnswerEngine on the message text
     * 3. If answer found → send reply and return true
     * 4. If not found → return false (continue human workflow)
     *
     * @param ChannelAccount $account
     * @param array          $data     Message data (text, external_user_id, etc.)
     * @return FAQConversationResult
     */
    public function processIncoming(ChannelAccount $account, array $data): FAQConversationResult
    {
        // 1. Save incoming message (existing CRM/entity flow)
        $conversation = $this->conversationService->saveIncoming($account, $data);

        // Get the saved message (most recent inbound message for this conversation)
        $message = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->latest()
            ->first();

        $messageText = $data['text'] ?? $message?->body ?? '';

        Log::info('[FAQConversationBridge] Processing incoming message', [
            'conversation_id' => $conversation->id,
            'message_id'      => $message?->id,
            'channel'         => $account->channel?->slug ?? $account->id,
        ]);

        // 2. Run FAQ engine
        $result = $this->answerEngine->answer(
            query: $messageText,
            workspaceId: $account->workspace_id,
            conversationId: $conversation->id,
            messageId: $message?->id,
        );

        // 3. If answer found → send reply
        if ($result->answered && $result->getAnswer()) {
            $replyMessage = $result->getAnswer();

            $this->conversationService->saveOutgoing(
                conversation: $conversation,
                message: $replyMessage,
                response: [
                    'source'        => 'faq_engine',
                    'faq_id'        => $result->faq?->id,
                    'confidence'    => $result->confidence,
                    'match_type'    => $result->matchType,
                ],
            );

            Log::info('[FAQConversationBridge] FAQ answer sent', [
                'conversation_id' => $conversation->id,
                'faq_id'          => $result->faq?->id,
                'confidence'      => $result->confidence,
            ]);

            return new FAQConversationResult(
                conversation: $conversation,
                answered: true,
                answerText: $replyMessage,
                confidence: $result->confidence,
                faqId: $result->faq?->id,
            );
        }

        // 4. No answer found — continue human workflow
        Log::info('[FAQConversationBridge] No FAQ answer, continuing human workflow', [
            'conversation_id' => $conversation->id,
            'best_confidence' => $result->confidence,
        ]);

        return new FAQConversationResult(
            conversation: $conversation,
            answered: false,
        );
    }
}
