# M2.03 — ProcessGroup

Manages a pool of N workers sharing one socket and protocol handler. Handles startup, rolling restart on worker death, and graceful shutdown.

## What it builds

`ProcessGroup`:
- Constructor: `$name`, `$size`, `ProtocolHandlerInterface $handler`
- `start(Socket $socket): void` — forks N workers (0..size-1), stores pid → Worker map
- `restart(int $pid): void` — rolling restart:
  - Only acts if PID belongs to this group (silent no-op for foreign PIDs — watpliwosci #8)
  - Max 1 concurrent restart (`$restartingCount` check)
  - Creates new worker with same `processNumber`, replaces dead one in map
- `shutdown(): void` — graceful: SIGTERM to all → wait up to 30s → SIGKILL stragglers (watpliwosci #34)
- `withConfig(WorkerConfig $config): self` — passed to every new worker
- `withLogger(LoggerInterface $logger): self` — PSR-3 logger
- `getName(): string`, `getHandler(): ProtocolHandlerInterface`, `getWorkers(): array`

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/ProcessGroup.php` |
| Create | `tests/Server/ProcessGroupTest.php` |

## References

Spec: ProcessGroup section | Plan: Task 15 (updated), Task 18 (rolling restart) | Watpliwosci: #8, #28, #34

## Dependencies

M2.02 (Worker), M2.01 (WorkerConfig), M1.10 (ProtocolHandlerInterface), M1.03 (Socket)

## Acceptance criteria

- `start()` creates exactly `$size` workers with sequential processNumbers
- `restart()` only acts on PIDs belonging to this group
- Max 1 concurrent restart (rolling)
- `shutdown()`: SIGTERM → 30s wait → SIGKILL
- `withConfig()` passes WorkerConfig to all subsequent workers
- Test passes (fork workers, start + shutdown)
