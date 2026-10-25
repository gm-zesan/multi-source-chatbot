<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\RoleEnum;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait AuthorizesConversationAccess
{
    /**
     * Ensure the conversation belongs to the authenticated user's workspace context.
     * Fails closed with 404 if the conversation has no valid channel account or workspace.
     * Fails closed with 403 if the user belongs to another workspace and is not a superadmin.
     */
    protected function authorizeConversation(Conversation $conversation): void
    {
        $conversation->loadMissing('channelAccount');

        $workspaceId = ($conversation->channelAccount?->workspace_id !== null && (int) $conversation->channelAccount->workspace_id > 0)
            ? (int) $conversation->channelAccount->workspace_id
            : null;

        if ($workspaceId === null) {
            abort(404, 'Conversation has no valid workspace association.');
        }

        $this->authorizeWorkspace($workspaceId);
    }

    /**
     * Ensure the target workspace matches the authenticated user's workspace context.
     * Superadmins are allowed across all workspaces.
     */
    protected function authorizeWorkspace(int $workspaceId): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return;
        }

        if (! $user || (int) $user->workspace_id !== $workspaceId) {
            abort(403, 'Unauthorized access to conversation from another workspace.');
        }
    }
}
