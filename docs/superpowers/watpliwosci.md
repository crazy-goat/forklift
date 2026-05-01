# Forklift Server — Spec vs Plan Gaps & Decisions

Date: 2026-05-01

---

## 🔴 Specification gaps (`specs/2026-05-01-forklift-server-design.md`)

### 1. Missing `ConfigKeys` definition
The plan's architecture tree (Task 1) lists `src/Server/Types/ConfigKeys.php`, but the spec doesn't mention this class — it's neither in the directory tree nor in the enum descriptions. **Decision:** needed — add to spec.

### 2. Builder union types: enums or interfaces?
Example 3 in the spec shows:
```php
$server->listen(9090)->proxy(new MyCustomProxy())->attach($group);
```
But `ListenerBuilder::proxy()` in the plan only accepts `ProxyType` enum. Same for `protocol()` — Example 3 shows `new MyCustomProtocol()`, but the builder only takes `ProtocolType`.

**Decision:** both — builders accept union types `ProtocolType|ProtocolHandlerInterface` and `ProxyType|SocketProxyInterface`.

### 3. `fromYaml` mentioned but doesn't exist
Testing Plan section: *"ForkliftConfig — fromArray/fromJson/fromYaml with validation"*. Neither the ForkliftConfig spec nor plan implement `fromYaml`.

**Decision:** add `YamlConfig` via `ConfigInterface` (requires `symfony/yaml` as suggest).

### 4. `ProtocolNotSupportedException` — dead code
Exception defined in hierarchy but never thrown anywhere in plan or spec. No handler or proxy checks whether a protocol is supported.

**Decision:** removed. Not needed.

---

## 🔴 Implementation plan gaps (`plans/2026-05-01-forklift-server.md`)

### 5. `ConfigKeys` listed but unimplemented
Task 1 mentions: `Create: src/Server/Types/ConfigKeys.php` — but no code or description for this class. Task 1 has only 2 steps (ProtocolType + ProxyType), missing step 3 for ConfigKeys.

**Decision:** fixed — added Step 3.

### 6. `SocketFactory::create()` ignores `$port` and `$protocol` params
```php
public static function create(ProxyType $type, int $port, ProtocolType $protocol): SocketProxyInterface
{
    return match($type) {
        ProxyType::REUSE_PORT => static::createReuseOrMaster(),
        ProxyType::MASTER => new MasterProxy(),
    };
}
```
`$port` and `$protocol` are not passed to proxy constructors. If a custom proxy needs this info, it has no access.

**Decision:** params not needed in proxy constructors — `createSocket()` receives them at call time. Keep current approach.

### 7. Old `MasterProxy` doesn't implement socket passing
Spec: *"Master creates socket and listens. Accepts connections and passes them to workers via socket passing."*

The plan's original MasterProxy was plain `listen()` + `accept()` — each worker had its own socket (via fork + SO_REUSEADDR), with no mechanism to pass descriptors between master and workers. In practice, this behaved more like ReusePort (without SO_REUSEPORT) — kernel round-robins accept between workers.

**Decision:** rename old MasterProxy to `ForkSharedProxy`. Create new `MasterProxy` with real SCM_RIGHTS socket passing via `sysvmsg`.

### 8. `checkWorkers()` restarts worker in ALL listeners
```php
public function checkWorkers(): void
{
    while (($pid = \pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
        foreach ($this->listeners as $listener) {
            $listener->restartWorker($pid);  // every listener!
        }
    }
}
```
For each dead PID, calls `restartWorker($pid)` on **every** listener. `ProcessGroup::restart()` silently does nothing if PID doesn't belong to the group — but this is inefficient (N listeners × M deaths = wasted iterations).

**Decision:** optimize — ProcessGroup::restart() checks ownership first (already no-op for foreign PIDs). Acceptable for now.

### 9. Listener / ListenerBuilder — missing unit tests
Plan has no tests for `Listener.php` or `ListenerBuilder.php`. Spec Testing Plan section says: *"Listener — mock socket and proxy"* — not implemented.

**Decision:** add unit tests with mocked SocketProxy and ProtocolHandler.

### 10. `ProtocolFactory::create()` missing optional PSR-15 handler
Spec defines:
```php
ProtocolFactory::create(ProtocolType $type, ?RequestHandlerInterface $psr15Handler = null)
```
Plan implementation has no second parameter. HTTP handler created by factory will always be without middleware.

**Decision:** add optional `$psr15Handler` and `$tcpCallback` parameters.

### 11. `TcpHandler` created by `ProtocolFactory` without callback
```php
ProtocolType::TCP => new TcpHandler(),
```
Factory creates `TcpHandler` without callback — every TCP connection will be accepted, but no user logic will execute. User has no way to pass their own callback through the factory.

**Decision:** fixed — factory accepts optional `$tcpCallback` parameter.

### 12. Missing test for `ForkliftConfig::fromJson()`
`ForkliftConfigTest` only tests `fromArray`. No test for JSON parsing (invalid JSON, valid JSON, etc.).

**Decision:** add JSON validation tests.

---

## 🟡 Minor inconsistencies

| #  | Element                     | Spec                                      | Plan                                      | Problem                       |
| -- | --------------------------- | ----------------------------------------- | ----------------------------------------- | ----------------------------- |
| 13 | `Socket::__construct`       | `public readonly`                        | `private ?`                               | different visibility/readonly |
| 14 | `Socket::setOption`         | `setOption(int $option, mixed $value)`    | `setOption(int $level, int $option, mixed $value)` | different signature (missing $level) |
| 15 | Dependency `psr/http-server-middleware` | listed                              | missing in `composer require`             | missing explicit dependency   |
| 16 | `ForkliftConfig::load()` — handler per group | groups have `protocol` in config   | handler set only via Fluent API           | with `load()` groups have null handler |
| 17 | Worker has no `stop()` / `kill()`        | missing from spec                         | missing from plan                         | no way to stop individual worker |

---

## Decisions made (resolved)

1. **ConfigKeys** — needed. Contains all configuration key constants (18 total).
2. **Enums vs interfaces in builders** — union types: `ProtocolType|ProtocolHandlerInterface`, `ProxyType|SocketProxyInterface`.
3. **fromYaml** — add via `YamlConfig` implementing `ConfigInterface`. `symfony/yaml` as suggest.
4. **Socket passing in MasterProxy** — real `SCM_RIGHTS` via `sysvmsg`. Rename old to `ForkSharedProxy`.
5. **ProtocolNotSupportedException** — removed (unused).
6. **Socket::$resource** — `private ?\Socket`. Null after close, not readonly (set to null in close).
7. **Socket::setOption** — 3 parameters: `$level, $option, $value` (matches PHP `socket_set_option`).
8. **ProtocolFactory PSR-15** — add optional `?RequestHandlerInterface $psr15Handler` and `?\Closure $tcpCallback`.

---

## 🔴 Architectural gaps (queue / backpressure / async)

### 18. Missing queue and backpressure for HTTP

Worker in the plan operates fully synchronously — accept→handle→close loop:

```php
// Worker.php
private function acceptLoop(): void
{
    while (true) {
        $connection = $this->socket->accept();  // blocks until connection arrives
        $this->handler->handle($connection);     // parses request, generates response, sends, closes
        // only now returns to accept()
    }
}
```

What this means in practice:

| Problem | Consequence |
| ------- | ------------ |
| One worker handles **one connection at a time** | If handler takes 100ms → max 10 req/s per worker. 4 workers = max 40 req/s. |
| While `handle()` is running, new connections go to **kernel accept backlog** | Backlog is `SOMAXCONN` (typically 128). Once full, kernel **rejects** new connections (TCP RST). |
| **No timeout** at worker level | Slow handler = connection timeout client-side, but worker continues handling. |
| **No user-space queue** | Nowhere to buffer excess requests. No way to reject with 503 (standard HTTP behavior). |

For comparison: even nginx or Apache have separate acceptor thread pools and worker pools. Node.js uses an event loop.

**Decision:** prefork model accepted for MVP. Kernel accept backlog is the backpressure mechanism. Internal queue + 503 rejection deferred to follow-up (MasterProxy with SCM_RIGHTS will support dispatch queue).

### 19. Missing async support for TCP and WebSocket

**TCP** (`TcpHandler`):
```php
public function handle(Connection $connection): void
{
    if ($this->callback) {
        ($this->callback)($connection);  // synchronous call, then handle() returns
    }
    // connection is NOT auto-closed — but worker returns to accept()
    // → next connection blocked until callback returns
}
```

Callback receives raw `Connection`, but has no way to do async read/write:
- No `socket_set_nonblock()`
- No `socket_select()` or `stream_select()`
- No event loop
- No feedback mechanism "more data available" or "done"

**WebSocket** (`WebSocketHandler`):
```php
public function handle(Connection $connection): void
{
    // handshake...
    while (true) {                              // infinite loop on one connection
        $data = $connection->read(65536);        // blocking read
        if ($data === false || $data === '') break;
        // frame handling...
    }
    $connection->close();
}
```

This means **one worker handles exactly one WebSocket session forever**. Worker never returns to `accept()` — permanently tied to one client. With 4 workers and a `ws` group, you serve max 4 concurrent WebSocket connections.

**Decision:** prefork model accepted for MVP (1 connection = 1 worker). WebSocket groups should be sized appropriately (N = expected concurrent connections). Event loop model deferred.

### 20. Spec is silent on concurrency model

Neither spec nor plan defines the **concurrency model** for the server. Unclear:

- Is it **prefork** (like Apache mpm_prefork): N workers × 1 connection each?
- Is it **event-driven** (like nginx/Node.js): 1 worker × many connections via non-blocking I/O?
- Is it **hybrid**: N workers, each with its own event loop?

This is a fundamental architectural decision that determines behavior for questions #18 and #19.

**Decision:** prefork (N workers × 1 connection each). Documented in spec Key Concepts section. Event loop deferred to post-MVP.

---

## 🔴 Long-running risks — what can "blow up" the forks

### 21. Missing timeouts — worker vulnerable to Slowloris / hung connections

Neither spec nor plan **contains any timeout mechanism**. Problem spans three levels:

#### 21a. `Connection::read()` timeout — missing

```php
// Connection::read()
public function read(int $length = 65536): string|false
{
    return \socket_read($this->resource, $length);  // blocks WITHOUT time limit!
}
```

`socket_read()` without `SO_RCVTIMEO` blocks **forever** if client doesn't send data. Attack scenario:

| Phase | What client does | What worker does |
| ----- | ---------------- | ---------------- |
| 1.    | Opens TCP connection | `accept()` — OK |
| 2.    | Sends `GET / HTTP/1.1\r\n` | `read()` — blocks forever, waiting for `\r\n\r\n` |
| 3.    | **Sends nothing** | Worker blocked **indefinitely**. Never returns to `accept()`. |

Result: N connections = N blocked workers. Server stops responding (**Slowloris**).

**Decision:** set `SO_RCVTIMEO` on accepted connections via `Connection::setOption()`. Worker checks `$connection->isClosed()` after timeout and kills hung connections.

#### 21b. `Socket::accept()` timeout — missing

```php
// Socket::accept()
public function accept(): Connection
{
    $accepted = @\socket_accept($this->resource);  // also blocks without limit!
}
```

No `SO_RCVTIMEO` on listen socket means `accept()` also blocks forever. Less critical (kernel manages backlog), but in edge cases can also freeze a worker.

**Decision:** set `SO_RCVTIMEO` on listen socket. Worker loops back to accept after timeout.

#### 21c. HTTP Keep-Alive — not supported

Current `HttpHandler` **always closes connection** after sending response. This means:
- No support for `Connection: keep-alive` (HTTP/1.1 default)
- Every request = new TCP handshake → performance overhead
- But at the same time **protects** against #21a (because connection is closed)

**Paradox:** if we add keep-alive, worker must keep connection open and return to `read()` to receive next request. But then we're back to problem #21a — worker blocks waiting for next request on same connection. Without timeout, keep-alive = Slowloris.

**Decision:** keep-alive deferred to MVP+1. Current behavior (always close) is safe.

#### 21d. WebSocket — ping/pong without timeout

Ping/pong is implemented ONLY as response to client ping. Server never sends pings. Worker waits in `$connection->read(65536)` — if client goes silent, worker is blocked forever.

Standard WebSocket implementation should:
- Send ping every N seconds
- Close connection if no pong within M seconds

**Decision:** server-initiated ping/pong deferred to MVP+1 (follow-up #6).

### 22. Memory leak via unbounded string concatenation (HTTP + WebSocket handshake)

Both handlers concatenate data in a loop without size limit:

```php
$raw = '';
while (($chunk = $connection->read(8192)) !== false && $chunk !== '') {
    $raw .= $chunk;  // unbounded! No limit on header/body size
    if (\str_contains($chunk, "\r\n\r\n")) {
        break;
    }
}
```

| Problem | Effect |
| ------- | ------ |
| Client sends headers without `\r\n\r\n` indefinitely | `$raw` grows until `memory_limit` → `OutOfMemoryError` → worker killed |
| Attack: "long header" — 100MB header | Same effect |
| No `Content-Length` check before reading body | If body is large, whole thing ends up in `$raw` before parsing |

**Decision:** add `maxHeaderSize` (64KB → 431 response) and `maxBodySize` (10MB → 413 response) checks. Worker adds size check before concatenation.

### 23. WebSocket: memory accumulation in long-lived sessions

Long-lived WebSocket connections pose risks: callback holding references, worker living days/weeks with PHP memory fragmentation (PHP doesn't return memory to OS), no frame size limit.

**Decision:** `max_requests` and `max_lifetime` worker config covers WebSocket workers too. Callback memory management is user responsibility.

### 24. WebSocketFrame::decode() — potential out-of-bounds read

Old implementation: `$payload = \substr($data, $offset, $len)` with `$len` from frame header (up to 2^63). If client sends frame with declared length `$len = 2GB` but actually sends only 100 bytes:
- `substr($data, $offset, $len)` returns only what's in `$data` (max 64KB from `read(65536)`)
- No check `if ($offset + $len > strlen($data))` — frame is silently truncated
- No partial frame buffering — remaining data from next `read()` is NOT appended

**This is a bug:** WebSocket frames can be fragmented across multiple TCP reads. Current implementation doesn't handle this.

**Decision:** `decode()` returns `null` for incomplete frames. Handler buffers data between reads and reassembles partial frames. Added `frameSize()` method.

### 25. Worker — missing request limit

Each `fork()` inherits parent memory via COW (copy-on-write). In PHP:
- Over time, memory pages are copied on every allocation
- PHP doesn't return memory to OS (frees internally, but `brk()` is not reversed)
- After thousands of requests, worker may use **significantly more RAM than at start**
- PHP heap fragmentation grows

Standard solution (used by php-fpm, nginx, Apache): **restart worker after N requests**.

**Decision:** add `max_requests` (0 = disabled), `max_lifetime` (seconds), `memory_limit` (bytes) to `WorkerConfig`.

### 26. Worker — missing memory_limit check before accept()

PHP has `memory_limit` in `php.ini`. If worker exceeds it during request handling:
- PHP throws `Error` (which is `\Throwable`)
- `catch (\Throwable $e)` in acceptLoop catches it
- But after `OutOfMemoryError`, worker state is unpredictable — better to kill and restart

**Decision:** distinguish between "normal" exception and critical error (OOM). On OOM, worker should `exit()` immediately (ProcessGroup will restart).

### 27. Worker — potential socket fd leak

After `handle()`, worker returns to `accept()` without closing the previous connection if handler didn't close it. For `TcpHandler`, if callback doesn't close → **fd leak**. After thousands of connections → `too many open files` → worker dies.

**Decision:** worker auto-closes connection after `handle()` if handler didn't close it (`if (!$connection->isClosed()) $connection->close()`).

### 28. ProcessGroup — missing proactive worker restart

Restart is **reactive** — only after worker dies (SIGCHLD). Missing **proactive** restart for `max_requests`, `max_lifetime`, `memory_limit`, health checks.

**Decision:** Worker self-exits when limits exceeded (signals parent via normal exit). ProcessGroup's `restart()` handles the reactive side. Worker's `acceptLoop()` handles proactive self-exit via config checks before each accept.

### 29. ForkliftServer — incorrect signal handler signature

```php
\pcntl_signal(SIGCHLD, [$this, 'checkWorkers']);  // checkWorkers() has no parameter!

public function checkWorkers(): void  // signature doesn't accept int $signo
```

PHP `pcntl_signal` passes `int $signo` to callback. While PHP tolerates this (no error), it's bad practice and would break if logic later depends on `$signo` (distinguishing SIGCHLD vs SIGUSR1).

**Decision:** fix signatures: `checkWorkers(int $signo): void`, `handleShutdown(int $signo): void`.

### 30. Missing worker monitoring / metrics

Neither spec nor plan includes worker statistics or monitoring interface.

**Decision:** add `Worker::getStats(): array` with requestCount, memoryUsage, uptime, status, openConnections. Add optional stats HTTP endpoint in ForkliftServer.

### 31. Missing graceful shutdown per worker

SIGTERM to worker **immediately** kills it — even if it's handling a request. Client gets broken pipe / reset.

**Decision:** Worker's child process installs SIGTERM handler: sets `$this->running = false`. Completes current request, then exits. 30s timeout → SIGKILL from parent (ProcessGroup).

### 32. TCP — missing TCP_NODELAY

Without `TCP_NODELAY` (Nagle's algorithm), small HTTP responses may be delayed by ~200ms (waiting for ACK of previous segment). For HTTP, this is a **significant performance degradation**.

**Decision:** add `TCP_NODELAY` option in proxy `createSocket()`. Configurable per listener via `ListenerBuilder::tcpNodelay()`.

### 33. WebSocket — missing fragmented frame support (continuation)

RFC 6455 requires fragmented frame support (opcode 0x0 continuation) and control frames interleaved between fragments. Old implementation treated each frame as a complete message — fragments were lost.

**Decision:** implement fragmented frame support in WebSocketHandler. `WebSocketFrame::decode()` returns `null` for incomplete frames. Handler buffers and reassembles.

### 34. ProcessGroup — zombie processes

`fork() + exec()` is not used — worker is the same PHP process. If worker has state corruption (e.g. after OutOfMemoryError), restart doesn't clean state — new worker inherits the same (damaged) process state via fork.

**Decision:** worker self-exits on critical errors (OOM). ProcessGroup restarts with fresh fork from clean parent.

---

## Summary — what's missing for production

| # | Mechanism | Priority | Protects against |
|---|-----------|----------|-----------------|
| 22 | `max_header_size` / `max_body_size` | 🔴 critical | OOM via large headers |
| 25 | `max_requests` per worker | 🔴 critical | Memory leak in long-running |
| 26 | `memory_limit` check per worker | 🔴 critical | OOM → unpredictable state |
| 27 | Auto-close connection after `handle()` | 🟡 high | fd leak in TcpHandler |
| 28 | Proactive worker restart | 🟡 high | Cumulative problems over time |
| 24 | Partial WS frame buffering | 🟡 high | Frames > 64KB lost |
| 33 | WS fragmented frame support | 🟡 high | RFC 6455 compliance |
| 31 | Graceful worker shutdown | 🟡 high | Broken pipe on restart |
| 32 | `TCP_NODELAY` | 🟡 high | +200ms latency on small responses |
| 30 | Worker monitoring / metrics | 🟢 medium | Debugging, observability |
| 29 | Correct signal handler signatures | 🟢 low | Code quality |

---

## 🟡 Missing functionality

### 35. SSL/TLS (HTTPS + WSS) — completely missing

Spec and plan don't mention encryption. No handler uses `stream_socket_enable_crypto()`. This means HTTP only in plaintext, WebSocket only `ws://` (no `wss://`), no certificate configuration.

**Decision:** add `TlsProxy` decorator for `SocketProxyInterface` that calls `stream_socket_enable_crypto()` after `accept()`.

### 36. Missing HTTP routing
### 37. Missing middleware pipeline (PSR-15 chain)

**Decision:** routing and middleware pipeline are user responsibility. PSR-15 handler interface is supported, user brings their own router (e.g. FastRoute).

### 38. Missing request body parsing (POST/PUT/PATCH)

Old `parseRequest()` parsed only method, URI, and headers — body was ignored. Every POST/PUT request lost data. Missing: Content-Length reading, JSON parsing, form-urlencoded parsing.

**Decision:** add body parsing for `application/json` and `application/x-www-form-urlencoded`. Multipart deferred to MVP+1.

### 39-43. Features deferred to MVP+1/2

| Feature | Status |
|---------|--------|
| HTTP keep-alive | MVP+1 |
| Transfer-Encoding: chunked | MVP+1 |
| Multipart/form-data upload | MVP+1 |
| Gzip/brotli compression | User middleware |
| Unix socket support (AF_UNIX) | MVP+2 |
| CPU affinity | MVP+2 |
| Chunked transfer encoding | MVP+1 |

### 44. Hot reload (SIGHUP)

**Decision:** add `ForkliftServer::reload()` — SIGHUP handler. Loads new config, starts new listeners/groups, graceful shutdown of old ones.

### 45. Daemon mode + PID file

**Decision:** deferred to MVP+1. For now, use `nohup` or `&`.

### 46. Rate limiting / connection limiting

**Decision:** deferred to MVP+1. MasterProxy's dispatch queue provides basic backpressure.

### 47. Structured access logging

**Decision:** add `AccessLogFormatterInterface` + `JsonAccessLogFormatter` in MVP. Logs via PSR-3 logger (INFO level) after each request.

### 48-49. WebSocket subprotocol negotiation, broadcast/pub-sub

**Decision:** deferred to MVP+1/MVP+2 respectively.

### 50. Request body size limit

**Decision:** add `max_body_size` to HttpHandler (default 10MB → 413).

### 51. Process title

**Decision:** add `cli_set_process_title("forklift: worker {group} #{n}")` in Worker child process. Enabled by default.

### 52. SIGUSR1/SIGUSR2 administrative signals

**Decision:** deferred to MVP+2.

### 53. WebSocket server-initiated ping/pong

**Decision:** respond to client ping (implemented). Server-initiated ping deferred to MVP+1.

### 54. HTTP query string parsing
### 55. HTTP Expect: 100-continue
### 56. Correlation headers (X-Request-Id, Server-Timing)

**Decision:** deferred to MVP+2.
