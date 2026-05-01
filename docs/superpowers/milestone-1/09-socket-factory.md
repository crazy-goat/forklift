# M1.09 — SocketFactory

Static factory that creates the correct `SocketProxyInterface` for a given `ProxyType`. Implements the fallback chain: REUSE_PORT → ForkShared when SO_REUSEPORT is unavailable.

## What it builds

`SocketFactory`:
- `create(ProxyType $type, int $port, ProtocolType $protocol): SocketProxyInterface`
- `REUSE_PORT` → tries `ReusePortProxy`, falls back to `ForkSharedProxy` if not supported
- `FORK_SHARED` → always `ForkSharedProxy`
- `MASTER` → always `MasterProxy`

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Socket/SocketFactory.php` |
| Create | `tests/Server/Socket/SocketFactoryTest.php` |

## References

Spec: SocketFactory section | Plan: Task 8 | Watpliwosci: #7 (fallback chain)

## Dependencies

M1.05 (ReusePortProxy), M1.06 (ForkSharedProxy), M1.07 (MasterProxy)

## Acceptance criteria

- `create(MASTER)` returns `MasterProxy`
- `create(FORK_SHARED)` returns `ForkSharedProxy`
- `create(REUSE_PORT)` returns `ReusePortProxy` if supported, else `ForkSharedProxy`
- Test passes (validates fallback logic)
