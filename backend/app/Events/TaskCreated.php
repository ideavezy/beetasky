<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Task $task;
    public ?string $userId;

    public function __construct(Task $task, ?string $userId = null)
    {
        $this->task = $task;
        $this->userId = $userId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->task->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.created';
    }

    public function broadcastWith(): array
    {
        return [
            'task' => [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'description' => $this->task->description,
                'status' => $this->task->status,
                'priority' => $this->task->priority,
                'completed' => $this->task->completed,
                'topic_id' => $this->task->topic_id,
                'order' => $this->task->order,
                'due_date' => $this->task->due_date,
                'assignees' => [],
            ],
            'user_id' => $this->userId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

