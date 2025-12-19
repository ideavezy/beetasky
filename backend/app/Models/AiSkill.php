<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AiSkill extends Model
{
    use HasUuids;

    protected $table = 'ai_skills';

    protected $fillable = [
        'company_id',
        'category',
        'name',
        'slug',
        'description',
        'icon',
        'skill_type',
        'mcp_tool_class',
        'api_config',
        'composite_steps',
        'input_schema',
        'function_definition',
        'secret_fields',
        'is_system',
        'is_active',
        'requires_permission',
    ];

    protected $casts = [
        'api_config' => 'array',
        'composite_steps' => 'array',
        'input_schema' => 'array',
        'function_definition' => 'array',
        'secret_fields' => 'array',
        'is_system' => PostgresBoolean::class,
        'is_active' => PostgresBoolean::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AiSkill $skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    /**
     * Get the company that owns this skill.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get user settings for this skill.
     */
    public function userSettings(): HasMany
    {
        return $this->hasMany(UserSkillSetting::class, 'skill_id');
    }

    /**
     * Get execution logs for this skill.
     */
    public function executions(): HasMany
    {
        return $this->hasMany(AiSkillExecution::class, 'skill_id');
    }

    /**
     * Scope to get skills available to a user (global + company-specific).
     */
    public function scopeForUser(Builder $query, string $userId, ?string $companyId = null): Builder
    {
        return $query->whereRaw('is_active = true')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id'); // Global skills
                if ($companyId) {
                    $q->orWhere('company_id', $companyId); // Company-specific skills
                }
            });
    }

    /**
     * Scope to get only active skills.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereRaw('is_active = true');
    }

    /**
     * Scope to filter by category.
     */
    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by skill type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('skill_type', $type);
    }

    /**
     * Check if this is an MCP tool.
     */
    public function isMcpTool(): bool
    {
        return $this->skill_type === 'mcp_tool';
    }

    /**
     * Check if this is an API call skill.
     */
    public function isApiCall(): bool
    {
        return $this->skill_type === 'api_call';
    }

    /**
     * Check if this is a composite skill.
     */
    public function isComposite(): bool
    {
        return $this->skill_type === 'composite';
    }

    /**
     * Check if this is a webhook skill.
     */
    public function isWebhook(): bool
    {
        return $this->skill_type === 'webhook';
    }

    /**
     * Generate OpenAI function definition from input schema.
     */
    public function toFunctionDefinition(): array
    {
        // If function_definition is already set, use it
        if (!empty($this->function_definition) && isset($this->function_definition['name'])) {
            return $this->function_definition;
        }

        // Otherwise, generate from input_schema
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->slug,
                'description' => $this->description ?? $this->name,
                'parameters' => $this->input_schema ?: [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
        ];
    }

    /**
     * Get the list of secret field names that users need to provide.
     */
    public function getSecretFieldNames(): array
    {
        return $this->secret_fields ?? [];
    }

    /**
     * Check if user has provided all required secrets.
     */
    public function hasRequiredSecrets(array $userConfig): bool
    {
        $secretFields = $this->getSecretFieldNames();

        foreach ($secretFields as $field) {
            if (empty($userConfig[$field])) {
                return false;
            }
        }

        return true;
    }
}

