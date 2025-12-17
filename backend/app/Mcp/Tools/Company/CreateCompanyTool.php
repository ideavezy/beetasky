<?php

namespace App\Mcp\Tools\Company;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateCompanyTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'create_company';

    /**
     * The tool's description.
     */
    protected string $description = 'Create a new company. The authenticated user will be set as the owner.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'The name of the company',
                    'minLength' => 2,
                    'maxLength' => 255,
                ],
            ],
            'required' => ['name'],
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ProjectManagementService $service): Response
    {
        $user = $request->user();

        if (!$user) {
            return Response::error('Authentication required');
        }

        $name = $request->input('name');

        if (!$name || strlen($name) < 2) {
            return Response::error('Company name must be at least 2 characters');
        }

        try {
            $result = $service->createCompany($user, ['name' => $name]);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to create company');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'company' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to create company: ' . $e->getMessage());
        }
    }
}

