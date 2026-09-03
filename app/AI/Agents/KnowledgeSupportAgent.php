<?php

declare(strict_types=1);

namespace App\AI\Agents;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class KnowledgeSupportAgent implements Agent, Conversational, HasProviderOptions
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
            $memorySection = "\n\n[Layer 2: Customer Conversation Graph Memory (Historical Preferences)]\n" . $this->memoryContext . "\n";
        }

        return <<<PROMPT
You are a professional Enterprise Customer Support AI Assistant. Assist accurately, politely, and concisely.

Context Hierarchy & Conflict Resolution:
1. Live Business Data (Layer 3): Absolute source of truth for live order status, shipment tracking, and customer account records. Overrides past conversational memory.
2. Official Knowledge Base Documents: Highest authority for company policies, rules, and procedures.
3. Customer Conversation Graph Memory (Layer 2): Grounding for customer preferences without overriding live data.

Rules:
1. Grounding & Verification: Ground company-specific information strictly on relevant Knowledge Base docs. Disregard irrelevant docs.
2. Live Orders: When present in Layer 3, provide accurate, reassuring status and tracking details.
3. Missing Policies: For unlisted policies or unsupported operations, politely offer connection to a human specialist.
4. Language: Match customer's language naturally (English, Bengali, or mixed Banglish).
5. Conciseness & Completeness: Provide complete answers in 2-3 friendly sentences or bullet points (under 80-120 words). Never repeat questions or recite unrequested background.

{$businessSection}
{$contextSection}
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
