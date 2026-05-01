# M2.04 — ProcessGroupBuilder

Fluent builder for creating ProcessGroup instances and registering them with ForkliftServer.

## What it builds

`ProcessGroupBuilder`:
- Constructor takes `ForkliftServer`, `$name`, `$size`
- `handler(ProtocolHandlerInterface|\Closure $handler): self` — sets handler; Closure is auto-wrapped in `TcpHandler`
- `config(WorkerConfig $config): self` — sets worker lifecycle config
- `create(): ProcessGroup` — validates handler is set, creates ProcessGroup with config, calls `$server->addGroup($group)`

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/ProcessGroupBuilder.php` |

## References

Spec: ProcessGroupBuilder section (Fluent API Examples) | Plan: Task 16 (updated), Task 19

## Dependencies

M2.03 (ProcessGroup), M2.01 (WorkerConfig), M1.10 (TcpHandler for Closure wrapping), M1.02 (InvalidConfigurationException)

## Acceptance criteria

- `handler()` accepts both `ProtocolHandlerInterface` and `\Closure`
- `\Closure` auto-wrapped in `TcpHandler`
- `create()` throws `InvalidConfigurationException` if handler not set
- `create()` calls `$server->addGroup()` to register the group
