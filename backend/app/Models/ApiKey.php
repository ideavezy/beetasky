<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'api_keys';

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'key_hash',
        'key_prefix',
        'last_used_at',
        'expires_at',
        'scopes',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'scopes' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'key_hash',
    ];

    /**
     * Get the company that owns the API key.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user that owns the API key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new API key.
     * Returns the plain-text key (only available at creation time).
     */
    public static function generate(
        string $userId,
        string $name,
        ?string $companyId = null,
        array $scopes = ['mcp:*'],
        ?\DateTimeInterface $expiresAt = null
    ): array {
        // Generate a secure random key with prefix for identification
        $prefix = 'bsk_' . Str::random(8);
        $secret = Str::random(32);
        $plainKey = $prefix . '_' . $secret;

        $apiKey = static::create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'name' => $name,
            'key_hash' => Hash::make($plainKey),
            'key_prefix' => $prefix,
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        return [
            'api_key' => $apiKey,
            'plain_key' => $plainKey, // Only returned once at creation
        ];
    }

    /**
     * Validate an API key and return the model if valid.
     */
    public static function validate(string $plainKey): ?static
    {
        // Extract prefix from key (format: bsk_xxxxxxxx_...)
        $parts = explode('_', $plainKey);
        if (count($parts) < 3 || $parts[0] !== 'bsk') {
            return null;
        }

        $prefix = $parts[0] . '_' . $parts[1];

        // Find keys with matching prefix
        $keys = static::where('key_prefix', $prefix)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        foreach ($keys as $key) {
            if (Hash::check($plainKey, $key->key_hash)) {
                // Update last used timestamp
                $key->update(['last_used_at' => now()]);
                return $key;
            }
        }

        return null;
    }

    /**
     * Check if the key has a specific scope.
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        // Check for wildcard
        if (in_array('mcp:*', $scopes) || in_array('*', $scopes)) {
            return true;
        }

        // Check for exact match
        if (in_array($scope, $scopes)) {
            return true;
        }

        // Check for category wildcard (e.g., 'mcp:projects:*' matches 'mcp:projects:create')
        $scopeParts = explode(':', $scope);
        for ($i = count($scopeParts) - 1; $i > 0; $i--) {
            $wildcardScope = implode(':', array_slice($scopeParts, 0, $i)) . ':*';
            if (in_array($wildcardScope, $scopes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the key is valid (active and not expired).
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Revoke the API key.
     */
    public function revoke(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Scope for active keys.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope for a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for a specific company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

