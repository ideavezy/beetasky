<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SkillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    protected SkillService $skillService;

    public function __construct(SkillService $skillService)
    {
        $this->skillService = $skillService;
    }

    /**
     * List all skills available to the user with their settings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $skills = $this->skillService->getUserSkills($user->id, $companyId);

        // Group by category
        $grouped = $skills->groupBy(fn($s) => $s['skill']->category);

        $categories = $this->skillService->getCategories();

        return response()->json([
            'success' => true,
            'data' => [
                'skills' => $skills->map(fn($s) => $this->formatSkillWithSettings($s)),
                'grouped' => $grouped->map(fn($items) => $items->map(fn($s) => $this->formatSkillWithSettings($s))),
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * Get details of a specific skill.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $skill = $this->skillService->getSkillBySlug($slug);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'error' => 'Skill not found',
            ], 404);
        }

        // Check access
        if ($skill->company_id && $skill->company_id !== $companyId) {
            return response()->json([
                'success' => false,
                'error' => 'Access denied',
            ], 403);
        }

        $userSettings = $this->skillService->getUserSkills($user->id, $companyId)
            ->firstWhere(fn($s) => $s['skill']->id === $skill->id);

        return response()->json([
            'success' => true,
            'data' => $this->formatSkillWithSettings($userSettings ?? ['skill' => $skill]),
        ]);
    }

    /**
     * Update user settings for a skill.
     */
    public function updateSettings(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $skill = $this->skillService->getSkillBySlug($slug);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'error' => 'Skill not found',
            ], 404);
        }

        // Check access
        if ($skill->company_id && $skill->company_id !== $companyId) {
            return response()->json([
                'success' => false,
                'error' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'is_enabled' => 'sometimes|boolean',
            'custom_config' => 'sometimes|array',
        ]);

        try {
            $setting = $this->skillService->updateUserSettings(
                $user->id,
                $skill->id,
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => [
                    'is_enabled' => $setting->is_enabled,
                    'custom_config' => $setting->getMaskedConfig(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute a skill (for testing).
     */
    public function execute(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $validated = $request->validate([
            'params' => 'sometimes|array',
        ]);

        $params = $validated['params'] ?? [];

        try {
            $result = $this->skillService->executeSkill(
                $slug,
                $params,
                $user->id,
                $companyId
            );

            return response()->json([
                'success' => $result['success'] ?? false,
                'data' => $result['data'] ?? null,
                'message' => $result['message'] ?? null,
                'error' => $result['error'] ?? null,
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get execution history for a skill.
     */
    public function history(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 20);

        $executions = $this->skillService->getExecutionHistory($slug, $user->id, $limit);

        return response()->json([
            'success' => true,
            'data' => $executions->map(fn($e) => [
                'id' => $e->id,
                'status' => $e->status,
                'input_params' => $e->input_params,
                'output_result' => $e->output_result,
                'error_message' => $e->error_message,
                'latency_ms' => $e->latency_ms,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Format a skill with its user settings for API response.
     */
    protected function formatSkillWithSettings(array $data): array
    {
        $skill = $data['skill'];

        return [
            'id' => $skill->id,
            'slug' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'category' => $skill->category,
            'skill_type' => $skill->skill_type,
            'icon' => $skill->icon,
            'is_system' => $skill->is_system,
            'is_active' => $skill->is_active,
            'input_schema' => $skill->input_schema,
            'secret_fields' => $skill->secret_fields,
            'api_config' => $skill->isApiCall() || $skill->isWebhook()
                ? $this->sanitizeApiConfig($skill->api_config)
                : null,
            // User-specific settings
            'is_enabled' => $data['is_enabled'] ?? true,
            'custom_config' => $data['custom_config'] ?? [],
            'has_secrets_configured' => $data['has_secrets_configured'] ?? false,
            'usage_count' => $data['usage_count'] ?? 0,
            'last_used_at' => isset($data['last_used_at'])
                ? $data['last_used_at']?->toIso8601String()
                : null,
        ];
    }

    /**
     * Sanitize API config for response (hide sensitive defaults).
     */
    protected function sanitizeApiConfig(?array $config): ?array
    {
        if (!$config) {
            return null;
        }

        // Don't expose header values that might contain secrets
        $sanitized = $config;
        if (isset($sanitized['headers'])) {
            foreach ($sanitized['headers'] as $key => $value) {
                if (str_contains(strtolower($key), 'auth') || str_contains(strtolower($key), 'key')) {
                    $sanitized['headers'][$key] = '{{' . strtolower(str_replace('-', '_', $key)) . '}}';
                }
            }
        }

        return $sanitized;
    }
}

