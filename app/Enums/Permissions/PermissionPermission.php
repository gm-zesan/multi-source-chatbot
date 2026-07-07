<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum PermissionPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'permission.view';
    case CREATE = 'permission.create';
    case UPDATE = 'permission.update';
    case DELETE = 'permission.delete';
}
