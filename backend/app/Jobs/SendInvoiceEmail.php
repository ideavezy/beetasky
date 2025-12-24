<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\DocumentEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInvoiceEmail implements ShouldQueue
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
        public Invoice $invoice,
        public string $recipientEmail,
        public string $recipientName,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentEmailService $emailService): void
    {
        try {
            Log::info('Sending invoice email', [
                'invoice_id' => $this->invoice->id,
                'recipient' => $this->recipientEmail,
            ]);

            $emailService->sendInvoiceEmail(
                $this->invoice,
                $this->recipientEmail,
                $this->recipientName
            );

            // Update invoice status and sent_at timestamp
            $this->invoice->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Log event
            $this->invoice->events()->create([
                'event_type' => 'sent',
                'event_data' => [
                    'recipient_email' => $this->recipientEmail,
                    'recipient_name' => $this->recipientName,
                    'sent_at' => now()->toISOString(),
                ],
            ]);

            Log::info('Invoice email sent successfully', [
                'invoice_id' => $this->invoice->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send invoice email', [
                'invoice_id' => $this->invoice->id,
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
        Log::error('Invoice email job failed permanently', [
            'invoice_id' => $this->invoice->id,
            'error' => $exception->getMessage(),
        ]);

        // Log failure event
        $this->invoice->events()->create([
            'event_type' => 'send_failed',
            'event_data' => [
                'error' => $exception->getMessage(),
                'recipient_email' => $this->recipientEmail,
            ],
        ]);
    }
}

