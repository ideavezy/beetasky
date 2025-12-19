<?php

namespace App\Skills;

use App\Models\AiSkill;
use App\Skills\Executors\McpToolExecutor;
use App\Skills\Executors\ApiCallExecutor;
use App\Skills\Executors\CompositeExecutor;
use App\Skills\Executors\WebhookExecutor;

/**
 * Routes skill execution to the appropriate executor based on skill type.
 */
class SkillExecutor
{
    public function __construct(
        protected McpToolExecutor $mcpExecutor,
        protected ApiCallExecutor $apiExecutor,
        protected CompositeExecutor $compositeExecutor,
        protected WebhookExecutor $webhookExecutor
    ) {}

    /**
     * Execute a skill with the given parameters and context.
     *
     * @param AiSkill $skill The skill to execute
     * @param array $params Input parameters for the skill
     * @param array $context Execution context (user_id, company_id, custom_config, etc.)
     * @return array Result with 'success', 'data', 'message', or 'error' keys
     */
    public function execute(AiSkill $skill, array $params, array $context): array
    {
        return match ($skill->skill_type) {
            'mcp_tool' => $this->mcpExecutor->execute($skill, $params, $context),
            'api_call' => $this->apiExecutor->execute($skill, $params, $context),
            'composite' => $this->compositeExecutor->execute($skill, $params, $context),
            'webhook' => $this->webhookExecutor->execute($skill, $params, $context),
            default => [
                'success' => false,
                'error' => "Unknown skill type: {$skill->skill_type}",
            ],
        };
    }

    /**
     * Validate that a skill can be executed.
     */
    public function validate(AiSkill $skill, array $params, array $context): array
    {
        $errors = [];

        // Check if skill is active
        if (!$skill->is_active) {
            $errors[] = 'Skill is not active';
        }

        // Validate required parameters from input schema
        $inputSchema = $skill->input_schema ?? [];
        $required = $inputSchema['required'] ?? [];

        foreach ($required as $field) {
            if (!isset($params[$field]) || $params[$field] === '') {
                $errors[] = "Missing required parameter: {$field}";
            }
        }

        // Check secret fields for API skills
        if ($skill->isApiCall() || $skill->isWebhook()) {
            $secretFields = $skill->getSecretFieldNames();
            $customConfig = $context['custom_config'] ?? [];

            foreach ($secretFields as $field) {
                if (empty($customConfig[$field])) {
                    $errors[] = "Missing required secret: {$field}";
                }
            }
        }

        return $errors;
    }
}

