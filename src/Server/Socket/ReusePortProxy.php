<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketCreationException;
use CrazyGoat\Forklift\Server\Types\ProtocolType;

class ReusePortProxy implements SocketProxyInterface
{
    public function createSocket(int $port, ProtocolType $protocol): Socket
    {
        $resource = @\socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($resource === false) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error()),
            );
        }

        $socket = new Socket($resource);

        $socket->setOption(SOL_SOCKET, SO_REUSEADDR, 1);

        if ($this->isSupported()) {
            $socket->setOption(SOL_SOCKET, SO_REUSEPORT, 1);
        }

        $socket->bind('0.0.0.0', $port);
        $socket->listen(SOMAXCONN);

        return $socket;
    }

    public function accept(Socket $socket): Connection
    {
        return $socket->accept();
    }

    public function isSupported(): bool
    {
        return defined('SO_REUSEPORT') && PHP_OS_FAMILY !== 'Windows';
    }
}
