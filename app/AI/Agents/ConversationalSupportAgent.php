<?php

declare(strict_types=1);

namespace App\AI\Agents;

use App\Models\Conversation;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class ConversationalSupportAgent implements Agent, Conversational
{
    use Promptable;

    public function __construct(
        public readonly ?Conversation $conversation = null,
        public readonly ?string $memoryContext = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $memorySection = "";
        if (!empty($this->memoryContext)) {
            $memorySection = "\n\nCustomer Conversation Graph Memory (Known Historical Preferences):\n" . $this->memoryContext . "\nWhen relevant, acknowledge their known context politely and warmly without being intrusive.\n";
        }

        return <<<PROMPT
You are a warm, polite, and professional Enterprise Customer Support AI Assistant.

Instructions:
1. Respond warmly and politely to customer greetings, pleasantries, thanks, and conversational remarks.
2. Ask how you can assist them today regarding their account, orders, subscription, or our platform features.
3. If the user writes in Bangla, respond in polite Bangla (বাংলা). If they write in English or Banglish, respond in the matching natural tone.
4. Keep the response concise, engaging, and helpful.
{$memorySection}
PROMPT;
    }

    public function messages(): iterable
    {
        if ($this->conversation === null) {
            return [];
        }

        $limit = (int) config('ai.memory.max_messages', 10);
        $maxChars = (int) config('ai.memory.max_message_chars', 1000);

        $rawMessages = $this->conversation->messages()
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $aiMessages = [];
        foreach ($rawMessages as $msg) {
            $body = trim((string) $msg->body);
            if ($body === '') {
                continue;
            }

            if ($maxChars > 0 && mb_strlen($body) > $maxChars) {
                $body = mb_substr($body, 0, $maxChars) . '... [truncated]';
            }

            if ($msg->direction === 'inbound') {
                $aiMessages[] = new UserMessage($body);
            } else {
                $aiMessages[] = new AssistantMessage($body);
            }
        }

        return $aiMessages;
    }
}
