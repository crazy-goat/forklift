# M5.08 — MVP+2: Unix sockets, CPU affinity, HTTP compliance, signals

Smaller platform and HTTP features deferred to the second post-MVP wave.

## What it builds

**Unix socket support (AF_UNIX):** proxy option for `unix:///path` instead of TCP

**CPU affinity:** `WorkerConfig::cpuAffinity(array $cores)` — Linux `sched_setaffinity`, no-op elsewhere

**HTTP query string:** parse URI query → `$request->getQueryParams()`, handle array syntax

**HTTP Expect: 100-continue:** send `100 Continue` before reading large body

**Correlation headers:** `X-Request-Id` (UUID), `Server-Timing` (W3C), `X-Worker-Pid`

**SIGUSR1/SIGUSR2:** reopen logs, dump stats, graceful rolling restart

## References

Follow-up MVP+2 #9-14

## Dependencies

All M1-M4

## Acceptance criteria

- Each feature independently functional with clear config surface
- No regressions on existing functionality
