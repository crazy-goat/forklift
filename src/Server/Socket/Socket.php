<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketAcceptException;
use CrazyGoat\Forklift\Server\Exception\SocketCreationException;

class Socket
{
    public function __construct(private ?\Socket $resource)
    {
    }

    public function accept(): Connection
    {
        if (!$this->resource instanceof \Socket) {
            throw new SocketAcceptException('Socket is closed');
        }

        $accepted = @\socket_accept($this->resource);

        if ($accepted === false) {
            throw new SocketAcceptException(
                \socket_strerror(\socket_last_error($this->resource)),
            );
        }

        return new Connection($accepted);
    }

    public function close(): void
    {
        if ($this->resource instanceof \Socket) {
            \socket_close($this->resource);
        }

        $this->resource = null;
    }

    /** @param array<int, mixed>|int|string $value */
    public function setOption(int $level, int $option, array|int|string $value): void
    {
        if (!$this->resource instanceof \Socket) {
            throw new SocketCreationException('Socket is closed');
        }

        if (\socket_set_option($this->resource, $level, $option, $value) === false) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error($this->resource)),
            );
        }
    }

    public function bind(string $address, int $port): void
    {
        if (!$this->resource instanceof \Socket) {
            throw new SocketCreationException('Socket is closed');
        }

        if (!\socket_bind($this->resource, $address, $port)) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error($this->resource)),
            );
        }
    }

    public function listen(int $backlog = SOMAXCONN): void
    {
        if (!$this->resource instanceof \Socket) {
            throw new SocketCreationException('Socket is closed');
        }

        if (!\socket_listen($this->resource, $backlog)) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error($this->resource)),
            );
        }
    }
}
