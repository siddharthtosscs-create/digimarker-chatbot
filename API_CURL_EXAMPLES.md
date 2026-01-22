# Agent Panel API - cURL Examples

## Disconnect Chat API

### Endpoint
```
POST /api/agent/disconnect.php
```

### cURL Command

**Windows (PowerShell):**
```powershell
curl.exe -X POST http://localhost/digimarker/public/api/agent/disconnect.php `
  -H "Content-Type: application/json" `
  -H "X-API-Key: my_secure_key_12345" `
  -d '{\"session_id\": \"your-session-id-here\", \"agent_id\": 1}'
```

**Linux/Mac:**
```bash
curl -X POST http://localhost/digimarker/public/api/agent/disconnect.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: my_secure_key_12345" \
  -d '{
    "session_id": "your-session-id-here",
    "agent_id": 1
  }'
```

### Request Body
```json
{
  "session_id": "uuid-or-session-id",
  "agent_id": 1
}
```

### Headers
- `Content-Type: application/json`
- `X-API-Key: your_agent_api_key` (must match `DIGIMARKER_AGENT_API_KEY` in config)

### Success Response (200)
```json
{
  "success": true,
  "message": "Chat disconnected successfully."
}
```

### Error Responses

**401 Unauthorized:**
```json
{
  "error": "Unauthorized"
}
```

**400 Bad Request:**
```json
{
  "error": "Missing or invalid session_id or agent_id."
}
```

**403 Forbidden:**
```json
{
  "error": "Agent not assigned to this session or session not found."
}
```

### Example with Real Values
```bash
curl -X POST http://localhost/digimarker/public/api/agent/disconnect.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: my_secure_key_12345" \
  -d '{
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "agent_id": 1
  }'
```

---

## Other Agent APIs

### Get Waiting Chats Queue
```bash
curl -X GET http://localhost/digimarker/public/api/agent/queue.php \
  -H "X-API-Key: my_secure_key_12345"
```

### Accept Chat
```bash
curl -X POST http://localhost/digimarker/public/api/agent/accept.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: my_secure_key_12345" \
  -d '{
    "session_id": "your-session-id",
    "agent_id": 1
  }'
```

### Get Chat Session
```bash
curl -X GET "http://localhost/digimarker/public/api/agent/chat-session.php?session_id=your-session-id" \
  -H "X-API-Key: my_secure_key_12345"
```

### Send Agent Message
```bash
curl -X POST http://localhost/digimarker/public/api/agent/send-message.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: my_secure_key_12345" \
  -d '{
    "session_id": "your-session-id",
    "agent_id": 1,
    "message": "Hello, how can I help you?"
  }'
```

### Get All Agents
```bash
curl -X GET http://localhost/digimarker/public/api/agent/agents.php \
  -H "X-API-Key: my_secure_key_12345"
```

### Update Agent Status
```bash
curl -X POST http://localhost/digimarker/public/api/agent/update-status.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: my_secure_key_12345" \
  -d '{
    "agent_id": 1,
    "status": "online"
  }'
```

---

## Notes

- Replace `my_secure_key_12345` with your actual API key from `config/config.local.php`
- Replace `your-session-id` with actual session IDs from your database
- Replace `agent_id` with actual agent IDs from your `agents` table
- For production, use `https://` instead of `http://`
- All agent APIs require the `X-API-Key` header for authentication

