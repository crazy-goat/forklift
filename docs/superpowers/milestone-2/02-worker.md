# M2.02 — Worker

Single forked process running the accept→handle→close loop. Manages its own lifecycle through config-driven limits and signal-based graceful shutdown.

## What it builds

`Worker`:
- Constructor takes: `processNumber`, `Socket`, `ProtocolHandlerInterface`, optional `LoggerInterface`, optional `WorkerConfig`
- `run(): int` — `fork()`, child enters `acceptLoop()`, parent returns PID
- `getPid(): int` — throws if not started
- `getStats(): array` — process_number, pid, request_count, memory_usage, uptime, status, open_connections
- `getStatus(): string` — `'idle'` | `'busy'` | `'dead'`

**Child process accept loop:**
1. `cli_set_process_title("forklift: worker {group} #{n}")` (watpliwosci #51)
2. Install `pcntl_signal(SIGTERM, fn() => $this->running = false)` — graceful shutdown (watpliwosci #31)
3. `pcntl_signal(SIGCHLD, SIG_IGN)` — ignore in child
4. **MasterProxy mode (SCM_RIGHTS):** if proxy is MasterProxy, worker receives file descriptors from the `sysvmsg` message queue instead of calling `$this->socket->accept()`. It calls `msg_receive()` to get the fd, then wraps it in a `Connection`. When the worker's proxy type is not Master, skip to step 5.
5. Before each `accept()` (or `msg_receive()`):
   - Check `max_requests` → self-exit if exceeded
   - Check `max_lifetime` → self-exit if uptime exceeded
   - Check `memory_limit` → self-exit if `memory_get_usage(true) > limit`
   - Check connection timeout — close idle connections
6. `$connection = $this->socket->accept()` (or via SCM_RIGHTS as in step 4)
7. `$this->handler->handle($connection)`
8. Auto-close: if handler didn't close connection, worker closes it (fd leak prevention — watpliwosci #27)
9. Increment `$requestCount`

**On SIGTERM:** set `$running = false`, complete current request, `exit(0)`.

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Worker.php` |

## References

Spec: Worker, Worker lifecycle, Worker stats sections | Plan: Task 14 (updated), Task 17 | Watpliwosci: #25, #26, #27, #31, #51

## Dependencies

M1.03 (Socket), M1.04 (Connection), M1.10 (ProtocolHandlerInterface), M2.01 (WorkerConfig), PSR-3 LoggerInterface

## Acceptance criteria

- `run()` forks, child runs accept loop, parent returns PID
- SIGTERM in child → graceful shutdown (finish request → exit)
- `max_requests` exceeded → self-exit
- `max_lifetime` exceeded → self-exit
- `memory_limit` exceeded → self-exit
- Auto-close connection after `handle()` if handler didn't close
- `getStats()` returns all 7 keys
- `cli_set_process_title()` called in child
