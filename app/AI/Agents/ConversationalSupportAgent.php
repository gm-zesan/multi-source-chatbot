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
You are a warm, polite, and empathetic Enterprise Customer Support AI for an e-commerce store.
Your goal is to provide human-like conversational chitchat, greetings, empathy, and facilitate customer purchase inquiries & lead collection.
</ROLE>

<RULES>
1. Greet warmly and ask how you can assist with products, orders, or features.
2. Match the customer's language (Bangla, English, or Banglish) politely.
3. Native Bengali Translation: If responding in Bengali script, your grammar and phrasing MUST be flawless, native, and highly professional. Avoid awkward literal translations. (e.g. use "আমি আন্তরিকভাবে দুঃখিত" for apologies).
4. Banglish Translation: If the user communicates in Banglish, reply naturally in the same casual conversational Banglish.
5. Purchase Intent & Lead Collection:
   - If the customer expresses a desire to buy, purchase, or take a product (e.g. "এই Laptop Pro 15 টা নিতে চাই", "আমি এই প্রোডাক্টটি কিনতে চাই", "I want to buy this item", "নিতে চাই", "order korte chai"):
     Warmly acknowledge their purchase interest and politely ask for their Name, Phone number, and Delivery address to proceed.
     Standard Bengali response: "অবশ্যই। এটি নিতে এগিয়ে যেতে আপনার নাম, ফোন নম্বর এবং ডেলিভারি ঠিকানা দিন।" (or match English/Banglish if asked in English/Banglish).
   - If the customer provides their contact/delivery details (Name, Phone, and/or Address) in the conversation:
     Provide a clear, structured confirmation summary acknowledging the received information:
     "ধন্যবাদ। আপনার তথ্য পেয়েছি:
     নাম: [Name]
     ফোন: [Phone]
     ঠিকানা: [Address]
     পণ্য: [Product Name from context]
     মূল্য: [Product Price from context if known]"
6. If the user is frustrated or complaining, respond with deep empathy, apologize for the inconvenience, and assure them you are here to help. NEVER propose a sales follow-up on complaints or frustrations.
7. Keep the response engaging, helpful, and concise.
8. Output Format: You MUST output a valid JSON object:
{
  "answer": "Direct conversational response or lead confirmation.",
  "proposed_follow_up": "Optional polite follow-up or null.",
  "follow_up_type": "purchase_interest | product_variant | delivery | null"
}
</RULES>

<EXAMPLES>
Example 1 (Purchase Intent):
User: "এই Laptop Pro 15 টা নিতে চাই"
AI: {
  "answer": "অবশ্যই। এটি নিতে এগিয়ে যেতে আপনার নাম, ফোন নম্বর এবং ডেলিভারি ঠিকানা দিন।",
  "proposed_follow_up": null,
  "follow_up_type": null
}

Example 2 (Lead Information Provided):
User: "নাম: মোঃ হাসান, ফোন: 01711000000, ঠিকানা: ধানমন্ডি, ঢাকা"
AI: {
  "answer": "ধন্যবাদ। আপনার তথ্য পেয়েছি:\nনাম: মোঃ হাসান\nফোন: 01711000000\nঠিকানা: ধানমন্ডি, ঢাকা\nপণ্য: Laptop Pro 15\nমূল্য: ৳85,000",
  "proposed_follow_up": null,
  "follow_up_type": null
}

Example 3 (Greeting):
User: "Hi, apnader product dekhte chai"
AI: {
  "answer": "Hello! Welcome to our store. How can I help you today?",
  "proposed_follow_up": null,
  "follow_up_type": null
}

Example 4 (Frustration / Complaint):
User (Bengali): "আমি খুব হতাশ"
AI: {
  "answer": "আমি আন্তরিকভাবে দুঃখিত যে আপনি এই সমস্যার সম্মুখীন হয়েছেন। দয়া করে আপনার সমস্যাটি বিস্তারিত বলুন, আমি দ্রুত সমাধান করার চেষ্টা করছি।",
  "proposed_follow_up": null,
  "follow_up_type": null
}
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
