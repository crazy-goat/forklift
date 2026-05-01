<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Types;

final class ConfigKeys
{
    public const string GROUPS = 'groups';
    public const string LISTENERS = 'listeners';
    public const string NAME = 'name';
    public const string SIZE = 'size';
    public const string PORT = 'port';
    public const string PROTOCOL = 'protocol';
    public const string PROXY = 'proxy';
    public const string GROUP = 'group';
    public const string HANDLER = 'handler';
    public const string TCP_NODELAY = 'tcp_nodelay';
    public const string MAX_HEADER_SIZE = 'max_header_size';
    public const string MAX_BODY_SIZE = 'max_body_size';
    public const string MAX_REQUESTS = 'max_requests';
    public const string MAX_LIFETIME = 'max_lifetime';
    public const string MEMORY_LIMIT = 'memory_limit';
    public const string CONNECTION_TIMEOUT = 'connection_timeout';
    public const string STATS = 'stats';
    public const string STATS_KEY = 'stats_key';
}
