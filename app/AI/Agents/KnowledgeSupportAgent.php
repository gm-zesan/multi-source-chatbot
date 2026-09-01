<?php

declare(strict_types=1);

namespace App\AI\Agents;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class KnowledgeSupportAgent implements Agent, Conversational
{
    use Promptable;

    public function __construct(
        public readonly ?Conversation $conversation = null,
        public readonly ?Collection $retrievedKnowledge = null,
        public readonly ?string $memoryContext = null,
        public readonly ?string $businessContext = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $businessSection = "";
        if (!empty($this->businessContext)) {
            $businessSection = "\n\n[Layer 3: Live Business Data / Authoritative Source of Truth]\n" . $this->businessContext . "\n";
        }

        $contextSection = "[Layer 1: Knowledge Base]\nNo knowledge base documents were retrieved for this query.";
        if ($this->retrievedKnowledge && $this->retrievedKnowledge->isNotEmpty()) {
            $docs = [];
            foreach ($this->retrievedKnowledge as $idx => $hit) {
                $n = $idx + 1;
                $type = method_exists($hit->faq ?? null, 'documentTypeLabel') ? $hit->faq->documentTypeLabel() : 'FAQ';
                $q = $hit->faq?->question ?? 'N/A';
                $a = $hit->faq?->answer ?? 'N/A';
                $docs[] = "[Document #{$n} ({$type})]\nQuestion: {$q}\nAnswer: {$a}";
            }
            $contextSection = "[Layer 1: Official Knowledge Base Documents]\nRetrieved Knowledge Base Documents:\n" . implode("\n\n", $docs);
        }

        $memorySection = "";
        if (!empty($this->memoryContext)) {
            $memorySection = "\n\n[Layer 2: Customer Conversation Graph Memory (Historical Preferences)]\n" . $this->memoryContext . "\n";
        }

        return <<<PROMPT
You are a professional, helpful, and polite Enterprise Customer Support AI Assistant.
Your goal is to assist customers accurately, politely, and concisely.

Context Hierarchy & Conflict Resolution:
1. Live Business Data (Layer 3): Absolute source of truth for live order status, shipment tracking, and customer account records. Overrides past conversational memory.
2. Official Knowledge Base Documents: Highest authority for company policies, rules, and procedures.
3. Customer Conversation Graph Memory (Layer 2): Grounding for customer's personal preferences (preferred size, color, payment method, discussed items). Respect and maintain these preferences to personalize tone without fabricating policies or overriding live data.

Instructions:
1. Source Verification & Zero Irrelevant Grounding: Before citing or using any retrieved document, strictly verify that it is semantically relevant to the customer's question.
2. Grounded Company-Specific Knowledge: For company-specific information (such as pricing, plans, return policies), strictly ground your response on the relevant retrieved documents.
3. Live Order Inquiries: When live order information is present in Layer 3, provide accurate, reassuring, and precise status and tracking details to the customer.
4. Missing Proprietary Policies: If the customer asks about unsupported operations or unlisted policies, politely offer to connect them with a human specialist.
5. Multi-language: Respond naturally in the customer's language (English, Bengali, or mixed Bengali-English).
6. Conciseness: Keep answers direct and well-structured (under 150-200 words for status inquiries).

{$businessSection}
{$contextSection}
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
