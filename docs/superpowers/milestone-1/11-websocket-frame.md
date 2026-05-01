# M1.11 — WebSocketFrame

RFC 6455 frame encoder/decoder. Handles all payload length variants (7-bit, 16-bit, 64-bit), masked frames from clients, and incomplete frame detection for TCP fragmentation.

## What it builds

`WebSocketFrame` (value object: `opcode`, `payload`, `fin`):
- `encode(string $data, int $opcode = 0x1): string` — payload → raw frame bytes
- `decode(string $data): ?self` — raw bytes → frame or `null` if incomplete (watpliwosci #24)
- `frameSize(string $data): ?int` — total frame byte size from header, or `null` if incomplete

Handles:
- Payload lengths: ≤125 (7-bit), 126 (16-bit extended), 127 (64-bit extended)
- Masking XOR for client→server frames
- Returns `null` when available bytes < required size (no silent truncation)

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Protocol/WebSocketFrame.php` |

## References

Spec: WebSocketFrame section (part of WebSocketHandler) | Plan: Task 12 (part 1) | Watpliwosci: #24, #33

## Dependencies

None (pure byte manipulation, no framework types).

## Acceptance criteria

- `encode()` produces valid RFC 6455 frames with correct length encoding
- `decode()` returns `null` for data shorter than required frame size (incomplete)
- `decode()` returns `WebSocketFrame` for complete frames with correct payload
- `frameSize()` reports total frame size including header
- Masked payloads correctly XOR-unmasked
- 16-bit and 64-bit extended payload lengths handled
