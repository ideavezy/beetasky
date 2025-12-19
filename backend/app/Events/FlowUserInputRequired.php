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

class FlowUserInputRequired implements ShouldBroadcast
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
        return 'flow.user_input_required';
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
            'flow_title' => $this->flow->title,
            'step_id' => $this->step->id,
            'step_position' => $this->step->position,
            'step_title' => $this->step->title,
            'prompt_type' => $this->step->prompt_type,
            'prompt_message' => $this->step->prompt_message,
            'prompt_options' => $this->step->prompt_options,
            'completed_steps' => $this->flow->completed_steps,
            'total_steps' => $this->flow->total_steps,
        ];
    }
}

