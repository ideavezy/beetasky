<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSkill;
use App\Services\SkillService;
use App\Skills\Executors\ApiCallExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSkillController extends Controller
{
    protected SkillService $skillService;
    protected ApiCallExecutor $apiExecutor;

    public function __construct(SkillService $skillService, ApiCallExecutor $apiExecutor)
    {
        $this->skillService = $skillService;
        $this->apiExecutor = $apiExecutor;
    }

    /**
     * Create a new custom skill.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'error' => 'Company ID is required',
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'category' => 'required|string|in:project_management,crm,integration,custom',
            'icon' => 'nullable|string|max:50',
            'skill_type' => 'required|string|in:api_call,webhook',
            'api_config' => 'required|array',
            'api_config.method' => 'required_if:skill_type,api_call|string|in:GET,POST,PUT,PATCH,DELETE',
            'api_config.url' => 'required|string|url',
            'api_config.headers' => 'nullable|array',
            'api_config.body_template' => 'nullable|array',
            'api_config.timeout' => 'nullable|integer|min:1|max:120',
            'input_schema' => 'nullable|array',
            'input_schema.type' => 'sometimes|string|in:object',
            'input_schema.properties' => 'nullable|array',
            'input_schema.required' => 'nullable|array',
            'secret_fields' => 'nullable|array',
        ]);

        // Validate API config
        if ($validated['skill_type'] === 'api_call') {
            $configErrors = $this->apiExecutor->validateConfig($validated['api_config']);
            if (!empty($configErrors)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid API configuration',
                    'validation_errors' => $configErrors,
                ], 422);
            }
        }

        try {
            $skill = $this->skillService->createSkill($validated, $companyId);

            return response()->json([
                'success' => true,
                'message' => 'Skill created successfully',
                'data' => $this->formatSkill($skill),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing skill.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        $skill = AiSkill::find($id);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'error' => 'Skill not found',
            ], 404);
        }

        // Check ownership
        if ($skill->company_id !== $companyId) {
            return response()->json([
                'success' => false,
                'error' => 'Access denied',
            ], 403);
        }

        if ($skill->is_system) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot modify system skills',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'category' => 'sometimes|string|in:project_management,crm,integration,custom',
            'icon' => 'nullable|string|max:50',
            'api_config' => 'sometimes|array',
            'api_config.method' => 'sometimes|string|in:GET,POST,PUT,PATCH,DELETE',
            'api_config.url' => 'sometimes|string|url',
            'api_config.headers' => 'nullable|array',
            'api_config.body_template' => 'nullable|array',
            'api_config.timeout' => 'nullable|integer|min:1|max:120',
            'input_schema' => 'nullable|array',
            'secret_fields' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $skill = $this->skillService->updateSkill($id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Skill updated successfully',
                'data' => $this->formatSkill($skill),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a skill.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        $skill = AiSkill::find($id);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'error' => 'Skill not found',
            ], 404);
        }

        // Check ownership
        if ($skill->company_id !== $companyId) {
            return response()->json([
                'success' => false,
                'error' => 'Access denied',
            ], 403);
        }

        if ($skill->is_system) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete system skills',
            ], 403);
        }

        try {
            $this->skillService->deleteSkill($id);

            return response()->json([
                'success' => true,
                'message' => 'Skill deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format skill for API response.
     */
    protected function formatSkill(AiSkill $skill): array
    {
        return [
            'id' => $skill->id,
            'slug' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'category' => $skill->category,
            'skill_type' => $skill->skill_type,
            'icon' => $skill->icon,
            'api_config' => $skill->api_config,
            'input_schema' => $skill->input_schema,
            'secret_fields' => $skill->secret_fields,
            'is_system' => $skill->is_system,
            'is_active' => $skill->is_active,
            'created_at' => $skill->created_at?->toIso8601String(),
            'updated_at' => $skill->updated_at?->toIso8601String(),
        ];
    }
}

