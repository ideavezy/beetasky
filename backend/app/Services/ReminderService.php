<?php

namespace App\Services;

use App\Models\ContactReminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Reminder Service
 * 
 * Handles contact follow-up reminders.
 */
class ReminderService
{
    /**
     * Create a new reminder.
     */
    public function createReminder(User $user, ?string $companyId, array $data): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            // Parse remind_at date
            $remindAt = $this->parseRemindAt($data['remind_at'] ?? $data['date'] ?? null);

            if (!$remindAt) {
                return ['success' => false, 'error' => 'Invalid or missing remind_at date'];
            }

            $reminder = ContactReminder::create([
                'company_id' => $companyId,
                'contact_id' => $data['contact_id'],
                'user_id' => $user->id,
                'type' => $data['type'] ?? 'follow_up',
                'title' => $data['title'] ?? 'Follow up',
                'description' => $data['description'] ?? null,
                'remind_at' => $remindAt,
                'ai_generated' => $data['ai_generated'] ?? false,
            ]);

            $reminder->load('contact');

            return [
                'success' => true,
                'message' => "Reminder set for {$remindAt->format('M j, Y g:i A')}",
                'data' => [
                    'id' => $reminder->id,
                    'title' => $reminder->title,
                    'type' => $reminder->type,
                    'contact_id' => $reminder->contact_id,
                    'contact_name' => $reminder->contact?->full_name,
                    'remind_at' => $reminder->remind_at->toISOString(),
                    'remind_at_formatted' => $reminder->remind_at->format('M j, Y g:i A'),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('ReminderService createReminder failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse various date formats.
     */
    protected function parseRemindAt($input): ?Carbon
    {
        if (!$input) {
            return null;
        }

        try {
            // Handle natural language
            $input = strtolower(trim($input));

            // Common patterns
            $patterns = [
                'tomorrow' => now()->addDay()->setTime(9, 0),
                'next week' => now()->addWeek()->setTime(9, 0),
                'next monday' => now()->next('monday')->setTime(9, 0),
                'next tuesday' => now()->next('tuesday')->setTime(9, 0),
                'next wednesday' => now()->next('wednesday')->setTime(9, 0),
                'next thursday' => now()->next('thursday')->setTime(9, 0),
                'next friday' => now()->next('friday')->setTime(9, 0),
                'in an hour' => now()->addHour(),
                'in 1 hour' => now()->addHour(),
                'in 2 hours' => now()->addHours(2),
                'in 3 days' => now()->addDays(3)->setTime(9, 0),
            ];

            if (isset($patterns[$input])) {
                return $patterns[$input];
            }

            // Try parsing as date
            return Carbon::parse($input);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * List reminders for a user.
     */
    public function listReminders(User $user, ?string $companyId, array $filters = []): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $query = ContactReminder::forCompany($companyId)
                ->forUser($user->id)
                ->with('contact');

            // Filters
            if (!empty($filters['contact_id'])) {
                $query->where('contact_id', $filters['contact_id']);
            }

            if (!empty($filters['pending_only']) || ($filters['pending_only'] ?? true)) {
                $query->pending();
            }

            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            $limit = $filters['limit'] ?? 20;
            $reminders = $query
                ->orderBy('remind_at', 'asc')
                ->limit($limit)
                ->get();

            return [
                'success' => true,
                'data' => $reminders->map(fn($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'description' => $r->description,
                    'type' => $r->type,
                    'contact_id' => $r->contact_id,
                    'contact_name' => $r->contact?->full_name,
                    'remind_at' => $r->remind_at->toISOString(),
                    'remind_at_formatted' => $r->remind_at->format('M j, Y g:i A'),
                    'is_overdue' => $r->isOverdue(),
                    'is_due_soon' => $r->isDueSoon(),
                    'is_completed' => $r->is_completed,
                ])->toArray(),
                'total' => $reminders->count(),
            ];
        } catch (\Exception $e) {
            Log::error('ReminderService listReminders failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Complete a reminder.
     */
    public function completeReminder(User $user, ?string $companyId, string $reminderId): array
    {
        if (!$companyId) {
            return ['success' => false, 'error' => 'Company ID is required'];
        }

        try {
            $reminder = ContactReminder::forCompany($companyId)
                ->forUser($user->id)
                ->where('id', $reminderId)
                ->with('contact')
                ->first();

            if (!$reminder) {
                return ['success' => false, 'error' => 'Reminder not found'];
            }

            $reminder->markComplete();

            return [
                'success' => true,
                'message' => "Completed reminder: {$reminder->title}",
                'data' => [
                    'id' => $reminder->id,
                    'title' => $reminder->title,
                    'contact_name' => $reminder->contact?->full_name,
                    'completed_at' => $reminder->completed_at->format('M j, Y g:i A'),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('ReminderService completeReminder failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get pending reminders count.
     */
    public function getPendingCount(User $user, ?string $companyId): int
    {
        if (!$companyId) {
            return 0;
        }

        return ContactReminder::forCompany($companyId)
            ->forUser($user->id)
            ->pending()
            ->count();
    }

    /**
     * Get overdue reminders.
     */
    public function getOverdueReminders(User $user, ?string $companyId): array
    {
        if (!$companyId) {
            return [];
        }

        $reminders = ContactReminder::forCompany($companyId)
            ->forUser($user->id)
            ->overdue()
            ->with('contact')
            ->orderBy('remind_at', 'asc')
            ->limit(10)
            ->get();

        return $reminders->map(fn($r) => [
            'id' => $r->id,
            'title' => $r->title,
            'contact_name' => $r->contact?->full_name,
            'remind_at' => $r->remind_at->diffForHumans(),
        ])->toArray();
    }

    /**
     * Get upcoming reminders.
     */
    public function getUpcomingReminders(User $user, ?string $companyId, int $days = 7): array
    {
        if (!$companyId) {
            return [];
        }

        $reminders = ContactReminder::forCompany($companyId)
            ->forUser($user->id)
            ->upcoming($days)
            ->with('contact')
            ->orderBy('remind_at', 'asc')
            ->limit(10)
            ->get();

        return $reminders->map(fn($r) => [
            'id' => $r->id,
            'title' => $r->title,
            'type' => $r->type,
            'contact_id' => $r->contact_id,
            'contact_name' => $r->contact?->full_name,
            'remind_at' => $r->remind_at->toISOString(),
            'remind_at_formatted' => $r->remind_at->format('M j, Y g:i A'),
        ])->toArray();
    }
}

