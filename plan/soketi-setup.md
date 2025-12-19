# Soketi WebSocket Integration Guide

This document explains how to configure Soketi (Pusher-compatible WebSocket server) for real-time features in BeetaSky.

## Railway Soketi Configuration

Your Soketi service on Railway has the following configuration:

| Variable | Value |
|----------|-------|
| `SOKETI_DEFAULT_APP_ID` | `O0DcvcsT` |
| `SOKETI_DEFAULT_APP_KEY` | `cqlc5yili98f5ikoytrgz9ypl2lo5tpc` |
| `SOKETI_DEFAULT_APP_SECRET` | `kv4rzmvm2o6g5sq9lye7r11cw886fvd1` |
| `SOKETI_PUBLIC_HOST` | `soketi-production-f84e.up.railway.app` |
| `SOKETI_PUBLIC_PORT` | `443` |

## Backend Configuration (Laravel)

### Environment Variables

Add these to your `.env` file:

```bash
# Broadcasting Configuration
BROADCAST_CONNECTION=pusher

# Pusher/Soketi Configuration
PUSHER_APP_ID=O0DcvcsT
PUSHER_APP_KEY=cqlc5yili98f5ikoytrgz9ypl2lo5tpc
PUSHER_APP_SECRET=kv4rzmvm2o6g5sq9lye7r11cw886fvd1
PUSHER_HOST=soketi-production-f84e.up.railway.app
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1
```

### Railway Backend Service Variables

Make sure your **Beemud - Backend** service on Railway has these environment variables:

```bash
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=O0DcvcsT
PUSHER_APP_KEY=cqlc5yili98f5ikoytrgz9ypl2lo5tpc
PUSHER_APP_SECRET=kv4rzmvm2o6g5sq9lye7r11cw886fvd1
PUSHER_HOST=soketi-production-f84e.up.railway.app
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1
```

## Client Configuration (React)

### Environment Variables

Add these to your client app's `.env` file:

```bash
# API URL (your Laravel backend)
VITE_API_URL=https://your-backend-url.railway.app
```

The client will automatically fetch the Pusher/Soketi configuration from the backend API endpoint `/api/config/pusher`.

## How It Works

### Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   React Client  │────▶│  Soketi Server  │◀────│  Laravel API    │
│  (Laravel Echo) │     │   (Railway)     │     │   (Railway)     │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                                                │
        │  1. Fetch config from /api/config/pusher       │
        │◀───────────────────────────────────────────────│
        │                                                │
        │  2. Connect to Soketi WebSocket                │
        │────────────────────────▶                       │
        │                                                │
        │  3. Authenticate via /api/broadcasting/auth    │
        │◀───────────────────────────────────────────────│
        │                                                │
        │  4. Subscribe to private/presence channels     │
        │────────────────────────▶                       │
        │                                                │
        │  5. Laravel broadcasts events to Soketi        │
        │                        ◀───────────────────────│
        │                                                │
        │  6. Soketi pushes events to connected clients  │
        │◀───────────────────────                        │
```

### Key Files Created/Updated

| File | Purpose |
|------|---------|
| `apps/client/src/lib/echo.ts` | Echo initialization with Soketi config |
| `apps/client/src/stores/echo.ts` | Zustand store for Echo connection state |
| `apps/client/src/hooks/useEcho.ts` | React hooks for channel subscriptions |
| `apps/client/src/components/AuthProvider.tsx` | Auto-connects Echo when authenticated |
| `apps/client/src/pages/ProjectDetailPage.tsx` | Example with real-time task updates |
| `backend/config/broadcasting.php` | Pusher driver configuration |
| `backend/routes/channels.php` | Channel authorization rules |

### Available Hooks

```typescript
// Connect Echo when authenticated (used in AuthProvider)
useEchoConnection()

// Subscribe to private channels
usePrivateChannel('channel-name', {
  '.event-name': (data) => console.log(data)
})

// Subscribe to presence channels (who's online)
usePresenceChannel('channel-name', {
  onHere: (members) => {},
  onJoining: (member) => {},
  onLeaving: (member) => {},
  events: { '.event-name': (data) => {} }
})

// Project-specific channels
useProjectChannel(projectId, {
  onTaskCreated: (data) => {},
  onTaskUpdated: (data) => {},
  onTaskDeleted: (data) => {},
  onTopicUpdated: (data) => {},
  onCommentAdded: (data) => {}
})

// User notifications channel
useUserChannel(userId, {
  onNotification: (data) => {},
  onFlowUpdate: (data) => {},
  onFlowCompleted: (data) => {},
  onFlowInputRequired: (data) => {}
})

// Conversation messages
useConversationChannel(conversationId, {
  onMessage: (data) => {},
  onTyping: (data) => {},
  onRead: (data) => {}
})

// Project presence (who's viewing)
useProjectPresence(projectId, {
  onMembersChange: (members) => {}
})
```

### Broadcasting Events from Laravel

To broadcast events from Laravel:

```php
// In your controller or service
use App\Events\TaskUpdated;

broadcast(new TaskUpdated($task))->toOthers();
```

Example event class:

```php
<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Task $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->task->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'task' => $this->task->toArray(),
        ];
    }
}
```

## Troubleshooting

### Connection Issues

1. **Check browser console** for WebSocket connection errors
2. **Verify CORS** - Ensure your backend allows the client origin
3. **Check Railway logs** for Soketi service
4. **Verify environment variables** are set correctly

### Authentication Failures

1. **Check `/api/broadcasting/auth` endpoint** returns 200
2. **Ensure Supabase token** is being sent in Authorization header
3. **Verify channel authorization** logic in `routes/channels.php`

### Events Not Received

1. **Check Laravel logs** for broadcast errors
2. **Verify event implements** `ShouldBroadcast`
3. **Check channel name** matches between Laravel and React
4. **Event name must include dot prefix** in React (e.g., `.task.updated`)

## Local Development

For local development, you can run Soketi locally:

```bash
# Install Soketi globally
npm install -g @soketi/soketi

# Run Soketi
soketi start
```

Then update your `.env`:

```bash
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
```

And client `.env`:

```bash
VITE_API_URL=http://localhost:8000
```

