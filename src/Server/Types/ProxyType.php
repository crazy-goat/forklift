<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Types;

enum ProxyType: string
{
    case REUSE_PORT = 'reuse_port';
    case FORK_SHARED = 'fork_shared';
    case MASTER = 'master';
}
