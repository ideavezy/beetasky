<?php

namespace App\Services;

use App\Jobs\SendContractEmail;
use App\Jobs\SendInvoiceEmail;
use App\Models\Contract;
use App\Models\Invoice;

class DocumentEmailService
{
    /**
     * Queue contract email.
     */
    public function sendContractEmail(Contract $contract, bool $schedule = false): void
    {
        if ($schedule) {
            SendContractEmail::dispatch($contract)->delay(now()->addMinutes(5));
        } else {
            SendContractEmail::dispatch($contract);
        }
    }

    /**
     * Queue invoice email.
     */
    public function sendInvoiceEmail(Invoice $invoice, bool $schedule = false): void
    {
        if ($schedule) {
            SendInvoiceEmail::dispatch($invoice)->delay(now()->addMinutes(5));
        } else {
            SendInvoiceEmail::dispatch($invoice);
        }
    }

    /**
     * Get contract signing URL.
     */
    public function getContractUrl(Contract $contract): string
    {
        return config('app.client_url') . '/public/contracts/' . $contract->token;
    }

    /**
     * Get invoice payment URL.
     */
    public function getInvoiceUrl(Invoice $invoice): string
    {
        return config('app.client_url') . '/public/invoices/' . $invoice->token;
    }
}

