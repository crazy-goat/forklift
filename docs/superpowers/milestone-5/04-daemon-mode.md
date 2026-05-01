# M5.04 — Daemon mode + PID file

Detach ForkliftServer from terminal to run as a background service.

## What it builds

**Daemon mode** in `ForkliftServer::run()`:
- `--daemon` / `-d` CLI flag
- Double-fork: parent exits, child → `setsid()` → second fork → fully detached
- Redirect stdin → /dev/null, stdout/stderr → log or /dev/null

**PID file:**
- `--pid-file <path>` (default `/var/run/forklift.pid`)
- Write PID on startup, remove on shutdown
- Check for existing PID file → refuse if stale process running

## References

Follow-up MVP+1 #4

## Dependencies

M3.06 (ForkliftServer)

## Acceptance criteria

- `--daemon` detaches from terminal, process continues in background
- PID file created/removed correctly
- Duplicate start prevented by PID file check
- Signal forwarding works for daemon process
