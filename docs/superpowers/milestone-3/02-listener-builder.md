# M3.02 — ListenerBuilder

Fluent API for configuring and attaching a listener to a port. This is the user-facing API surface from `ForkliftServer::listen($port)`.

## What it builds

`ListenerBuilder`:
- Constructor takes `ForkliftServer`, `$port`
- `protocol(ProtocolType|ProtocolHandlerInterface $protocol): self` — union type (watpliwosci #2)
- `proxy(ProxyType|SocketProxyInterface $proxy): self` — union type (watpliwosci #2)
- `attach(ProcessGroup $group): void` — validates, creates `Listener`, calls `$server->addListener()`
- `tcpNodelay(bool $enabled = true): self` — enable TCP_NODELAY on socket (watpliwosci #32)
- `tls(string $certFile, string $keyFile, array $options = []): self` — wraps proxy in TlsProxy (watpliwosci #35). `$options` supports `ciphers` (string), `protocols` (int), `verify_peer` (bool), `verify_peer_name` (bool).
- `maxHeaderSize(int $bytes): self` — HTTP header limit (default 65536)
- `maxBodySize(int $bytes): self` — HTTP body limit (default 10485760)
- `connectionTimeout(float $seconds): self`

`attach()` throws `InvalidConfigurationException` if protocol or proxy not set.

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/ListenerBuilder.php` |
| Create | `tests/Server/ListenerBuilderTest.php` |

## References

Spec: ListenerBuilder section (Fluent API Examples), Testing Plan (ListenerBuilder — union type validation) | Plan: Task 18 (updated), Task 20-21 | Watpliwosci: #2, #9, #32, #35

## Dependencies

M3.01 (Listener), M1.08 (TlsProxy), M2.03 (ProcessGroup)

## Test coverage

`ListenerBuilderTest` — unit test with mocked `ForkliftServer` and `ProcessGroup`:
- `protocol()` and `proxy()` accept both enum and interface types (union types)
- `attach()` throws `InvalidConfigurationException` if protocol not set
- `attach()` throws `InvalidConfigurationException` if proxy not set
- `tls()` wraps inner proxy in `TlsProxy` before constructing `Listener`
- `tcpNodelay()` flag propagation verified
- `maxHeaderSize()`, `maxBodySize()`, `connectionTimeout()` store/forward config
- `attach()` calls `$server->addListener()`

## Acceptance criteria

- `protocol()` and `proxy()` accept both enum and interface types
- `attach()` throws if protocol or proxy not set
- `tls()` wraps inner proxy in TlsProxy before constructing Listener
- `tcpNodelay()` passes flag to proxy/socket creation
- `attach()` calls `$server->addListener()`
- `ListenerBuilderTest` passes
