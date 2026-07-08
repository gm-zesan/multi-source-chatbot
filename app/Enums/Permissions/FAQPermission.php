<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum FAQPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'faq.view';
    case CREATE = 'faq.create';
    case UPDATE = 'faq.update';
    case DELETE = 'faq.delete';
}
