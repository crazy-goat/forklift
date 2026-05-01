# M1.03 — Socket wrapper

Wraps PHP's `\Socket` resource behind a clean API. A listen socket: bind, listen, accept (returns Connection), set options, close.

## What it builds

`Socket` class:
- `__construct(\Socket $resource)` — wraps raw resource
- `bind(string $address, int $port): void` — throws `SocketCreationException` on failure
- `listen(int $backlog = SOMAXCONN): void`
- `accept(): Connection` — returns next accepted connection, throws `SocketAcceptException`
- `setOption(int $level, int $option, mixed $value): void` — 3 params matching PHP's `socket_set_option` (watpliwosci #14)
- `close(): void` — idempotent, sets resource to null after close
- Resource is `private ?\Socket` (nullable, not readonly — watpliwosci #13)

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Socket/Socket.php` |
| Create | `tests/Server/Socket/SocketTest.php` |

## References

Spec: Socket section | Plan: Task 3 | Watpliwosci: #13, #14

## Dependencies

M1.02 (exceptions)

## Acceptance criteria

- `accept()` returns `Connection` instance, throws on error (not returns false)
- `close()` is idempotent, resource set to null
- `setOption()` has 3 parameters: `$level, $option, $value`
- `SocketTest` passes (bind, listen, close, constructor)
