<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\DocumentEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendContractEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Contract $contract,
        public string $recipientEmail,
        public string $recipientName,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentEmailService $emailService): void
    {
        try {
            Log::info('Sending contract email', [
                'contract_id' => $this->contract->id,
                'recipient' => $this->recipientEmail,
            ]);

            $emailService->sendContractEmail(
                $this->contract,
                $this->recipientEmail,
                $this->recipientName
            );

            // Update contract status and sent_at timestamp
            $this->contract->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Log event
            $this->contract->events()->create([
                'event_type' => 'sent',
                'event_data' => [
                    'recipient_email' => $this->recipientEmail,
                    'recipient_name' => $this->recipientName,
                    'sent_at' => now()->toISOString(),
                ],
            ]);

            Log::info('Contract email sent successfully', [
                'contract_id' => $this->contract->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send contract email', [
                'contract_id' => $this->contract->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Contract email job failed permanently', [
            'contract_id' => $this->contract->id,
            'error' => $exception->getMessage(),
        ]);

        // Log failure event
        $this->contract->events()->create([
            'event_type' => 'send_failed',
            'event_data' => [
                'error' => $exception->getMessage(),
                'recipient_email' => $this->recipientEmail,
            ],
        ]);
    }
}

