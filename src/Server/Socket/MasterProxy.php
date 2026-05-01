<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketCreationException;
use CrazyGoat\Forklift\Server\Types\ProtocolType;

class MasterProxy implements SocketProxyInterface
{
    private \Socket $sendSocket;

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
        $socket->bind('0.0.0.0', $port);
        $socket->listen(SOMAXCONN);

        $queue = @\msg_get_queue(\ftok(__FILE__, 'M'));

        if ($queue === false) {
            throw new SocketCreationException('Failed to create message queue');
        }

        $streams = @\stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, 0);

        if ($streams === false) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error()),
            );
        }

        $sendSocket = \socket_import_stream($streams[0]);

        if ($sendSocket === false) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error()),
            );
        }

        $this->sendSocket = $sendSocket;

        return $socket;
    }

    public function accept(Socket $socket): Connection
    {
        $connection = $socket->accept();

        $reflection = new \ReflectionProperty(Connection::class, 'resource');
        $acceptedSocket = $reflection->getValue($connection);

        @\socket_sendmsg($this->sendSocket, [
            'iov' => [' '],
            'control' => [
                [
                    'cmsg_level' => \SOL_SOCKET,
                    'cmsg_type' => \SCM_RIGHTS,
                    'cmsg_data' => $acceptedSocket,
                ],
            ],
        ]);

        return $connection;
    }

    public function isSupported(): bool
    {
        return \function_exists('msg_get_queue') && \function_exists('socket_sendmsg');
    }
}
