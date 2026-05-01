# M4.02 — Integration tests: HTTP + multi-port

End-to-end tests verifying the server responds to real TCP connections.

## What to test

1. **HTTP request/response** — ForkliftServer with HttpHandler, real TCP connect → send GET → receive 200 with body
2. **Multi-port** — two listeners (8080 + 8081) with different handlers, both respond independently
3. **Config-based startup** — ForkliftServer loaded from ArrayConfig, JSON file
4. **WebSocket handshake** — valid upgrade → 101, missing key → 400

## Files

| Action | Path |
|--------|------|
| Create | `tests/Server/ForkliftServerIntegrationTest.php` |

## References

Spec: Testing Plan (integration tests) | Plan: Task 22, Task 27

## Dependencies

All M1-M3 components, `nyholm/psr7`

## Acceptance criteria

- HTTP: GET / → 200 with expected response body
- Multi-port: both ports respond independently with correct handlers
- Config: ArrayConfig and JsonConfig both start the server correctly
- WebSocket: valid key gets 101, invalid gets 400
- Tests use `findFreePort()` to avoid port conflicts
