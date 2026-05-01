# M3.09 — M3 documentation + examples

Update docs and create full server examples.

## What to do

- [ ] Verify spec ForkliftServer, ListenerBuilder, ForkliftConfig, Access Log sections match implementation
- [ ] Verify plan Tasks 17-18, 20-25 are aligned
- [ ] Verify watpliwosci decisions #2, #3, #8, #16, #29, #32, #35, #44, #47 are reflected
- [ ] Create `examples/server.php` — full Fluent API example: HTTP + WebSocket + stats
- [ ] Create `examples/server-config.php` — config-based startup via ArrayConfig
- [ ] Create `examples/forklift.json` — JSON config matching the spec example
- [ ] Create `examples/forklift.yaml` — YAML config (requires symfony/yaml)
- [ ] Update `docs/superpowers/specs/...` with any missing usage examples

## Acceptance criteria

- All examples run without errors
- JSON and YAML configs are valid and loadable
- Spec Fluent API Examples section is complete
