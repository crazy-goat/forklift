# M4.01 — composer.json + autoload

Register all dependencies and PSR-4 autoloading for the new `CrazyGoat\Forklift\Server` namespace.

## What to do

**Add to `require`:**
- `ext-sockets: *` — socket API
- `ext-sysvmsg: *` — message queues (MasterProxy SCM_RIGHTS)
- `psr/http-message: ^1.0|^2.0` — PSR-7
- `psr/http-server-handler: ^1.0` — PSR-15 handler
- `psr/http-server-middleware: ^1.0` — PSR-15 middleware interface (watpliwosci #15)

**Add to `require-dev`:**
- `nyholm/psr7: ^1.0` — PSR-7 implementation for tests

**Add to `suggest`:**
- `symfony/yaml: ^6.0|^7.0` — for YamlConfig

**Add to `autoload.psr-4`:**
- `"CrazyGoat\\Forklift\\Server\\": "src/Server/"`

Keep existing: `"CrazyGoat\\Forklift\\": "src/"`

## Files

| Action | Path |
|--------|------|
| Modify | `composer.json` |

## Steps

1. Edit `composer.json` with above entries
2. `composer update --with-all-dependencies`
3. `composer dump-autoload`
4. `composer validate`
5. Commit

## References

Spec: Dependencies section | Plan: Task 21, Task 26 | Watpliwosci: #15

## Acceptance criteria

- `composer validate` passes
- All NEW extensions and packages in require/require-dev/suggest
- `CrazyGoat\Forklift\Server\` autoloads from `src/Server/`
- Existing tests still pass after update
