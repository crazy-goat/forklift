# M1.10 — ProtocolHandlerInterface + TcpHandler

Defines the protocol handler contract and implements the simplest handler: raw TCP passthrough with an optional user callback.

## What it builds

**ProtocolHandlerInterface:**
- `handle(Connection $connection): void` — handler must close the connection (or intentionally leave it open; worker monitors for leaks)

**TcpHandler:**
- `__construct(?\Closure $callback = null)`
- `handle()` — calls the callback with the connection if set; no-op otherwise
- Does NOT close the connection (callback's responsibility)

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Protocol/ProtocolHandlerInterface.php` |
| Create | `src/Server/Protocol/TcpHandler.php` |

## References

Spec: ProtocolHandlerInterface, TcpHandler sections | Plan: Tasks 9, 10 | Watpliwosci: #11 (callback support)

## Dependencies

M1.04 (Connection)

## Acceptance criteria

- Interface has single method: `handle(Connection): void`
- `TcpHandler` accepts optional Closure callback
- No callback = no-op (connection accepted, nothing happens)
- Handler does NOT close the connection (worker will auto-close)
