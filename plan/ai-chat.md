# AI Chat System Documentation

## Overview

The AI Chat system provides a real-time conversational interface between users and an AI assistant. It uses **Server-Sent Events (SSE)** for streaming AI responses token-by-token, providing a responsive chat experience similar to ChatGPT.

### Key Features

- **SSE Streaming** - Real-time token-by-token AI responses
- **Conversation Persistence** - Chat history saved to database
- **Context Awareness** - AI has access to user's tasks, projects, and contacts
- **Markdown Rendering** - AI responses rendered with full markdown support
- **Multi-tenant** - Company-scoped conversations
- **Future-ready** - Architecture supports adding Soketi for multi-user group chat

---

## Architecture

### Technology Stack

| Component | Technology |
|-----------|------------|
| AI Provider | OpenAI API (GPT-4o-mini) |
| Streaming Protocol | Server-Sent Events (SSE) |
| Backend | Laravel 12 (StreamedResponse) |
| Frontend | React 18 + Zustand |
| Markdown Rendering | react-markdown |
| Future Multi-user | Laravel Broadcasting + Soketi |

### Communication Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                         USER SENDS MESSAGE                       │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  Frontend (React)                                                │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 1. Add optimistic user message to UI                        ││
│  │ 2. Create placeholder AI message with streaming cursor      ││
│  │ 3. POST /api/v1/ai/chat/stream (fetch with ReadableStream)  ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  Backend (Laravel)                                               │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 1. Authenticate user via Supabase JWT                       ││
│  │ 2. Create/get conversation                                  ││
│  │ 3. Save user message to database                            ││
│  │ 4. Build AI context (tasks, projects, contacts)             ││
│  │ 5. Stream to OpenAI with cURL                               ││
│  │ 6. Forward chunks to client via SSE                         ││
│  │ 7. Save complete AI message to database                     ││
│  │ 8. Log AI run for analytics                                 ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│  SSE Response Format                                             │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ data: {"type":"start","conversation_id":"uuid"}             ││
│  │ data: {"type":"chunk","content":"Hello"}                    ││
│  │ data: {"type":"chunk","content":"!"}                        ││
│  │ data: {"type":"chunk","content":" How"}                     ││
│  │ ...                                                         ││
│  │ data: {"type":"done","message_id":"uuid","conversation_id"}  ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

The chat system uses tables defined in `architecture-plan.md`:

### conversations
```sql
-- Type can be 'ai_chat' for solo AI conversations
type VARCHAR(20) CHECK (type IN ('internal', 'customer', 'ai_chat'))
```

### conversation_participants
```sql
-- AI assistant uses a fixed UUID as participant
participant_type: 'ai_agent'
participant_id: '00000000-0000-0000-0000-000000000001'  -- System AI Assistant
```

### messages
```sql
-- sender_type identifies the message source
sender_type: 'user' | 'ai_agent' | 'system'
role: 'user' | 'assistant' | 'system'  -- Maps to LLM roles
```

### ai_runs
```sql
-- Each AI response is logged for analytics and debugging
run_type: 'chat_reply'
input_tokens, output_tokens, latency_ms  -- Cost tracking
```

---

## API Endpoints

### Base URL: `/api/v1/ai/chat`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/conversations` | List user's AI chat conversations |
| POST | `/conversations` | Create new conversation |
| GET | `/conversations/{id}/messages` | Get messages for a conversation |
| DELETE | `/conversations/{id}` | Delete a conversation |
| POST | `/stream` | **Stream AI response (SSE)** |

### SSE Stream Endpoint

**POST** `/api/v1/ai/chat/stream`

**Headers:**
```
Authorization: Bearer {supabase_jwt}
X-Company-ID: {company_uuid}
Content-Type: application/json
Accept: text/event-stream
```

**Request Body:**
```json
{
  "message": "What are my overdue tasks?",
  "conversation_id": "uuid-or-null"
}
```

**SSE Response Events:**

| Event Type | Payload | Description |
|------------|---------|-------------|
| `start` | `{conversation_id}` | Stream started, conversation created/identified |
| `chunk` | `{content}` | AI response text chunk |
| `done` | `{message_id, conversation_id, usage}` | Stream complete |
| `error` | `{message}` | Error occurred |

---

## Backend Implementation

### Files

| File | Purpose |
|------|---------|
| `app/Models/Conversation.php` | Conversation model with participants |
| `app/Models/Message.php` | Message model with polymorphic sender |
| `app/Services/AIService.php` | OpenAI streaming and context building |
| `app/Http/Controllers/Api/AiChatController.php` | SSE endpoint controller |
| `routes/api.php` | Route definitions |

### AIService Key Methods

```php
// Stream chat response from OpenAI
public function streamChat(
    string $message,
    array $conversationHistory,
    array $context,
    callable $onChunk
): array

// Build context from company data for AI
public function buildChatContext(?string $companyId): array
```

### Context Data Structure

The AI receives this context to answer questions:

```php
[
    'has_company' => true,
    'tasks' => [
        'total' => 15,
        'pending' => 8,
        'overdue' => 3,
        'due_soon' => 5,
        'sample' => [
            ['title' => 'Task name', 'priority' => 'high', 'due_date' => 'Jan 15, 2025', ...],
            // ... up to 10 sample tasks
        ]
    ],
    'projects' => [
        'total' => 5,
        'active' => 3,
        'sample' => [...]
    ],
    'contacts' => [
        'total' => 100,
        'leads' => 30,
        'customers' => 70
    ]
]
```

---

## Frontend Implementation

### Files

| File | Purpose |
|------|---------|
| `src/stores/chat.ts` | Zustand store for chat state |
| `src/hooks/useAIStream.ts` | SSE streaming hooks |
| `src/components/ChatMessages.tsx` | Message list with markdown |
| `src/components/Layout.tsx` | Chat sidebar integration |

### Chat Store State

```typescript
interface ChatState {
  conversationId: string | null
  messages: ChatMessage[]
  isStreaming: boolean
  error: string | null
  
  // Actions
  sendMessage: (content: string, companyId: string, userId: string) => Promise<void>
  stopStreaming: () => void
  clearConversation: () => void
  appendStreamChunk: (chunk: string) => void
}
```

### Message Type

```typescript
interface ChatMessage {
  id: string
  conversationId: string
  senderType: 'user' | 'ai_agent' | 'system'
  senderId: string | null
  sender: { id, name, avatar, type }
  role: 'user' | 'assistant' | 'system'
  content: string
  isStreaming?: boolean
  error?: boolean
  createdAt: string
}
```

### Markdown Rendering

Uses `react-markdown` with custom components for:
- Headers (h1-h4)
- Bold/Italic
- Ordered/Unordered lists
- Code blocks with syntax highlighting
- Inline code
- Links (open in new tab)
- Blockquotes

---

## Configuration

### Environment Variables

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4o-mini  # or gpt-4o, gpt-3.5-turbo
```

### config/services.php

```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
],
```

### Server Configuration (Nginx)

SSE requires disabling response buffering:

```nginx
location /api/v1/ai/chat/stream {
    proxy_buffering off;
    proxy_cache off;
    chunked_transfer_encoding on;
}
```

The controller also sets headers:
```php
'X-Accel-Buffering' => 'no'  // Disable nginx buffering
```

---

## Constants

### AI Assistant UUID

A fixed UUID is used to identify the system AI assistant as a participant:

```php
// Backend: AiChatController.php
protected const AI_ASSISTANT_ID = '00000000-0000-0000-0000-000000000001';

// Frontend: chat.ts
const AI_ASSISTANT_ID = '00000000-0000-0000-0000-000000000001'
```

---

## Future Enhancements

### Phase 2: Multi-User Group Chat with Soketi

The architecture is designed to support adding real-time multi-user chat:

```
┌─────────────────────────────────────────────────────────────────┐
│  Hybrid Architecture                                             │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ User Messages  → POST /send → Soketi broadcast to all       ││
│  │ AI Responses   → SSE stream to requester → then broadcast   ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

**Files to add:**
- `app/Events/MessageSent.php` - Broadcast when user sends message
- `app/Events/AiMessageComplete.php` - Broadcast when AI finishes
- `src/hooks/useChatChannel.ts` - Echo/Soketi subscription

**New endpoint:**
- `POST /conversations/{id}/send` - Send message (broadcasts via Soketi)

### Other Future Features

| Feature | Description |
|---------|-------------|
| **Conversation History Panel** | Browse and resume past conversations |
| **Message Reactions** | Like/thumbs up AI responses |
| **Copy to Clipboard** | Copy code blocks or full responses |
| **Export Chat** | Download conversation as markdown/PDF |
| **AI Tools** | Allow AI to execute actions (create task, etc.) |
| **Voice Input** | Speech-to-text for chat input |
| **Attachments** | Send images/files in chat |
| **@Mentions** | Trigger AI in group chats with @assistant |

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 401 Unauthorized | Check JWT token is being passed correctly |
| 500 Internal Server Error | Check Laravel logs for specific error |
| No streaming (all at once) | Check nginx buffering configuration |
| "OpenAI API key not configured" | Add `OPENAI_API_KEY` to `.env` |
| UUID error for AI assistant | Ensure using proper UUID format |
| CORS errors | Check `config/cors.php` allows frontend origin |

### Testing with cURL

```bash
curl -N -X POST "http://localhost:8000/api/v1/ai/chat/stream" \
  -H "Authorization: Bearer YOUR_SUPABASE_JWT" \
  -H "X-Company-ID: YOUR_COMPANY_UUID" \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello, what can you help me with?"}'
```

---

## Related Documentation

| Document | Relevance |
|----------|-----------|
| [architecture-plan.md](./architecture-plan.md) | Database schema for conversations, messages, ai_runs |
| [coding-guidelines.md](./coding-guidelines.md) | PostgreSQL boolean handling, Zustand patterns |
| [ui-requirements.md](./ui-requirements.md) | Chat UI styling guidelines |

