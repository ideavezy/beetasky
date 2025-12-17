<?php

namespace App\Mcp\Tools\Company;

use App\Services\ProjectManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListCompaniesTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'list_companies';

    /**
     * The tool's description.
     */
    protected string $description = 'List all companies the authenticated user has access to.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ProjectManagementService $service): Response
    {
        $user = $request->user();

        if (!$user) {
            return Response::error('Authentication required');
        }

        $result = $service->listCompanies($user);

        if (!$result['success']) {
            return Response::error($result['error'] ?? 'Failed to list companies');
        }

        return Response::text(json_encode($result['data'], JSON_PRETTY_PRINT));
    }
}

