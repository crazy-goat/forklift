# M1.15 — Milestone 1 verification

Verify all M1 components work correctly before proceeding to M2.

## What to verify

- [ ] All 7 type/exception files exist in `src/Server/Types/` and `src/Server/Exception/`
- [ ] `ProtocolType` and `ProxyType` enums compile (`php -l`)
- [ ] `SocketTest` passes — bind, listen, close, accept
- [ ] `ReusePortProxyTest` passes (or skips on unsupported platforms)
- [ ] `ForkSharedProxyTest` passes
- [ ] `MasterProxyTest` passes — socket creation, isSupported, queue creation
- [ ] `TlsProxyTest` passes — delegate passthrough, isSupported
- [ ] `SocketFactoryTest` passes — validates REUSE_PORT → ForkShared fallback
- [ ] `HttpHandlerTest` passes — real TCP, PSR-7 request/response
- [ ] `WebSocketHandlerTest` passes — handshake 101, invalid → 400
- [ ] `ProtocolFactoryTest` passes — all three types

**Run:**
```bash
vendor/bin/phpunit tests/Server/
```

## Acceptance criteria

- All tests green
- No PHP warnings or notices in test output
- Every file has `declare(strict_types=1)` and correct namespace
