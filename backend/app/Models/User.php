<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, TwoFactorAuthenticatable, WebAuthnAuthentication;

    /**
     * Indicates if the IDs are auto-incrementing.
     * Set to false because we use Supabase UUIDs.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id', // Allow mass assignment of ID for Supabase UUID sync
        'first_name',
        'last_name',
        'email',
        'password',
        'avatar_url',
        'phone',
        'global_role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be appended.
     *
     * @var list<string>
     */
    protected $appends = ['full_name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the companies that the user belongs to (as staff/owner).
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot(['role_in_company', 'permissions', 'is_active', 'joined_at']);
    }

    /**
     * Get active company memberships only.
     */
    public function activeCompanies(): BelongsToMany
    {
        return $this->companies()->whereRaw('company_user.is_active = true');
    }

    /**
     * Get the companies where user is an owner.
     */
    public function ownedCompanies(): BelongsToMany
    {
        return $this->companies()->wherePivot('role_in_company', 'owner');
    }

    /**
     * Get the social accounts linked to this user.
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Get the contact profile linked to this user (if they are also a contact).
     * This allows a user to also be a customer/lead of other companies.
     */
    public function contactProfile(): HasOne
    {
        return $this->hasOne(Contact::class, 'auth_user_id');
    }

    /**
     * Get the user's presets (settings, default company, etc.).
     */
    public function preset(): HasOne
    {
        return $this->hasOne(UserPreset::class);
    }

    /**
     * Get or create presets for this user.
     */
    public function getOrCreatePreset(): UserPreset
    {
        return UserPreset::getOrCreateForUser($this->id);
    }

    /**
     * Check if user is an admin (platform level / super admin).
     */
    public function isAdmin(): bool
    {
        return $this->global_role === 'admin';
    }

    /**
     * Check if user is an owner of a specific company.
     */
    public function isOwnerOf(Company $company): bool
    {
        return $this->companies()
            ->wherePivot('company_id', $company->id)
            ->wherePivot('role_in_company', 'owner')
            ->exists();
    }

    /**
     * Check if user is a manager of a specific company.
     */
    public function isManagerOf(Company $company): bool
    {
        return $this->companies()
            ->wherePivot('company_id', $company->id)
            ->wherePivotIn('role_in_company', ['owner', 'manager'])
            ->exists();
    }

    /**
     * Check if user is staff of a specific company (any role).
     */
    public function isStaffOf(Company $company): bool
    {
        return $this->companies()
            ->wherePivot('company_id', $company->id)
            ->whereRaw('company_user.is_active = true')
            ->exists();
    }

    /**
     * Get user's role in a specific company.
     */
    public function roleInCompany(Company $company): ?string
    {
        $membership = $this->companies()
            ->where('company_id', $company->id)
            ->first();

        return $membership?->pivot->role_in_company;
    }

    /**
     * Check if user has a linked social account for the given provider.
     */
    public function hasSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }
}
