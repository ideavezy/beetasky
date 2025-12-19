<?php

namespace App\Events;

use App\Models\AiFlowQueue;
use App\Models\AiFlowStep;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlowStepCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AiFlowQueue $flow;
    public AiFlowStep $step;

    /**
     * Create a new event instance.
     */
    public function __construct(AiFlowQueue $flow, AiFlowStep $step)
    {
        $this->flow = $flow;
        $this->step = $step;
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
        return 'flow.step.completed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'flow_id' => $this->flow->id,
            'step_id' => $this->step->id,
            'step_position' => $this->step->position,
            'step_title' => $this->step->title,
            'step_type' => $this->step->step_type,
            'skill_slug' => $this->step->skill_slug,
            'status' => $this->step->status,
            'result' => $this->step->result,
            'completed_steps' => $this->flow->completed_steps,
            'total_steps' => $this->flow->total_steps,
            'progress_percentage' => $this->flow->getProgressPercentage(),
            'flow_status' => $this->flow->status,
        ];
    }
}

