<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\DealService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DealController extends Controller
{
    public function __construct(protected DealService $dealService)
    {
    }

    /**
     * List deals.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json(['error' => 'Company ID required'], 400);
        }

        $query = Deal::forCompany($companyId)
            ->with(['contact', 'assignee']);

        // Filters
        if ($request->has('stage')) {
            $query->byStage($request->stage);
        }

        if ($request->boolean('open_only', true)) {
            $query->open();
        }

        if ($request->has('assigned_to')) {
            if ($request->assigned_to === 'me') {
                $query->where('assigned_to', $user->id);
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        if ($request->has('search')) {
            $query->where('title', 'ilike', '%' . $request->search . '%');
        }

        $deals = $query
            ->orderByRaw("
                CASE stage
                    WHEN 'qualification' THEN 1
                    WHEN 'proposal' THEN 2
                    WHEN 'negotiation' THEN 3
                    WHEN 'closed_won' THEN 4
                    WHEN 'closed_lost' THEN 5
                END
            ")
            ->orderBy('expected_close_date', 'asc')
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json([
            'data' => $deals->map(fn($deal) => [
                'id' => $deal->id,
                'title' => $deal->title,
                'description' => $deal->description,
                'value' => $deal->value ? (float) $deal->value : null,
                'currency' => $deal->currency,
                'stage' => $deal->stage,
                'probability' => $deal->probability,
                'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
                'contact' => $deal->contact ? [
                    'id' => $deal->contact->id,
                    'name' => $deal->contact->full_name,
                    'email' => $deal->contact->email,
                ] : null,
                'assignee' => $deal->assignee ? [
                    'id' => $deal->assignee->id,
                    'name' => $deal->assignee->first_name,
                    'avatar' => $deal->assignee->avatar_url,
                ] : null,
                'is_closed' => $deal->isClosed(),
                'created_at' => $deal->created_at->toISOString(),
            ]),
            'total' => $deals->count(),
        ]);
    }

    /**
     * Get pipeline stats.
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json(['error' => 'Company ID required'], 400);
        }

        return response()->json($this->dealService->getPipelineStats($companyId));
    }

    /**
     * Create a new deal.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'stage' => 'nullable|in:qualification,proposal,negotiation,closed_won,closed_lost',
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'expected_close_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json(['error' => 'Company ID required'], 400);
        }

        $result = $this->dealService->createDeal($user, $companyId, $request->all());

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json($result['data'], 201);
    }

    /**
     * Get a single deal.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json(['error' => 'Company ID required'], 400);
        }

        $result = $this->dealService->getDeal($user, $companyId, $id);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 404);
        }

        return response()->json($result['data']);
    }

    /**
     * Update a deal.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'value' => 'nullable|numeric|min:0',
            'stage' => 'nullable|in:qualification,proposal,negotiation,closed_won,closed_lost',
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'expected_close_date' => 'nullable|date',
            'description' => 'nullable|string',
            'lost_reason' => 'nullable|string',
        ]);

        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json(['error' => 'Company ID required'], 400);
        }

        $result = $this->dealService->updateDeal($user, $companyId, $id, $request->all());

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json($result['data']);
    }

    /**
     * Delete a deal.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json(['error' => 'Company ID required'], 400);
        }

        $deal = Deal::forCompany($companyId)->find($id);

        if (!$deal) {
            return response()->json(['error' => 'Deal not found'], 404);
        }

        $deal->delete();

        return response()->json(['message' => 'Deal deleted']);
    }
}

