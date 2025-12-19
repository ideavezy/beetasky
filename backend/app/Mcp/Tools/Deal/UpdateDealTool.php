<?php

namespace App\Mcp\Tools\Deal;

use App\Services\DealService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateDealTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'update_deal';

    /**
     * The tool's description.
     */
    protected string $description = 'Update a deal, move it to a new stage, or mark it as won/lost.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deal_id' => [
                    'type' => 'string',
                    'description' => 'The UUID of the deal to update (REQUIRED)',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'New deal title',
                ],
                'value' => [
                    'type' => 'number',
                    'description' => 'New deal value',
                ],
                'stage' => [
                    'type' => 'string',
                    'description' => 'Move to a new pipeline stage',
                    'enum' => ['qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'],
                ],
                'mark_won' => [
                    'type' => 'boolean',
                    'description' => 'Mark the deal as won',
                ],
                'mark_lost' => [
                    'type' => 'boolean',
                    'description' => 'Mark the deal as lost',
                ],
                'lost_reason' => [
                    'type' => 'string',
                    'description' => 'Reason for losing the deal (used with mark_lost)',
                ],
                'expected_close_date' => [
                    'type' => 'string',
                    'description' => 'New expected close date (YYYY-MM-DD)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Updated description',
                ],
                'assigned_to' => [
                    'type' => 'string',
                    'description' => 'User ID to assign the deal to',
                ],
            ],
            'required' => ['deal_id'],
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, DealService $service): Response
    {
        $user = $request->user();

        if (!$user) {
            return Response::error('Authentication required');
        }

        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$companyId) {
            return Response::error('company_id is required');
        }

        $dealId = $request->input('deal_id') ?? $request->input('id');

        if (!$dealId) {
            return Response::error('deal_id is required');
        }

        $data = array_filter([
            'title' => $request->input('title'),
            'value' => $request->input('value'),
            'stage' => $request->input('stage'),
            'mark_won' => $request->input('mark_won'),
            'mark_lost' => $request->input('mark_lost'),
            'lost_reason' => $request->input('lost_reason'),
            'expected_close_date' => $request->input('expected_close_date'),
            'description' => $request->input('description'),
            'assigned_to' => $request->input('assigned_to'),
        ], fn($v) => $v !== null);

        if (empty($data)) {
            return Response::error('No update fields provided');
        }

        try {
            $result = $service->updateDeal($user, $companyId, $dealId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to update deal');
            }

            $deal = $result['data'];
            $output = "✅ **Deal Updated**\n\n";
            $output .= "**{$deal['title']}**\n";
            $output .= "Stage: {$deal['stage_name']} ({$deal['probability']}% probability)\n";

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to update deal: ' . $e->getMessage());
        }
    }
}

