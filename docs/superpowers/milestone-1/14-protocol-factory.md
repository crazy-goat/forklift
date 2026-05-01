# M1.14 — ProtocolFactory

Static factory that creates the correct `ProtocolHandlerInterface` for a given `ProtocolType`. Accepts optional parameters for PSR-15 handler (HTTP) and TCP callback.

## What it builds

`ProtocolFactory`:
- `create(ProtocolType $type, ?RequestHandlerInterface $psr15Handler = null, ?\Closure $tcpCallback = null): ProtocolHandlerInterface`
- `TCP` → `new TcpHandler($tcpCallback)`
- `HTTP` → `new HttpHandler($psr15Handler)`
- `WEBSOCKET` → `new WebSocketHandler()`

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Protocol/ProtocolFactory.php` |
| Create | `tests/Server/Protocol/ProtocolFactoryTest.php` |

## References

Spec: ProtocolFactory section | Plan: Task 13 (updated) | Watpliwosci: #10 (PSR-15 param), #11 (TCP callback)

## Dependencies

M1.01 (ProtocolType), M1.10 (TcpHandler), M1.12 (HttpHandler), M1.13 (WebSocketHandler)

## Acceptance criteria

- `create(TCP)` returns `TcpHandler` instance, passes callback if provided
- `create(HTTP)` returns `HttpHandler` instance, passes PSR-15 handler if provided
- `create(WEBSOCKET)` returns `WebSocketHandler` instance
- Test validates all three types
