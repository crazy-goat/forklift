# M1.12 — HttpHandler

PSR-7/PSR-15 HTTP server handler. Parses raw HTTP into `ServerRequestInterface`, delegates to a PSR-15 handler, builds and sends the response. Includes body parsing and size limits.

## What it builds

`HttpHandler`:
- `__construct(?RequestHandlerInterface $handler, int $maxHeaderSize = 64KB, int $maxBodySize = 10MB)`
- `handle(Connection)`:
  1. Read request line and headers (with `maxHeaderSize` check — 431 if exceeded)
  2. Parse method, URI, headers into `ServerRequestInterface`
  3. Read body by `Content-Length` (with `maxBodySize` check — 413 if exceeded)
  4. Parse body content: `application/json` → `withParsedBody()`, `application/x-www-form-urlencoded` → `withParsedBody()`
  5. Call PSR-15 handler or return default 200
  6. Serialize `ResponseInterface` to raw HTTP response string
  7. Write response, close connection

Key design choices:
- `maxHeaderSize` and `maxBodySize` with proper HTTP error codes
- JSON body parsed into `$request->getParsedBody()`
- Form-urlencoded body parsed via `parse_str()`
- Always closes connection after response (keep-alive in M5)

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Protocol/HttpHandler.php` |
| Create | `tests/Server/Protocol/HttpHandlerTest.php` |

## References

Spec: HttpHandler section | Plan: Task 11 (updated) | Watpliwosci: #22 (size limits), #38 (body parsing)

## Dependencies

M1.10 (ProtocolHandlerInterface), M1.04 (Connection), PSR-7 (`psr/http-message`, `nyholm/psr7`), PSR-15 (`psr/http-server-handler`)

## Acceptance criteria

- Parses method, URI, headers from raw HTTP
- Parses JSON body (`application/json`) into `$request->getParsedBody()`
- Parses form-urlencoded body into `$request->getParsedBody()`
- Returns 431 when headers exceed `maxHeaderSize`
- Returns 413 when body exceeds `maxBodySize`
- Without PSR-15 handler, returns default 200 OK
- Test passes (real TCP connection, HTTP request/response)
