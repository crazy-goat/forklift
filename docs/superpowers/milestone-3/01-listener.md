# M3.01 — Listener

Binds a port, protocol, proxy, and worker group into one running endpoint. The glue between SocketFactory and ProcessGroup.

## What it builds

`Listener`:
- Constructor: `$port`, `ProtocolType`, `ProxyType` (or `SocketProxyInterface`), `ProcessGroup`
- `start(): void` — `SocketFactory::create(proxyType, port, protocol)` → proxy → `createSocket()` → `group->start(socket)`
- `restartWorker(int $pid): void` — delegates to `group->restart($pid)`
- `shutdown(): void` — delegates to `group->shutdown()`
- `withLogger(LoggerInterface): void`

Listener carries the immutable binding: one port → one proxy → one protocol → one group. All configuration (TLS certs, TCP_NODELAY, timeouts) is applied by the builder before creating the Listener.

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Listener.php` |
| Create | `tests/Server/ListenerTest.php` |

## References

Spec: Listener section (Flow description), Testing Plan (Listener — mocking socket and proxy) | Plan: Task 17 (updated), Task 20-21 | Watpliwosci: #9

## Dependencies

M1.09 (SocketFactory), M2.03 (ProcessGroup), M1.01 (ProtocolType, ProxyType)

## Test coverage

`ListenerTest` — unit test with mocked `SocketProxyInterface` and `ProcessGroup`:
- `start()` calls `SocketFactory::create()` and `ProcessGroup::start()` with correct args
- `restartWorker($pid)` delegates to `ProcessGroup::restart($pid)`
- `shutdown()` delegates to `ProcessGroup::shutdown()`
- `withLogger()` propagates logger to group

## Acceptance criteria

- `start()` creates socket via factory, starts group with that socket
- `restartWorker()` and `shutdown()` delegate to group
- Logs listener info (port, protocol, proxy, group name) on start
- `ListenerTest` passes (mocked dependencies, no real sockets)
