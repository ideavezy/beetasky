<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SocialAccount Model
 * 
 * Stores OAuth provider information for social login (Google, etc.)
 */
class SocialAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'avatar_url',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the user that owns this social account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is expired.
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * Find a social account by provider and provider user ID.
     */
    public static function findByProvider(string $provider, string $providerUserId): ?self
    {
        return static::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }
}

