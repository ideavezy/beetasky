<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Deal Service
 * 
 * Handles deal/opportunity pipeline management.
 */
class DealService
{
    /**
     * Create a new deal.
     */
    public function createDeal(User $user, ?string $companyId, array $data): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $deal = Deal::create([
                'company_id' => $companyId,
                'contact_id' => $data['contact_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'value' => $data['value'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'stage' => $data['stage'] ?? 'qualification',
                'probability' => $data['probability'] ?? Deal::STAGES[$data['stage'] ?? 'qualification']['probability'],
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'created_by' => $user->id,
                'assigned_to' => $data['assigned_to'] ?? $user->id,
            ]);

            return [
                'success' => true,
                'message' => "Created deal '{$deal->title}'",
                'data' => [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'value' => $deal->value,
                    'currency' => $deal->currency,
                    'stage' => $deal->stage,
                    'stage_name' => Deal::STAGES[$deal->stage]['name'],
                    'probability' => $deal->probability,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('DealService createDeal failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * List deals with filters.
     */
    public function listDeals(User $user, ?string $companyId, array $filters = []): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $query = Deal::forCompany($companyId)
                ->with(['contact', 'assignee']);

            // Apply filters
            if (!empty($filters['stage'])) {
                $query->byStage($filters['stage']);
            }

            if (!empty($filters['open_only']) || ($filters['open_only'] ?? false)) {
                $query->open();
            }

            if (!empty($filters['assigned_to'])) {
                if ($filters['assigned_to'] === 'me') {
                    $query->where('assigned_to', $user->id);
                } else {
                    $query->where('assigned_to', $filters['assigned_to']);
                }
            }

            if (!empty($filters['contact_id'])) {
                $query->where('contact_id', $filters['contact_id']);
            }

            $limit = $filters['limit'] ?? 20;
            $deals = $query->orderByRaw("
                CASE stage
                    WHEN 'qualification' THEN 1
                    WHEN 'proposal' THEN 2
                    WHEN 'negotiation' THEN 3
                    WHEN 'closed_won' THEN 4
                    WHEN 'closed_lost' THEN 5
                END
            ")
                ->orderBy('expected_close_date', 'asc')
                ->limit($limit)
                ->get();

            return [
                'success' => true,
                'data' => $deals->map(fn($deal) => [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'value' => $deal->value,
                    'currency' => $deal->currency,
                    'stage' => $deal->stage,
                    'stage_name' => Deal::STAGES[$deal->stage]['name'] ?? $deal->stage,
                    'probability' => $deal->probability,
                    'weighted_value' => $deal->weighted_value,
                    'contact' => $deal->contact?->full_name,
                    'assignee' => $deal->assignee?->first_name,
                    'expected_close' => $deal->expected_close_date?->format('M j, Y'),
                    'is_closed' => $deal->isClosed(),
                ])->toArray(),
                'total' => $deals->count(),
            ];
        } catch (\Exception $e) {
            Log::error('DealService listDeals failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get a single deal.
     */
    public function getDeal(User $user, ?string $companyId, string $dealId): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $deal = Deal::forCompany($companyId)
                ->where('id', $dealId)
                ->with(['contact', 'assignee', 'creator'])
                ->first();

            if (!$deal) {
                return ['success' => false, 'error' => 'Deal not found'];
            }

            return [
                'success' => true,
                'data' => [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'description' => $deal->description,
                    'value' => $deal->value,
                    'currency' => $deal->currency,
                    'stage' => $deal->stage,
                    'stage_name' => Deal::STAGES[$deal->stage]['name'] ?? $deal->stage,
                    'probability' => $deal->probability,
                    'weighted_value' => $deal->weighted_value,
                    'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
                    'contact' => $deal->contact ? [
                        'id' => $deal->contact->id,
                        'name' => $deal->contact->full_name,
                        'email' => $deal->contact->email,
                    ] : null,
                    'assignee' => $deal->assignee ? [
                        'id' => $deal->assignee->id,
                        'name' => $deal->assignee->first_name,
                    ] : null,
                    'created_by' => $deal->creator?->first_name,
                    'is_closed' => $deal->isClosed(),
                    'is_won' => $deal->isWon(),
                    'lost_reason' => $deal->lost_reason,
                    'closed_at' => $deal->closed_at?->format('M j, Y'),
                    'created_at' => $deal->created_at->format('M j, Y'),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('DealService getDeal failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update a deal.
     */
    public function updateDeal(User $user, ?string $companyId, string $dealId, array $data): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $deal = Deal::forCompany($companyId)
                ->where('id', $dealId)
                ->first();

            if (!$deal) {
                return ['success' => false, 'error' => 'Deal not found'];
            }

            // Handle stage change
            if (!empty($data['stage']) && $data['stage'] !== $deal->stage) {
                $deal->moveToStage($data['stage']);
                unset($data['stage']);
            }

            // Handle marking as won/lost
            if (!empty($data['mark_won'])) {
                $deal->markAsWon();
                unset($data['mark_won']);
            } elseif (!empty($data['mark_lost'])) {
                $deal->markAsLost($data['lost_reason'] ?? null);
                unset($data['mark_lost']);
            }

            // Update other fields
            $allowedFields = ['title', 'description', 'value', 'currency', 'probability', 'expected_close_date', 'contact_id', 'assigned_to'];
            $updates = array_filter(
                array_intersect_key($data, array_flip($allowedFields)),
                fn($v) => $v !== null
            );

            if (!empty($updates)) {
                $deal->update($updates);
            }

            return [
                'success' => true,
                'message' => "Updated deal '{$deal->title}'",
                'data' => [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'stage' => $deal->stage,
                    'stage_name' => Deal::STAGES[$deal->stage]['name'] ?? $deal->stage,
                    'value' => $deal->value,
                    'probability' => $deal->probability,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('DealService updateDeal failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get pipeline statistics.
     */
    public function getPipelineStats(?string $companyId): array
    {
        if (!$companyId) {
            return [
                'total_value' => 0,
                'weighted_value' => 0,
                'deals_count' => 0,
                'by_stage' => [],
            ];
        }

        try {
            $openDeals = Deal::forCompany($companyId)->open()->get();

            $totalValue = $openDeals->sum('value');
            $weightedValue = $openDeals->sum('weighted_value');

            $byStage = [];
            foreach (Deal::STAGES as $stageKey => $stageInfo) {
                if (in_array($stageKey, ['closed_won', 'closed_lost'])) {
                    continue; // Skip closed stages for pipeline stats
                }

                $stageDeals = $openDeals->where('stage', $stageKey);
                $byStage[$stageKey] = [
                    'name' => $stageInfo['name'],
                    'count' => $stageDeals->count(),
                    'value' => $stageDeals->sum('value'),
                ];
            }

            // Get deals closing soon
            $closingSoon = Deal::forCompany($companyId)
                ->closingSoon(7)
                ->count();

            return [
                'total_value' => $totalValue,
                'weighted_value' => $weightedValue,
                'deals_count' => $openDeals->count(),
                'closing_soon' => $closingSoon,
                'by_stage' => $byStage,
            ];
        } catch (\Exception $e) {
            Log::error('DealService getPipelineStats failed', ['error' => $e->getMessage()]);
            return [
                'total_value' => 0,
                'weighted_value' => 0,
                'deals_count' => 0,
                'by_stage' => [],
            ];
        }
    }
}

