<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartImportJob extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'project_id',
        'company_id',
        'ai_run_id',
        'status',
        'progress',
        'message',
        'results',
        'source_files',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'source_files' => 'array',
            'progress' => 'integer',
        ];
    }

    /**
     * Status options for import jobs.
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ];

    /**
     * Get the user who initiated the import.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project for the import.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Check if the job is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the job is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the job is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the job has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Update the progress of the job.
     */
    public function updateProgress(int $progress, ?string $message = null): void
    {
        $data = ['progress' => min(100, max(0, $progress))];
        if ($message !== null) {
            $data['message'] = $message;
        }
        $this->update($data);
    }

    /**
     * Mark the job as processing.
     */
    public function markAsProcessing(?string $message = null): void
    {
        $this->update([
            'status' => 'processing',
            'message' => $message ?? 'Processing...',
        ]);
    }

    /**
     * Mark the job as completed.
     */
    public function markAsCompleted(array $results, ?string $message = null): void
    {
        $this->update([
            'status' => 'completed',
            'progress' => 100,
            'results' => $results,
            'message' => $message ?? 'Import completed successfully.',
        ]);
    }

    /**
     * Mark the job as failed.
     */
    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'message' => $message,
        ]);
    }

    /**
     * Scope to get pending jobs.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get processing jobs.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

