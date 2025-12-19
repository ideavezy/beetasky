<?php

namespace App\Skills\Executors;

use App\Models\AiSkill;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Executes API call skills (HTTP POST/GET/PUT/DELETE requests).
 */
class ApiCallExecutor
{
    /**
     * Execute an API call skill.
     */
    public function execute(AiSkill $skill, array $params, array $context): array
    {
        $config = $skill->api_config ?? [];
        $customConfig = $context['custom_config'] ?? [];

        // Merge user's custom config (API keys, etc.)
        $mergedConfig = array_merge($config, $customConfig);

        $method = strtoupper($mergedConfig['method'] ?? 'GET');
        $url = $this->interpolate($mergedConfig['url'] ?? '', $params, $customConfig);
        $headers = $this->buildHeaders($mergedConfig['headers'] ?? [], $params, $customConfig);
        $timeout = $mergedConfig['timeout'] ?? 30;

        // Build body for POST/PUT/PATCH
        $body = null;
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $bodyTemplate = $mergedConfig['body_template'] ?? [];
            $body = $this->buildBody($bodyTemplate, $params, $customConfig);
        }

        try {
            Log::info('Executing API skill', [
                'skill' => $skill->slug,
                'method' => $method,
                'url' => $url,
            ]);

            $response = Http::withHeaders($headers)
                ->timeout($timeout);

            // Execute request based on method
            $httpResponse = match ($method) {
                'GET' => $response->get($url, $params),
                'POST' => $response->post($url, $body),
                'PUT' => $response->put($url, $body),
                'PATCH' => $response->patch($url, $body),
                'DELETE' => $response->delete($url, $body),
                default => throw new \Exception("Unsupported HTTP method: {$method}"),
            };

            if ($httpResponse->successful()) {
                return [
                    'success' => true,
                    'data' => $httpResponse->json() ?? $httpResponse->body(),
                    'status_code' => $httpResponse->status(),
                    'message' => 'API call successful',
                ];
            }

            // Handle error responses
            return [
                'success' => false,
                'error' => "API returned {$httpResponse->status()}: " . $httpResponse->body(),
                'status_code' => $httpResponse->status(),
                'data' => $httpResponse->json(),
            ];
        } catch (\Exception $e) {
            Log::error('API call skill failed', [
                'skill' => $skill->slug,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'API call failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Interpolate placeholders in a string.
     * Replaces {{key}} with values from params or config.
     */
    protected function interpolate(string $template, array $params, array $config): string
    {
        // First, replace from params
        foreach ($params as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace("{{" . $key . "}}", (string) $value, $template);
            }
        }

        // Then, replace from config (for secrets)
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

        // Ensure content-type is set for JSON APIs
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * Build request body with interpolation.
     */
    protected function buildBody(array $bodyTemplate, array $params, array $config): array
    {
        return $this->interpolateArray($bodyTemplate, $params, $config);
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

    /**
     * Validate API config.
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['url'])) {
            $errors[] = 'URL is required';
        }

        if (empty($config['method'])) {
            $errors[] = 'HTTP method is required';
        }

        $validMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        if (isset($config['method']) && !in_array(strtoupper($config['method']), $validMethods)) {
            $errors[] = 'Invalid HTTP method. Must be one of: ' . implode(', ', $validMethods);
        }

        return $errors;
    }
}

