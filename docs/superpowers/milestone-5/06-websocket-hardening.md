# M5.06 — WebSocket: server ping/pong + subprotocol negotiation

Two RFC 6455 compliance improvements for WebSocket.

## What it builds

**Server-initiated ping:**
- Send ping every `pingInterval` seconds (default 30)
- No pong within `pingTimeout` (default 60) → close connection
- Configuration: `WebSocketHandler::pingInterval(int)`, `WebSocketHandler::pingTimeout(int)`

**Subprotocol negotiation:**
- Parse `Sec-WebSocket-Protocol` from client handshake
- `WebSocketHandler::subprotocol(string $name, ProtocolHandlerInterface $handler)` — register handler
- Match client protocols → first match wins → include in 101 response
- Matched handler processes all frames for that connection

## References

Follow-up MVP+1 #6, #7 | RFC 6455 §5.5.2, §4.2.1

## Dependencies

M1.13 (WebSocketHandler), M2.02 (Worker connection timeout)

## Acceptance criteria

- Server pings every `pingInterval` on idle connection
- No pong → connection closed within `pingTimeout`
- Subprotocol matched from client header, correct handler selected
- No match → connection still works without subprotocol header
