<?php

declare(strict_types=1);

namespace App\AI\Decision\DTOs;

use App\Models\Conversation;
use App\Models\User;

/**
 * Preflight-evaluated Request DTO passed to UnifiedDecisionEngine.
 */
class DecisionRequest
{
    /**
     * @param string $query Natural language user input
     * @param int $workspaceId Trusted tenant workspace identifier
     * @param User|null $user Authenticated actor
     * @param Conversation|null $conversation Active conversation model
     * @param string $channelSlug Identified communication channel (e.g. 'simulator', 'whatsapp', 'web_widget')
     * @param bool $isCustomer Whether this request originates from an untrusted public customer channel
     * @param string|null $compactContext KGM Compact Topic State & substantive turns
     * @param array<int, string> $availableTools List of permitted tool names for this caller
     */
    public function __construct(
        public readonly string $query,
        public readonly int $workspaceId,
        public readonly ?User $user = null,
        public readonly ?Conversation $conversation = null,
        public readonly string $channelSlug = 'simulator',
        public readonly bool $isCustomer = false,
        public readonly ?string $compactContext = null,
        public readonly array $availableTools = [],
    ) {}
}

