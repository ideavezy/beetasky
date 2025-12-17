<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CompanyUser Pivot Model
 * 
 * Represents the membership of a user in a company.
 * One user can be:
 * - Owner of Company A
 * - Manager of Company B  
 * - Staff of Company C
 */
class CompanyUser extends Model
{
    protected $table = 'company_user';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'user_id',
        'role_in_company',
        'permissions',
        'is_active',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    /**
     * Role constants.
     */
    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_STAFF = 'staff';
    public const ROLE_AGENT = 'agent';

    /**
     * Valid roles.
     */
    public static function roles(): array
    {
        return [
            self::ROLE_OWNER,
            self::ROLE_MANAGER,
            self::ROLE_STAFF,
            self::ROLE_AGENT,
        ];
    }

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is an owner membership.
     */
    public function isOwner(): bool
    {
        return $this->role_in_company === self::ROLE_OWNER;
    }

    /**
     * Check if this is a manager membership.
     */
    public function isManager(): bool
    {
        return in_array($this->role_in_company, [self::ROLE_OWNER, self::ROLE_MANAGER]);
    }

    /**
     * Check if membership is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Activate membership.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate membership.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        // Owners have all permissions
        if ($this->isOwner()) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Grant a permission.
     */
    public function grantPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }
    }

    /**
     * Revoke a permission.
     */
    public function revokePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->update(['permissions' => array_values($permissions)]);
    }

    /**
     * Update role.
     */
    public function updateRole(string $role): void
    {
        if (in_array($role, self::roles())) {
            $this->update(['role_in_company' => $role]);
        }
    }

    /**
     * Scope for active memberships.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role_in_company', $role);
    }

    /**
     * Scope for owners.
     */
    public function scopeOwners($query)
    {
        return $query->withRole(self::ROLE_OWNER);
    }

    /**
     * Scope for managers (includes owners).
     */
    public function scopeManagers($query)
    {
        return $query->whereIn('role_in_company', [self::ROLE_OWNER, self::ROLE_MANAGER]);
    }
}

