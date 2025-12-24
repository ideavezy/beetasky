<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContractTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $companyId = $request->header('X-Company-ID');
        if (!$companyId) {
            return response()->json(['message' => 'X-Company-ID header is required'], 422);
        }

        $query = ContractTemplate::query()
            ->where('company_id', $companyId)
            ->with(['creator:id,name,email']);

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search by name or description
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $templates = $query->paginate($request->input('per_page', 20));

        return response()->json($templates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $companyId = $request->header('X-Company-ID');
        if (!$companyId) {
            return response()->json(['message' => 'X-Company-ID header is required'], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sections' => 'required|array',
            'merge_fields' => 'nullable|array',
            'clickwrap_text' => 'nullable|string',
            'default_contract_type' => 'nullable|in:fixed_price,milestone,subscription',
            'default_pricing_data' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template = ContractTemplate::create([
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
            ...$validated,
        ]);

        $template->load(['creator:id,name,email']);

        return response()->json([
            'message' => 'Contract template created successfully',
            'data' => $template,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $companyId = $request->header('X-Company-ID');
        if (!$companyId) {
            return response()->json(['message' => 'X-Company-ID header is required'], 422);
        }

        $template = ContractTemplate::where('company_id', $companyId)
            ->with(['creator:id,name,email', 'contracts' => function ($query) {
                $query->select('id', 'template_id', 'title', 'status', 'created_at')
                      ->latest()
                      ->limit(5);
            }])
            ->findOrFail($id);

        return response()->json(['data' => $template]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $companyId = $request->header('X-Company-ID');
        if (!$companyId) {
            return response()->json(['message' => 'X-Company-ID header is required'], 422);
        }

        $template = ContractTemplate::where('company_id', $companyId)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'sections' => 'sometimes|required|array',
            'merge_fields' => 'nullable|array',
            'clickwrap_text' => 'nullable|string',
            'default_contract_type' => 'nullable|in:fixed_price,milestone,subscription',
            'default_pricing_data' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update($validated);

        $template->load(['creator:id,name,email']);

        return response()->json([
            'message' => 'Contract template updated successfully',
            'data' => $template,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $companyId = $request->header('X-Company-ID');
        if (!$companyId) {
            return response()->json(['message' => 'X-Company-ID header is required'], 422);
        }

        $template = ContractTemplate::where('company_id', $companyId)
            ->findOrFail($id);

        // Check if template is being used
        $contractCount = $template->contracts()->count();
        if ($contractCount > 0) {
            return response()->json([
                'message' => "Cannot delete template. It is being used by {$contractCount} contract(s).",
            ], 422);
        }

        $template->delete();

        return response()->json([
            'message' => 'Contract template deleted successfully',
        ]);
    }

    /**
     * Duplicate an existing template.
     */
    public function duplicate(Request $request, string $id)
    {
        $companyId = $request->header('X-Company-ID');
        if (!$companyId) {
            return response()->json(['message' => 'X-Company-ID header is required'], 422);
        }

        $template = ContractTemplate::where('company_id', $companyId)
            ->findOrFail($id);

        $duplicate = $template->replicate();
        $duplicate->name = $template->name . ' (Copy)';
        $duplicate->created_by = $request->user()->id;
        $duplicate->save();

        $duplicate->load(['creator:id,name,email']);

        return response()->json([
            'message' => 'Contract template duplicated successfully',
            'data' => $duplicate,
        ], 201);
    }

    /**
     * Generate contract sections using AI.
     */
    public function generateWithAi(Request $request, AIService $aiService)
    {
        $companyId = $request->header('X-Company-ID');
        if (!$companyId) {
            return response()->json(['message' => 'X-Company-ID header is required'], 422);
        }

        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'contract_type' => 'nullable|in:fixed_price,milestone,subscription',
            'client_name' => 'nullable|string|max:255',
            'project_name' => 'nullable|string|max:255',
        ]);

        try {
            $result = $aiService->generateContractSections(
                $validated['prompt'],
                [
                    'contract_type' => $validated['contract_type'] ?? 'fixed_price',
                    'client_name' => $validated['client_name'] ?? '{{client.full_name}}',
                    'project_name' => $validated['project_name'] ?? '{{project.name}}',
                ],
                $request->user()->id,
                $companyId
            );

            return response()->json([
                'message' => 'Contract sections generated successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Contract template AI generation failed', [
                'user_id' => $request->user()?->id,
                'company_id' => $companyId,
                'message' => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), 'OpenAI API key not configured')) {
                return response()->json([
                    'message' => 'OpenAI is not configured. Please set OPENAI_API_KEY in backend .env and restart the server.',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return response()->json([
                'message' => 'Failed to generate contract sections',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a single section (heading/paragraph) using AI, with template context.
     * Used by the template builder for per-block AI writing.
     */
    public function generateSectionWithAi(Request $request, AIService $aiService)
    {
        $companyId = $request->header('X-Company-ID');
        if (!$companyId) {
            return response()->json(['message' => 'X-Company-ID header is required'], 422);
        }

        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'section_type' => 'required|in:heading,paragraph',
            'template_context' => 'nullable|array',
            'template_context.template_name' => 'nullable|string|max:255',
            'template_context.contract_type' => 'nullable|in:fixed_price,milestone,subscription',
            'template_context.sections' => 'nullable|array',
            'template_context.sections.*.type' => 'nullable|string|max:50',
            'template_context.sections.*.text' => 'nullable|string|max:5000',
            'template_context.sections.*.order' => 'nullable|integer|min:0',
        ]);

        try {
            $result = $aiService->generateContractSection(
                $validated['prompt'],
                $validated['section_type'],
                $validated['template_context'] ?? [],
                $request->user()->id,
                $companyId
            );

            return response()->json([
                'message' => 'Section generated successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate section',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
