<?php

declare(strict_types=1);

namespace App\Domain\Router\Enums;

enum RouterProtocol: string
{
    case Socket = 'socket';
    case SocketSsl = 'socket_ssl';
    case RestHttps = 'rest_https';

    public function defaultPort(): int
    {
        return match ($this) {
            self::Socket => 8728,
            self::SocketSsl => 8729,
            self::RestHttps => 443,
        };
    }
}
