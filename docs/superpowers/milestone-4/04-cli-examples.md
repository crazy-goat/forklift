# M4.04 — CLI entrypoint + examples

Command-line interface and working example scripts.

## What to build

**CLI** (`bin/forklift-server`):
- `#!/usr/bin/env php`, `chmod +x`
- `--config <path>` — load specified config file
- `--daemon` / `-d` — detach from terminal (deferred implementation, flag parsed)
- Auto-detection: without `--config`, search CWD for `forklift.php` → `forklift.json` → `forklift.yaml`

**Examples** (`examples/`):
- `server.php` — Fluent API: HTTP on 8080, WebSocket on 8081, stats on 9099
- `forklift.json` — equivalent JSON config
- `forklift.yaml` — equivalent YAML config (requires symfony/yaml)

## Files

| Action | Path |
|--------|------|
| Create | `bin/forklift-server` |
| Create | `examples/server.php` |
| Create | `examples/forklift.json` |
| Create | `examples/forklift.yaml` |

## References

Spec: Fluent API Examples, CLI (run method) | Plan: Task 20 (run())

## Acceptance criteria

- `bin/forklift-server` executable, starts server with `--config`
- Auto-detection finds config files in CWD
- `--daemon` flag recognized (implementation in M5)
- `examples/server.php` runs and responds to HTTP
- Example configs match spec structure
