# M4.05 — Milestone 4 verification

Final verification of the complete MVP: all tests, static analysis, composer, examples.

## What to verify

- [ ] **All tests:** `vendor/bin/phpunit` — every test passes (unit + integration)
- [ ] **Integration:** HTTP responds correctly on both ports
- [ ] **Integration:** WebSocket handshake 101 works
- [ ] **Integration:** Worker restart on SIGCHLD
- [ ] **Integration:** Graceful shutdown on SIGTERM
- [ ] **Integration:** Stats endpoint returns JSON metrics
- [ ] **Integration:** Hot reload via SIGHUP
- [ ] **Static analysis:** `vendor/bin/phpstan analyse src/Server` — no errors (level 5+)
- [ ] **Composer:** `composer validate` — valid
- [ ] **Autoload:** `composer dump-autoload` — no errors
- [ ] **CLI:** `bin/forklift-server --help` displays usage
- [ ] **Example:** `php examples/server.php` starts and responds

**Run all:**
```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse src/Server
composer validate
```

## Acceptance criteria

- All tests green
- Static analysis clean
- CLI and examples functional
- Every file has `declare(strict_types=1)`
- No references to removed `ProtocolNotSupportedException`
- Existing `src/` files (Forklift.php, etc.) untouched
