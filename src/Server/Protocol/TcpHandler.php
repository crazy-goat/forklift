<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Protocol;

use CrazyGoat\Forklift\Server\Socket\Connection;

class TcpHandler implements ProtocolHandlerInterface
{
    public function __construct(
        /** @var \Closure(Connection): void|null */
        private readonly ?\Closure $callback = null,
    ) {
    }

    public function handle(Connection $connection): void
    {
        if ($this->callback instanceof \Closure) {
            ($this->callback)($connection);
        }
    }
}
