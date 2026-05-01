# M3.04 — JsonConfig + YamlConfig

File-based configuration loaders: JSON and YAML. Both implement `ConfigInterface`.

## What it builds

**JsonConfig:**
- Constructor takes file path
- `toArray()` reads file, `json_decode()`, validates structure (delegates to ArrayConfig logic)
- Throws `InvalidConfigurationException` on parse errors or missing keys

**YamlConfig:**
- Constructor takes file path
- `toArray()` reads file, parses YAML via `symfony/yaml`, validates structure
- Gracefully handles missing `symfony/yaml` (throws clear error message)
- `symfony/yaml` is a `suggest` dependency, not `require`

**Auto-detection in CLI:** `forklift.php` → `forklift.json` → `forklift.yaml` (first found).

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Config/JsonConfig.php` |
| Create | `src/Server/Config/YamlConfig.php` |
| Create | `tests/Server/Config/JsonConfigTest.php` |
| Create | `tests/Server/Config/YamlConfigTest.php` |

## References

Spec: ConfigInterface section (implementations), Testing Plan (JsonConfig — validation, YamlConfig — validation) | Plan: Task 22 (NEW) | Watpliwosci: #3

## Dependencies

M3.03 (ConfigInterface, ArrayConfig), `symfony/yaml` (suggest)

## Test coverage

`JsonConfigTest`:
- Valid JSON file parses and validates correctly
- Invalid JSON syntax throws `InvalidConfigurationException`
- Missing groups/listeners in JSON throws `InvalidConfigurationException`
- Listener with missing keys throws `InvalidConfigurationException`

`YamlConfigTest`:
- Valid YAML file parses and validates correctly (skip if `symfony/yaml` not installed)
- Invalid YAML syntax throws `InvalidConfigurationException`
- Reports clear error when `symfony/yaml` is not installed
- Validates structure same as ArrayConfig

## Acceptance criteria

- `JsonConfig` throws on invalid JSON
- `YamlConfig` throws on invalid YAML
- `YamlConfig` throws clear error if `symfony/yaml` not installed
- Both validate groups/listeners structure
- CLI auto-detection logic (implemented in M3.06 run() method)
- `JsonConfigTest` and `YamlConfigTest` pass
