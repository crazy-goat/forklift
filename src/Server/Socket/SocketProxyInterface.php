<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Types\ProtocolType;

interface SocketProxyInterface
{
    public function createSocket(int $port, ProtocolType $protocol): Socket;

    public function accept(Socket $socket): Connection;

    public function isSupported(): bool;
}
