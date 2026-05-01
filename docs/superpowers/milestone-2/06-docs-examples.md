# M2.06 — M2 documentation + examples

Update docs and create examples for worker and process group functionality.

## What to do

- [ ] Verify spec Worker, WorkerConfig, ProcessGroup sections match implementation
- [ ] Verify plan Tasks 14-19 are aligned with actual code
- [ ] Verify watpliwosci.md decisions #25, #26, #27, #28, #31, #51 are reflected
- [ ] Create `examples/m2-workers.php` — standalone script demonstrating Worker lifecycle: start, stats poll, graceful shutdown
- [ ] Create `examples/m2-process-group.php` — script demonstrating ProcessGroup with multiple workers, restart on failure

## Acceptance criteria

- Spec sections for M2 components up to date
- Plan tasks 14-19 marked complete
- Both example scripts demonstrate worker lifecycle correctly
