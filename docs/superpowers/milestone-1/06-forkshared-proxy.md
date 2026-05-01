# M1.06 — ForkSharedProxy

Simple proxy that works everywhere. Workers inherit the same listening fd via `fork()`, kernel round-robins `accept()` between them. No SO_REUSEPORT, no SCM_RIGHTS.

## What it builds

`ForkSharedProxy`:
- Creates socket with `SO_REUSEADDR` only
- Binds to `0.0.0.0:$port`, listens with `SOMAXCONN`
- `isSupported()` always returns `true`
- `accept()` delegates to `$socket->accept()`

This is the former "MasterProxy" from the original plan, renamed per watpliwosci #7.

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Socket/ForkSharedProxy.php` |
| Create | `tests/Server/Socket/ForkSharedProxyTest.php` |

## References

Spec: ForkSharedProxy section | Plan: Task 7 (renamed) | Watpliwosci: #7

## Dependencies

M1.05 (SocketProxyInterface)

## Acceptance criteria

- Implements `SocketProxyInterface`
- `isSupported()` always `true`
- Uses only `SO_REUSEADDR` (no SO_REUSEPORT)
- Test passes on all platforms
