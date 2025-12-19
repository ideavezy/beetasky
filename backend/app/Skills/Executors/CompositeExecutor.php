<?php

namespace App\Skills\Executors;

use App\Models\AiSkill;
use Illuminate\Support\Facades\Log;

/**
 * Executes composite skills (chains of other skills).
 */
class CompositeExecutor
{
    protected ApiCallExecutor $apiExecutor;
    protected WebhookExecutor $webhookExecutor;

    public function __construct(
        ApiCallExecutor $apiExecutor,
        WebhookExecutor $webhookExecutor
    ) {
        $this->apiExecutor = $apiExecutor;
        $this->webhookExecutor = $webhookExecutor;
    }

    /**
     * Execute a composite skill (chain of skills).
     */
    public function execute(AiSkill $skill, array $params, array $context): array
    {
        $steps = $skill->composite_steps ?? [];

        if (empty($steps)) {
            return [
                'success' => false,
                'error' => 'No steps defined in composite skill',
            ];
        }

        $results = [];
        $currentParams = $params;
        $allSuccess = true;

        foreach ($steps as $index => $stepConfig) {
            $stepSlug = $stepConfig['skill_slug'] ?? null;

            if (!$stepSlug) {
                return [
                    'success' => false,
                    'error' => "Step {$index} missing skill_slug",
                ];
            }

            try {
                $stepSkill = AiSkill::where('slug', $stepSlug)->first();

                if (!$stepSkill) {
                    return [
                        'success' => false,
                        'error' => "Step skill not found: {$stepSlug}",
                    ];
                }

                // Merge step params with current params
                $stepParams = array_merge($currentParams, $stepConfig['params'] ?? []);

                // Execute the step based on its type
                $result = $this->executeStep($stepSkill, $stepParams, $context);
                $results[] = [
                    'step' => $index,
                    'skill' => $stepSlug,
                    'result' => $result,
                ];

                if (!($result['success'] ?? false)) {
                    $allSuccess = false;

                    // Check if we should stop on error
                    if ($stepConfig['stop_on_error'] ?? true) {
                        return [
                            'success' => false,
                            'error' => "Step {$index} ({$stepSlug}) failed: " . ($result['error'] ?? 'Unknown error'),
                            'results' => $results,
                        ];
                    }
                }

                // Pass output to next step if configured
                if (isset($result['data']) && ($stepConfig['pass_output'] ?? true)) {
                    $currentParams = array_merge($currentParams, [
                        '_previous_output' => $result['data'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Composite step execution failed', [
                    'skill' => $skill->slug,
                    'step' => $index,
                    'step_skill' => $stepSlug,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'error' => "Step {$index} exception: " . $e->getMessage(),
                    'results' => $results,
                ];
            }
        }

        return [
            'success' => $allSuccess,
            'data' => $results,
            'message' => 'Composite skill completed',
        ];
    }

    /**
     * Execute a single step.
     */
    protected function executeStep(AiSkill $skill, array $params, array $context): array
    {
        return match ($skill->skill_type) {
            'api_call' => $this->apiExecutor->execute($skill, $params, $context),
            'webhook' => $this->webhookExecutor->execute($skill, $params, $context),
            default => [
                'success' => false,
                'error' => "Unsupported step skill type: {$skill->skill_type}",
            ],
        };
    }
}

