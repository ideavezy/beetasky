<?php

namespace App\Events;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCommentAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TaskComment $comment;
    public Task $task;

    public function __construct(TaskComment $comment, Task $task)
    {
        $this->comment = $comment;
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
        return 'task.comment.added';
    }

    public function broadcastWith(): array
    {
        return [
            'comment' => [
                'id' => $this->comment->id,
                'content' => $this->comment->content,
                'user' => $this->comment->user ? [
                    'id' => $this->comment->user->id,
                    'name' => $this->comment->user->first_name . ' ' . ($this->comment->user->last_name ?? ''),
                    'avatar' => $this->comment->user->avatar_url,
                ] : null,
                'time' => $this->comment->created_at->diffForHumans(),
                'created_at' => $this->comment->created_at,
            ],
            'task_id' => $this->task->id,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

