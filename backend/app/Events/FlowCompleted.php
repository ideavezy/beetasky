<?php

namespace App\Events;

use App\Models\AiFlowQueue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlowCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AiFlowQueue $flow;

    /**
     * Create a new event instance.
     */
    public function __construct(AiFlowQueue $flow)
    {
        $this->flow = $flow;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->flow->user_id),
            new PrivateChannel('flow.' . $this->flow->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'flow.completed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $context = $this->flow->flow_context ?? [];

        return [
            'flow_id' => $this->flow->id,
            'flow_title' => $this->flow->title,
            'status' => $this->flow->status,
            'completed_steps' => $this->flow->completed_steps,
            'total_steps' => $this->flow->total_steps,
            'started_at' => $this->flow->started_at?->toIso8601String(),
            'completed_at' => $this->flow->completed_at?->toIso8601String(),
            'created_entities' => $context['created_entities'] ?? [],
            'resolved_entities' => $context['resolved_entities'] ?? [],
            'suggestions' => $context['suggestions'] ?? [],
        ];
    }
}

