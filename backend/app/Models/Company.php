<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'logo_url',
        'billing_status',
        'billing_cycle',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the owner of the company (primary owner).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all users (staff members) that belong to the company.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot(['role_in_company', 'permissions', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get active staff members only.
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()->whereRaw('company_user.is_active = true');
    }

    /**
     * Get owners of this company.
     */
    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role_in_company', 'owner');
    }

    /**
     * Get managers of this company.
     */
    public function managers(): BelongsToMany
    {
        return $this->users()->wherePivotIn('role_in_company', ['owner', 'manager']);
    }

    /**
     * Get all contacts associated with this company (any relationship type).
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'company_contacts')
            ->withPivot([
                'relation_type',
                'status',
                'source',
                'assigned_to',
                'converted_at',
                'first_seen_at',
                'last_activity_at',
                'metadata',
            ])
            ->withTimestamps();
    }

    /**
     * Get leads for this company.
     */
    public function leads(): BelongsToMany
    {
        return $this->contacts()->wherePivot('relation_type', 'lead');
    }

    /**
     * Get customers for this company.
     */
    public function customers(): BelongsToMany
    {
        return $this->contacts()->wherePivot('relation_type', 'customer');
    }

    /**
     * Get prospects for this company.
     */
    public function prospects(): BelongsToMany
    {
        return $this->contacts()->wherePivot('relation_type', 'prospect');
    }

    /**
     * Get vendors for this company.
     */
    public function vendors(): BelongsToMany
    {
        return $this->contacts()->wherePivot('relation_type', 'vendor');
    }

    /**
     * Get partners for this company.
     */
    public function partners(): BelongsToMany
    {
        return $this->contacts()->wherePivot('relation_type', 'partner');
    }

    /**
     * Get the company contact relationships (pivot model access).
     */
    public function companyContacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    /**
     * Add a user to this company with a role.
     */
    public function addUser(User $user, string $role = 'staff', array $permissions = []): void
    {
        $this->users()->attach($user->id, [
            'role_in_company' => $role,
            'permissions' => json_encode($permissions),
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    /**
     * Add a contact to this company with a relationship type.
     */
    public function addContact(Contact $contact, string $relationType = 'lead', array $attributes = []): void
    {
        $this->contacts()->attach($contact->id, array_merge([
            'relation_type' => $relationType,
            'status' => 'active',
            'first_seen_at' => now(),
        ], $attributes));
    }

    /**
     * Convert a lead to customer for this company.
     */
    public function convertLeadToCustomer(Contact $contact): bool
    {
        return $this->contacts()
            ->wherePivot('contact_id', $contact->id)
            ->wherePivot('relation_type', 'lead')
            ->updateExistingPivot($contact->id, [
                'relation_type' => 'customer',
                'converted_at' => now(),
            ]);
    }
}
