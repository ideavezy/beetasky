<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserSkillSetting extends Model
{
    use HasUuids;

    protected $table = 'user_skill_settings';

    protected $fillable = [
        'user_id',
        'skill_id',
        'is_enabled',
        'custom_config',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'is_enabled' => PostgresBoolean::class,
        'custom_config' => 'array',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns this setting.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the skill this setting is for.
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(AiSkill::class, 'skill_id');
    }

    /**
     * Get or create settings for a user and skill.
     */
    public static function getOrCreateForUser(string $userId, string $skillId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'skill_id' => $skillId],
            ['is_enabled' => true, 'custom_config' => []]
        );
    }

    /**
     * Get decrypted custom config.
     * Secrets are stored encrypted in the database.
     */
    public function getDecryptedConfig(): array
    {
        $config = $this->custom_config ?? [];
        $skill = $this->skill;

        if (!$skill) {
            return $config;
        }

        $secretFields = $skill->getSecretFieldNames();
        $decrypted = [];

        foreach ($config as $key => $value) {
            if (in_array($key, $secretFields) && !empty($value)) {
                try {
                    $decrypted[$key] = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    // If decryption fails, use raw value (might be already decrypted or invalid)
                    $decrypted[$key] = $value;
                }
            } else {
                $decrypted[$key] = $value;
            }
        }

        return $decrypted;
    }

    /**
     * Set config with encryption for secret fields.
     */
    public function setEncryptedConfig(array $config): void
    {
        $skill = $this->skill;

        if (!$skill) {
            $this->custom_config = $config;
            return;
        }

        $secretFields = $skill->getSecretFieldNames();
        $encrypted = [];

        foreach ($config as $key => $value) {
            if (in_array($key, $secretFields) && !empty($value)) {
                $encrypted[$key] = Crypt::encryptString($value);
            } else {
                $encrypted[$key] = $value;
            }
        }

        $this->custom_config = $encrypted;
    }

    /**
     * Increment usage count and update last used timestamp.
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get masked config for display (hide sensitive values).
     */
    public function getMaskedConfig(): array
    {
        $config = $this->custom_config ?? [];
        $skill = $this->skill;

        if (!$skill) {
            return $config;
        }

        $secretFields = $skill->getSecretFieldNames();
        $masked = [];

        foreach ($config as $key => $value) {
            if (in_array($key, $secretFields) && !empty($value)) {
                $masked[$key] = '••••••••';
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}

