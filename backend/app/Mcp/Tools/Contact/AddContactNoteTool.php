<?php

namespace App\Mcp\Tools\Contact;

use App\Services\CRMService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class AddContactNoteTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'add_contact_note';

    /**
     * The tool's description.
     */
    protected string $description = 'Add a note or activity log to a contact.';

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
                'content' => [
                    'type' => 'string',
                    'description' => 'The note content (REQUIRED)',
                ],
                'note' => [
                    'type' => 'string',
                    'description' => 'Alias for content',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Type of activity (default: note)',
                    'enum' => ['note', 'call', 'email', 'meeting', 'task'],
                ],
                'is_pinned' => [
                    'type' => 'boolean',
                    'description' => 'Pin this note to the top',
                ],
            ],
            'required' => ['content'],
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

        $content = $request->input('content') ?? $request->input('note');

        if (!$content) {
            return Response::error('Note content is required');
        }

        $data = [
            'content' => $content,
            'type' => $request->input('type') ?? 'note',
            'is_pinned' => $request->input('is_pinned') ?? false,
        ];

        try {
            $result = $service->addNote($user, $companyId, $contactId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to add note');
            }

            $noteData = $result['data'];
            $output = "✅ Added {$noteData['type']} to {$noteData['contact_name']}\n\n";
            $output .= "> {$noteData['content']}\n\n";
            $output .= "_Created: {$noteData['created_at']}_";

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to add note: ' . $e->getMessage());
        }
    }
}

