<?php

declare(strict_types=1);

namespace App\AI\Agents;

use App\Models\Conversation;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class ConversationalSupportAgent implements Agent, Conversational, HasProviderOptions
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
You are a warm, polite Enterprise Customer Support AI.

Rules:
1. Greet warmly and ask how you can assist with accounts, orders, or features.
2. Match customer's language (Bangla, English, or Banglish) politely.
3. Keep response engaging, helpful, and concise (1-2 sentences).
{$memorySection}
PROMPT;
    }

    public function maxTokens(): int
    {
        return (int) config('ai.chat_max_tokens', 128);
    }

    public function providerOptions(Lab|string $provider): array
    {
        return [
            'max_tokens' => $this->maxTokens(),
        ];
    }

    public function temperature(): float
    {
        return 0.4;
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
