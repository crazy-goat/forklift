# M1.07 — MasterProxy (SCM_RIGHTS)

Dispatcher proxy using `SCM_RIGHTS` socket passing via System V message queues. Master accepts connections and dispatches file descriptors to workers through `sysvmsg`.

## What it builds

`MasterProxy`:
- Creates socket with `SO_REUSEADDR`, binds, listens
- Creates a `sysvmsg` message queue (`msg_get_queue`)
- `accept()` — accepts connection, then dispatches the fd to a worker queue via `socket_sendmsg()` with `SCM_RIGHTS`
- `isSupported()` — checks `function_exists('msg_get_queue') && function_exists('socket_sendmsg')`

Workers receive fd from the message queue and handle the connection (worker-side receive logic goes in Worker, not here).

## Files

| Action | Path |
|--------|------|
| Create | `src/Server/Socket/MasterProxy.php` |
| Create | `tests/Server/Socket/MasterProxyTest.php` |

## References

Spec: MasterProxy section | Plan: Task 7b (NEW) | Watpliwosci: #7

## Dependencies

M1.05 (SocketProxyInterface), `ext-sysvmsg`

## Acceptance criteria

- Implements `SocketProxyInterface`
- Creates message queue on `createSocket()`
- Uses `socket_sendmsg()` with `SCM_RIGHTS` to pass fd to workers
- `isSupported()` checks for `msg_get_queue` and `socket_sendmsg`
- Test passes: validates socket creation, `isSupported()`, queue creation
