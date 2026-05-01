<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Types;

final class ConfigKeys
{
    public const GROUPS = 'groups';
    public const LISTENERS = 'listeners';
    public const NAME = 'name';
    public const SIZE = 'size';
    public const PORT = 'port';
    public const PROTOCOL = 'protocol';
    public const PROXY = 'proxy';
    public const GROUP = 'group';
    public const HANDLER = 'handler';
    public const TCP_NODELAY = 'tcp_nodelay';
    public const MAX_HEADER_SIZE = 'max_header_size';
    public const MAX_BODY_SIZE = 'max_body_size';
    public const MAX_REQUESTS = 'max_requests';
    public const MAX_LIFETIME = 'max_lifetime';
    public const MEMORY_LIMIT = 'memory_limit';
    public const CONNECTION_TIMEOUT = 'connection_timeout';
    public const STATS = 'stats';
    public const STATS_KEY = 'stats_key';
}
