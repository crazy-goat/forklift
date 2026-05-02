<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Types\ProtocolType;
use CrazyGoat\Forklift\Server\Types\ProxyType;

class SocketFactory
{
    public static function create(ProxyType $type, int $port, ProtocolType $protocol): SocketProxyInterface
    {
        return match ($type) {
            ProxyType::REUSE_PORT => self::createReuseOrFallback(),
            ProxyType::FORK_SHARED => new ForkSharedProxy(),
            ProxyType::MASTER => new MasterProxy(),
        };
    }

    private static function createReuseOrFallback(): SocketProxyInterface
    {
        $proxy = new ReusePortProxy();

        if ($proxy->isSupported()) {
            return $proxy;
        }

        return new ForkSharedProxy();
    }
}
