# M1.04 — Connection wrapper

Wraps an accepted connection's `\Socket` resource with read/write, state tracking, and peer info.

## What it builds

`Connection` class:
- `__construct(\Socket $resource)` — wraps accepted socket
- `read(int $length = 65536): string|false` — returns `false` if closed
- `write(string $data): int|false` — returns `false` if closed
- `close(): void` — idempotent, sets `$closed = true`
- `isClosed(): bool`
- `getPeerName(): array` — `['host' => '...', 'port' => 123]`
- `getLastActivity(): float` — `microtime(true)` of last read/write, for timeout detection
- `setOption(int $level, int $option, mixed $value): void` — sets socket option on connection (e.g. `SO_RCVTIMEO` for connection timeout)
- Updates `$lastActivity` on every read/write

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Socket/Connection.php` |

## References

Spec: Connection section | Plan: Task 4

## Dependencies

None (pure wrapper around `\Socket`).

## Acceptance criteria

- `read()` and `write()` return `false` when `$closed === true`
- `close()` is idempotent
- `getLastActivity()` returns float, updated on each read/write
- `getPeerName()` returns array with `host` and `port`
- `setOption()` delegates to `socket_set_option()` with 3 params: `$level, $option, $value`
