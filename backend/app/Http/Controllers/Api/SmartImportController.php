<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SmartImportJob;
use App\Models\Task;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SmartImportController extends Controller
{
    /**
     * Get import jobs for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $jobs = SmartImportJob::forCompany($companyId)
            ->where('user_id', $user->id)
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json(['success' => true, 'data' => $jobs]);
    }

    /**
     * Get a specific import job.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $job = SmartImportJob::forCompany($companyId)
            ->where('user_id', $user->id)
            ->with('project')
            ->find($id);

        if (!$job) {
            return response()->json(['success' => false, 'message' => 'Import job not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $job]);
    }

    /**
     * Start a new smart import job.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        try {
            $validated = $request->validate([
                'project_id' => 'nullable|uuid|exists:projects,id',
                'content' => 'required|string|min:10',
                'source_files' => 'nullable|array',
            ]);

            // If project_id provided, verify access
            if (!empty($validated['project_id'])) {
                $project = Project::forCompany($companyId)
                    ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
                    ->find($validated['project_id']);

                if (!$project) {
                    return response()->json(['success' => false, 'message' => 'Project not found'], 404);
                }
            }

            $job = SmartImportJob::create([
                'user_id' => $user->id,
                'project_id' => $validated['project_id'] ?? null,
                'company_id' => $companyId,
                'status' => 'pending',
                'progress' => 0,
                'message' => 'Import job created',
                'source_files' => $validated['source_files'] ?? [],
            ]);

            // In a real implementation, this would dispatch to a queue
            // For now, we'll process synchronously with simulated AI parsing
            $this->processImport($job, $validated['content']);

            return response()->json(['success' => true, 'message' => 'Import started', 'data' => $job->fresh()], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }
    }

    /**
     * Process the import (simulated AI parsing).
     */
    protected function processImport(SmartImportJob $job, string $content): void
    {
        $job->markAsProcessing('Analyzing content...');

        try {
            // Simple parsing logic - in production this would call OpenAI API
            $lines = explode("\n", trim($content));
            $tasks = [];
            $currentTopic = null;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Check if it's a topic/section header (starts with # or is uppercase)
                if (str_starts_with($line, '#') || (strlen($line) > 0 && ctype_upper($line[0]) && !str_contains($line, ' '))) {
                    $currentTopic = ltrim($line, '# ');
                    continue;
                }

                // Check if it's a task (starts with -, *, [ ], or numbered)
                if (preg_match('/^[-*\[\]0-9.]+\s*/', $line)) {
                    $taskTitle = preg_replace('/^[-*\[\]0-9.]+\s*/', '', $line);
                    $tasks[] = [
                        'title' => $taskTitle,
                        'topic' => $currentTopic,
                        'priority' => $this->detectPriority($taskTitle),
                    ];
                }
            }

            $job->updateProgress(50, 'Creating tasks...');

            $results = ['topics_created' => 0, 'tasks_created' => 0, 'items' => []];

            if (!empty($tasks) && $job->project_id) {
                DB::transaction(function () use ($job, $tasks, &$results) {
                    $topicMap = [];

                    foreach ($tasks as $taskData) {
                        $topicId = null;
                        
                        if ($taskData['topic']) {
                            if (!isset($topicMap[$taskData['topic']])) {
                                $topic = Topic::create([
                                    'project_id' => $job->project_id,
                                    'company_id' => $job->company_id,
                                    'name' => $taskData['topic'],
                                    'position' => count($topicMap),
                                ]);
                                $topicMap[$taskData['topic']] = $topic->id;
                                $results['topics_created']++;
                            }
                            $topicId = $topicMap[$taskData['topic']];
                        }

                        $task = Task::create([
                            'project_id' => $job->project_id,
                            'topic_id' => $topicId,
                            'company_id' => $job->company_id,
                            'title' => $taskData['title'],
                            'status' => 'new',
                            'priority' => $taskData['priority'],
                            'ai_generated' => true,
                            'order' => $results['tasks_created'],
                        ]);

                        $results['tasks_created']++;
                        $results['items'][] = ['type' => 'task', 'id' => $task->id, 'title' => $task->title];
                    }
                });
            }

            $job->markAsCompleted($results, "Successfully imported {$results['tasks_created']} tasks");

        } catch (\Exception $e) {
            $job->markAsFailed('Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Detect priority from task title.
     */
    protected function detectPriority(string $title): string
    {
        $title = strtolower($title);
        if (str_contains($title, 'urgent') || str_contains($title, 'asap')) return 'urgent';
        if (str_contains($title, 'important') || str_contains($title, 'high')) return 'high';
        if (str_contains($title, 'low')) return 'low';
        return 'medium';
    }
}

