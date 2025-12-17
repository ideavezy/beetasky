<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'task_id',
        'comment_id',
        'company_id',
        'uploaded_by',
        'filename',
        'bunny_filename',
        'path',
        'mime_type',
        'size',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * Status options for attachments.
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ];

    /**
     * Get the task that owns the attachment.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the comment that owns the attachment.
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'comment_id');
    }

    /**
     * Get the company that owns the attachment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who uploaded the attachment.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the attachment is a document.
     */
    public function isDocument(): bool
    {
        $docTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument'];
        foreach ($docTypes as $type) {
            if (str_starts_with($this->mime_type, $type)) {
                return true;
            }
        }
        return false;
    }
}

