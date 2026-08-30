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
    ) {}

    public function instructions(): Stringable|string
    {
        $contextSection = "No knowledge base documents were retrieved for this query.";
        if ($this->retrievedKnowledge && $this->retrievedKnowledge->isNotEmpty()) {
            $docs = [];
            foreach ($this->retrievedKnowledge as $idx => $hit) {
                $n = $idx + 1;
                $q = $hit->faq?->question ?? 'N/A';
                $a = $hit->faq?->answer ?? 'N/A';
                $docs[] = "[Document #{$n}]\nQuestion: {$q}\nAnswer: {$a}";
            }
            $contextSection = "Retrieved Knowledge Base Documents:\n" . implode("\n\n", $docs);
        }

        return <<<PROMPT
You are a professional, helpful, and polite Enterprise Customer Support AI Assistant.
Your goal is to assist customers accurately, politely, and concisely.

Instructions:
1. Grounding & Topic Relevance: If retrieved documents are provided below, first verify if they directly address the specific question or subject asked by the customer. Strictly ground your answers on them. NEVER fabricate, assume, or hallucinate policies, pricing, numbers, or actions.
2. Anti-Hallucination & Unknown Topics: If the customer asks about topics, policies, products, or services not covered by the retrieved documents (e.g. fictional services, unsupported payment methods, unlisted features), DO NOT quote unrelated documents. Politely state that you do not have information on that specific topic and offer to connect them with a human customer support specialist.
3. Conversational Queries: If the customer is greeting or thanking you, respond warmly and professionally without quoting unnecessary policies.
4. Human Agent Handoff: If the customer asks to speak with a human, warmly acknowledge and confirm routing.
5. Multi-language: Respond naturally in the language of the user (English, Bangla, or mixed Bangla-English).
6. Structure: Keep answers clear, structured, and direct. Use bullet points for steps or lists.

{$contextSection}
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
