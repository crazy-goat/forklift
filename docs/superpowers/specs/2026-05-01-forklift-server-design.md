# Forklift Server - Design Spec

**Date:** 2026-05-01
**Status:** Draft (updated 2026-05-01 — decisions from doubts review)
**Author:** Forklift team

## Overview

Extension of Forklift with **supervisor + TCP/HTTP router on sockets**. The system allows defining worker groups that listen on TCP/HTTP/WebSocket ports with SO_REUSEPORT support or fallback to master proxy.

## Requirements

1. **Supervisor + router** on TCP/HTTP/WebSocket sockets
2. **SO_REUSEPORT** as default load-balancing mechanism (fallback: ForkSharedProxy → MasterProxy)
3. **Multi-port** — ability to listen on multiple ports simultaneously
4. **Worker groups** — one group can be attached to multiple ports
5. **Proxy per port** — each listener has its own proxy type (reuseport/fork-shared/master/custom)
6. **Interfaces + factories** for SocketProxy and ProtocolHandler — custom implementations possible
7. **PSR-7/PSR-15** for HTTP
8. **PSR-7 + WebSocket handshake** for WebSocket
9. **Enums/classes only** — no strings in API
10. **Fluent API** as default, **ConfigInterface** for Array/JSON/YAML
11. **New architecture** — new clean classes, existing files untouched (Forklift.php, ForkliftManager.php, Process.php, ProcessGroup.php etc.) — kept for backward compat

## Architecture

### Existing files (backward compat — DO NOT MODIFY)
```
src/
├── Forklift.php                # Old class — left unchanged
├── ForkliftManager.php         # Old supervisor — left unchanged
├── Process.php                 # Old Process — left unchanged
├── ProcessGroup.php            # Old ProcessGroup — left unchanged
├── Exception/                  # Existing exceptions
└── Log/                        # Existing loggers
```

### New files (new architecture in Server namespace)
```
src/Server/
├── ForkliftServer.php          # Main class - fluent API, lifecycle management
├── ForkliftConfig.php          # Configuration (loads ConfigInterface)
├── ProcessGroup.php            # Worker pool + handler, group management
├── ProcessGroupBuilder.php     # Fluent builder for ProcessGroup
├── Listener.php                # Port + Protocol + Proxy + Group
├── ListenerBuilder.php         # Fluent builder for Listener
├── Worker.php                  # Single worker process
├── Types/
│   ├── ProtocolType.php        # Enum: TCP, HTTP, WEBSOCKET
│   ├── ProxyType.php           # Enum: REUSE_PORT, FORK_SHARED, MASTER
│   └── ConfigKeys.php          # Constants for configuration keys (GROUPS, LISTENERS, ...)
├── Config/
│   ├── ConfigInterface.php     # Configuration source interface
│   ├── ArrayConfig.php         # Configuration from PHP array
│   ├── JsonConfig.php          # Configuration from JSON file
│   └── YamlConfig.php          # Configuration from YAML file
├── Socket/
│   ├── SocketProxyInterface.php
│   ├── ReusePortProxy.php      # SO_REUSEPORT
│   ├── ForkSharedProxy.php     # fork + shared socket (fallback, works everywhere)
│   ├── MasterProxy.php         # SCM_RIGHTS socket passing (master → worker)
│   ├── TlsProxy.php            # Decorator - SSL/TLS after accept()
│   ├── SocketFactory.php
│   ├── Socket.php              # Wrapper around PHP socket resource
│   └── Connection.php          # Connection read/write + state
├── Protocol/
│   ├── ProtocolHandlerInterface.php
│   ├── TcpHandler.php
│   ├── HttpHandler.php         # PSR-7/PSR-15 + body parsing
│   ├── WebSocketHandler.php   # PSR-7 + handshake + frame buffering
│   │   └── WebSocketFrame.php  # WebSocket frame encoder/decoder (+ fragmented)
│   └── ProtocolFactory.php    # Factory with optional handler
├── Log/
│   ├── AccessLogFormatterInterface.php   # Access log formatting interface
│   └── JsonAccessLogFormatter.php        # Default JSON formatter
└── Exception/
    ├── SocketCreationException.php
    ├── SocketAcceptException.php
    ├── WorkerFailedException.php
    └── InvalidConfigurationException.php
```

Namespace: `CrazyGoat\Forklift\Server\*` — separate from old code, no conflicts.

## Key Concepts

### Relationships
- **ForkliftServer** (1) has many **Listeners** (N)
- **Listener** (1) binds: port (1) + proxy (1) + protocol (1) + group (1)
- **ProcessGroup** (1) can be attached to multiple **Listeners** (N)
- **Listener** has its own **SocketProxy** (per port)
- **ProcessGroup** manages a pool of **Workers** (1..N)

### Concurrency Model
Prefork (N workers × 1 connection each). Worker handles one connection at a time — synchronously. User does not need to use event loops. Backpressure relies on SOMAXCONN (ReusePort/ForkShared) or the dispatcher's internal queue (MasterProxy).

### Flow
1. ForkliftServer.start() → creates all Listeners
2. Listener.start() → SocketFactory.create() → SocketProxy.createSocket() → ProcessGroup.start(socket)
3. ProcessGroup.start() → creates N workers via fork() (each worker gets the socket and handler from ProcessGroup)
4. Worker.loop() → Socket.accept() → handler.handle(connection) → (optionally) timeout check / auto-close
5. Worker monitors connection timeouts between requests, closes idle/expired connections

## Enums

```php
enum ProtocolType: string {
    case TCP = 'tcp';
    case HTTP = 'http';
    case WEBSOCKET = 'websocket';
}

enum ProxyType: string {
    case REUSE_PORT = 'reuseport';   // SO_REUSEPORT
    case FORK_SHARED = 'forkshared'; // fork + shared socket (fallback, works everywhere)
    case MASTER = 'master';          // SCM_RIGHTS socket passing
}
```

## ConfigKeys

```php
class ConfigKeys {
    const GROUPS = 'groups';
    const LISTENERS = 'listeners';
    const NAME = 'name';
    const SIZE = 'size';
    const PORT = 'port';
    const PROTOCOL = 'protocol';
    const PROXY = 'proxy';
    const GROUP = 'group';
    const HANDLER = 'handler';
    const TCP_NODELAY = 'tcp_nodelay';
    const MAX_HEADER_SIZE = 'max_header_size';
    const MAX_BODY_SIZE = 'max_body_size';
    const MAX_REQUESTS = 'max_requests';
    const MAX_LIFETIME = 'max_lifetime';
    const MEMORY_LIMIT = 'memory_limit';
    const CONNECTION_TIMEOUT = 'connection_timeout';
    const STATS = 'stats';
    const STATS_KEY = 'stats_key';
}
```

## ConfigInterface

```php
interface ConfigInterface {
    public function toArray(): array;
}
```

Implementations:
- **ArrayConfig** — from PHP array
- **JsonConfig** — from JSON file (accepts file path)
- **YamlConfig** — from YAML file (accepts file path, requires `symfony/yaml`)

Auto-detection order for `--config` (CLI): `forklift.php`, `forklift.json`, `forklift.yaml` — first found.

## Socket and Connection (base classes)

### Socket
Wrapper around PHP `\Socket` resource:
```php
class Socket {
    private ?\Socket $resource;
    public function __construct(\Socket $resource);
    public function accept(): Connection;    // socket_accept → Connection
    public function close(): void;           // socket_close
    public function setOption(int $level, int $option, mixed $value): void;  // socket_set_option
    public function bind(string $address, int $port): void;      // socket_bind
    public function listen(int $backlog = SOMAXCONN): void;      // socket_listen
}
```

### Connection
Wrapper around accepted connection:
```php
class Connection {
    private \Socket $resource;
    private float $lastActivity;         // microtime(true) of last activity
    private bool $closed = false;

    public function __construct(\Socket $resource);
    public function read(int $length = 65536): string|false;   // socket_read
    public function write(string $data): int|false;             // socket_write
    public function close(): void;                              // socket_close, marks closed
    public function isClosed(): bool;                           // whether closed
    public function getPeerName(): array{host,port};            // socket_getpeername
    public function getLastActivity(): float;                   // timestamp
    public function setOption(int $level, int $option, mixed $value): void;
}
```

## Socket Proxy System

### SocketProxyInterface
```php
interface SocketProxyInterface {
    public function createSocket(int $port, ProtocolType $protocol): Socket;
    public function accept(Socket $socket): Connection;
    public function isSupported(): bool;
}
```

### ReusePortProxy
- Creates socket with `SO_REUSEPORT` option
- Each worker binds the same port — kernel distributes traffic
- `isSupported()` checks whether `SO_REUSEPORT` and `socket_create` are available (Linux/macOS, not Windows)

### ForkSharedProxy
- Creates socket with `SO_REUSEADDR` (no SO_REUSEPORT)
- Forked workers inherit the same listening fd
- Kernel round-robins accept between workers — apache mpm_prefork model
- `isSupported()` always returns true — works on every platform

### MasterProxy (SCM_RIGHTS)
- Master creates socket and listens
- Accepts connections and passes them to workers via `SCM_RIGHTS` (socket passing)
- Master acts as dispatcher — can manage queue and return 503 on overflow
- `isSupported()` always returns true

### TlsProxy (decorator)
- Decorator for `SocketProxyInterface`
- After `accept()` calls `stream_socket_enable_crypto()` for TLS
- Certificate configuration per listener: `ssl_certificate`, `ssl_key`, `ssl_ciphers`, `ssl_protocols`
- Enables HTTPS and WSS

### SocketFactory
```php
class SocketFactory {
    public static function create(ProxyType $type, int $port, ProtocolType $protocol): SocketProxyInterface;
}
```
- Creates the appropriate proxy
- Chain fallback: REUSE_PORT → ForkShared (if REUSE_PORT unsupported)
- MASTER: always MasterProxy

## Protocol Handler System

### ProtocolHandlerInterface
```php
interface ProtocolHandlerInterface {
    public function handle(Connection $connection): void;
}
```
Contract: handler MUST close the connection after finishing (or intentionally leave it open for keep-alive — handler's responsibility). Worker monitors connection state and logs warning on leaks.

Note: `createRequest()` removed from interface — not every protocol needs PSR-7. HttpHandler and WebSocketHandler create PSR-7 internally.

### TcpHandler
- Raw TCP — user implements logic via Connection::read()/write()
```php
class TcpHandler implements ProtocolHandlerInterface {
    public function __construct(private ?\Closure $callback = null) {}
    public function handle(Connection $connection): void;
}
```

### HttpHandler
- Parses HTTP request → PSR-7 `ServerRequestInterface`
- Supports PSR-15 middleware (`RequestHandlerInterface`)
- Parses body: `Content-Length` + `Content-Type` (JSON, form-urlencoded, plain text)
- Handles `max_header_size` (default 64KB) and `max_body_size` (default 10MB)
- Returns PSR-7 `ResponseInterface`
- Always closes connection after response (keep-alive as follow-up)

### WebSocketHandler
- Checks Upgrade header (PSR-7)
- Performs WebSocket handshake (RFC 6455)
- Buffers partial frames (handles frames > 64KB split across multiple TCP reads)
- Fragmented frame support (continuation opcode 0x0 — RFC 6455 compliance)
- Ping/pong (responds to client ping, server ping as follow-up)
- After handshake: full WebSocket connection with frame read/write

### ProtocolFactory
```php
class ProtocolFactory {
    public static function create(
        ProtocolType $type,
        ?RequestHandlerInterface $psr15Handler = null,
        ?\Closure $tcpCallback = null
    ): ProtocolHandlerInterface;
}
```
- Creates the appropriate handler
- HTTP: `$psr15Handler` optional — without it handler returns 200 OK by default
- TCP: `$tcpCallback` optional — without it handler ignores traffic

## Worker

### Worker
```php
class Worker {
    private ?int $pid = null;
    private int $requestCount = 0;
    private float $startedAt;          // microtime(true)
    private bool $running = true;
    private array $openConnections = []; // active connections

    public function __construct(
        public readonly int $processNumber,
        public readonly Socket $socket,
        private ProtocolHandlerInterface $handler,
        private ?LoggerInterface $logger = null,
        private ?WorkerConfig $config = null,
    );

    public function run(): int;        // fork() → acceptLoop() in child
    public function getPid(): int;
    public function getStats(): array; // requestCount, memoryUsage, uptime, status
    public function getStatus(): string; // idle | busy | dead

    // acceptLoop() handles:
    // - SIGTERM → graceful shutdown (finish request, exit())
    // - SIGCHLD → ignored in child
    // - memory_limit check before accept()
    // - connection timeout check between requests
    // - max_requests check → self-exit
}
```

### Worker lifecycle
| Mechanism | Default | Description |
|-----------|---------|-------------|
| `max_requests` | 0 (disabled) | Restart after N requests |
| `max_lifetime` | 0 (disabled) | Restart after T seconds |
| `memory_limit` | 0 (disabled) | `memory_get_usage(true) > X` → restart |
| `health_check_interval` | 5s | Check if worker is responding |
| Connection timeout | 30s (TCP), 60s (HTTP keep-alive) | `isClosed()` idle connections |
| Graceful shutdown | SIGTERM → finish request → exit | Timeout 30s → SIGKILL |
| Rolling restart | max 1 at a time | New worker → ready → old SIGTERM |

### Worker stats
```php
Worker::getStats(): array — [
    'process_number' => int,
    'pid' => int,
    'request_count' => int,
    'memory_usage' => int,        // bytes (memory_get_usage(true))
    'uptime' => float,            // seconds
    'status' => 'idle'|'busy'|'dead',
    'open_connections' => int,
]
```

## ProcessGroup

```php
class ProcessGroup {
    private string $name;
    private int $size;
    private ProtocolHandlerInterface $handler;
    private array<int, Worker> $workers; // pid → Worker
    private int $restartingCount = 0;    // number of workers currently restarting

    public function __construct(string $name, int $size, ProtocolHandlerInterface $handler);

    public function start(Socket $socket): void;   // creates N workers
    public function restart(int $pid): void;        // rolling restart (SIGCHLD handler)
    public function shutdown(): void;               // graceful shutdown of all workers
    public function withLogger(LoggerInterface $logger): self;
    public function withConfig(WorkerConfig $config): self; // max_requests, max_lifetime, memory_limit

    public function getName(): string;
    public function getHandler(): ProtocolHandlerInterface;
    public function getWorkers(): array;            // pid → Worker
}
```

## WorkerConfig (per-group worker settings)

```php
class WorkerConfig {
    public function __construct(
        public readonly int $maxRequests = 0,           // 0 = no limit
        public readonly int $maxLifetime = 0,           // seconds, 0 = no limit
        public readonly int $memoryLimit = 0,           // bytes, 0 = no limit
        public readonly float $connectionTimeout = 30.0, // idle seconds → close
        public readonly float $healthCheckInterval = 5.0, // seconds
    ) {}
}
```

## ForkliftServer (main class)

```php
class ForkliftServer {
    private array $listeners = [];
    private array $groups = [];
    private LoggerInterface $logger;
    private bool $running = false;
    private ?int $statsPort = null;
    private ?string $statsKey = null;
    private ?string $configFilePath = null;

    // Fluent API
    public function group(string $name, int $size): ProcessGroupBuilder;
    public function listen(int $port): ListenerBuilder;
    public function addGroup(ProcessGroup $group): void;
    public function addListener(Listener $listener): void;

    // Stats endpoint (optional, disabled by default)
    public function stats(int $port): self;
    public function statsKey(string $key): self;

    // Configuration
    public function load(ConfigInterface $config): self;

    // Lifecycle
    public function start(): void;

    // Hot reload
    public function reload(): void;    // SIGHUP handler — loads new configuration

    // CLI
    public function run(): void;       // parses --config, --daemon, starts
}
```

## ListenerBuilder (fluent API)

```php
class ListenerBuilder {
    public function protocol(ProtocolType|ProtocolHandlerInterface $protocol): self;
    public function proxy(ProxyType|SocketProxyInterface $proxy): self;
    public function attach(ProcessGroup $group): void;
    public function tcpNodelay(bool $enabled = true): self;          // default true
    public function maxHeaderSize(int $bytes): self;                 // default 64KB
    public function maxBodySize(int $bytes): self;                   // default 10MB
    public function tls(string $certFile, string $keyFile): self;    // SSL/TLS
    public function connectionTimeout(float $seconds): self;
}
```

## Access Log

### AccessLogFormatterInterface
```php
interface AccessLogFormatterInterface {
    public function format(string $method, string $path, int $status, int $bytes,
                          float $durationMs, string $peer, array $context = []): string;
}
```

### JsonAccessLogFormatter (default)
```php
// Output: {"time":"...","method":"GET","path":"/","status":200,"bytes":1234,"duration_ms":5,"peer":"127.0.0.1"}
```

User can provide their own implementation per listener.

## Fluent API Examples

### Example 1: HTTP + WebSocket on different ports
```php
$server = new ForkliftServer(new ConsoleLogger());

$httpGroup = $server->group('http', 4)
    ->handler(new HttpHandler($psr15Middleware))
    ->create();

$wsGroup = $server->group('ws', 2)
    ->handler(new WebSocketHandler())
    ->create();

$server->listen(8080)
    ->protocol(ProtocolType::HTTP)
    ->proxy(ProxyType::REUSE_PORT)
    ->tcpNodelay()
    ->attach($httpGroup);

$server->listen(443)
    ->protocol(ProtocolType::HTTP)
    ->proxy(ProxyType::REUSE_PORT)
    ->tls('/etc/certs/cert.pem', '/etc/certs/key.pem')
    ->attach($httpGroup);

$server->listen(8081)
    ->protocol(ProtocolType::WEBSOCKET)
    ->proxy(ProxyType::MASTER)
    ->attach($wsGroup);

// Optional stats endpoint
$server->stats(9099)->statsKey('sekret123');

$server->start();
```

### Example 2: ConfigInterface (ArrayConfig)
```php
$config = new ArrayConfig([
    'groups' => [
        ['name' => 'http', 'size' => 4, 'protocol' => ProtocolType::HTTP, 'handler' => MyHandler::class],
        ['name' => 'ws', 'size' => 2, 'protocol' => ProtocolType::WEBSOCKET],
    ],
    'listeners' => [
        ['port' => 8080, 'protocol' => ProtocolType::HTTP, 'proxy' => ProxyType::REUSE_PORT, 'group' => 'http'],
        ['port' => 80, 'protocol' => ProtocolType::HTTP, 'proxy' => ProxyType::REUSE_PORT, 'group' => 'http'],
        ['port' => 8081, 'protocol' => ProtocolType::WEBSOCKET, 'proxy' => ProxyType::MASTER, 'group' => 'ws'],
    ],
    'tcp_nodelay' => true,
    'max_header_size' => 65536,
]);

$server = new ForkliftServer();
$server->load($config);
$server->start();
```

### Example 3: Custom handler + proxy (union type)
```php
$server->group('custom', 2)
    ->handler(new MyCustomProtocol())
    ->create();

$server->listen(9090)
    ->protocol(new MyCustomProtocol())   // ProtocolHandlerInterface
    ->proxy(new MyCustomProxy())         // SocketProxyInterface
    ->attach($customGroup);
```

### Example 4: Hot reload (SIGHUP)
```php
// CLI: php forklift-server.php --config forklift.json
// SIGHUP → ForkliftServer::reload() — new config, old listeners graceful shutdown
```

### Example 5: Process title
```php
// Worker sets: cli_set_process_title("forklift: worker http #1")
// Visible in ps aux
// Optional, enabled by default
```

## Exception Hierarchy

```php
SocketCreationException       // socket creation error
SocketAcceptException         // connection accept error
WorkerFailedException         // worker could not start
InvalidConfigurationException // invalid configuration
```

## Error Handling

- ForkliftServer.start() - configuration validation before start
- SocketFactory.create() - fallback chain: REUSE_PORT → ForkShared
- ProcessGroup - rolling restart on worker failure (SIGCHLD)
- Worker - graceful shutdown on SIGTERM (finish request, timeout 30s → SIGKILL)
- Worker - memory_limit check before each accept()
- Worker - connection timeout check between requests
- Signal handling:
  - SIGINT, SIGTERM → graceful shutdown of all listeners
  - SIGCHLD → monitor workers, rolling restart
  - SIGHUP → hot reload configuration
- All errors logged via PSR-3 LoggerInterface

## Testing Plan

### Unit tests
- SocketFactory with extension mocking
- ProtocolFactory for each type (with optional handler)
- ArrayConfig / JsonConfig — validation, FQCN handler
- YamlConfig — validation (if symfony/yaml available)
- Listener — mocking socket and proxy
- ListenerBuilder — union type validation (enum + interface)
- ProcessGroup with worker mocking
- ProcessGroupBuilder — state validation
- WorkerConfig — default values
- Connection — state (isClosed, lastActivity)

### Integration tests
- ForkliftServer.start() with real TCP connections
- ReusePortProxy - SO_REUSEPORT verification (Linux/macOS only)
- ForkSharedProxy - fork + shared socket (all platforms)
- MasterProxy - SCM_RIGHTS socket passing
- TlsProxy — TLS handshake + certificate
- HTTP handler - PSR-7 request/response + PSR-15 middleware + body parsing
- WebSocket handler - handshake + frame encode/decode + fragmented frames
- Worker — graceful shutdown, memory_limit, connection timeout
- ProcessGroup — rolling restart

### Acceptance tests
- Multi-port listening (80 + 8080 + 8081 + 443)
- Worker restart on failure (SIGCHLD → rolling restart)
- Graceful shutdown (SIGTERM with timeout)
- Hot reload (SIGHUP → new configuration)
- Fallback chain: REUSE_PORT → ForkShared
- Stats endpoint with key + missing key → 401

## Dependencies

- `ext-pcntl: *` - process forking
- `ext-posix: *` - signal handling, kill, waitpid
- `ext-sockets: *` - **NEW** - socket API (SO_REUSEPORT)
- `ext-sysvmsg: *` - **NEW** - message queue (for SCM_RIGHTS in MasterProxy)
- `psr/log: ^3.0` - logging
- `psr/http-message: ^1.0|^2.0` - **NEW** - PSR-7
- `psr/http-server-handler: ^1.0` - **NEW** - PSR-15 handler
- `psr/http-server-middleware: ^1.0` - **NEW** - PSR-15 middleware interface (interface only)
- `symfony/yaml: ^6.0|^7.0` - **NEW (suggest)** - for YamlConfig (optional)

## Follow-up (beyond MVP)

Detailed follow-up document: `docs/superpowers/plans/follow-up-forklift-server.md`

Key features deferred to future versions:
- HTTP keep-alive
- Transfer-Encoding: chunked
- Multipart/form-data (file upload)
- WebSocket server ping + subprotocol negotiation + broadcast/pub-sub
- Daemon mode + PID file
- Rate limiting / connection limiting
- Unix socket support (AF_UNIX)
- CPU affinity
- Compression (gzip/brotli) — middleware domain
- Query string parsing
- Expect: 100-continue
- Correlation headers (X-Request-Id, Server-Timing)
- SIGUSR1/SIGUSR2 administrative signals
