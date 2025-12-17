<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends Model
{
    use HasUuids;

    protected $table = 'ai_runs';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'ai_agent_id',
        'user_id',
        'conversation_id',
        'run_type',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'latency_ms',
        'request_payload',
        'response_payload',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_usd' => 'decimal:4',
            'latency_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the company that owns the AI run.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who triggered the AI run.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the conversation associated with the AI run.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Calculate estimated cost based on token usage.
     */
    public function calculateCost(): float
    {
        // GPT-4o-mini pricing (approximate)
        $inputCostPer1k = 0.00015;
        $outputCostPer1k = 0.0006;

        $inputCost = ($this->input_tokens ?? 0) / 1000 * $inputCostPer1k;
        $outputCost = ($this->output_tokens ?? 0) / 1000 * $outputCostPer1k;

        return round($inputCost + $outputCost, 6);
    }
}

