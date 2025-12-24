<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\DocumentPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateContractPdf implements ShouldQueue
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
        public Contract $contract,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentPdfService $pdfService): void
    {
        try {
            Log::info('Generating contract PDF', [
                'contract_id' => $this->contract->id,
            ]);

            $pdfPath = $pdfService->generateContractPdf($this->contract);

            // Update contract with PDF path
            $this->contract->update([
                'pdf_path' => $pdfPath,
                'pdf_generated_at' => now(),
            ]);

            // Log event
            $this->contract->events()->create([
                'event_type' => 'pdf_generated',
                'event_data' => [
                    'pdf_path' => $pdfPath,
                    'generated_at' => now()->toISOString(),
                ],
            ]);

            Log::info('Contract PDF generated successfully', [
                'contract_id' => $this->contract->id,
                'pdf_path' => $pdfPath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate contract PDF', [
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
        Log::error('Contract PDF generation job failed permanently', [
            'contract_id' => $this->contract->id,
            'error' => $exception->getMessage(),
        ]);

        // Log failure event
        $this->contract->events()->create([
            'event_type' => 'pdf_generation_failed',
            'event_data' => [
                'error' => $exception->getMessage(),
            ],
        ]);
    }
}

