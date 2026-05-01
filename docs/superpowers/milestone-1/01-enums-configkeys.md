# M1.01 — ProtocolType, ProxyType enums + ConfigKeys

Two string-backed enums defining the server's core dimensions, and a constants class for all configuration keys. Every other component references these types — they have zero dependencies.

## What it builds

- `ProtocolType` — `TCP`, `HTTP`, `WEBSOCKET` (backed: string)
- `ProxyType` — `REUSE_PORT`, `FORK_SHARED`, `MASTER` (backed: string)
- `ConfigKeys` — 18 string constants: GROUPS, LISTENERS, NAME, SIZE, PORT, PROTOCOL, PROXY, GROUP, HANDLER, TCP_NODELAY, MAX_HEADER_SIZE, MAX_BODY_SIZE, MAX_REQUESTS, MAX_LIFETIME, MEMORY_LIMIT, CONNECTION_TIMEOUT, STATS, STATS_KEY

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Types/ProtocolType.php` |
| Create | `src/Server/Types/ProxyType.php` |
| Create | `src/Server/Types/ConfigKeys.php` |

## References

Spec: Enums, ConfigKeys sections | Plan: Task 1

## Dependencies

None — this is the first task.

## Acceptance criteria

- Both enums use `string` backing type, 3 cases each
- ConfigKeys has all 18 constants defined
- Namespace: `CrazyGoat\Forklift\Server\Types`
