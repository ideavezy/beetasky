<?php

namespace App\Mcp\Tools\Contact;

use App\Services\CRMService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateContactTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'update_contact';

    /**
     * The tool's description.
     */
    protected string $description = 'Update contact information. Only include fields you want to change.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'contact_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the contact to update (REQUIRED)',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search for contact by name or email (if contact_id not provided)',
                ],
                'full_name' => [
                    'type' => 'string',
                    'description' => 'Updated full name',
                ],
                'email' => [
                    'type' => 'string',
                    'description' => 'Updated email address',
                ],
                'phone' => [
                    'type' => 'string',
                    'description' => 'Updated phone number',
                ],
                'organization' => [
                    'type' => 'string',
                    'description' => 'Updated organization name',
                ],
                'job_title' => [
                    'type' => 'string',
                    'description' => 'Updated job title',
                ],
                'relation_type' => [
                    'type' => 'string',
                    'description' => 'Change contact type',
                    'enum' => ['lead', 'prospect', 'customer', 'vendor', 'partner'],
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Change status',
                    'enum' => ['active', 'inactive', 'converted', 'lost'],
                ],
                'assigned_to' => [
                    'type' => 'string',
                    'description' => 'User ID to assign contact to',
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

        $contactId = $request->input('contact_id') ?? $request->input('id');
        $search = $request->input('search');

        // If no contact_id, try to find by search
        if (!$contactId && $search) {
            $findResult = $service->findContact($user, $companyId, $search);
            if (!$findResult['success']) {
                return Response::error($findResult['error']);
            }
            $contactId = $findResult['data']['id'];
        }

        if (!$contactId) {
            return Response::error('Please provide a contact_id or search term to identify the contact');
        }

        $data = array_filter([
            'full_name' => $request->input('full_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'organization' => $request->input('organization'),
            'job_title' => $request->input('job_title'),
            'relation_type' => $request->input('relation_type'),
            'status' => $request->input('status'),
            'assigned_to' => $request->input('assigned_to'),
        ], fn($v) => $v !== null);

        if (empty($data)) {
            return Response::error('No update fields provided');
        }

        try {
            $result = $service->updateContact($user, $companyId, $contactId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to update contact');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'contact' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to update contact: ' . $e->getMessage());
        }
    }
}

