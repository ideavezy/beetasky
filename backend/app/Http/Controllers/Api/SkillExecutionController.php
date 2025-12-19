<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSkillExecution;
use App\Models\AiSkill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillExecutionController extends Controller
{
    /**
     * List all skill executions for the company (admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        $query = AiSkillExecution::where('company_id', $companyId)
            ->with(['user:id,name,email', 'skill:id,name,slug,skill_type'])
            ->orderBy('created_at', 'desc');

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by skill
        if ($request->has('skill_id')) {
            $query->where('skill_id', $request->input('skill_id'));
        }

        // Filter by skill slug
        if ($request->has('skill_slug')) {
            $skill = AiSkill::where('slug', $request->input('skill_slug'))->first();
            if ($skill) {
                $query->where('skill_id', $skill->id);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $perPage = min($request->input('per_page', 50), 100);
        $executions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $executions->map(fn($e) => $this->formatExecution($e)),
            'pagination' => [
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
                'per_page' => $executions->perPage(),
                'total' => $executions->total(),
            ],
        ]);
    }

    /**
     * Get execution statistics for the company.
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID is required',
            ], 400);
        }

        $days = $request->input('days', 30);
        $since = now()->subDays($days);

        // Total executions
        $totalExecutions = AiSkillExecution::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->count();

        // Successful vs failed
        $successCount = AiSkillExecution::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->where('status', 'success')
            ->count();

        $errorCount = AiSkillExecution::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->where('status', 'error')
            ->count();

        // Average latency
        $avgLatency = AiSkillExecution::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');

        // Top skills by usage
        $topSkills = AiSkillExecution::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->selectRaw('skill_id, COUNT(*) as execution_count')
            ->groupBy('skill_id')
            ->orderByDesc('execution_count')
            ->limit(10)
            ->with('skill:id,name,slug')
            ->get()
            ->map(fn($row) => [
                'skill_name' => $row->skill?->name ?? 'Unknown',
                'skill_slug' => $row->skill?->slug ?? 'unknown',
                'execution_count' => $row->execution_count,
            ]);

        // Top users by usage
        $topUsers = AiSkillExecution::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->selectRaw('user_id, COUNT(*) as execution_count')
            ->groupBy('user_id')
            ->orderByDesc('execution_count')
            ->limit(10)
            ->with('user:id,name,email')
            ->get()
            ->map(fn($row) => [
                'user_name' => $row->user?->name ?? 'Unknown',
                'user_email' => $row->user?->email ?? 'unknown',
                'execution_count' => $row->execution_count,
            ]);

        // Daily breakdown
        $dailyStats = AiSkillExecution::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'total' => (int) $row->total,
                'success_count' => (int) $row->success_count,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'period_days' => $days,
                'total_executions' => $totalExecutions,
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'success_rate' => $totalExecutions > 0 ? round(($successCount / $totalExecutions) * 100, 1) : 0,
                'avg_latency_ms' => $avgLatency ? round($avgLatency, 0) : null,
                'top_skills' => $topSkills,
                'top_users' => $topUsers,
                'daily_stats' => $dailyStats,
            ],
        ]);
    }

    /**
     * Get a specific execution by ID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        $execution = AiSkillExecution::where('company_id', $companyId)
            ->where('id', $id)
            ->with(['user:id,name,email', 'skill:id,name,slug,skill_type,description'])
            ->first();

        if (!$execution) {
            return response()->json([
                'success' => false,
                'message' => 'Execution not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatExecution($execution, true),
        ]);
    }

    /**
     * Get user's own skill execution history.
     */
    public function myHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $query = AiSkillExecution::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->with(['skill:id,name,slug,skill_type'])
            ->orderBy('created_at', 'desc');

        // Filter by skill
        if ($request->has('skill_slug')) {
            $skill = AiSkill::where('slug', $request->input('skill_slug'))->first();
            if ($skill) {
                $query->where('skill_id', $skill->id);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $limit = min($request->input('limit', 20), 100);
        $executions = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $executions->map(fn($e) => $this->formatExecution($e)),
        ]);
    }

    /**
     * Format execution for API response.
     */
    protected function formatExecution(AiSkillExecution $execution, bool $detailed = false): array
    {
        $data = [
            'id' => $execution->id,
            'skill' => $execution->skill ? [
                'id' => $execution->skill->id,
                'name' => $execution->skill->name,
                'slug' => $execution->skill->slug,
                'type' => $execution->skill->skill_type,
            ] : null,
            'user' => $execution->user ? [
                'id' => $execution->user->id,
                'name' => $execution->user->name,
                'email' => $execution->user->email,
            ] : null,
            'status' => $execution->status,
            'latency_ms' => $execution->latency_ms,
            'error_message' => $execution->error_message,
            'created_at' => $execution->created_at?->toIso8601String(),
        ];

        // Include detailed data for single-item view
        if ($detailed) {
            $data['input_params'] = $execution->input_params;
            $data['output_result'] = $execution->output_result;
            $data['conversation_id'] = $execution->conversation_id;
            $data['ai_run_id'] = $execution->ai_run_id;
            $data['company_id'] = $execution->company_id;
        }

        return $data;
    }
}

