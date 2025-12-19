<?php

namespace App\Mcp\Tools\Contact;

use App\Services\ReminderService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListRemindersTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'list_reminders';

    /**
     * The tool's description.
     */
    protected string $description = 'List upcoming follow-up reminders for your contacts.';

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
                    'description' => 'Filter by specific contact',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Filter by reminder type',
                    'enum' => ['follow_up', 'call', 'email', 'meeting', 'task'],
                ],
                'pending_only' => [
                    'type' => 'boolean',
                    'description' => 'Show only pending reminders (default: true)',
                    'default' => true,
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results (default: 20)',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ReminderService $service): Response
    {
        $user = $request->user();

        if (!$user) {
            return Response::error('Authentication required');
        }

        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$companyId) {
            return Response::error('company_id is required');
        }

        $filters = [
            'contact_id' => $request->input('contact_id'),
            'type' => $request->input('type'),
            'pending_only' => $request->input('pending_only') ?? true,
            'limit' => $request->input('limit') ?? 20,
        ];

        try {
            $result = $service->listReminders($user, $companyId, $filters);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to list reminders');
            }

            $reminders = $result['data'];
            $total = $result['total'];

            if (empty($reminders)) {
                return Response::text("No pending reminders found.");
            }

            $output = "## Upcoming Reminders ({$total})\n\n";

            // Group by status
            $overdue = array_filter($reminders, fn($r) => $r['is_overdue']);
            $dueSoon = array_filter($reminders, fn($r) => $r['is_due_soon'] && !$r['is_overdue']);
            $upcoming = array_filter($reminders, fn($r) => !$r['is_overdue'] && !$r['is_due_soon']);

            if (!empty($overdue)) {
                $output .= "### ⚠️ Overdue\n";
                foreach ($overdue as $r) {
                    $output .= "- **{$r['title']}** - {$r['contact_name']}\n";
                    $output .= "  _Was due: {$r['remind_at_formatted']}_\n";
                }
                $output .= "\n";
            }

            if (!empty($dueSoon)) {
                $output .= "### 🔔 Due Soon\n";
                foreach ($dueSoon as $r) {
                    $output .= "- **{$r['title']}** - {$r['contact_name']}\n";
                    $output .= "  _Due: {$r['remind_at_formatted']}_\n";
                }
                $output .= "\n";
            }

            if (!empty($upcoming)) {
                $output .= "### 📅 Upcoming\n";
                foreach ($upcoming as $r) {
                    $output .= "- **{$r['title']}** - {$r['contact_name']}\n";
                    $output .= "  _Due: {$r['remind_at_formatted']}_\n";
                }
            }

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to list reminders: ' . $e->getMessage());
        }
    }
}

