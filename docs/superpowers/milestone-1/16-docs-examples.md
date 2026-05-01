# M1.16 — M1 documentation + examples

Update docs and create examples covering M1 components.

## What to do

- [ ] Verify `docs/superpowers/specs/2026-05-01-forklift-server-design.md` reflects all M1 decisions (enums, exceptions, Socket, Connection, proxies, handlers)
- [ ] Verify `docs/superpowers/plans/2026-05-01-forklift-server.md` Tasks 1-13 are aligned with actual implementations
- [ ] Verify `docs/superpowers/watpliwosci.md` decisions #4, #7, #13, #14, #22, #24, #33, #35 are reflected
- [ ] Add code examples to spec if any M1 components lack usage examples
- [ ] Create `examples/m1-sockets.php` — standalone script demonstrating Socket + Connection + proxies without ForkliftServer
- [ ] Create `examples/m1-protocols.php` — standalone script demonstrating HttpHandler + WebSocketHandler usage on raw connections

## Acceptance criteria

- Spec sections for all M1 components are up to date
- Plan tasks 1-13 are marked as complete
- Both example scripts run without errors
