# M3.06 — ForkliftServer

The main orchestrator. Owns all listeners and groups, provides the fluent API, manages lifecycle, signal handling, hot reload, stats endpoint, and CLI entrypoint.

## What it builds

`ForkliftServer`:

**Fluent API:**
- `group(string $name, int $size): ProcessGroupBuilder`
- `listen(int $port): ListenerBuilder`
- `addGroup(ProcessGroup): void`, `addListener(Listener): void`
- `stats(int $port): self`, `statsKey(string $key): self` — optional stats HTTP endpoint

**Configuration:**
- `load(ConfigInterface $config): self` — creates ProcessGroups and Listeners from config

**Lifecycle:**
- `start(): void` — starts all listeners, installs signal handlers, enters main loop
- Main loop: `while ($running) { pcntl_signal_dispatch(); checkWorkers(); sleep(1); }`
- `reload(): void` — SIGHUP handler: reloads config, graceful shutdown of old listeners, starts new ones (watpliwosci #44)
- `run(): void` — CLI entrypoint: parses `--config`, `--daemon`, auto-detects config file, starts

**Signal handling (correct signatures per watpliwosci #29):**
- `handleShutdown(int $signo): void` — SIGINT, SIGTERM → `$running = false`
- `checkWorkers(int $signo): void` — SIGCHLD → iterates listeners, restarts dead workers
- `reload(int $signo): void` — SIGHUP → reload config

**Graceful shutdown:** each listener → ProcessGroup → SIGTERM → 30s wait → SIGKILL

**Stats endpoint:** when enabled via `stats(port)`, serves JSON with worker metrics from all groups. Requires `?key=<statsKey>` if key is set.

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/ForkliftServer.php` |

## References

Spec: ForkliftServer section | Plan: Task 20 (updated), Task 25 | Watpliwosci: #8, #29, #44

## Dependencies

M3.02 (ListenerBuilder), M3.05 (ForkliftConfig), M2.04 (ProcessGroupBuilder), M1.14 (ProtocolFactory), PSR-3 LoggerInterface

## Acceptance criteria

- `group()` and `listen()` return builders
- `load(ConfigInterface)` creates groups with handlers, listeners with correct bindings
- `start()` enters event loop with signal dispatch
- Signal handler callbacks have correct `int $signo` parameter
- `reload()` starts new listeners, shuts down old ones gracefully
- `stats(port)` serves JSON worker metrics (with optional key auth)
- Graceful shutdown: 30s per worker, then SIGKILL
