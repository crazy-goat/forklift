# M2.05 — Milestone 2 verification

Verify M2 worker and process group functionality works end-to-end.

## What to verify

- [ ] `WorkerConfigTest` passes — all fields `public readonly`, correct defaults, 0 = disabled
- [ ] `Worker` — `run()` forks, child runs accept loop, parent gets PID
- [ ] Worker stats: `getStats()` returns all 7 keys with valid values
- [ ] Worker SIGTERM: child catches signal, finishes current request, exits cleanly
- [ ] Worker limits: `max_requests` exceeded → self-exit; `memory_limit` exceeded → self-exit
- [ ] Worker FD leak protection: auto-closes connection if handler didn't
- [ ] Worker process title: `cli_set_process_title()` called in child
- [ ] `ProcessGroupTest` passes — start N workers, shutdown, no zombies
- [ ] `ProcessGroup::restart()` only restarts workers belonging to this group
- [ ] `ProcessGroupBuilder::create()` validates handler is set

**Run:**
```bash
vendor/bin/phpunit tests/Server/ProcessGroupTest.php
```

## Acceptance criteria

- `ProcessGroupTest` passes
- No zombie processes after test completes
- All worker lifecycle behaviors verified
