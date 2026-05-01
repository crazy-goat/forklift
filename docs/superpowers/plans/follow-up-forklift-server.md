# Forklift Server — Follow-up (MVP+1 / MVP+2)

**Date:** 2026-05-01
**Parent spec:** `docs/superpowers/specs/2026-05-01-forklift-server-design.md`

## Overview

Features deferred beyond MVP. Grouped by version — MVP+1 (next) and MVP+2 (later).

---

## MVP+1 — HTTP/1.1 compliance + production hardening

### 1. HTTP Keep-Alive

**Problem:** HttpHandler currently closes the connection after every request. HTTP/1.1 uses keep-alive by default — each new TCP+TLS handshake adds ~5-50ms latency.

**Solution:**
- Worker after `handle()` does not close the connection if the response has `Connection: keep-alive`
- Returns to `read()` waiting for the next request (max `keepalive_timeout` seconds)
- Timeout per connection — default 60s
- Configuration per listener: `keepAlive(true)`, `keepAliveTimeout(60)`

**Dependencies:** Connection timeout is already in MVP. Only the Worker needs a keep-alive loop extension.

**Priority:** High. Without this, every request = new TCP handshake.

---

### 2. Transfer-Encoding: chunked (request + response)

**Problem:** HTTP/1.1 requires chunked support. Currently `parseRequest()` does not recognize `Transfer-Encoding: chunked`, and `buildResponse()` cannot chunk.

**Solution:**
- Request parser: detect chunked body, reassemble chunks into full body
- Response builder: optional chunking (for large/streamed responses)
- `HttpHandler::useChunkedEncoding(bool)` — default false (Content-Length)

**Priority:** Medium. Chunked requests are rare outside streaming.

---

### 3. Multipart/form-data (file upload)

**Problem:** Current body parser handles only JSON and form-urlencoded. File upload via `multipart/form-data` does not work.

**Solution:**
- Multipart parser: reads boundary, parses parts (headers + body per part)
- Writes files to temporary `\SplTempFileObject`
- `ServerRequestInterface::getUploadedFiles()` returns `UploadedFileInterface[]`
- Upload size limit: `max_upload_size` per listener

**Priority:** Low. Requires a separate parser.

---

### 4. Daemon mode + PID file

**Problem:** ForkliftServer cannot run as a daemon. The process is attached to the terminal.

**Solution:**
- `--daemon` / `-d` flag in CLI
- `fork()` + `setsid()` → detach from terminal
- Write PID to `/var/run/forklift.pid` (or configurable path)
- Redirect stdout/stderr to logs
- Signals unchanged (SIGTERM → shutdown, SIGHUP → reload)

**Priority:** Medium. Needed for production, but for testing the process can run in background (`nohup` / `&`).

---

### 5. Rate limiting / connection limiting

**Problem:** No protection against overload. One aggressive client can block all workers.

**Solution:**
- `max_connections_per_ip` — limit concurrent connections from a single IP (default 0 = no limit)
- `max_requests_per_ip_per_sec` — request rate limit (token bucket)
- `max_total_connections` — global connection limit
- Configuration per listener

**Priority:** High. Without this the server is vulnerable to DoS.

---

### 6. WebSocket — server-initiated ping/pong

**Problem:** Ping/pong only works as a response to client ping. The server never sends a ping. Dead connections (client disconnect without FIN) are not detected.

**Solution:**
- `WebSocketHandler` sends a ping every `ping_interval` (default 30s)
- Closes the connection if no pong within `ping_timeout` (default 60s)
- Configuration: `pingInterval(30)`, `pingTimeout(60)`

**Priority:** High. Without this, the WebSocket worker is blocked forever on dead connections.

---

### 7. WebSocket — subprotocol negotiation

**Problem:** WebSocket handshake ignores `Sec-WebSocket-Protocol`. Cannot select a handler per subprotocol (e.g. `graphql-ws`, `wamp`).

**Solution:**
- `WebSocketHandler::subprotocol(string $name, ProtocolHandlerInterface $handler)` — register handler per subprotocol
- During handshake: parse `Sec-WebSocket-Protocol`, select handler, respond with chosen subprotocol

**Priority:** Low. Rarely needed outside specific use cases.

---

### 8. WebSocket — broadcast / pub-sub

**Problem:** WebSocket workers live in separate processes. No mechanism for inter-worker communication — broadcast to all clients requires an external broker.

**Solution:**
- Optional `WebSocketBroadcast` interface
- Default implementation via Unix socket IPC or `sysvmsg`
- `$handler->broadcast('channel', $message)` — sends to all workers, which then distribute to their clients
- Ability to plug in Redis pub-sub as backend

**Priority:** Low. Requires IPC architecture design.

---

## MVP+2 — Extensions

### 9. Unix socket support (AF_UNIX)

- New proxy or `createSocket()` option — create Unix socket instead of TCP
- Faster than TCP (no TCP/IP stack overhead)
- Useful for inter-container communication (Docker shared volume)
- Used by php-fpm, nginx + PHP upstream

### 10. CPU affinity

- Linux-only (`sched_setaffinity`)
- Pin worker to specific CPU core
- Configuration per group: `cpuAffinity([0, 1])`
- Reduces cache thrashing under NUMA

### 11. HTTP query string parsing

- `parseRequest()` — parse query string from URI
- `$request->getQueryParams()` returns `['key' => 'value']`
- Uses Nyholm PSR-7 `withQueryParams()`

### 12. HTTP Expect: 100-continue

- Client sends `Expect: 100-continue`
- Server responds `HTTP/1.1 100 Continue` before reading body
- Without this, client may wait for timeout before sending large body

### 13. Correlation headers

- `X-Request-Id` — unique identifier per request
- `Server-Timing` — processing time information (W3C spec)
- `X-Worker-Pid` — which worker handled the request
- Generated in HttpHandler, added to PSR-7 request attributes

### 14. SIGUSR1/SIGUSR2 — administrative signals

- `SIGUSR1` — reopen log files (logrotate)
- `SIGUSR2` — dump stats to log
- `SIGUSR1` — graceful restart workers one by one

---

## Intentionally omitted (application domain, not framework)

| Feature             | Reason                                             |
| ------------------- | -------------------------------------------------- |
| HTTP routing        | User brings their own (e.g. FastRoute)             |
| Middleware pipeline | User brings their own (e.g. middlewares/*)         |
| Compression (gzip)  | Implemented as application middleware               |
| YAML parsing        | Separate dependency `symfony/yaml`, via YamlConfig  |
