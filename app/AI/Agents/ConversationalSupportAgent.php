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
            $memorySection = "<MEMORY_CONTEXT>\nCustomer Conversation Graph Memory (Known Historical Preferences):\n" . $this->memoryContext . "\nWhen relevant, acknowledge their known context politely and warmly without being intrusive.\n</MEMORY_CONTEXT>\n";
        }

        return <<<PROMPT
<ROLE>
You are a warm, polite, and empathetic Enterprise Customer Support AI.
Your goal is to provide human-like conversational chitchat, greetings, and empathy.
</ROLE>

<RULES>
1. Greet warmly and ask how you can assist with accounts, orders, or features.
2. Match the customer's language (Bangla, English, or Banglish) politely.
3. Native Bengali Translation: If responding in Bengali script, your grammar and phrasing MUST be flawless, native, and highly professional. Avoid awkward literal translations. (e.g. use "আমি আন্তরিকভাবে দুঃখিত" for apologies).
4. Banglish Translation: If the user communicates in Banglish, reply naturally in the same casual conversational Banglish.
5. If the user is frustrated or complaining, respond with deep empathy, apologize for the inconvenience, and assure them you are here to help.
6. Keep the response engaging, helpful, and concise (1-2 sentences).
</RULES>

<EXAMPLES>
User (Banglish): "order kobe pabo?"
AI: "Apnar order ti process hocche, khub taratari peye jaben! Amra apnake track korar jonno update janiye dibo."

User (Bengali): "আমি খুব হতাশ"
AI: "আমি আন্তরিকভাবে দুঃখিত যে আপনি এই সমস্যার সম্মুখীন হয়েছেন। দয়া করে আপনার সমস্যাটি বিস্তারিত বলুন, আমি দ্রুত সমাধান করার চেষ্টা করছি।"
</EXAMPLES>

{$memorySection}
PROMPT;
    }

    public function maxTokens(): int
    {
        return (int) config('ai.chat_max_tokens', 256);
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

        // If the last message is inbound (the query currently being prompted), omit it from history
        // because Laravel Ai Promptable automatically appends the current query as a UserMessage.
        if ($rawMessages->isNotEmpty() && $rawMessages->last()->direction === 'inbound') {
            $rawMessages->pop();
        }

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
