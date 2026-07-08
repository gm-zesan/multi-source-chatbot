<?php

namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum UnansweredQuestionPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'unanswered-question.view';
    case UPDATE = 'unanswered-question.update';
    case DELETE = 'unanswered-question.delete';
}
