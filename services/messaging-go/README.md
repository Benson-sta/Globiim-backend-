# Globiim Go realtime messaging service

This service provides authenticated WebSocket typing events for Laravel conversations.

## Environment
- `DB_DSN`: MySQL DSN, e.g. `app:password@tcp(mysql:3306)/globiim?parseTime=true`
- `LISTEN_ADDR`: defaults to `:8081`
- `ALLOWED_ORIGINS`: comma-separated exact frontend origins
- `ALLOW_ANY_ORIGIN`: development only; keep unset/false in production

## WebSocket
Connect to `/ws?conversation_id=123` using WebSocket subprotocols `globiim.messaging.v1` and `bearer.<Laravel-Sanctum-token>`. The token is sent as a subprotocol, not in the URL. The service validates the Sanctum token against `personal_access_tokens` and checks `conversation_user` membership before accepting the connection.

Supported client events: `{"type":"typing.start"}` and `{"type":"typing.stop"}`. These are broadcast to other participants connected to the same conversation. Message persistence and permissions remain in Laravel's HTTP API.

This first version uses an in-process hub and is intended for one Go instance. Add Redis Pub/Sub before horizontal scaling. Deploy behind TLS (`wss://`), restrict allowed origins, and never expose the MySQL port publicly.
