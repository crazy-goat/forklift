# M5.07 — WebSocket broadcast / pub-sub

Fan-out messages from one WebSocket client to all connected clients across worker processes via IPC.

## What it builds

**WebSocketBroadcast interface:**
```
send(channel, message) | subscribe(channel) | unsubscribe(channel)
```

**Unix socket IPC implementation (default):**
- AF_UNIX relay socket — all workers connect
- Worker publishes → relay distributes to subscribed workers → workers forward to their clients
- Lightweight relay process or master-integrated

**Redis pub-sub implementation (alternative):**
- Workers subscribe to Redis channels, publish for broadcast
- For multi-machine deployments

**Integration:**
- `WebSocketHandler::broadcast(channel, message)` — send to all
- `WebSocketHandler::onBroadcast(callback)` — receive broadcast

## References

Follow-up MVP+1 #8

## Dependencies

M1.13 (WebSocketHandler), IPC (ext-sockets or Redis)

## Acceptance criteria

- Broadcast reaches all workers subscribed to channel
- Messages forwarded to connected WebSocket clients
- Unix socket IPC works single-machine
- Redis backend works as alternative
