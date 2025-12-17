<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $taskId;
    public string $projectId;
    public string $topicId;
    public ?string $userId;

    public function __construct(string $taskId, string $projectId, string $topicId, ?string $userId = null)
    {
        $this->taskId = $taskId;
        $this->projectId = $projectId;
        $this->topicId = $topicId;
        $this->userId = $userId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->projectId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->taskId,
            'topic_id' => $this->topicId,
            'user_id' => $this->userId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

