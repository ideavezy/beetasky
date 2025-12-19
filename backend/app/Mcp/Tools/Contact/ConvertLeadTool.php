<?php

namespace App\Mcp\Tools\Contact;

use App\Services\CRMService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ConvertLeadTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'convert_lead';

    /**
     * The tool's description.
     */
    protected string $description = 'Convert a lead or prospect to a customer.';

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
                    'description' => 'The UUID of the lead to convert',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search for contact by name or email (if contact_id not provided)',
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
            return Response::error('Please provide a contact_id or search term to identify the lead');
        }

        try {
            $result = $service->convertLead($user, $companyId, $contactId);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to convert lead');
            }

            $data = $result['data'];
            $output = "✅ **Converted to Customer!**\n\n";
            $output .= "- Contact: {$data['full_name']}\n";
            $output .= "- Previous Type: {$data['previous_type']}\n";
            $output .= "- New Type: {$data['new_type']}\n";
            if ($data['converted_at']) {
                $output .= "- Converted On: {$data['converted_at']}\n";
            }

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to convert lead: ' . $e->getMessage());
        }
    }
}

