<?php

namespace App\Mcp\Tools\Company;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateCompanyTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'update_company';

    /**
     * The tool's description.
     */
    protected string $description = 'Update a company. Requires owner or manager role.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'company_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the company to update',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'The new name of the company',
                    'minLength' => 2,
                    'maxLength' => 255,
                ],
                'logo_url' => [
                    'type' => 'string',
                    'description' => 'URL to the company logo',
                ],
            ],
            'required' => ['company_id'],
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

        $companyId = $request->input('company_id');

        if (!$companyId) {
            return Response::error('company_id is required');
        }

        $data = array_filter([
            'name' => $request->input('name'),
            'logo_url' => $request->input('logo_url'),
        ], fn($v) => $v !== null);

        if (empty($data)) {
            return Response::error('At least one field to update is required');
        }

        try {
            $result = $service->updateCompany($user, $companyId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to update company');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'company' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to update company: ' . $e->getMessage());
        }
    }
}

