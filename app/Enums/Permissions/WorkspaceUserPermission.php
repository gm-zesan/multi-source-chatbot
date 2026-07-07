<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum WorkspaceUserPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'workspace-user.view';
    case CREATE = 'workspace-user.create';
    case UPDATE = 'workspace-user.update';
    case DELETE = 'workspace-user.delete';
}
