# M3.05 — ForkliftConfig

Validated configuration holder. Loads from any `ConfigInterface` source and stores parsed groups and listeners. Supports FQCN handler resolution for file-based configs.

## What it builds

`ForkliftConfig`:
- `load(ConfigInterface $config): self` — loads, validates, returns self for chaining
- Validates:
  - At least 1 group and 1 listener
  - Each listener has port, protocol, proxy, group
  - Referenced group names exist in the groups array
- FQCN handler resolution: if `'handler' => 'My\App\Handler::class'`, instantiates via `new $className`
- Stores: `$groups` (array of name, size, handler) and `$listeners` (array of port, protocol, proxy, group, options)

Replaces old plan's static `fromArray()`/`fromJson()` — now uses `ConfigInterface` exclusively (plan update #23).

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/ForkliftConfig.php` |
| Create | `tests/Server/ForkliftConfigTest.php` |

## References

Spec: ForkliftConfig section (updated) | Plan: Task 19, Task 23 | Watpliwosci: #16 (handler per group)

## Dependencies

M3.03 (ConfigInterface), M1.01 (ConfigKeys)

## Acceptance criteria

- `load(ConfigInterface)` returns `self`
- Throws on missing groups/listeners
- Throws on listener referencing nonexistent group
- FQCN handler strings resolved to instances
- Test passes (valid config, missing keys, bad handler class)
