<?php

namespace App\Mcp\Tools\Contact;

use App\Services\CRMService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateContactTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'create_contact';

    /**
     * The tool's description.
     */
    protected string $description = 'Create a new contact (lead, prospect, customer, vendor, or partner).';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'full_name' => [
                    'type' => 'string',
                    'description' => 'Full name of the contact (REQUIRED)',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Alias for full_name',
                ],
                'email' => [
                    'type' => 'string',
                    'description' => 'Email address',
                ],
                'phone' => [
                    'type' => 'string',
                    'description' => 'Phone number',
                ],
                'organization' => [
                    'type' => 'string',
                    'description' => 'Company or organization name',
                ],
                'company' => [
                    'type' => 'string',
                    'description' => 'Alias for organization',
                ],
                'job_title' => [
                    'type' => 'string',
                    'description' => 'Job title or role',
                ],
                'relation_type' => [
                    'type' => 'string',
                    'description' => 'Type of contact relationship (default: lead)',
                    'enum' => ['lead', 'prospect', 'customer', 'vendor', 'partner'],
                ],
                'source' => [
                    'type' => 'string',
                    'description' => 'How this contact was acquired',
                    'enum' => ['website', 'referral', 'social_media', 'advertisement', 'cold_call', 'email_campaign', 'trade_show', 'partner', 'ai_chat', 'manual', 'other'],
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

        $fullName = $request->input('full_name') ?? $request->input('name');
        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$fullName) {
            return Response::error('Contact name is required');
        }

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $data = [
            'full_name' => $fullName,
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'organization' => $request->input('organization') ?? $request->input('company'),
            'job_title' => $request->input('job_title'),
            'relation_type' => $request->input('relation_type') ?? 'lead',
            'source' => $request->input('source') ?? 'ai_chat',
        ];

        try {
            $result = $service->createContact($user, $companyId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to create contact');
            }

            return Response::text(json_encode([
                'message' => $result['message'],
                'contact' => $result['data'],
            ], JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::error('Failed to create contact: ' . $e->getMessage());
        }
    }
}

