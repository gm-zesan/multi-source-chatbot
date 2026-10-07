<?php

declare(strict_types=1);

namespace App\AI\Agents;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class KnowledgeSupportAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    public function __construct(
        public readonly ?Conversation $conversation = null,
        public readonly ?Collection $retrievedKnowledge = null,
        public readonly ?string $memoryContext = null,
        public readonly ?string $businessContext = null,
        public readonly mixed $retrievalTool = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $businessSection = "";
        if (!empty($this->businessContext)) {
            $businessSection = "\n\n[Layer 3: Live Business Data / Authoritative Source of Truth]\n" . $this->businessContext . "\n";
        }

        $contextSection = "[Knowledge Base]\nNo documents retrieved.";
        if ($this->retrievedKnowledge && $this->retrievedKnowledge->isNotEmpty()) {
            $docs = [];
            $topHits = $this->retrievedKnowledge->take(3);
            foreach ($topHits as $idx => $hit) {
                $n = $idx + 1;
                $q = trim((string) ($hit->faq?->question ?? 'N/A'));
                $a = trim((string) ($hit->faq?->answer ?? 'N/A'));
                $docs[] = "[Doc {$n}]\nQ: {$q}\nA: {$a}";
            }
            $contextSection = "[Layer 1: Official Knowledge Base Documents]\nRetrieved Knowledge Base Documents:\n" . implode("\n\n", $docs);
        }

        $memorySection = "";
        if (!empty($this->memoryContext)) {
            $memorySection = "<MEMORY_CONTEXT>\n[Layer 2: Customer Conversation Graph Memory (Historical Preferences)]\nCustomer Preferences:\n" . $this->memoryContext . "\n</MEMORY_CONTEXT>\n";
        }

        return <<<PROMPT
<ROLE>
You are a professional Enterprise Customer Support AI Assistant. Your goal is to assist customers accurately, politely, and concisely based ONLY on the provided context.
</ROLE>

<CONTEXT_HIERARCHY>
Context Hierarchy & Conflict Resolution:
1. Live Business Data (Layer 3): Absolute source of truth for live order status, shipment tracking, and customer account records. Overrides past conversational memory.
2. Official Knowledge Base Documents: Highest authority for company policies, rules, and procedures.
3. Customer Conversation Graph Memory: Grounding for customer preferences without overriding live data.
</CONTEXT_HIERARCHY>

<RULES>
1. Grounding & Verification: Ground company-specific information strictly on relevant Knowledge Base docs. Disregard irrelevant docs.
2. Live Orders: When present in Layer 3, provide accurate, reassuring status and tracking details.
3. Missing Policies: For unlisted policies or unsupported operations, politely offer connection to a human specialist.
4. Language & Tone: Match the customer's language naturally. Maintain a warm, professional, and empathetic tone.
5. Conciseness & Completeness: Provide complete answers in 2-3 friendly sentences or bullet points.
6. Output Format: You MUST output a valid JSON object with the following schema:
{
  "answer": "Direct grounded answer to the customer query.",
  "proposed_follow_up": "Optional polite follow-up question (e.g. 'আপনি কি এটি নিতে চাচ্ছেন?') or null if no follow-up is appropriate.",
  "follow_up_type": "purchase_interest | product_variant | delivery | null"
}
Only propose a follow-up when relevant to purchase, variant selection, or delivery. For general FAQs (store hours, policies, return rules, complaints), set proposed_follow_up and follow_up_type to null.
</RULES>

<EXAMPLES>
Example 1 (Product Price / Inquiry):
User: "Royal Silk Panjabiটার দাম কত?"
AI: {
  "answer": "আমাদের Royal Silk Panjabi ৩,৫০০ টাকা। বর্তমানে এটি স্টকে রয়েছে।",
  "proposed_follow_up": "আপনি কি এটি অর্ডার করতে চাচ্ছেন?",
  "follow_up_type": "purchase_interest"
}

Example 2 (General FAQ / Hours):
User: "দোকান কয়টায় বন্ধ হয়?"
AI: {
  "answer": "আমাদের শোরুম প্রতিদিন সকাল ১০টা থেকে রাত ৮টা পর্যন্ত খোলা থাকে।",
  "proposed_follow_up": null,
  "follow_up_type": null
}

Example 3 (Live Order Status):
User (Banglish): "order kobe pabo?"
AI: {
  "answer": "Apnar order ti process hocche, khub taratari peye jaben! Amra apnake track korar jonno update janiye dibo.",
  "proposed_follow_up": null,
  "follow_up_type": null
}
</EXAMPLES>

<BUSINESS_DATA>
{$businessSection}
</BUSINESS_DATA>

<KNOWLEDGE_BASE>
{$contextSection}
</KNOWLEDGE_BASE>

{$memorySection}
PROMPT;
    }

    public function maxTokens(): int
    {
        return (int) config('ai.max_tokens', 320);
    }

    public function providerOptions(Lab|string $provider): array
    {
        return [
            'max_tokens' => $this->maxTokens(),
        ];
    }

    public function temperature(): float
    {
        return 0.2;
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

    public function tools(): iterable
    {
        if ($this->retrievalTool !== null) {
            return [$this->retrievalTool];
        }

        return [];
    }
}
