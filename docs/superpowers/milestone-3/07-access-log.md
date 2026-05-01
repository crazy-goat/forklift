# M3.07 — AccessLogFormatterInterface + JsonAccessLogFormatter

Structured access logging for HTTP requests. Pluggable formatter with JSON default.

## What it builds

**AccessLogFormatterInterface:**
```
format(method, path, status, bytes, durationMs, peer, context = []) → string
```

**JsonAccessLogFormatter (default):**
- Produces JSON lines: `{"time":"2026-05-01T12:00:00+00:00","method":"GET","path":"/","status":200,"bytes":1234,"duration_ms":5,"peer":"127.0.0.1"}`
- Includes ISO 8601 timestamp

**HttpHandler integration:**
- Accepts optional `AccessLogFormatterInterface` and PSR-3 logger
- After each request, measures duration, formats entry, logs at INFO level

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Log/AccessLogFormatterInterface.php` |
| Create | `src/Server/Log/JsonAccessLogFormatter.php` |

## References

Spec: Access Log section | Plan: Task 24 (NEW) | Watpliwosci: #47

## Dependencies

PSR-3 LoggerInterface, M1.12 (HttpHandler)

## Acceptance criteria

- `AccessLogFormatterInterface::format()` returns string
- `JsonAccessLogFormatter` outputs valid JSON with 7 fields including ISO 8601 time
- HttpHandler accepts optional formatter + logger, logs after each request
