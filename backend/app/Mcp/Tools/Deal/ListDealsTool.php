<?php

namespace App\Mcp\Tools\Deal;

use App\Services\DealService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListDealsTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'list_deals';

    /**
     * The tool's description.
     */
    protected string $description = 'List deals in the sales pipeline with optional filters.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'stage' => [
                    'type' => 'string',
                    'description' => 'Filter by pipeline stage',
                    'enum' => ['qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'],
                ],
                'open_only' => [
                    'type' => 'boolean',
                    'description' => 'Show only open deals (not closed)',
                    'default' => true,
                ],
                'assigned_to' => [
                    'type' => 'string',
                    'description' => 'Filter by assignee (use "me" for current user)',
                ],
                'contact_id' => [
                    'type' => 'string',
                    'description' => 'Filter by associated contact',
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

        $filters = [
            'stage' => $request->input('stage'),
            'open_only' => $request->input('open_only') ?? true,
            'assigned_to' => $request->input('assigned_to'),
            'contact_id' => $request->input('contact_id'),
            'limit' => $request->input('limit') ?? 20,
        ];

        try {
            $result = $service->listDeals($user, $companyId, $filters);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to list deals');
            }

            $deals = $result['data'];
            $total = $result['total'];

            if (empty($deals)) {
                return Response::text("No deals found matching your criteria.");
            }

            // Calculate totals
            $totalValue = array_sum(array_column($deals, 'value'));
            $totalWeighted = array_sum(array_column($deals, 'weighted_value'));

            $output = "## Pipeline ({$total} deals)\n\n";
            $output .= "**Total Value:** " . number_format($totalValue, 2) . " | ";
            $output .= "**Weighted:** " . number_format($totalWeighted, 2) . "\n\n";

            // Group by stage
            $byStage = [];
            foreach ($deals as $deal) {
                $stage = $deal['stage'];
                if (!isset($byStage[$stage])) {
                    $byStage[$stage] = [];
                }
                $byStage[$stage][] = $deal;
            }

            foreach ($byStage as $stage => $stageDeals) {
                $stageName = $stageDeals[0]['stage_name'] ?? ucfirst($stage);
                $output .= "### {$stageName}\n";

                foreach ($stageDeals as $deal) {
                    $output .= "- **{$deal['title']}**";
                    if ($deal['value']) {
                        $output .= " ({$deal['currency']} " . number_format($deal['value'], 2) . ")";
                    }
                    if ($deal['contact']) {
                        $output .= " - {$deal['contact']}";
                    }
                    $output .= "\n";
                }
                $output .= "\n";
            }

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to list deals: ' . $e->getMessage());
        }
    }
}

