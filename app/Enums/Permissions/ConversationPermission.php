<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum ConversationPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'conversation.view';
    case CREATE = 'conversation.create';
    case UPDATE = 'conversation.update';
    case DELETE = 'conversation.delete';
}
