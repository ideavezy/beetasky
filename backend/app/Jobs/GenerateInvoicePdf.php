<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\DocumentPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateInvoicePdf implements ShouldQueue
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
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Invoice $invoice,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentPdfService $pdfService): void
    {
        try {
            Log::info('Generating invoice PDF', [
                'invoice_id' => $this->invoice->id,
            ]);

            $pdfPath = $pdfService->generateInvoicePdf($this->invoice);

            // Update invoice with PDF path
            $this->invoice->update([
                'pdf_path' => $pdfPath,
                'pdf_generated_at' => now(),
            ]);

            // Log event
            $this->invoice->events()->create([
                'event_type' => 'pdf_generated',
                'event_data' => [
                    'pdf_path' => $pdfPath,
                    'generated_at' => now()->toISOString(),
                ],
            ]);

            Log::info('Invoice PDF generated successfully', [
                'invoice_id' => $this->invoice->id,
                'pdf_path' => $pdfPath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice PDF', [
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
        Log::error('Invoice PDF generation job failed permanently', [
            'invoice_id' => $this->invoice->id,
            'error' => $exception->getMessage(),
        ]);

        // Log failure event
        $this->invoice->events()->create([
            'event_type' => 'pdf_generation_failed',
            'event_data' => [
                'error' => $exception->getMessage(),
            ],
        ]);
    }
}

