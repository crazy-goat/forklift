# M1.08 — TlsProxy decorator

Decorator wrapping any `SocketProxyInterface` to add SSL/TLS encryption. After `accept()`, converts the socket to a stream and enables crypto. Enables HTTPS and WSS.

## What it builds

`TlsProxy`:
- Constructor takes inner proxy + `$certFile`, `$keyFile`, optional SSL context options
- `createSocket()` — delegates to inner proxy
- `accept()` — delegates to inner proxy, then `socket_export_stream()` + `stream_socket_enable_crypto()` with 30s timeout
- `isSupported()` — checks `function_exists('stream_socket_enable_crypto') && function_exists('socket_export_stream')`
- Throws `SocketCreationException` on TLS handshake failure

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Socket/TlsProxy.php` |
| Create | `tests/Server/Socket/TlsProxyTest.php` |

## References

Spec: TlsProxy section | Plan: Task 9 (NEW) | Watpliwosci: #35

## Dependencies

M1.05 (SocketProxyInterface), M1.02 (SocketCreationException)

## Acceptance criteria

- Decorates any `SocketProxyInterface` (inner proxy)
- `accept()` calls `stream_socket_enable_crypto()` with `STREAM_CRYPTO_METHOD_TLS_SERVER`
- 30s socket timeout set before TLS handshake
- `isSupported()` checks function availability
- Throws on TLS failure
- Test passes: validates delegate passthrough, `isSupported()`
