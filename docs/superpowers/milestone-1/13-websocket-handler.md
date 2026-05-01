# M1.13 — WebSocketHandler

RFC 6455 WebSocket server. Handles the HTTP upgrade handshake, then enters a frame read/write loop with buffering for TCP-fragmented frames and support for continuation (fragmented) frames.

## What it builds

`WebSocketHandler`:
- Handshake phase:
  - Reads HTTP upgrade request, extracts `Sec-WebSocket-Key`
  - If no key → 400 Bad Request
  - Computes `Sec-WebSocket-Accept` = base64(sha1(key + GUID))
  - Sends `101 Switching Protocols` response
- Frame loop phase:
  - Reads raw data in a loop, appends to internal buffer
  - Uses `WebSocketFrame::frameSize()` and `decode()` to extract complete frames
  - Incomplete frames stay in buffer for next read (TCP fragmentation)
  - Fragmented frame support (opcode 0x0 continuation): accumulates payload, dispatches on final fragment
  - Control frames:
    - opcode 0x8 (close) → calls close callback, breaks loop
    - opcode 0x9 (ping) → sends pong (0xA) response
    - opcode 0xA (pong) → no-op
  - Data frames (0x1 text, 0x2 binary): calls `onMessage` callback
- Fluent callbacks: `onMessage(\Closure)`, `onClose(\Closure)`

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Protocol/WebSocketHandler.php` |
| Create | `tests/Server/Protocol/WebSocketHandlerTest.php` |

## References

Spec: WebSocketHandler section | Plan: Task 12 (updated) | Watpliwosci: #24 (frame buffering), #33 (fragmented frames)

## Dependencies

M1.10 (ProtocolHandlerInterface), M1.11 (WebSocketFrame), M1.04 (Connection)

## Acceptance criteria

- Valid handshake: `Sec-WebSocket-Key` → correct `Sec-WebSocket-Accept`, 101 response
- Missing key → 400 Bad Request
- Frame buffering: incomplete frames survive between `read()` calls
- Fragmented frames: continuation payloads accumulated, dispatched as one message on final fragment
- Ping → pong response
- Close frame → close callback called, connection closed
- Test passes (handshake test with real TCP)
