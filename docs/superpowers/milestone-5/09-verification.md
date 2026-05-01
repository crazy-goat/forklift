# M5.09 — Milestone 5 verification

Verify all MVP+1 and MVP+2 features work correctly.

## What to verify

- [ ] HTTP Keep-Alive: multiple requests on one connection
- [ ] Keep-Alive timeout: idle connection closed after `keepAliveTimeout`
- [ ] Chunked encoding: requests parsed, responses chunked
- [ ] Multipart uploads: files in `getUploadedFiles()`, fields in `getParsedBody()`
- [ ] Daemon mode: `--daemon` detaches, PID file created/removed
- [ ] Rate limiting: `maxConnectionsPerIp` enforced, `maxRequestsPerSec` returns 429
- [ ] WebSocket server ping: pings sent, connection closed on timeout
- [ ] WebSocket subprotocol: client protocol matched, correct handler selected
- [ ] WebSocket broadcast: message reaches all workers' clients
- [ ] Unix sockets: `unix://` listener works
- [ ] CPU affinity: worker pinned (Linux), no-op elsewhere
- [ ] Query string: `getQueryParams()` populated
- [ ] Expect: 100: `100 Continue` sent before body
- [ ] Correlation headers: `X-Request-Id`, `Server-Timing`, `X-Worker-Pid` in response
- [ ] SIGUSR1: log files reopened
- [ ] SIGUSR2: stats dumped to log

## Acceptance criteria

- All M5 feature tests pass
- No regressions on M1-M4 functionality
