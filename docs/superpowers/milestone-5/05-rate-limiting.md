# M5.05 — Rate limiting

Protect the server from overload with per-IP connection and request limits.

## What it builds

**Connection limiting:**
- `ListenerBuilder::maxConnectionsPerIp(int)` — concurrent connections per IP (0 = unlimited)
- Track via shared memory; MasterProxy can enforce at dispatch level

**Rate limiting:**
- `ListenerBuilder::maxRequestsPerSec(int)` — requests/sec/IP (token bucket)
- Burst capacity = rate; 429 when exhausted
- Approximate per-worker counting

**Global limit:**
- `ListenerBuilder::maxTotalConnections(int)` — absolute cap across all IPs

## References

Follow-up MVP+1 #5

## Dependencies

All M1-M4; shared memory IPC

## Acceptance criteria

- `maxConnectionsPerIp` enforced (reject or 503)
- `maxRequestsPerSec` enforced (429 after burst exhausted)
- `maxTotalConnections` global cap
- Zero values = disabled (backward compatible default)
