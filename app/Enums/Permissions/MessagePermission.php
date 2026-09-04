<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum MessagePermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'message.view';
    case CREATE = 'message.create';
    case UPDATE = 'message.update';
    case DELETE = 'message.delete';
}
