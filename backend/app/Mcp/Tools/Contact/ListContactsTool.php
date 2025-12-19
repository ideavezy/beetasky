<?php

namespace App\Mcp\Tools\Contact;

use App\Services\CRMService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListContactsTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'list_contacts';

    /**
     * The tool's description.
     */
    protected string $description = 'List and search contacts with optional filters.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'Search by name, email, or organization',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Filter by contact type',
                    'enum' => ['lead', 'prospect', 'customer', 'vendor', 'partner'],
                ],
                'relation_type' => [
                    'type' => 'string',
                    'description' => 'Alias for type filter',
                    'enum' => ['lead', 'prospect', 'customer', 'vendor', 'partner'],
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter by status',
                    'enum' => ['active', 'inactive', 'converted', 'lost'],
                ],
                'assigned_to' => [
                    'type' => 'string',
                    'description' => 'Filter by assignee (use "me" for current user)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results (default: 10)',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, CRMService $service): Response
    {
        $user = $request->user();

        if (!$user) {
            return Response::error('Authentication required');
        }

        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $filters = [
            'search' => $request->input('search'),
            'type' => $request->input('type') ?? $request->input('relation_type'),
            'status' => $request->input('status'),
            'assigned_to' => $request->input('assigned_to'),
            'limit' => $request->input('limit') ?? 10,
        ];

        try {
            $result = $service->listContacts($user, $companyId, $filters);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to list contacts');
            }

            $contacts = $result['data'];
            $total = $result['total'];

            if (empty($contacts)) {
                return Response::text("No contacts found matching your criteria.");
            }

            $output = "Found {$total} contact(s):\n\n";
            foreach ($contacts as $contact) {
                $output .= "• **{$contact['full_name']}** ({$contact['relation_type']})\n";
                if ($contact['email']) {
                    $output .= "  Email: {$contact['email']}\n";
                }
                if ($contact['organization']) {
                    $output .= "  Organization: {$contact['organization']}\n";
                }
                if ($contact['last_activity']) {
                    $output .= "  Last Activity: {$contact['last_activity']}\n";
                }
                $output .= "\n";
            }

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to list contacts: ' . $e->getMessage());
        }
    }
}

