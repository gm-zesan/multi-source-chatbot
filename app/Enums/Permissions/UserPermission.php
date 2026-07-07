<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum UserPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'user.view';
    case CREATE = 'user.create';
    case UPDATE = 'user.update';
    case DELETE = 'user.delete';
}
