<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum RolePermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'role.view';
    case CREATE = 'role.create';
    case UPDATE = 'role.update';
    case DELETE = 'role.delete';
}
