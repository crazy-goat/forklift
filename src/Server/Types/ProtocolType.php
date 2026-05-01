<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Types;

enum ProtocolType: string
{
    case TCP = 'tcp';
    case HTTP = 'http';
    case WEBSOCKET = 'websocket';
}
