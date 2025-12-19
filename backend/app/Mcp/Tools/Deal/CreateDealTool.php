<?php

namespace App\Mcp\Tools\Deal;

use App\Services\DealService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateDealTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'create_deal';

    /**
     * The tool's description.
     */
    protected string $description = 'Create a new sales deal/opportunity in the pipeline.';

    /**
     * Define the input schema.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Title of the deal (REQUIRED)',
                ],
                'value' => [
                    'type' => 'number',
                    'description' => 'Deal value/amount',
                ],
                'currency' => [
                    'type' => 'string',
                    'description' => 'Currency code (default: USD)',
                    'default' => 'USD',
                ],
                'stage' => [
                    'type' => 'string',
                    'description' => 'Pipeline stage',
                    'enum' => ['qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'],
                    'default' => 'qualification',
                ],
                'contact_id' => [
                    'type' => 'string',
                    'description' => 'UUID of the associated contact',
                ],
                'expected_close_date' => [
                    'type' => 'string',
                    'description' => 'Expected close date (YYYY-MM-DD)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Deal description or notes',
                ],
            ],
            'required' => ['title'],
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

        $title = $request->input('title');
        if (!$title) {
            return Response::error('Deal title is required');
        }

        $data = [
            'title' => $title,
            'value' => $request->input('value'),
            'currency' => $request->input('currency') ?? 'USD',
            'stage' => $request->input('stage') ?? 'qualification',
            'contact_id' => $request->input('contact_id'),
            'expected_close_date' => $request->input('expected_close_date'),
            'description' => $request->input('description'),
        ];

        try {
            $result = $service->createDeal($user, $companyId, $data);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to create deal');
            }

            $deal = $result['data'];
            $output = "✅ **Deal Created**\n\n";
            $output .= "**{$deal['title']}**\n";
            if ($deal['value']) {
                $output .= "Value: {$deal['currency']} " . number_format($deal['value'], 2) . "\n";
            }
            $output .= "Stage: {$deal['stage_name']} ({$deal['probability']}% probability)\n";

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to create deal: ' . $e->getMessage());
        }
    }
}

