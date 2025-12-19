<?php

namespace App\Skills\Executors;

use App\Models\AiSkill;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Executes webhook skills (outbound webhook calls).
 */
class WebhookExecutor
{
    /**
     * Execute a webhook skill.
     */
    public function execute(AiSkill $skill, array $params, array $context): array
    {
        $config = $skill->api_config ?? [];
        $customConfig = $context['custom_config'] ?? [];

        $url = $this->interpolate($config['url'] ?? '', $params, $customConfig);
        $headers = $this->buildHeaders($config['headers'] ?? [], $params, $customConfig);
        $timeout = $config['timeout'] ?? 30;

        // Build webhook payload
        $payloadTemplate = $config['payload_template'] ?? $config['body_template'] ?? [];
        $payload = $this->buildPayload($payloadTemplate, $params, $customConfig, $context);

        try {
            Log::info('Executing webhook skill', [
                'skill' => $skill->slug,
                'url' => $url,
            ]);

            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json() ?? ['message' => 'Webhook sent successfully'],
                    'status_code' => $response->status(),
                    'message' => 'Webhook triggered successfully',
                ];
            }

            return [
                'success' => false,
                'error' => "Webhook returned {$response->status()}: " . $response->body(),
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Webhook skill failed', [
                'skill' => $skill->slug,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Webhook failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Interpolate placeholders in a string.
     */
    protected function interpolate(string $template, array $params, array $config): string
    {
        foreach ($params as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace("{{" . $key . "}}", (string) $value, $template);
            }
        }

        foreach ($config as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace("{{" . $key . "}}", (string) $value, $template);
            }
        }

        return $template;
    }

    /**
     * Build headers with interpolation.
     */
    protected function buildHeaders(array $headerTemplates, array $params, array $config): array
    {
        $headers = [];

        foreach ($headerTemplates as $key => $value) {
            $headers[$key] = $this->interpolate($value, $params, $config);
        }

        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * Build webhook payload with context metadata.
     */
    protected function buildPayload(array $payloadTemplate, array $params, array $config, array $context): array
    {
        $payload = $this->interpolateArray($payloadTemplate, $params, $config);

        // Add metadata if not already present
        if (!isset($payload['_metadata'])) {
            $payload['_metadata'] = [
                'timestamp' => now()->toIso8601String(),
                'source' => 'beetasky',
            ];
        }

        return $payload;
    }

    /**
     * Recursively interpolate an array.
     */
    protected function interpolateArray(array $template, array $params, array $config): array
    {
        $result = [];

        foreach ($template as $key => $value) {
            if (is_string($value)) {
                $result[$key] = $this->interpolate($value, $params, $config);
            } elseif (is_array($value)) {
                $result[$key] = $this->interpolateArray($value, $params, $config);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

