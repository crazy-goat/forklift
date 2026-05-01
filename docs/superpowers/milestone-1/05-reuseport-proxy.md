# M1.05 — SocketProxyInterface + ReusePortProxy

Defines the socket proxy contract and implements SO_REUSEPORT proxy — each worker binds the same port, kernel distributes traffic.

## What it builds

**SocketProxyInterface:**
- `createSocket(int $port, ProtocolType $protocol): Socket`
- `accept(Socket $socket): Connection`
- `isSupported(): bool`

**ReusePortProxy:**
- Creates socket with `SO_REUSEADDR` + `SO_REUSEPORT` (when supported)
- Binds to `0.0.0.0:$port`, listens with `SOMAXCONN`
- `isSupported()` — `defined('SO_REUSEPORT') && PHP_OS_FAMILY !== 'Windows'`
- `accept()` delegates to `$socket->accept()`

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Socket/SocketProxyInterface.php` |
| Create | `src/Server/Socket/ReusePortProxy.php` |
| Create | `tests/Server/Socket/ReusePortProxyTest.php` |

## References

Spec: SocketProxyInterface, ReusePortProxy sections | Plan: Tasks 5, 6

## Dependencies

M1.01 (ProtocolType), M1.03 (Socket), M1.04 (Connection), M1.02 (SocketCreationException)

## Acceptance criteria

- Interface defines 3 methods with correct signatures
- `ReusePortProxy` implements interface, uses `SO_REUSEADDR` + conditional `SO_REUSEPORT`
- `isSupported()` returns `false` on Windows or when constant undefined
- Test passes (or skips on unsupported platforms)
