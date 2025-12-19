<?php

namespace App\Services;

use App\Models\AiSkill;
use App\Models\AiSkillExecution;
use App\Models\UserSkillSetting;
use App\Skills\SkillExecutor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SkillService
{
    protected SkillExecutor $executor;

    public function __construct(SkillExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Get all skills available to a user with their settings.
     */
    public function getUserSkills(string $userId, ?string $companyId = null): Collection
    {
        // Get global skills + company skills
        $skills = AiSkill::forUser($userId, $companyId)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Get user settings for these skills
        $userSettings = UserSkillSetting::where('user_id', $userId)
            ->whereIn('skill_id', $skills->pluck('id'))
            ->get()
            ->keyBy('skill_id');

        return $skills->map(function ($skill) use ($userSettings) {
            $setting = $userSettings->get($skill->id);

            return [
                'skill' => $skill,
                'is_enabled' => $setting?->is_enabled ?? true, // Default enabled
                'custom_config' => $setting?->getMaskedConfig() ?? [],
                'has_secrets_configured' => $setting ? $skill->hasRequiredSecrets($setting->custom_config ?? []) : false,
                'usage_count' => $setting?->usage_count ?? 0,
                'last_used_at' => $setting?->last_used_at,
            ];
        });
    }

    /**
     * Get only enabled skills for a user.
     */
    public function getEnabledSkills(string $userId, ?string $companyId = null): Collection
    {
        return $this->getUserSkills($userId, $companyId)
            ->filter(fn($s) => $s['is_enabled'])
            ->values();
    }

    /**
     * Get function definitions for OpenAI function calling.
     */
    public function getFunctionDefinitions(string $userId, ?string $companyId = null): array
    {
        $skills = $this->getEnabledSkills($userId, $companyId);

        return $skills->map(function ($s) {
            return $s['skill']->toFunctionDefinition();
        })->filter()->values()->toArray();
    }

    /**
     * Execute a skill by slug.
     */
    public function executeSkill(
        string $skillSlug,
        array $params,
        string $userId,
        ?string $companyId = null,
        ?string $conversationId = null,
        ?string $aiRunId = null
    ): array {
        $startTime = microtime(true);

        try {
            $skill = AiSkill::where('slug', $skillSlug)->firstOrFail();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Skill '{$skillSlug}' not found",
            ];
        }

        // Check user has access and skill is enabled
        $userSetting = UserSkillSetting::getOrCreateForUser($userId, $skill->id);

        if (!$userSetting->is_enabled) {
            return [
                'success' => false,
                'error' => 'Skill is disabled for this user',
            ];
        }

        // Build execution context
        $context = [
            'user_id' => $userId,
            'company_id' => $companyId,
            'conversation_id' => $conversationId,
            'ai_run_id' => $aiRunId,
            'custom_config' => $userSetting->getDecryptedConfig(),
        ];

        // Log start of execution
        Log::info('Skill execution started', [
            'skill_slug' => $skillSlug,
            'skill_id' => $skill->id,
            'skill_type' => $skill->skill_type,
            'user_id' => $userId,
            'company_id' => $companyId,
            'params' => array_keys($params),
        ]);

        // Execute via appropriate executor
        try {
            $result = $this->executor->execute($skill, $params, $context);
            $status = ($result['success'] ?? false) ? 'success' : 'error';
        } catch (\Exception $e) {
            Log::error('Skill execution failed', [
                'skill' => $skillSlug,
                'skill_id' => $skill->id,
                'user_id' => $userId,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $result = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
            $status = 'error';
        }

        // Calculate latency
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // Log execution to database
        $execution = AiSkillExecution::log(
            $skill->id,
            $params,
            $context,
            $status,
            $result,
            $result['error'] ?? null,
            $latencyMs
        );

        // Log completion
        Log::info('Skill execution completed', [
            'skill_slug' => $skillSlug,
            'execution_id' => $execution->id,
            'user_id' => $userId,
            'company_id' => $companyId,
            'status' => $status,
            'latency_ms' => $latencyMs,
        ]);

        // Update usage stats
        $userSetting->recordUsage();

        return $result;
    }

    /**
     * Create a new custom skill.
     */
    public function createSkill(array $data, ?string $companyId = null): AiSkill
    {
        $data['company_id'] = $companyId;

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Ensure unique slug
        $baseSlug = $data['slug'];
        $counter = 1;
        while (AiSkill::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $baseSlug . '-' . $counter++;
        }

        // Generate function definition from input schema
        if (!empty($data['input_schema']) && empty($data['function_definition'])) {
            $data['function_definition'] = [
                'type' => 'function',
                'function' => [
                    'name' => $data['slug'],
                    'description' => $data['description'] ?? $data['name'],
                    'parameters' => $data['input_schema'],
                ],
            ];
        }

        return AiSkill::create($data);
    }

    /**
     * Update an existing skill.
     */
    public function updateSkill(string $skillId, array $data): AiSkill
    {
        $skill = AiSkill::findOrFail($skillId);

        if ($skill->is_system) {
            throw new \Exception('Cannot modify system skills');
        }

        // Regenerate function definition if input schema changed
        if (isset($data['input_schema'])) {
            $data['function_definition'] = [
                'type' => 'function',
                'function' => [
                    'name' => $skill->slug,
                    'description' => $data['description'] ?? $skill->description ?? $skill->name,
                    'parameters' => $data['input_schema'],
                ],
            ];
        }

        $skill->update($data);

        return $skill->fresh();
    }

    /**
     * Delete a skill.
     */
    public function deleteSkill(string $skillId): bool
    {
        $skill = AiSkill::findOrFail($skillId);

        if ($skill->is_system) {
            throw new \Exception('Cannot delete system skills');
        }

        return $skill->delete();
    }

    /**
     * Update user settings for a skill.
     */
    public function updateUserSettings(
        string $userId,
        string $skillId,
        array $settings
    ): UserSkillSetting {
        $userSetting = UserSkillSetting::getOrCreateForUser($userId, $skillId);

        if (isset($settings['is_enabled'])) {
            $userSetting->is_enabled = $settings['is_enabled'];
        }

        if (isset($settings['custom_config'])) {
            // Merge with existing config
            $existingConfig = $userSetting->getDecryptedConfig();
            $newConfig = array_merge($existingConfig, $settings['custom_config']);
            $userSetting->setEncryptedConfig($newConfig);
        }

        $userSetting->save();

        return $userSetting;
    }

    /**
     * Get execution history for a skill.
     */
    public function getExecutionHistory(
        string $skillSlug,
        string $userId,
        int $limit = 20
    ): Collection {
        $skill = AiSkill::where('slug', $skillSlug)->first();

        if (!$skill) {
            return collect([]);
        }

        return AiSkillExecution::forUser($userId)
            ->forSkill($skill->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get skill by slug.
     */
    public function getSkillBySlug(string $slug): ?AiSkill
    {
        return AiSkill::where('slug', $slug)->first();
    }

    /**
     * Get available categories.
     */
    public function getCategories(): array
    {
        return [
            'project_management' => 'Project Management',
            'crm' => 'CRM',
            'integration' => 'Integrations',
            'custom' => 'Custom',
        ];
    }
}

