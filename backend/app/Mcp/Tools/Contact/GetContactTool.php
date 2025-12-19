<?php

namespace App\Mcp\Tools\Contact;

use App\Services\CRMService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetContactTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'get_contact';

    /**
     * The tool's description.
     */
    protected string $description = 'Get detailed information about a specific contact.';

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
                    'description' => 'The UUID of the contact',
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
            return Response::error('Please provide a contact_id or search term');
        }

        try {
            $result = $service->getContact($user, $companyId, $contactId);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Contact not found');
            }

            $contact = $result['data'];

            $output = "## {$contact['full_name']}\n\n";
            $output .= "**Type:** {$contact['relation_type']} | **Status:** {$contact['status']}\n\n";
            
            $output .= "### Contact Information\n";
            if ($contact['email']) {
                $output .= "- Email: {$contact['email']}\n";
            }
            if ($contact['phone']) {
                $output .= "- Phone: {$contact['phone']}\n";
            }
            if ($contact['organization']) {
                $output .= "- Organization: {$contact['organization']}\n";
            }
            if ($contact['job_title']) {
                $output .= "- Job Title: {$contact['job_title']}\n";
            }
            if ($contact['source']) {
                $output .= "- Source: {$contact['source']}\n";
            }
            if ($contact['lead_score'] ?? 0 > 0) {
                $output .= "- Lead Score: {$contact['lead_score']}\n";
            }

            $output .= "\n### Activity\n";
            $output .= "- First Seen: {$contact['first_seen_at']}\n";
            $output .= "- Last Activity: " . ($contact['last_activity_at'] ?? 'Never') . "\n";

            if (!empty($contact['recent_notes'])) {
                $output .= "\n### Recent Notes\n";
                foreach ($contact['recent_notes'] as $note) {
                    $output .= "- [{$note['type']}] {$note['content']}... ({$note['created_at']})\n";
                }
            }

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to get contact: ' . $e->getMessage());
        }
    }
}

