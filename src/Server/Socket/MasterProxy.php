<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketAcceptException;
use CrazyGoat\Forklift\Server\Exception\SocketCreationException;
use CrazyGoat\Forklift\Server\Types\ProtocolType;

/**
 * Dispatcher proxy that uses SCM_RIGHTS (socket fd passing) over a Unix socket
 * pair to hand accepted connections to worker processes.  A System V message
 * queue (sysvmsg) is created for worker-coordination signalling.
 */
class MasterProxy implements SocketProxyInterface
{
    /**
     * Master → Worker end of the Unix socket pair.
     * SCM_RIGHTS ancillary data with accepted fds is sent through this socket.
     */
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
            $error = \error_get_last();

            throw new SocketCreationException(
                \is_array($error) ? $error['message'] : 'stream_socket_pair failed',
            );
        }

        $sendSocket = \socket_import_stream($streams[0]);

        if ($sendSocket === false) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error()),
            );
        }

        $receiveSocket = \socket_import_stream($streams[1]);

        if ($receiveSocket === false) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error()),
            );
        }

        $this->sendSocket = $sendSocket;

        return $socket;
    }

    public function accept(Socket $socket): Connection
    {
        $listeningReflection = new \ReflectionProperty(Socket::class, 'resource');
        $listeningResource = $listeningReflection->getValue($socket);

        if (!$listeningResource instanceof \Socket) {
            throw new SocketAcceptException('Socket is closed');
        }

        $accepted = @\socket_accept($listeningResource);

        if ($accepted === false) {
            throw new SocketAcceptException(
                \socket_strerror(\socket_last_error($listeningResource)),
            );
        }

        // Dispatch the accepted fd to workers via SCM_RIGHTS.
        // The @ silences platform-specific warnings (e.g. macOS PHP builds
        // that do not support SCM_RIGHTS in socket_sendmsg).
        $dispatched = @\socket_sendmsg($this->sendSocket, [
            'iov' => [' '],
            'control' => [
                [
                    'cmsg_level' => \SOL_SOCKET,
                    'cmsg_type' => \SCM_RIGHTS,
                    'cmsg_data' => $accepted,
                ],
            ],
        ]);

        if ($dispatched === false) {
            // SCM_RIGHTS not supported on this platform;
            // the connection remains in the master process.
        }

        return new Connection($accepted);
    }

    public function isSupported(): bool
    {
        return \function_exists('msg_get_queue') && \function_exists('socket_sendmsg');
    }
}
