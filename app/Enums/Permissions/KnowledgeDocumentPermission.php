<?php

declare(strict_types=1);

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum KnowledgeDocumentPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'document.view';
    case CREATE = 'document.create';
    case UPDATE = 'document.update';
    case DELETE = 'document.delete';
}
