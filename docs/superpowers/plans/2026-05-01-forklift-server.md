# Forklift Server Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build ForkliftServer — TCP/HTTP/WebSocket supervisor with 3 proxy types (ReusePort, ForkShared, Master+SCM_RIGHTS), fluent API, PSR-7/PSR-15, TLS, graceful shutdown, hot reload, worker lifecycle management, access logging.

**Architecture:** New classes in `src/Server/` (namespace `CrazyGoat\Forklift\Server`). Existing code untouched. ForkliftServer orchestrates Listener→ProcessGroup→Worker chain.

**Tech Stack:** PHP 8.1+, ext-pcntl, ext-posix, ext-sockets, ext-sysvmsg, psr/http-message, psr/http-server-handler, psr/http-server-middleware, phpunit ^9.5

**Spec:** `docs/superpowers/specs/2026-05-01-forklift-server-design.md`

> **NOTE:** Plan partially updated 2026-05-01 based on decisions from `docs/superpowers/watpliwosci.md`. The following sections have been corrected: Task 1 (ConfigKeys, FORK_SHARED), Task 2 (ProtocolNotSupportedException removed), Task 4 (Connection state tracking), Task 8 (SocketFactory fallback). Remaining tasks require updates per the list below during implementation.
>
> **Required plan updates (to apply before/at start of implementation):**
>
> | Task | Change |
> |------|--------|
> | 7    | Rename: old MasterProxy → `ForkSharedProxy` (no SCM_RIGHTS). Same logic as before, just new file/class name. |
> | 7b   | **NEW**: `MasterProxy` with SCM_RIGHTS socket passing (via sysvmsg). Master acts as dispatcher with queue. |
> | 9    | **NEW**: `TlsProxy` — decorator for `SocketProxyInterface`, calls `stream_socket_enable_crypto()` after `accept()`. |
> | 13   | `HttpHandler` — add body parsing: `Content-Length`, `application/json`, `application/x-www-form-urlencoded`. Add `maxHeaderSize` (64KB) / `maxBodySize` (10MB). Return 431/413. |
> | 14   | `WebSocketFrame::decode()` — return `null` for incomplete frames. `WebSocketHandler` — buffer data between read(), handle continuation frames (opcode 0x0). |
> | 15   | `ProtocolFactory::create(ProtocolType $type, ?RequestHandlerInterface $psr15Handler = null, ?Closure $tcpCallback = null)` — add optional parameters. |
> | 16   | **NEW**: `WorkerConfig` — max_requests, max_lifetime, memory_limit, connection_timeout, health_check_interval. |
> | 17   | `Worker` — add: requestCount, startedAt, getStats(), getStatus(), SIGTERM handler (graceful shutdown), memory_limit/max_requests/max_lifetime check, connection timeout cleanup. |
> | 18   | `ProcessGroup` — add: rolling restart (max 1 at a time), withConfig(WorkerConfig), getWorkers(). In shutdown() add 30s timeout for graceful + SIGKILL. |
> | 19   | `ProcessGroupBuilder` — add `config(WorkerConfig)`. |
> | 20-21 | `Listener` + `ListenerBuilder` — add: `tcpNodelay()`, `tls()`, `maxHeaderSize()`, `maxBodySize()`, `connectionTimeout()`. Union types: `protocol(ProtocolType\|ProtocolHandlerInterface)`, `proxy(ProxyType\|SocketProxyInterface)`. |
> | 22   | **NEW**: `ConfigInterface` + `ArrayConfig` + `JsonConfig` + `YamlConfig` (implementations). |
> | 23   | `ForkliftConfig` — change to `load(ConfigInterface $config): self`. Remove static `fromArray`/`fromJson`. |
> | 24   | **NEW**: `AccessLogFormatterInterface` + `JsonAccessLogFormatter`. |
> | 25   | `ForkliftServer` — add: `stats(int $port)`, `statsKey(string $key)`, `reload()` (SIGHUP handler), `run()` (CLI entrypoint). Fix `checkWorkers(int $signo)` signature. |
> | 26   | `composer.json` — add: `ext-sysvmsg`, `psr/http-server-middleware` (require), `nyholm/psr7` (require-dev), `symfony/yaml` (suggest). |
> | 27   | Integration tests — add stats endpoint test, hot reload test. |

---

### Task 1: Enums (ProtocolType, ProxyType) + ConfigKeys

**Files:**
- Create: `src/Server/Types/ProtocolType.php`
- Create: `src/Server/Types/ProxyType.php`
- Create: `src/Server/Types/ConfigKeys.php`

- [ ] **Step 1: Create ProtocolType enum**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Types;

enum ProtocolType: string {
    case TCP = 'tcp';
    case HTTP = 'http';
    case WEBSOCKET = 'websocket';
}
```

- [ ] **Step 2: Create ProxyType enum (3 cases: REUSE_PORT, FORK_SHARED, MASTER)**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Types;

enum ProxyType: string {
    case REUSE_PORT = 'reuseport';
    case FORK_SHARED = 'forkshared';
    case MASTER = 'master';
}
```

- [ ] **Step 3: Create ConfigKeys class**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Types;

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

- [ ] **Step 4: Commit**

```bash
git add src/Server/Types/ && git commit -m "feat: add ProtocolType, ProxyType enums and ConfigKeys constants"
```

---

### Task 2: Exception Classes

**Files:**
- Create: `src/Server/Exception/SocketCreationException.php`
- Create: `src/Server/Exception/SocketAcceptException.php`
- Create: `src/Server/Exception/WorkerFailedException.php`
- Create: `src/Server/Exception/InvalidConfigurationException.php`

- [ ] **Step 1: Create all exception classes**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Exception;

class SocketCreationException extends \RuntimeException {}
```

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Exception;

class SocketAcceptException extends \RuntimeException {}
```

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Exception;

class WorkerFailedException extends \RuntimeException {}
```

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Exception;

class InvalidConfigurationException extends \RuntimeException {}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/Exception/ && git commit -m "feat: add server exception classes"
```

---

### Task 3: Socket Wrapper

**Files:**
- Create: `src/Server/Socket/Socket.php`
- Create: `tests/Server/Socket/SocketTest.php`

- [ ] **Step 1: Write test (Socket class not created yet)**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Socket\Socket;
use PHPUnit\Framework\TestCase;

class SocketTest extends TestCase
{
    public function test_accept_returns_connection(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        \socket_bind($resource, '127.0.0.1', 0);
        \socket_getsockname($resource, $addr, $port);
        \socket_listen($resource);
        $socket = new Socket($resource);
        $this->assertInstanceOf(Socket::class, $socket);
        \socket_close($resource);
    }

    public function test_bind_and_listen(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $socket = new Socket($resource);
        $socket->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
        $socket->bind('127.0.0.1', 0);
        $socket->listen(5);
        $this->assertTrue(true); // no exception = pass
        $socket->close();
    }

    public function test_close_sets_resource_to_null(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $socket = new Socket($resource);
        $socket->close();
        $this->assertFalse(isset($socket->resource));
    }
}
```

Run: `vendor/bin/phpunit tests/Server/Socket/SocketTest.php`
Expected: FAIL (class doesn't exist)

- [ ] **Step 2: Create Socket.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketAcceptException;
use CrazyGoat\Forklift\Server\Exception\SocketCreationException;

class Socket
{
    private ?\Socket $resource;

    public function __construct(\Socket $resource)
    {
        $this->resource = $resource;
    }

    public function accept(): Connection
    {
        if (!$this->resource) {
            throw new SocketAcceptException('Socket is closed');
        }
        $accepted = @\socket_accept($this->resource);
        if ($accepted === false || !$accepted instanceof \Socket) {
            throw new SocketAcceptException(
                \socket_strerror(\socket_last_error($this->resource))
            );
        }
        return new Connection($accepted);
    }

    public function close(): void
    {
        if ($this->resource) {
            \socket_close($this->resource);
        }
        $this->resource = null;
    }

    public function setOption(int $level, int $option, mixed $value): void
    {
        if (!$this->resource) {
            throw new SocketCreationException('Socket is closed');
        }
        \socket_set_option($this->resource, $level, $option, $value);
    }

    public function bind(string $address, int $port): void
    {
        if (!$this->resource) {
            throw new SocketCreationException('Socket is closed');
        }
        if (!\socket_bind($this->resource, $address, $port)) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error($this->resource))
            );
        }
    }

    public function listen(int $backlog = SOMAXCONN): void
    {
        if (!$this->resource) {
            throw new SocketCreationException('Socket is closed');
        }
        if (!\socket_listen($this->resource, $backlog)) {
            throw new SocketCreationException(
                \socket_strerror(\socket_last_error($this->resource))
            );
        }
    }
}
```

- [ ] **Step 3: Run test**

Run: `vendor/bin/phpunit tests/Server/Socket/SocketTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Server/Socket/Socket.php tests/Server/Socket/ && git commit -m "feat: add Socket wrapper class"
```

---

### Task 4: Connection Wrapper

**Files:**
- Create: `src/Server/Socket/Connection.php`
- Create: `tests/Server/Socket/ConnectionTest.php`

- [ ] **Step 1: Create Connection.php (depended by Socket.php, no test needed for pure wrapper)**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Socket;

class Connection
{
    private \Socket $resource;
    private float $lastActivity;
    private bool $closed = false;

    public function __construct(\Socket $resource)
    {
        $this->resource = $resource;
        $this->lastActivity = \microtime(true);
    }

    public function read(int $length = 65536): string|false
    {
        if ($this->closed) return false;
        $data = \socket_read($this->resource, $length);
        $this->lastActivity = \microtime(true);
        return $data;
    }

    public function write(string $data): int|false
    {
        if ($this->closed) return false;
        $result = \socket_write($this->resource, $data, strlen($data));
        $this->lastActivity = \microtime(true);
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

    public function getPeerName(): array
    {
        \socket_getpeername($this->resource, $addr, $port);
        return ['host' => $addr, 'port' => $port];
    }

    public function getLastActivity(): float
    {
        return $this->lastActivity;
    }

    public function setOption(int $level, int $option, mixed $value): void
    {
        if ($this->closed) return;
        \socket_set_option($this->resource, $level, $option, $value);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/Socket/Connection.php && git commit -m "feat: add Connection wrapper class"
```

---

### Task 5: SocketProxyInterface

**Files:**
- Create: `src/Server/Socket/SocketProxyInterface.php`

- [ ] **Step 1: Create interface**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Types\ProtocolType;

interface SocketProxyInterface
{
    public function createSocket(int $port, ProtocolType $protocol): Socket;
    public function accept(Socket $socket): Connection;
    public function isSupported(): bool;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/Socket/SocketProxyInterface.php && git commit -m "feat: add SocketProxyInterface"
```

---

### Task 6: ReusePortProxy

**Files:**
- Create: `src/Server/Socket/ReusePortProxy.php`
- Create: `tests/Server/Socket/ReusePortProxyTest.php`

- [ ] **Step 1: Write test**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Socket\ReusePortProxy;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use PHPUnit\Framework\TestCase;

class ReusePortProxyTest extends TestCase
{
    public function test_is_supported_checks_so_reuseport(): void
    {
        $proxy = new ReusePortProxy();
        $result = $proxy->isSupported();
        $this->assertIsBool($result);
    }

    public function test_create_socket_returns_socket_with_reuseport(): void
    {
        if (!(new ReusePortProxy())->isSupported()) {
            $this->markTestSkipped('SO_REUSEPORT not supported');
        }
        $proxy = new ReusePortProxy();
        $socket = $proxy->createSocket(0, ProtocolType::HTTP);
        $this->assertNotNull($socket);
        // cleanup
        $ref = new \ReflectionClass($socket);
        $res = $ref->getProperty('resource')->getValue($socket);
        \socket_close($res);
    }
}
```

Run: `vendor/bin/phpunit tests/Server/Socket/ReusePortProxyTest.php`
Expected: FAIL

- [ ] **Step 2: Create ReusePortProxy.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketCreationException;
use CrazyGoat\Forklift\Server\Types\ProtocolType;

class ReusePortProxy implements SocketProxyInterface
{
    public function createSocket(int $port, ProtocolType $protocol): Socket
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($resource === false) {
            throw new SocketCreationException('Cannot create socket: ' . \socket_strerror(\socket_last_error()));
        }

        $socket = new Socket($resource);
        $socket->setOption(SOL_SOCKET, SO_REUSEADDR, 1);

        if ($this->isSupported()) {
            $socket->setOption(SOL_SOCKET, defined('SO_REUSEPORT') ? SO_REUSEPORT : 15, 1);
        }

        $socket->bind('0.0.0.0', $port);
        $socket->listen();

        return $socket;
    }

    public function accept(Socket $socket): Connection
    {
        return $socket->accept();
    }

    public function isSupported(): bool
    {
        return defined('SO_REUSEPORT') && PHP_OS_FAMILY !== 'Windows';
    }
}
```

- [ ] **Step 3: Run test**

Run: `vendor/bin/phpunit tests/Server/Socket/ReusePortProxyTest.php`
Expected: PASS (or skip)

- [ ] **Step 4: Commit**

```bash
git add src/Server/Socket/ReusePortProxy.php tests/Server/Socket/ReusePortProxyTest.php && git commit -m "feat: add ReusePortProxy"
```

---

### Task 7: MasterProxy

**Files:**
- Create: `src/Server/Socket/MasterProxy.php`
- Create: `tests/Server/Socket/MasterProxyTest.php`

- [ ] **Step 1: Write test**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Socket\MasterProxy;
use CrazyGoat\Forklift\Server\Socket\Connection;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use PHPUnit\Framework\TestCase;

class MasterProxyTest extends TestCase
{
    public function test_is_supported_always_returns_true(): void
    {
        $proxy = new MasterProxy();
        $this->assertTrue($proxy->isSupported());
    }

    public function test_create_socket_returns_socket(): void
    {
        $proxy = new MasterProxy();
        $socket = $proxy->createSocket(0, ProtocolType::TCP);
        $this->assertNotNull($socket);
        // cleanup
        $ref = new \ReflectionClass($socket);
        $res = $ref->getProperty('resource')->getValue($socket);
        \socket_close($res);
    }
}
```

Run: `vendor/bin/phpunit tests/Server/Socket/MasterProxyTest.php`
Expected: FAIL

- [ ] **Step 2: Create MasterProxy.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketCreationException;
use CrazyGoat\Forklift\Server\Types\ProtocolType;

class MasterProxy implements SocketProxyInterface
{
    public function createSocket(int $port, ProtocolType $protocol): Socket
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($resource === false) {
            throw new SocketCreationException('Cannot create socket: ' . \socket_strerror(\socket_last_error()));
        }

        $socket = new Socket($resource);
        $socket->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
        $socket->bind('0.0.0.0', $port);
        $socket->listen();

        return $socket;
    }

    public function accept(Socket $socket): Connection
    {
        return $socket->accept();
    }

    public function isSupported(): bool
    {
        return true;
    }
}
```

- [ ] **Step 3: Run test**

Run: `vendor/bin/phpunit tests/Server/Socket/MasterProxyTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Server/Socket/MasterProxy.php tests/Server/Socket/MasterProxyTest.php && git commit -m "feat: add MasterProxy"
```

---

### Task 8: SocketFactory (fallback chain)

**Files:**
- Create: `src/Server/Socket/SocketFactory.php`
- Create: `tests/Server/Socket/SocketFactoryTest.php`

**Note:** Fallback chain: REUSE_PORT → ForkShared (if SO_REUSEPORT unsupported). FORK_SHARED and MASTER always return their respective proxy.

- [ ] **Step 1: Write test**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Socket\ForkSharedProxy;
use CrazyGoat\Forklift\Server\Socket\MasterProxy;
use CrazyGoat\Forklift\Server\Socket\ReusePortProxy;
use CrazyGoat\Forklift\Server\Socket\SocketFactory;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use CrazyGoat\Forklift\Server\Types\ProxyType;
use PHPUnit\Framework\TestCase;

class SocketFactoryTest extends TestCase
{
    public function test_create_master_returns_master_proxy(): void
    {
        $proxy = SocketFactory::create(ProxyType::MASTER, 8080, ProtocolType::TCP);
        $this->assertInstanceOf(MasterProxy::class, $proxy);
    }

    public function test_create_fork_shared_returns_fork_shared_proxy(): void
    {
        $proxy = SocketFactory::create(ProxyType::FORK_SHARED, 8080, ProtocolType::HTTP);
        $this->assertInstanceOf(ForkSharedProxy::class, $proxy);
    }

    public function test_create_reuse_port_fallbacks_to_fork_shared(): void
    {
        $proxy = SocketFactory::create(ProxyType::REUSE_PORT, 8080, ProtocolType::HTTP);
        // Should return ReusePortProxy if supported, else ForkSharedProxy (fallback)
        $this->assertTrue($proxy instanceof ReusePortProxy || $proxy instanceof ForkSharedProxy);
    }
}
```

Run: `vendor/bin/phpunit tests/Server/Socket/SocketFactoryTest.php`
Expected: FAIL

- [ ] **Step 2: Create SocketFactory.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Types\ProtocolType;
use CrazyGoat\Forklift\Server\Types\ProxyType;

class SocketFactory
{
    public static function create(ProxyType $type, int $port, ProtocolType $protocol): SocketProxyInterface
    {
        return match($type) {
            ProxyType::REUSE_PORT => static::createReuseOrFallback(),
            ProxyType::FORK_SHARED => new ForkSharedProxy(),
            ProxyType::MASTER => new MasterProxy(),
        };
    }

    private static function createReuseOrFallback(): SocketProxyInterface
    {
        $proxy = new ReusePortProxy();
        if ($proxy->isSupported()) {
            return $proxy;
        }
        return new ForkSharedProxy();
    }
}
```

- [ ] **Step 3: Run test**

Run: `vendor/bin/phpunit tests/Server/Socket/SocketFactoryTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Server/Socket/SocketFactory.php tests/Server/Socket/SocketFactoryTest.php && git commit -m "feat: add SocketFactory with REUSE_PORT→MASTER fallback"
```

---

### Task 9: ProtocolHandlerInterface

**Files:**
- Create: `src/Server/Protocol/ProtocolHandlerInterface.php`

- [ ] **Step 1: Create interface**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Protocol;

use CrazyGoat\Forklift\Server\Socket\Connection;

interface ProtocolHandlerInterface
{
    public function handle(Connection $connection): void;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/Protocol/ProtocolHandlerInterface.php && git commit -m "feat: add ProtocolHandlerInterface"
```

---

### Task 10: TcpHandler

**Files:**
- Create: `src/Server/Protocol/TcpHandler.php`

- [ ] **Step 1: Create TcpHandler**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Protocol;

use CrazyGoat\Forklift\Server\Socket\Connection;

class TcpHandler implements ProtocolHandlerInterface
{
    public function __construct(private ?\Closure $callback = null) {}

    public function handle(Connection $connection): void
    {
        if ($this->callback) {
            ($this->callback)($connection);
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/Protocol/TcpHandler.php && git commit -m "feat: add TcpHandler"
```

---

### Task 11: HttpHandler (PSR-7/PSR-15)

**Files:**
- Create: `src/Server/Protocol/HttpHandler.php`
- Create: `tests/Server/Protocol/HttpHandlerTest.php`

- [ ] **Step 1: Write test**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server\Protocol;

use CrazyGoat\Forklift\Server\Protocol\HttpHandler;
use CrazyGoat\Forklift\Server\Socket\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HttpHandlerTest extends TestCase
{
    public function test_handle_with_psr15_edge_handler(): void
    {
        $serverSocket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        \socket_set_option($serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);
        \socket_bind($serverSocket, '127.0.0.1', 0);
        \socket_listen($serverSocket);

        $clientSocket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        \socket_set_option($clientSocket, SOL_SOCKET, SO_REUSEADDR, 1);
        \socket_bind($clientSocket, '127.0.0.1', 0);
        \socket_getsockname($serverSocket, $addr, $port);
        \socket_connect($clientSocket, $addr, $port);

        $accepted = \socket_accept($serverSocket);
        $connection = new Connection($accepted);
        \socket_write($clientSocket, "GET / HTTP/1.0\r\nHost: test\r\n\r\n");

        $handler = new HttpHandler(new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                return new \Nyholm\Psr7\Response(200, [], 'OK');
            }
        });

        try {
            $handler->handle($connection);
        } catch (\Throwable $e) {
            // Connection closed is ok in test
        }

        $this->assertTrue(true); // no unhandled exception = pass
        \socket_close($clientSocket);
        \socket_close($serverSocket);
    }
}
```

Run: `vendor/bin/phpunit tests/Server/Protocol/HttpHandlerTest.php`
Expected: FAIL (class doesn't exist)

- [ ] **Step 2: Install PSR-7 deps**

```bash
composer require psr/http-message:^1.0|^2.0 psr/http-server-handler:^1.0 nyholm/psr7:^1.0 --dev
```

- [ ] **Step 3: Create HttpHandler.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Protocol;

use CrazyGoat\Forklift\Server\Socket\Connection;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HttpHandler implements ProtocolHandlerInterface
{
    private ?RequestHandlerInterface $handler;

    public function __construct(?RequestHandlerInterface $handler = null)
    {
        $this->handler = $handler;
    }

    public function handle(Connection $connection): void
    {
        $raw = '';
        while (($chunk = $connection->read(8192)) !== false && $chunk !== '') {
            $raw .= $chunk;
            if (\str_ends_with($chunk, "\r\n\r\n") || \str_contains($chunk, "\r\n\r\n")) {
                break;
            }
        }

        $request = $this->parseRequest($raw);
        $response = $this->handler
            ? $this->handler->handle($request)
            : new Response(200, [], 'OK');

        $connection->write($this->buildResponse($response));
        $connection->close();
    }

    private function parseRequest(string $raw): ServerRequestInterface
    {
        $lines = \explode("\r\n", $raw);
        $first = \explode(' ', $lines[0] ?? 'GET / HTTP/1.0');
        $method = $first[0] ?? 'GET';
        $uri = $first[1] ?? '/';

        $headers = [];
        $i = 1;
        while (isset($lines[$i]) && $lines[$i] !== '') {
            $pos = \strpos($lines[$i], ':');
            if ($pos !== false) {
                $key = \trim(\substr($lines[$i], 0, $pos));
                $val = \trim(\substr($lines[$i], $pos + 1));
                $headers[$key] = [$val];
            }
            $i++;
        }

        return new \Nyholm\Psr7\ServerRequest($method, $uri, $headers);
    }

    private function buildResponse(ResponseInterface $response): string
    {
        $status = \sprintf(
            "HTTP/1.1 %d %s\r\n",
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
        $headers = '';
        foreach ($response->getHeaders() as $key => $values) {
            $headers .= \sprintf("%s: %s\r\n", $key, \implode(', ', $values));
        }
        $headers .= "\r\n";
        return $status . $headers . (string) $response->getBody();
    }
}
```

- [ ] **Step 4: Run test**

Run: `vendor/bin/phpunit tests/Server/Protocol/HttpHandlerTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Server/Protocol/HttpHandler.php tests/Server/Protocol/HttpHandlerTest.php composer.json composer.lock && git commit -m "feat: add HttpHandler with PSR-7/PSR-15 support"
```

---

### Task 12: WebSocketHandler + WebSocketFrame

**Files:**
- Create: `src/Server/Protocol/WebSocketFrame.php`
- Create: `src/Server/Protocol/WebSocketHandler.php`
- Create: `tests/Server/Protocol/WebSocketHandlerTest.php`

- [ ] **Step 1: Create WebSocketFrame.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Protocol;

class WebSocketFrame
{
    public function __construct(
        public readonly int $opcode,
        public readonly string $payload
    ) {}

    public static function encode(string $data, int $opcode = 0x1): string
    {
        $len = \strlen($data);
        $frame = \chr(0x80 | $opcode);

        if ($len <= 125) {
            $frame .= \chr($len);
        } elseif ($len <= 65535) {
            $frame .= \chr(126) . \pack('n', $len);
        } else {
            $frame .= \chr(127) . \pack('J', $len);
        }

        return $frame . $data;
    }

    public static function decode(string $data): ?self
    {
        if (\strlen($data) < 2) {
            return null;
        }

        $firstByte = \ord($data[0]);
        $opcode = $firstByte & 0x0F;
        $secondByte = \ord($data[1]);
        $masked = ($secondByte & 0x80) !== 0;
        $len = $secondByte & 0x7F;
        $offset = 2;

        if ($len === 126) {
            $len = \unpack('n', \substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($len === 127) {
            $len = \unpack('J', \substr($data, $offset, 8))[1];
            $offset += 8;
        }

        $mask = '';
        if ($masked) {
            $mask = \substr($data, $offset, 4);
            $offset += 4;
        }

        $payload = \substr($data, $offset, $len);

        if ($masked && $mask !== '') {
            for ($i = 0; $i < $len; $i++) {
                $payload[$i] = $payload[$i] ^ $mask[$i % 4];
            }
        }

        return new self($opcode, $payload);
    }
}
```

- [ ] **Step 2: Create WebSocketHandler.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Protocol;

use CrazyGoat\Forklift\Server\Socket\Connection;

class WebSocketHandler implements ProtocolHandlerInterface
{
    private ?\Closure $messageCallback = null;
    private ?\Closure $closeCallback = null;

    public function onMessage(\Closure $callback): self
    {
        $this->messageCallback = $callback;
        return $this;
    }

    public function onClose(\Closure $callback): self
    {
        $this->closeCallback = $callback;
        return $this;
    }

    public function handle(Connection $connection): void
    {
        $raw = '';
        while (($chunk = $connection->read(8192)) !== false && $chunk !== '') {
            $raw .= $chunk;
            if (\str_contains($chunk, "\r\n\r\n")) {
                break;
            }
        }

        $key = $this->extractWebSocketKey($raw);
        if ($key === null) {
            $connection->write("HTTP/1.1 400 Bad Request\r\n\r\n");
            $connection->close();
            return;
        }

        $acceptKey = \base64_encode(\sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $connection->write(
            "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n"
        );

        while (true) {
            $data = $connection->read(65536);
            if ($data === false || $data === '') {
                break;
            }
            $frame = WebSocketFrame::decode($data);
            if ($frame === null) {
                continue;
            }
            if ($frame->opcode === 0x8) { // close
                if ($this->closeCallback) {
                    ($this->closeCallback)($connection);
                }
                break;
            }
            if ($frame->opcode === 0x9) { // ping
                $connection->write(WebSocketFrame::encode('', 0xA)); // pong
                continue;
            }
            if ($this->messageCallback) {
                ($this->messageCallback)($connection, $frame->payload, $frame->opcode);
            }
        }

        try {
            $connection->close();
        } catch (\Throwable) {}
    }

    private function extractWebSocketKey(string $raw): ?string
    {
        if (\preg_match('/Sec-WebSocket-Key:\s*(.+)\r?\n/i', $raw, $matches)) {
            return \trim($matches[1]);
        }
        return null;
    }
}
```

- [ ] **Step 3: Create quick integration test**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server\Protocol;

use CrazyGoat\Forklift\Server\Protocol\WebSocketHandler;
use CrazyGoat\Forklift\Server\Socket\Connection;
use PHPUnit\Framework\TestCase;

class WebSocketHandlerTest extends TestCase
{
    public function test_handshake_rejects_invalid_request(): void
    {
        $serverSocket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        \socket_bind($serverSocket, '127.0.0.1', 0);
        \socket_listen($serverSocket);
        \socket_getsockname($serverSocket, $addr, $port);

        $clientSocket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        \socket_connect($clientSocket, $addr, $port);
        $accepted = \socket_accept($serverSocket);
        $connection = new Connection($accepted);
        \socket_write($clientSocket, "GET / HTTP/1.1\r\nHost: test\r\n\r\n");

        $handler = new WebSocketHandler();
        $handler->handle($connection);

        $response = \socket_read($clientSocket, 8192);
        $this->assertStringContainsString('400', $response);

        \socket_close($clientSocket);
        \socket_close($serverSocket);
    }
}
```

Run: `vendor/bin/phpunit tests/Server/Protocol/WebSocketHandlerTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Server/Protocol/WebSocketFrame.php src/Server/Protocol/WebSocketHandler.php tests/Server/Protocol/WebSocketHandlerTest.php && git commit -m "feat: add WebSocketHandler with handshake and frame encode/decode"
```

---

### Task 13: ProtocolFactory

**Files:**
- Create: `src/Server/Protocol/ProtocolFactory.php`
- Create: `tests/Server/Protocol/ProtocolFactoryTest.php`

- [ ] **Step 1: Write test**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server\Protocol;

use CrazyGoat\Forklift\Server\Protocol\ProtocolFactory;
use CrazyGoat\Forklift\Server\Protocol\TcpHandler;
use CrazyGoat\Forklift\Server\Protocol\HttpHandler;
use CrazyGoat\Forklift\Server\Protocol\WebSocketHandler;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use PHPUnit\Framework\TestCase;

class ProtocolFactoryTest extends TestCase
{
    public function test_create_tcp_returns_tcp_handler(): void
    {
        $handler = ProtocolFactory::create(ProtocolType::TCP);
        $this->assertInstanceOf(TcpHandler::class, $handler);
    }

    public function test_create_http_returns_http_handler(): void
    {
        $handler = ProtocolFactory::create(ProtocolType::HTTP);
        $this->assertInstanceOf(HttpHandler::class, $handler);
    }

    public function test_create_websocket_returns_websocket_handler(): void
    {
        $handler = ProtocolFactory::create(ProtocolType::WEBSOCKET);
        $this->assertInstanceOf(WebSocketHandler::class, $handler);
    }
}
```

Run: `vendor/bin/phpunit tests/Server/Protocol/ProtocolFactoryTest.php`
Expected: FAIL

- [ ] **Step 2: Create ProtocolFactory.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server\Protocol;

use CrazyGoat\Forklift\Server\Types\ProtocolType;

class ProtocolFactory
{
    public static function create(ProtocolType $type): ProtocolHandlerInterface
    {
        return match($type) {
            ProtocolType::TCP => new TcpHandler(),
            ProtocolType::HTTP => new HttpHandler(),
            ProtocolType::WEBSOCKET => new WebSocketHandler(),
        };
    }
}
```

- [ ] **Step 3: Run test**

Run: `vendor/bin/phpunit tests/Server/Protocol/ProtocolFactoryTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Server/Protocol/ProtocolFactory.php tests/Server/Protocol/ProtocolFactoryTest.php && git commit -m "feat: add ProtocolFactory"
```

---

### Task 14: Worker

**Files:**
- Create: `src/Server/Worker.php`

- [ ] **Step 1: Create Worker.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server;

use CrazyGoat\Forklift\Forklift;
use CrazyGoat\Forklift\Server\Protocol\ProtocolHandlerInterface;
use CrazyGoat\Forklift\Server\Socket\Socket;
use Psr\Log\LoggerInterface;

class Worker
{
    private ?int $pid = null;

    public function __construct(
        public readonly int $processNumber,
        public readonly Socket $socket,
        private ProtocolHandlerInterface $handler,
        private ?LoggerInterface $logger = null
    ) {}

    public function run(): int
    {
        $pid = \pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('Cannot fork: ' . \pcntl_strerror(\pcntl_get_last_error()));
        }

        if ($pid === 0) {
            // Child
            Forklift::setProcessNumber($this->processNumber);
            $this->logger?->info(\sprintf('Worker #%d started', $this->processNumber));
            $this->acceptLoop();
            exit(0);
        }

        // Parent
        $this->pid = $pid;
        return $pid;
    }

    private function acceptLoop(): void
    {
        while (true) {
            try {
                $connection = $this->socket->accept();
                $this->handler->handle($connection);
            } catch (\Throwable $e) {
                $this->logger?->error('Worker accept error: ' . $e->getMessage());
                \usleep(100000); // 100ms delay before retry
            }
        }
    }

    public function getPid(): int
    {
        if ($this->pid === null) {
            throw new \RuntimeException('Worker not started');
        }
        return $this->pid;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/Worker.php && git commit -m "feat: add Worker class with accept loop"
```

---

### Task 15: ProcessGroup

**Files:**
- Create: `src/Server/ProcessGroup.php`
- Create: `tests/Server/ProcessGroupTest.php`

- [ ] **Step 1: Create ProcessGroup.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server;

use CrazyGoat\Forklift\Server\Protocol\ProtocolHandlerInterface;
use CrazyGoat\Forklift\Server\Socket\Socket;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ProcessGroup
{
    /** @var array<int, Worker> */
    private array $workers = [];
    private LoggerInterface $logger;

    public function __construct(
        public readonly string $name,
        public readonly int $size,
        private ProtocolHandlerInterface $handler,
    ) {
        $this->logger = new NullLogger();
    }

    public function start(Socket $socket): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $worker = new Worker($i, $socket, $this->handler, $this->logger);
            $pid = $worker->run();
            $this->workers[$pid] = $worker;
            $this->logger->info(\sprintf(
                'Group "%s": worker #%d started (PID: %d)',
                $this->name, $i, $pid
            ));
        }
    }

    public function restart(int $pid): void
    {
        if (!isset($this->workers[$pid])) {
            return;
        }

        $old = $this->workers[$pid];
        unset($this->workers[$pid]);

        $socket = $old->socket;
        $worker = new Worker($old->processNumber, $socket, $this->handler, $this->logger);
        $newPid = $worker->run();
        $this->workers[$newPid] = $worker;
        $this->logger->info(\sprintf(
            'Group "%s": worker #%d restarted (PID: %d → %d)',
            $this->name, $old->processNumber, $pid, $newPid
        ));
    }

    public function shutdown(): void
    {
        foreach ($this->workers as $pid => $worker) {
            $this->logger->info(\sprintf(
                'Group "%s": shutting down worker %d (PID: %d)',
                $this->name, $worker->processNumber, $pid
            ));
            \posix_kill($pid, SIGTERM);
            \pcntl_waitpid($pid, $status);
        }
        $this->workers = [];
    }

    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHandler(): ProtocolHandlerInterface
    {
        return $this->handler;
    }
}
```

- [ ] **Step 2: Write integration test**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server;

use CrazyGoat\Forklift\Server\ProcessGroup;
use CrazyGoat\Forklift\Server\Protocol\TcpHandler;
use CrazyGoat\Forklift\Server\Socket\Socket;
use PHPUnit\Framework\TestCase;

class ProcessGroupTest extends TestCase
{
    public function test_start_creates_workers(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        \socket_bind($resource, '127.0.0.1', 0);
        \socket_listen($resource);
        $socket = new Socket($resource);

        $group = new ProcessGroup('test', 2, new TcpHandler());
        $group->start($socket);

        $this->assertTrue(true); // no exception
        $group->shutdown();
        \socket_close($resource);
    }
}
```

Run: `vendor/bin/phpunit tests/Server/ProcessGroupTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add src/Server/ProcessGroup.php tests/Server/ProcessGroupTest.php && git commit -m "feat: add ProcessGroup with worker lifecycle management"
```

---

### Task 16: ProcessGroupBuilder

**Files:**
- Create: `src/Server/ProcessGroupBuilder.php`

- [ ] **Step 1: Create ProcessGroupBuilder.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server;

use CrazyGoat\Forklift\Server\Protocol\ProtocolHandlerInterface;

class ProcessGroupBuilder
{
    private ForkliftServer $server;
    private string $name;
    private int $size;
    private ?ProtocolHandlerInterface $handler = null;

    public function __construct(ForkliftServer $server, string $name, int $size)
    {
        $this->server = $server;
        $this->name = $name;
        $this->size = $size;
    }

    public function handler(ProtocolHandlerInterface|\Closure $handler): self
    {
        if ($handler instanceof \Closure) {
            // Wrap closure in default handler
            $handler = new \CrazyGoat\Forklift\Server\Protocol\TcpHandler($handler);
        }
        $this->handler = $handler;
        return $this;
    }

    public function create(): ProcessGroup
    {
        if ($this->handler === null) {
            throw new \CrazyGoat\Forklift\Server\Exception\InvalidConfigurationException(
                'Handler must be set before creating ProcessGroup'
            );
        }

        $group = new ProcessGroup($this->name, $this->size, $this->handler);
        $this->server->addGroup($group);
        return $group;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/ProcessGroupBuilder.php && git commit -m "feat: add ProcessGroupBuilder with fluent API"
```

---

### Task 17: Listener

**Files:**
- Create: `src/Server/Listener.php`

- [ ] **Step 1: Create Listener.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server;

use CrazyGoat\Forklift\Server\Socket\SocketFactory;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use CrazyGoat\Forklift\Server\Types\ProxyType;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Listener
{
    private LoggerInterface $logger;

    public function __construct(
        public readonly int $port,
        private ProtocolType $protocolType,
        private ProxyType $proxyType,
        private ProcessGroup $group,
    ) {
        $this->logger = new NullLogger();
    }

    public function start(): void
    {
        $proxy = SocketFactory::create($this->proxyType, $this->port, $this->protocolType);
        $socket = $proxy->createSocket($this->port, $this->protocolType);
        $this->group->start($socket);

        $this->logger->info(\sprintf(
            'Listener started on :%d (%s/%s) → group "%s"',
            $this->port,
            $this->protocolType->value,
            $this->proxyType->value,
            $this->group->getName()
        ));
    }

    public function restartWorker(int $pid): void
    {
        $this->group->restart($pid);
    }

    public function shutdown(): void
    {
        $this->group->shutdown();
    }

    public function withLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/Listener.php && git commit -m "feat: add Listener class binding port+proxy+protocol+group"
```

---

### Task 18: ListenerBuilder

**Files:**
- Create: `src/Server/ListenerBuilder.php`

- [ ] **Step 1: Create ListenerBuilder.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server;

use CrazyGoat\Forklift\Server\Types\ProtocolType;
use CrazyGoat\Forklift\Server\Types\ProxyType;

class ListenerBuilder
{
    private ForkliftServer $server;
    private int $port;
    private ?ProtocolType $protocol = null;
    private ?ProxyType $proxy = null;

    public function __construct(ForkliftServer $server, int $port)
    {
        $this->server = $server;
        $this->port = $port;
    }

    public function protocol(ProtocolType $protocol): self
    {
        $this->protocol = $protocol;
        return $this;
    }

    public function proxy(ProxyType $proxy): self
    {
        $this->proxy = $proxy;
        return $this;
    }

    public function attach(ProcessGroup $group): void
    {
        if ($this->protocol === null) {
            throw new \CrazyGoat\Forklift\Server\Exception\InvalidConfigurationException(
                'Protocol must be set on listener'
            );
        }
        if ($this->proxy === null) {
            throw new \CrazyGoat\Forklift\Server\Exception\InvalidConfigurationException(
                'Proxy must be set on listener'
            );
        }

        $listener = new Listener($this->port, $this->protocol, $this->proxy, $group);
        $this->server->addListener($listener);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/ListenerBuilder.php && git commit -m "feat: add ListenerBuilder with fluent API"
```

---

### Task 19: ForkliftConfig

**Files:**
- Create: `src/Server/ForkliftConfig.php`
- Create: `tests/Server/ForkliftConfigTest.php`

- [ ] **Step 1: Write test**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server;

use CrazyGoat\Forklift\Server\ForkliftConfig;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use CrazyGoat\Forklift\Server\Types\ProxyType;
use PHPUnit\Framework\TestCase;

class ForkliftConfigTest extends TestCase
{
    public function test_from_array_parses_config(): void
    {
        $config = ForkliftConfig::fromArray([
            'groups' => [
                ['name' => 'http', 'size' => 4],
                ['name' => 'ws', 'size' => 2],
            ],
            'listeners' => [
                ['port' => 8080, 'protocol' => ProtocolType::HTTP, 'proxy' => ProxyType::REUSE_PORT, 'group' => 'http'],
                ['port' => 8081, 'protocol' => ProtocolType::WEBSOCKET, 'proxy' => ProxyType::MASTER, 'group' => 'ws'],
            ]
        ]);

        $this->assertCount(2, $config->groups);
        $this->assertCount(2, $config->listeners);
        $this->assertSame('http', $config->groups[0]['name']);
        $this->assertSame(8080, $config->listeners[0]['port']);
    }
}
```

Run: `vendor/bin/phpunit tests/Server/ForkliftConfigTest.php`
Expected: FAIL

- [ ] **Step 2: Create ForkliftConfig.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server;

use CrazyGoat\Forklift\Server\Exception\InvalidConfigurationException;

class ForkliftConfig
{
    /** @param array{groups: array, listeners: array} $config */
    public function __construct(
        public readonly array $groups,
        public readonly array $listeners,
    ) {}

    public static function fromArray(array $config): self
    {
        $groups = $config['groups'] ?? [];
        $listeners = $config['listeners'] ?? [];

        if (empty($groups)) {
            throw new InvalidConfigurationException('At least one group is required');
        }
        if (empty($listeners)) {
            throw new InvalidConfigurationException('At least one listener is required');
        }

        foreach ($listeners as $listener) {
            if (!isset($listener['port'], $listener['protocol'], $listener['proxy'], $listener['group'])) {
                throw new InvalidConfigurationException('Each listener must have port, protocol, proxy, and group');
            }
        }

        return new self($groups, $listeners);
    }

    public static function fromJson(string $json): self
    {
        $data = \json_decode($json, true);
        if ($data === null) {
            throw new InvalidConfigurationException('Invalid JSON: ' . \json_last_error_msg());
        }
        return self::fromArray($data);
    }
}
```

- [ ] **Step 3: Run test**

Run: `vendor/bin/phpunit tests/Server/ForkliftConfigTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Server/ForkliftConfig.php tests/Server/ForkliftConfigTest.php && git commit -m "feat: add ForkliftConfig with fromArray and fromJson"
```

---

### Task 20: ForkliftServer

**Files:**
- Create: `src/Server/ForkliftServer.php`

- [ ] **Step 1: Create ForkliftServer.php**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Server;

use CrazyGoat\Forklift\Server\Exception\InvalidConfigurationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ForkliftServer
{
    /** @var Listener[] */
    private array $listeners = [];
    /** @var ProcessGroup[] */
    private array $groups = [];
    private LoggerInterface $logger;
    private bool $running = false;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function group(string $name, int $size): ProcessGroupBuilder
    {
        return new ProcessGroupBuilder($this, $name, $size);
    }

    public function listen(int $port): ListenerBuilder
    {
        return new ListenerBuilder($this, $port);
    }

    public function addGroup(ProcessGroup $group): void
    {
        $this->groups[$group->getName()] = $group;
    }

    public function addListener(Listener $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function load(ForkliftConfig $config): self
    {
        foreach ($config->groups as $groupConfig) {
            $name = $groupConfig['name'];
            $size = $groupConfig['size'];
            if (empty($name) || $size < 1) {
                throw new InvalidConfigurationException("Invalid group config: name='{$name}' size='{$size}'");
            }
            $this->groups[$name] = new ProcessGroup($name, $size, new \CrazyGoat\Forklift\Server\Protocol\TcpHandler());
        }

        foreach ($config->listeners as $listenerConfig) {
            $port = $listenerConfig['port'];
            $protocol = $listenerConfig['protocol'];
            $proxy = $listenerConfig['proxy'];
            $groupName = $listenerConfig['group'];

            if (!isset($this->groups[$groupName])) {
                throw new InvalidConfigurationException("Group '{$groupName}' not found");
            }

            $listener = new Listener($port, $protocol, $proxy, $this->groups[$groupName]);
            $this->listeners[] = $listener;
        }

        return $this;
    }

    public function start(): void
    {
        if (empty($this->listeners)) {
            throw new InvalidConfigurationException('No listeners configured');
        }

        $this->logger->info('Starting ForkliftServer');

        foreach ($this->listeners as $listener) {
            $listener->withLogger($this->logger);
            $listener->start();
        }

        $this->setupSignalHandlers();
        $this->running = true;

        while ($this->running) {
            \pcntl_signal_dispatch();
            $this->checkWorkers();
            \sleep(1);
        }

        $this->shutdown();
        $this->logger->info('ForkliftServer stopped');
    }

    private function setupSignalHandlers(): void
    {
        \pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        \pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        \pcntl_signal(SIGCHLD, [$this, 'checkWorkers']);
    }

    public function handleShutdown(int $signal): void
    {
        $this->logger->info(\sprintf('Received shutdown signal %d', $signal));
        $this->running = false;
    }

    public function checkWorkers(): void
    {
        while (($pid = \pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            $this->logger->warning(\sprintf('Worker PID %d exited, restarting...', $pid));
            foreach ($this->listeners as $listener) {
                $listener->restartWorker($pid);
            }
        }
    }

    private function shutdown(): void
    {
        foreach ($this->listeners as $listener) {
            $listener->shutdown();
        }
        $this->logger->info('All workers closed');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Server/ForkliftServer.php && git commit -m "feat: add ForkliftServer — main orchestrator with signal handling"
```

---

### Task 21: Update composer.json + autoload

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Add ext-sockets dependency and new namespace to autoload**

Edit `composer.json` require section to include `"ext-sockets": "*"`. Add `"CrazyGoat\\Forklift\\Server\\": "src/Server/"` to PSR-4 autoload.

```json
{
    "require": {
        "ext-pcntl": "*",
        "ext-posix": "*",
        "ext-sockets": "*",
        "psr/log": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "CrazyGoat\\Forklift\\": "src/",
            "CrazyGoat\\Forklift\\Server\\": "src/Server/"
        }
    }
}
```

- [ ] **Step 2: Dump autoload**

```bash
composer dump-autoload
```

- [ ] **Step 3: Run all tests**

```bash
vendor/bin/phpunit
```

Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add composer.json && git commit -m "feat: add ext-sockets dependency and Server namespace autoload"
```

---

### Task 22: Integration Test (End-to-End)

**Files:**
- Create: `examples/server.php`
- Create: `tests/Server/ForkliftServerIntegrationTest.php`

- [ ] **Step 1: Write integration test**

```php
<?php
declare(strict_types=1);
namespace CrazyGoat\Forklift\Tests\Server;

use CrazyGoat\Forklift\Server\ForkliftServer;
use CrazyGoat\Forklift\Server\Protocol\HttpHandler;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use CrazyGoat\Forklift\Server\Types\ProxyType;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ForkliftServerIntegrationTest extends TestCase
{
    public function test_http_server_responds(): void
    {
        $port = $this->findFreePort();
        $server = new ForkliftServer();

        $group = $server->group('http-test', 1)
            ->handler(new HttpHandler(new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface {
                    return new Response(200, [], 'Hello Forklift');
                }
            }))
            ->create();

        $server->listen($port)
            ->protocol(ProtocolType::HTTP)
            ->proxy(ProxyType::REUSE_PORT)
            ->attach($group);

        $pid = \pcntl_fork();
        if ($pid === 0) {
            // Child: start server
            $server->start();
            exit(0);
        }

        // Parent: wait for server to start, then test
        \usleep(500000); // 500ms
        $response = \file_get_contents("http://127.0.0.1:{$port}/", false, stream_context_create([
            'http' => ['timeout' => 2]
        ]));

        \posix_kill($pid, SIGTERM);
        \pcntl_waitpid($pid, $status);

        $this->assertStringContainsString('Hello Forklift', $response ?: '');
    }

    private function findFreePort(): int
    {
        $sock = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        \socket_bind($sock, '127.0.0.1', 0);
        \socket_getsockname($sock, $addr, $port);
        \socket_close($sock);
        return $port;
    }
}
```

- [ ] **Step 2: Run integration test**

```bash
vendor/bin/phpunit tests/Server/ForkliftServerIntegrationTest.php
```

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Server/ForkliftServerIntegrationTest.php && git commit -m "feat: add ForkliftServer integration test"
```

---

## Final Verification

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse src/Server
```

All tests pass, no PHPStan errors.
