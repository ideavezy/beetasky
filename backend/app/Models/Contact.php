<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contact Model
 * 
 * Represents a global contact identity (person or organization).
 * One contact can have relationships with multiple companies:
 * - Lead of Company A
 * - Customer of Company B
 * - Vendor of Company C
 * 
 * Optionally linked to a User for portal login access.
 */
class Contact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'address',
        'auth_user_id',
        'organization',
        'job_title',
        'avatar_url',
        'custom_fields',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'custom_fields' => 'array',
            'tags' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the user account linked to this contact (for portal login).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auth_user_id');
    }

    /**
     * Get all companies this contact is associated with.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_contacts')
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
     * Get companies where this contact is a lead.
     */
    public function asLeadOf(): BelongsToMany
    {
        return $this->companies()->wherePivot('relation_type', 'lead');
    }

    /**
     * Get companies where this contact is a customer.
     */
    public function asCustomerOf(): BelongsToMany
    {
        return $this->companies()->wherePivot('relation_type', 'customer');
    }

    /**
     * Get companies where this contact is a vendor.
     */
    public function asVendorOf(): BelongsToMany
    {
        return $this->companies()->wherePivot('relation_type', 'vendor');
    }

    /**
     * Get companies where this contact is a partner.
     */
    public function asPartnerOf(): BelongsToMany
    {
        return $this->companies()->wherePivot('relation_type', 'partner');
    }

    /**
     * Get all company relationships (pivot model access).
     */
    public function companyRelationships(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    /**
     * Check if contact has a login user account.
     */
    public function hasUserAccount(): bool
    {
        return $this->auth_user_id !== null;
    }

    /**
     * Get the contact's relationship type with a specific company.
     */
    public function relationTypeWith(Company $company): ?string
    {
        $relationship = $this->companies()
            ->where('company_id', $company->id)
            ->first();

        return $relationship?->pivot->relation_type;
    }

    /**
     * Check if contact is a lead for a specific company.
     */
    public function isLeadOf(Company $company): bool
    {
        return $this->relationTypeWith($company) === 'lead';
    }

    /**
     * Check if contact is a customer of a specific company.
     */
    public function isCustomerOf(Company $company): bool
    {
        return $this->relationTypeWith($company) === 'customer';
    }

    /**
     * Get first name from full name.
     */
    public function getFirstNameAttribute(): string
    {
        $parts = explode(' ', $this->full_name, 2);
        return $parts[0] ?? '';
    }

    /**
     * Get last name from full name.
     */
    public function getLastNameAttribute(): ?string
    {
        $parts = explode(' ', $this->full_name, 2);
        return $parts[1] ?? null;
    }

    /**
     * Scope to find contacts by email.
     */
    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope to find contacts that have user accounts.
     */
    public function scopeWithUserAccount($query)
    {
        return $query->whereNotNull('auth_user_id');
    }

    /**
     * Scope to find contacts without user accounts.
     */
    public function scopeWithoutUserAccount($query)
    {
        return $query->whereNull('auth_user_id');
    }
}

