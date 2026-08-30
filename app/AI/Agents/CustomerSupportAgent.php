<?php

declare(strict_types=1);

namespace App\AI\Agents;

use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\Conversation;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * @deprecated
 *
 * Legacy Serial AI Agent (Phase 2E/2F/2G.2 Baseline)
 *
 * USAGE POLICY:
 * ├── Production runtime:           ❌ (DO NOT USE in new controllers, jobs, or listeners)
 * ├── New features:                 ❌ (DO NOT ADD new tools or rules here)
 * ├── Historical benchmarks:        ✅ (Retained for Phase 2F/2G.2 serial baseline evaluation)
 * ├── Existing test compatibility:  ✅ (Retained for CustomerSupportAgent::fake() test fakes)
 * └── Rollback / Reference:         ✅ (Reference for single-agent serial baseline)
 *
 * All live production traffic MUST use App\Services\AI\CustomerSupportService,
 * which routes via HybridRouter to specialized agents (KnowledgeSupportAgent,
 * ConversationalSupportAgent, ActionOrchestratorAgent).
 */
class CustomerSupportAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        public readonly ?Conversation $conversation = null,
        public readonly ?KnowledgeRetrievalTool $retrievalTool = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<PROMPT
You are a professional, helpful, and polite Enterprise Customer Support AI Assistant.
Your goal is to answer customer questions accurately and concisely.

Instructions:
1. Always maintain a polite, empathetic, and professional tone.
2. If the customer is asking about company policies, return rules, delivery times, shipping rates, warranty, or product info, use the KnowledgeRetrievalTool to search the enterprise knowledge base.
3. Ground your answers strictly in the retrieved knowledge base documents. NEVER fabricate, assume, or hallucinate policies, pricing, or details not found in the documentation.
4. If no relevant information is found in the knowledge base, politely state that you do not have that specific information and offer to connect them with a human customer support specialist.
5. If the customer specifically asks to speak with a human or representative, warmly acknowledge and confirm that their request is being routed to an agent.
6. Keep answers structured, clear, and direct. Use bullet points for steps or lists where helpful.
PROMPT;
    }

    /**
     * Get the list of messages comprising the conversation history.
     */
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

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        if ($this->retrievalTool !== null) {
            return [$this->retrievalTool];
        }

        return [];
    }
}
