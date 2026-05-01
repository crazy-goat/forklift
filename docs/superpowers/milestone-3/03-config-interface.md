# M3.03 — ConfigInterface + ArrayConfig

Configuration abstraction. Decouples config format from ForkliftConfig. First implementation: configuration from a PHP array.

## What it builds

**ConfigInterface:**
- `toArray(): array` — returns `['groups' => [...], 'listeners' => [...], ...]`

**ArrayConfig:**
- Constructor takes a PHP array
- `toArray()` validates:
  - `groups` key present and non-empty
  - `listeners` key present and non-empty
  - Each listener has `port`, `protocol`, `proxy`, `group` keys
- Throws `InvalidConfigurationException` on validation failure

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Config/ConfigInterface.php` |
| Create | `src/Server/Config/ArrayConfig.php` |
| Create | `tests/Server/Config/ArrayConfigTest.php` |

## References

Spec: ConfigInterface section, Testing Plan (ArrayConfig — validation) | Plan: Task 22 (NEW) | Watpliwosci: #3

## Dependencies

M1.02 (InvalidConfigurationException)

## Test coverage

`ArrayConfigTest`:
- Valid config with groups + listeners passes validation
- Missing `groups` key throws `InvalidConfigurationException`
- Missing `listeners` key throws `InvalidConfigurationException`
- Empty groups array throws `InvalidConfigurationException`
- Listener missing `port`/`protocol`/`proxy`/`group` key throws `InvalidConfigurationException`
- `toArray()` returns the original array for valid config

## Acceptance criteria

- `ConfigInterface` defines `toArray(): array`
- `ArrayConfig` validates groups and listeners are non-empty
- `ArrayConfig` validates each listener has required keys
- Throws `InvalidConfigurationException` on invalid config
- `ArrayConfigTest` passes
