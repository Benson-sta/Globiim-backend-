# Globiim Messaging API

Base path: `/api/v1/messaging`. All endpoints require a valid Laravel Sanctum bearer token.

## Create or find a direct conversation
`POST /conversations`
```json
{ "type": "direct", "participant_ids": [24] }
```
For groups, use `type: "group"`, optional `title`, and participant IDs.

## List conversations
`GET /conversations`

## Read messages
`GET /conversations/{conversation}/messages`
Returns a cursor-paginated message collection (50 per page, newest first).

## Send a text message
`POST /conversations/{conversation}/messages`
```json
{ "type": "text", "body": "Hello from Globiim" }
```

## Mark a conversation read
`POST /conversations/{conversation}/read`

The API checks conversation membership before reading, sending or marking read. Message bodies are limited to 10,000 characters. Attachments, editing, reactions, delivery receipts, group management and end-to-end encryption are not implemented in this first backend milestone.

## Go realtime service
See `services/messaging-go/README.md`. It currently broadcasts typing start/stop events to participants connected to the same Go instance. Message persistence remains in Laravel.

## Deployment / frontend connection
1. Install dependencies and run Laravel migrations on the API host.
2. Configure HTTPS and the frontend origin in CORS.
3. Set the frontend API base URL to the deployed Laravel API URL.
4. Pass the authenticated user's Sanctum token as `Authorization: Bearer <token>`.
5. Deploy the Go service with the same MySQL database and set the exact frontend origin in `ALLOWED_ORIGINS`.
6. Configure the frontend WebSocket URL as `wss://<realtime-host>/ws`.

Do not put database credentials or service secrets in the frontend.
