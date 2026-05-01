# M2.01 — WorkerConfig

Immutable value object holding lifecycle settings for workers. Used by both Worker (self-monitoring) and ProcessGroup (reactive restart).

## What it builds

`WorkerConfig`:
- `maxRequests` (int, 0 = no limit) — self-exit after handling N requests
- `maxLifetime` (int, 0 = no limit) — self-exit after T seconds of uptime
- `memoryLimit` (int, 0 = no limit) — self-exit when `memory_get_usage(true)` exceeds this
- `connectionTimeout` (float, 30.0) — close idle connections after this many seconds
- `healthCheckInterval` (float, 5.0) — how often to check limits and timeouts

All fields `public readonly` with defaults in constructor.

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/WorkerConfig.php` |
| Create | `tests/Server/WorkerConfigTest.php` |

## References

Spec: WorkerConfig section, Testing Plan (WorkerConfig — default values) | Plan: Task 16 (NEW) | Watpliwosci: #25, #26, #28

## Dependencies

None (pure value object).

## Test coverage

`WorkerConfigTest`:
- Verifies all 5 fields are `public readonly` with documented defaults
- Verifies that 0 means "disabled" for `maxRequests`, `maxLifetime`, `memoryLimit`
- Verifies `connectionTimeout` default is `30.0` and `healthCheckInterval` default is `5.0`

## Acceptance criteria

- 5 `public readonly` fields with documented defaults
- 0 values mean "disabled" for maxRequests, maxLifetime, memoryLimit
- `WorkerConfigTest` passes
