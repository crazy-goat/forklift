# M4.03 — Integration tests: lifecycle + stats

Tests verifying worker restart, graceful shutdown, hot reload, and the stats endpoint.

## What to test

1. **Worker restart on failure** — kill a worker PID → SIGCHLD → ProcessGroup spawns new worker within 2s
2. **Graceful shutdown** — SIGTERM → current request completes → no broken pipe
3. **Stats endpoint** — GET /stats?key=... → JSON with worker metrics (process_number, pid, request_count, memory_usage, uptime, status)
4. **Stats auth** — GET /stats without key → 401 Unauthorized
5. **Hot reload** — SIGHUP → new config loaded → new listeners start → old ones shutdown gracefully

## Files

| Action | Path |
|--------|------|
| Create | `tests/Server/ForkliftServerLifecycleTest.php` |

## References

Spec: Testing Plan (acceptance tests) | Plan: Task 27

## Dependencies

All M1-M3 components

## Acceptance criteria

- Worker restart: dead worker replaced within 2 seconds
- Graceful shutdown: SIGTERM does not cause broken pipe on active request
- Stats JSON contains all worker metrics fields
- Stats returns 401 without correct key
- Hot reload: new listener responds, old listener stops cleanly
