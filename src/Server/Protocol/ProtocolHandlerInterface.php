<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Protocol;

use CrazyGoat\Forklift\Server\Socket\Connection;

interface ProtocolHandlerInterface
{
    public function handle(Connection $connection): void;
}
