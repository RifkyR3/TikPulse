<?php

declare(strict_types=1);

namespace App\Domain\Router\Enums;

enum RouterStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Error = 'error';
    case Unknown = 'unknown';
}
