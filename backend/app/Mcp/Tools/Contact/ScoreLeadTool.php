<?php

namespace App\Mcp\Tools\Contact;

use App\Models\CompanyContact;
use App\Services\LeadScoringService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ScoreLeadTool extends Tool
{
    /**
     * The tool's name.
     */
    protected string $name = 'score_lead';

    /**
     * The tool's description.
     */
    protected string $description = 'Calculate or recalculate the lead score for contacts. Can score a single contact or all leads.';

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
                    'description' => 'Score a specific contact by UUID',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search for contact by name or email',
                ],
                'score_all' => [
                    'type' => 'boolean',
                    'description' => 'Score all leads in the company (default: false)',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, LeadScoringService $scoringService): Response
    {
        $user = $request->user();

        if (!$user) {
            return Response::error('Authentication required');
        }

        $companyId = $request->input('company_id') ?? request()->header('X-Company-ID');

        if (!$companyId) {
            return Response::error('company_id is required (provide as parameter or X-Company-ID header)');
        }

        $contactId = $request->input('contact_id');
        $search = $request->input('search');
        $scoreAll = $request->input('score_all', false);

        try {
            // Score all leads
            if ($scoreAll) {
                $result = $scoringService->scoreAllLeads($companyId);

                if (!$result['success']) {
                    return Response::error($result['error'] ?? 'Failed to score leads');
                }

                $output = "## Lead Scoring Complete\n\n";
                $output .= "Scored **{$result['scored']}** of {$result['total']} leads.\n\n";

                if (!empty($result['results'])) {
                    $output .= "### Top Scored Leads\n\n";
                    $output .= "| Lead | Score | Grade |\n";
                    $output .= "|------|-------|-------|\n";

                    foreach (array_slice($result['results'], 0, 10) as $lead) {
                        $output .= "| {$lead['name']} | {$lead['score']} | {$lead['grade']} |\n";
                    }
                }

                return Response::text($output);
            }

            // Find contact if search provided
            if (!$contactId && $search) {
                $companyContact = CompanyContact::where('company_id', $companyId)
                    ->whereHas('contact', function ($q) use ($search) {
                        $q->where('full_name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    })
                    ->with('contact')
                    ->first();

                if (!$companyContact) {
                    return Response::error("No contact found matching '{$search}'");
                }

                $contactId = $companyContact->contact_id;
            }

            if (!$contactId) {
                return Response::error('Please provide a contact_id, search term, or use score_all=true');
            }

            // Score single contact
            $companyContact = CompanyContact::where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->with('contact')
                ->first();

            if (!$companyContact) {
                return Response::error('Contact not found');
            }

            $result = $scoringService->scoreContact($companyContact);

            if (!$result['success']) {
                return Response::error($result['error'] ?? 'Failed to score contact');
            }

            $output = "## Lead Score for {$result['contact_name']}\n\n";
            $output .= "**Score:** {$result['score']}/100 (Grade: {$result['grade']})\n\n";
            $output .= "### Scoring Breakdown\n\n";

            foreach ($result['factors'] as $factor => $data) {
                $output .= "- **" . ucfirst($factor) . ":** +{$data['score']} points";
                if (isset($data['description'])) {
                    $output .= " ({$data['description']})";
                }
                $output .= "\n";
            }

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::error('Failed to score lead: ' . $e->getMessage());
        }
    }
}

