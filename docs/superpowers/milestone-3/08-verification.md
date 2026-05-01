# M3.08 — Milestone 3 verification

Verify the full server orchestration works: fluent API, config loading, lifecycle, access logging.

## What to verify

- [ ] `ListenerTest` passes — mocked dependencies, correct delegation
- [ ] `ListenerBuilderTest` passes — union types, TLS wrapping, validation
- [ ] `ArrayConfigTest` passes — validates groups/listeners structure
- [ ] `JsonConfigTest` passes — JSON parsing + validation
- [ ] `ConfigInterface` — ArrayConfig, JsonConfig, YamlConfig all load and validate
- [ ] `ForkliftConfig` — `load()` validates, resolves FQCN handlers
- [ ] `ForkliftServer` — fluent API creates groups and listeners
- [ ] `ForkliftServer::load(ConfigInterface)` — config-based startup
- [ ] `ForkliftServer::start()` — all listeners start, main loop runs
- [ ] Signal handling: SIGTERM → graceful shutdown, SIGCHLD → worker restart
- [ ] Stats endpoint: JSON with worker metrics, 401 without key
- [ ] Access log: HTTP requests logged via PSR-3 with JSON format

**Run:**
```bash
vendor/bin/phpunit tests/Server/ListenerTest.php
vendor/bin/phpunit tests/Server/ListenerBuilderTest.php
vendor/bin/phpunit tests/Server/Config/ArrayConfigTest.php
vendor/bin/phpunit tests/Server/Config/JsonConfigTest.php
vendor/bin/phpunit tests/Server/ForkliftConfigTest.php
```

**Manual smoke test:**
```bash
php examples/server.php  # should start and respond on port 8080
curl http://127.0.0.1:8080/
```

## Acceptance criteria

- All M3 unit tests green (`ListenerTest`, `ListenerBuilderTest`, `ArrayConfigTest`, `JsonConfigTest`, `ForkliftConfigTest`)
- Server starts via fluent API and config loading
- Stats endpoint returns valid JSON
- Access log entries appear in PSR-3 logger
