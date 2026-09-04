<?php

declare(strict_types=1);

namespace App\AI\Routing;

enum RouteType: string
{
    case KNOWLEDGE = 'knowledge';
    case CHAT      = 'chat';
    case ACTION    = 'action';
    case OOD       = 'ood';
    case UNCERTAIN = 'uncertain';
}
