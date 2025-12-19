<?php

namespace App\Services;

use App\Models\CompanyContact;
use App\Models\ContactNote;
use Illuminate\Support\Facades\Log;

/**
 * Lead Scoring Service
 * 
 * Calculates lead scores based on various factors:
 * - Source quality (referral, website, cold call)
 * - Profile completeness (email, phone, organization, job title)
 * - Activity frequency (notes, interactions)
 * - Engagement recency
 */
class LeadScoringService
{
    /**
     * Source quality scores.
     */
    protected const SOURCE_SCORES = [
        'referral' => 30,
        'partner' => 25,
        'website' => 20,
        'social_media' => 15,
        'trade_show' => 15,
        'email_campaign' => 10,
        'advertisement' => 10,
        'cold_call' => 5,
        'manual' => 5,
        'ai_chat' => 10,
        'other' => 5,
    ];

    /**
     * Profile field scores.
     */
    protected const PROFILE_SCORES = [
        'email' => 10,
        'phone' => 10,
        'organization' => 10,
        'job_title' => 5,
    ];

    /**
     * Activity scoring thresholds.
     */
    protected const ACTIVITY_THRESHOLDS = [
        ['min' => 10, 'score' => 25],  // 10+ notes = 25 points
        ['min' => 5, 'score' => 15],   // 5-9 notes = 15 points
        ['min' => 2, 'score' => 10],   // 2-4 notes = 10 points
        ['min' => 1, 'score' => 5],    // 1 note = 5 points
    ];

    /**
     * Recency scoring (days since last activity).
     */
    protected const RECENCY_THRESHOLDS = [
        ['max_days' => 3, 'score' => 20],   // Active within 3 days
        ['max_days' => 7, 'score' => 15],   // Active within a week
        ['max_days' => 14, 'score' => 10],  // Active within 2 weeks
        ['max_days' => 30, 'score' => 5],   // Active within a month
        // Older than 30 days = 0 points
    ];

    /**
     * Calculate the lead score for a company contact.
     */
    public function calculateScore(CompanyContact $companyContact): array
    {
        $contact = $companyContact->contact;
        $factors = [];
        $totalScore = 0;

        // 1. Source Quality Score
        $source = $companyContact->source ?? 'other';
        $sourceScore = self::SOURCE_SCORES[$source] ?? self::SOURCE_SCORES['other'];
        $factors['source'] = [
            'value' => $source,
            'score' => $sourceScore,
            'description' => "Source: {$source}",
        ];
        $totalScore += $sourceScore;

        // 2. Profile Completeness Score
        $profileScore = 0;
        $profileDetails = [];

        if (!empty($contact->email)) {
            $profileScore += self::PROFILE_SCORES['email'];
            $profileDetails[] = 'email';
        }
        if (!empty($contact->phone)) {
            $profileScore += self::PROFILE_SCORES['phone'];
            $profileDetails[] = 'phone';
        }
        if (!empty($contact->organization)) {
            $profileScore += self::PROFILE_SCORES['organization'];
            $profileDetails[] = 'organization';
        }
        if (!empty($contact->job_title)) {
            $profileScore += self::PROFILE_SCORES['job_title'];
            $profileDetails[] = 'job_title';
        }

        $factors['profile'] = [
            'fields' => $profileDetails,
            'score' => $profileScore,
            'description' => 'Profile completeness: ' . count($profileDetails) . '/4 fields',
        ];
        $totalScore += $profileScore;

        // 3. Activity Frequency Score
        $noteCount = ContactNote::where('contact_id', $contact->id)
            ->where('company_id', $companyContact->company_id)
            ->count();

        $activityScore = 0;
        foreach (self::ACTIVITY_THRESHOLDS as $threshold) {
            if ($noteCount >= $threshold['min']) {
                $activityScore = $threshold['score'];
                break;
            }
        }

        $factors['activity'] = [
            'note_count' => $noteCount,
            'score' => $activityScore,
            'description' => "{$noteCount} recorded activities",
        ];
        $totalScore += $activityScore;

        // 4. Engagement Recency Score
        $lastActivity = $companyContact->last_activity_at;
        $recencyScore = 0;

        if ($lastActivity) {
            $daysSinceActivity = now()->diffInDays($lastActivity);
            foreach (self::RECENCY_THRESHOLDS as $threshold) {
                if ($daysSinceActivity <= $threshold['max_days']) {
                    $recencyScore = $threshold['score'];
                    break;
                }
            }

            $factors['recency'] = [
                'days_since_activity' => $daysSinceActivity,
                'score' => $recencyScore,
                'description' => $daysSinceActivity === 0 
                    ? 'Active today' 
                    : "Active {$daysSinceActivity} days ago",
            ];
        } else {
            $factors['recency'] = [
                'days_since_activity' => null,
                'score' => 0,
                'description' => 'No recorded activity',
            ];
        }
        $totalScore += $recencyScore;

        // Cap score at 100
        $totalScore = min(100, $totalScore);

        return [
            'score' => $totalScore,
            'factors' => $factors,
            'grade' => $this->getGrade($totalScore),
        ];
    }

    /**
     * Get a letter grade based on score.
     */
    protected function getGrade(int $score): string
    {
        return match (true) {
            $score >= 80 => 'A',
            $score >= 60 => 'B',
            $score >= 40 => 'C',
            $score >= 20 => 'D',
            default => 'F',
        };
    }

    /**
     * Score and update a single company contact.
     */
    public function scoreContact(CompanyContact $companyContact): array
    {
        try {
            $result = $this->calculateScore($companyContact);

            $companyContact->update([
                'lead_score' => $result['score'],
                'score_factors' => $result['factors'],
                'score_updated_at' => now(),
            ]);

            return [
                'success' => true,
                'contact_id' => $companyContact->contact_id,
                'contact_name' => $companyContact->contact->full_name,
                'score' => $result['score'],
                'grade' => $result['grade'],
                'factors' => $result['factors'],
            ];
        } catch (\Exception $e) {
            Log::error('Lead scoring failed', [
                'contact_id' => $companyContact->contact_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Score all leads for a company.
     */
    public function scoreAllLeads(string $companyId): array
    {
        $leads = CompanyContact::where('company_id', $companyId)
            ->where('relation_type', 'lead')
            ->with('contact')
            ->get();

        $scored = 0;
        $errors = 0;
        $results = [];

        foreach ($leads as $lead) {
            $result = $this->scoreContact($lead);
            if ($result['success']) {
                $scored++;
                $results[] = [
                    'name' => $result['contact_name'],
                    'score' => $result['score'],
                    'grade' => $result['grade'],
                ];
            } else {
                $errors++;
            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] - $a['score']);

        return [
            'success' => true,
            'total' => count($leads),
            'scored' => $scored,
            'errors' => $errors,
            'results' => $results,
        ];
    }

    /**
     * Get hot leads (high score leads).
     */
    public function getHotLeads(string $companyId, int $limit = 5): array
    {
        $leads = CompanyContact::where('company_id', $companyId)
            ->where('relation_type', 'lead')
            ->where('lead_score', '>=', 60)
            ->orderBy('lead_score', 'desc')
            ->limit($limit)
            ->with('contact')
            ->get();

        return $leads->map(fn($cc) => [
            'id' => $cc->contact->id,
            'name' => $cc->contact->full_name,
            'email' => $cc->contact->email,
            'organization' => $cc->contact->organization,
            'score' => $cc->lead_score,
            'grade' => $this->getGrade($cc->lead_score),
        ])->toArray();
    }

    /**
     * Get stale leads that need follow-up.
     */
    public function getStaleLeads(string $companyId, int $days = 14, int $limit = 10): array
    {
        $leads = CompanyContact::where('company_id', $companyId)
            ->where('relation_type', 'lead')
            ->where(function ($q) use ($days) {
                $q->where('last_activity_at', '<', now()->subDays($days))
                    ->orWhereNull('last_activity_at');
            })
            ->orderBy('lead_score', 'desc')
            ->limit($limit)
            ->with('contact')
            ->get();

        return $leads->map(fn($cc) => [
            'id' => $cc->contact->id,
            'name' => $cc->contact->full_name,
            'email' => $cc->contact->email,
            'score' => $cc->lead_score,
            'last_activity' => $cc->last_activity_at?->diffForHumans() ?? 'Never',
        ])->toArray();
    }
}

