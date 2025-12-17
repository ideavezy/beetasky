<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'user_id',
        'company_id',
        'role',
        'status',
        'invitation_token',
        'invited_by',
        'joined_at',
        'is_customer',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'is_customer' => 'boolean',
            'permissions' => 'array',
        ];
    }

    /**
     * Role options for project members.
     */
    public const ROLES = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'member' => 'Member',
    ];

    /**
     * Status options for project members.
     */
    public const STATUSES = [
        'active' => 'Active',
        'pending' => 'Pending',
        'inactive' => 'Inactive',
    ];

    /**
     * Get the project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who invited this member.
     */
    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if the member is pending (hasn't accepted invitation).
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the member is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the member is an owner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if the member is an admin or owner.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin']);
    }

    /**
     * Accept the invitation and make the member active.
     */
    public function acceptInvitation(): void
    {
        $this->update([
            'status' => 'active',
            'joined_at' => now(),
            'invitation_token' => null,
        ]);
    }

    /**
     * Scope to get active members.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get pending members.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

