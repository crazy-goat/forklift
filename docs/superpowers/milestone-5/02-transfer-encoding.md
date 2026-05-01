# M5.02 — Transfer-Encoding: chunked

HTTP/1.1 chunked transfer: parse chunked request bodies, generate chunked responses.

## What it builds

**Request parser** (`HttpHandler`):
- Detect `Transfer-Encoding: chunked`
- Read chunk-size hex → chunk data → CRLF; repeat until 0-size + trailing headers
- Reassemble complete body; enforce `maxBodySize` on total

**Response builder:**
- `HttpHandler::useChunkedEncoding(bool)` — chunked output instead of Content-Length
- Format: hex size + CRLF + data + CRLF, terminated by `0\r\n\r\n`

## References

Follow-up MVP+1 #2 | RFC 7230 §4.1

## Dependencies

M1.12 (HttpHandler)

## Acceptance criteria

- Chunked requests parsed: chunks reassembled, trailing headers extracted
- `maxBodySize` enforced on total body
- `useChunkedEncoding(true)` produces valid chunked responses
- Non-chunked path unaffected
