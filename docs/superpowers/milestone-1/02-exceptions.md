# M1.02 — Exception classes

Four runtime exceptions for the distinct failure modes: socket creation, accept errors, worker failures, and invalid configuration. All extend `\RuntimeException`.

## What it builds

- `SocketCreationException` — socket could not be created or bound
- `SocketAcceptException` — connection accept failed
- `WorkerFailedException` — worker process could not start
- `InvalidConfigurationException` — configuration validation failed

`ProtocolNotSupportedException` is intentionally NOT created (decision from watpliwosci.md #4 — unused dead code).

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Exception/SocketCreationException.php` |
| Create | `src/Server/Exception/SocketAcceptException.php` |
| Create | `src/Server/Exception/WorkerFailedException.php` |
| Create | `src/Server/Exception/InvalidConfigurationException.php` |

## References

Spec: Exception Hierarchy section | Plan: Task 2 | Watpliwosci: #4

## Dependencies

None.

## Acceptance criteria

- 4 classes, each in its own file
- All extend `\RuntimeException`, empty body
- Namespace: `CrazyGoat\Forklift\Server\Exception`
- No `ProtocolNotSupportedException`
