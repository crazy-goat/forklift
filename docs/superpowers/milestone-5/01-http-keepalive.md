# M5.01 — HTTP Keep-Alive

Persistent connections: multiple HTTP requests reuse one TCP connection instead of a new handshake per request.

## What it builds

Worker inspects response `Connection` header after `handle()`:
- `Connection: keep-alive` → return to `read()` to receive next request on same connection
- `Connection: close` → close immediately (current behavior)

**New config:**
- `ListenerBuilder::keepAlive(bool)` — enable/disable (default true for HTTP/1.1)
- `ListenerBuilder::keepAliveTimeout(int)` — idle seconds before close (default 60)
- `WorkerConfig::keepAliveMaxRequests` — max requests per connection (default 100)

**Worker changes:** after response, check for keep-alive; if enabled, loop to `read()` with timeout instead of `accept()`.

## References

Follow-up MVP+1 #1 | Watpliwosci: #21a (read timeout), #21c (keep-alive paradox)

## Dependencies

All M1-M4, `SO_RCVTIMEO`

## Acceptance criteria

- Multiple requests on same connection without re-accept
- `Connection: close` preserves current close-immediately behavior
- Idle connection closed after timeout
- `keepAliveMaxRequests` enforced
