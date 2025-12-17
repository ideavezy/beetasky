<?php

namespace App\Mcp\Tools\Company;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetCompanyTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'get_company';

    /**
     * The tool's description.
     */
    protected string $description = 'Get details of a specific company.';

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
                    'description' => 'The UUID of the company to retrieve',
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

        $result = $service->getCompany($user, $companyId);

        if (!$result['success']) {
            return Response::error($result['error'] ?? 'Failed to get company');
        }

        return Response::text(json_encode($result['data'], JSON_PRETTY_PRINT));
    }
}

