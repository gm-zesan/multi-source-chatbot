<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum ChannelPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'channel.view';
    case CREATE = 'channel.create';
    case UPDATE = 'channel.update';
    case DELETE = 'channel.delete';
}
