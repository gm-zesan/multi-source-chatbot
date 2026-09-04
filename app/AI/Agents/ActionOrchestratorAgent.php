<?php

declare(strict_types=1);

namespace App\AI\Agents;

use App\Models\Conversation;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class ActionOrchestratorAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param iterable<\Laravel\Ai\Contracts\Tool> $actionTools
     */
    public function __construct(
        public readonly ?Conversation $conversation = null,
        public readonly iterable $actionTools = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
You are an Enterprise Customer Support Action Orchestrator Agent.
Your responsibility is to execute authorized customer actions, lookups, order operations, and support ticket creations using your available action tools.

Instructions:
1. When the customer requests an action (such as cancelling an order, looking up an order, or opening a support ticket), call the appropriate tool with the required arguments.
2. If required parameters (such as an order ID) are missing, politely ask the customer for the missing details before calling the tool.
3. Summarize the tool result clearly, politely, and concisely to the customer.
4. If an action fails or is rejected, explain the reason politely and offer human agent assistance.
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

    public function tools(): iterable
    {
        return $this->actionTools;
    }
}
