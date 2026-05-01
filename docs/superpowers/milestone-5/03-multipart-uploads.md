# M5.03 — Multipart/form-data file uploads

Parse `multipart/form-data` bodies into PSR-7 uploaded files and form fields.

## What it builds

**Multipart parser** in `HttpHandler`:
- Detect boundary from `Content-Type: multipart/form-data; boundary=...`
- Parse parts: per-part headers + body, extract `name`/`filename` from `Content-Disposition`
- File parts → `\SplTempFileObject` → `UploadedFileInterface[]`
- Form fields → `$request->getParsedBody()`
- `ListenerBuilder::maxUploadSize(int)` — per-file limit (default 10MB)

## References

Follow-up MVP+1 #3 | RFC 7578

## Dependencies

M1.12 (HttpHandler), PSR-7 `UploadedFileInterface`

## Acceptance criteria

- Uploaded files accessible via `getUploadedFiles()`
- Form fields in multipart added to `getParsedBody()`
- `maxUploadSize` enforced per file
- Non-multipart requests unaffected
