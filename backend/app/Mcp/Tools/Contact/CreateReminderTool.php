<?php

namespace App\Mcp\Tools\Contact;

use App\Services\CRMService;
use App\Services\ReminderService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateReminderTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'create_reminder';

    /**
     * The tool's description.
     */
    protected string $description = 'Create a follow-up reminder for a contact. Supports natural language dates like "tomorrow", "next Tuesday", "in 3 days".';

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
                'title' => [
                    'type' => 'string',
                    'description' => 'Title of the reminder (REQUIRED)',
                ],
                'remind_at' => [
                    'type' => 'string',
                    'description' => 'When to remind (REQUIRED). Supports: dates (YYYY-MM-DD), natural language (tomorrow, next week, in 3 days)',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Alias for remind_at',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Type of reminder',
                    'enum' => ['follow_up', 'call', 'email', 'meeting', 'task'],
                    'default' => 'follow_up',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Additional details about the reminder',
                ],
            ],
            'required' => ['title', 'remind_at'],
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ReminderService $reminderService, CRMService $crmService): Response
    {
        $user = $request->user();

        if (!$user) {
            return Response::error('Authentication required');
        }

        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$companyId) {
            return Response::error('company_id is required');
        }

        $contactId = $request->input('contact_id') ?? $request->input('id');
        $search = $request->input('search');

        // If no contact_id, try to find by search
        if (!$contactId && $search) {
            $findResult = $crmService->findContact($user, $companyId, $search);
            if (!$findResult['success']) {
                return Response::error($findResult['error']);
            }
            $contactId = $findResult['data']['id'];
        }

        if (!$contactId) {
            return Response::error('Please provide a contact_id or search term');
        }

        $title = $request->input('title');
        $remindAt = $request->input('remind_at') ?? $request->input('date');

        if (!$title) {
            return Response::error('Reminder title is required');
        }

        if (!$remindAt) {
            return Response::error('Reminder date is required');
        }

        $data = [
            'contact_id' => $contactId,
            'title' => $title,
            'remind_at' => $remindAt,
            'type' => $request->input('type') ?? 'follow_up',
            'description' => $request->input('description'),
        ];

        try {
            $result = $reminderService->createReminder($user, $companyId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to create reminder');
            }

            $reminder = $result['data'];
            $output = "✅ **Reminder Set**\n\n";
            $output .= "**{$reminder['title']}**\n";
            $output .= "For: {$reminder['contact_name']}\n";
            $output .= "When: {$reminder['remind_at_formatted']}\n";

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to create reminder: ' . $e->getMessage());
        }
    }
}

