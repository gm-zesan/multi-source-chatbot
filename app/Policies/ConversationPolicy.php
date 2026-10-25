<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view the conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->canAccess($user, $conversation);
    }

    /**
     * Determine whether the user can export the conversation.
     */
    public function export(User $user, Conversation $conversation): bool
    {
        return $this->canAccess($user, $conversation);
    }

    /**
     * Determine whether the user can access the conversation in their workspace context.
     */
    private function canAccess(User $user, Conversation $conversation): bool
    {
        $conversation->loadMissing('channelAccount');

        $workspaceId = ($conversation->channelAccount?->workspace_id !== null && (int) $conversation->channelAccount->workspace_id > 0)
            ? (int) $conversation->channelAccount->workspace_id
            : null;

        if ($workspaceId === null) {
            return false;
        }

        if ($user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return true;
        }

        return (int) $user->workspace_id === $workspaceId;
    }
}
