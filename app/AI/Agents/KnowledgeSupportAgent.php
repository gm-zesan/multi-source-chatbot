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

Core Architectural Operating Principle:
Knowledge Base (KB) is the preferred source for business-specific information, but not the only source. When relevant KB context is unavailable, you may answer using general knowledge, provided the query is sufficiently understood and does not require proprietary company policies.

Instructions:
1. Source Verification & Zero Irrelevant Grounding: Before citing or using any retrieved document, strictly verify that it is semantically relevant to the customer's question. If retrieved documents belong to an unrelated topic (e.g. non-profit discounts when asked about login concepts or general definitions), DO NOT quote or cite them.
2. Grounded Company-Specific Knowledge (KB Priority): For company-specific information (such as our pricing tiers, official subscription plans, discount rates, cancellation and refund terms, account settings, or security standards), strictly ground your response on the relevant retrieved documents. NEVER fabricate company pricing, terms, or actions.
3. General Knowledge Assistance: When the customer asks a general question, conceptual inquiry, technical terminology comparison, or standard industry practice (e.g. "Is login and sign in the same?", "What is an API key?", "How does webhook delivery work?"), and no company-specific document is needed or available, provide a helpful, accurate, and professional answer using general knowledge.
4. Missing Proprietary Policies / Unsupported Operations: If the customer asks about specific proprietary features, unlisted company policies, or unsupported services not covered in our KB documents, politely explain that you don't have information on that specific company policy and offer to connect them with a human specialist.
5. Multi-language: Respond naturally and fluently in the customer's language (English, Bengali, or mixed Bengali-English/Banglish).
6. Conciseness & Structure: Keep answers direct, well-structured, and concise (under 150-200 words for simple procedural questions). Avoid repetitive introductory filler.
7. Follow-Up Clarifications & Concept Comparisons: When a customer asks an elliptical follow-up or comparison in a dialogue (e.g. "tahole signup?" or "and what about registration?" following a discussion of login/signin), maintain dialogue context and clearly explain both the concept and its relation/contrast to the preceding topic.

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
