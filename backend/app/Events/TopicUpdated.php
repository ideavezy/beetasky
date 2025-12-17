<?php

namespace App\Events;

use App\Models\Topic;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TopicUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Topic $topic;
    public string $action;
    public ?string $userId;

    public function __construct(Topic $topic, string $action = 'updated', ?string $userId = null)
    {
        $this->topic = $topic;
        $this->action = $action;
        $this->userId = $userId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->topic->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'topic.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'topic' => [
                'id' => $this->topic->id,
                'name' => $this->topic->name,
                'color' => $this->topic->color,
                'position' => $this->topic->position,
            ],
            'action' => $this->action,
            'user_id' => $this->userId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

