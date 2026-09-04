<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum WorkspacePermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'workspace.view';
    case CREATE = 'workspace.create';
    case UPDATE = 'workspace.update';
    case DELETE = 'workspace.delete';
}
