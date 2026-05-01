<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Socket;

class Connection
{
    private float $lastActivity;
    private bool $closed = false;

    public function __construct(private readonly \Socket $resource)
    {
        $this->lastActivity = \microtime(true);
    }

    public function read(int $length = 65536): string|false
    {
        if ($this->closed) {
            return false;
        }

        $data = \socket_read($this->resource, $length);

        if ($data !== false) {
            $this->lastActivity = \microtime(true);
        }

        return $data;
    }

    public function write(string $data): int|false
    {
        if ($this->closed) {
            return false;
        }

        $result = \socket_write($this->resource, $data, strlen($data));

        if ($result !== false) {
            $this->lastActivity = \microtime(true);
        }

        return $result;
    }

    public function close(): void
    {
        if (!$this->closed) {
            \socket_close($this->resource);
            $this->closed = true;
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /** @return array{host: string, port: int} */
    public function getPeerName(): array
    {
        if ($this->closed) {
            throw new \RuntimeException('Connection is closed');
        }

        if (!\socket_getpeername($this->resource, $addr, $port)) {
            throw new \RuntimeException('Failed to get peer name');
        }

        /** @var string $addr */
        /** @var int $port */
        return ['host' => $addr, 'port' => $port];
    }

    public function getLastActivity(): float
    {
        return $this->lastActivity;
    }

    /** @param array<int, mixed>|int|string $value */
    public function setOption(int $level, int $option, array|int|string $value): void
    {
        if ($this->closed) {
            return;
        }

        if (\socket_set_option($this->resource, $level, $option, $value) === false) {
            throw new \RuntimeException(
                \socket_strerror(\socket_last_error($this->resource)),
            );
        }
    }
}
