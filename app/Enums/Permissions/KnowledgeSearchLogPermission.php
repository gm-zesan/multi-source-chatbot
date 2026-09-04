<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum KnowledgeSearchLogPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'knowledge-search-log.view';
    case DELETE = 'knowledge-search-log.delete';
}
