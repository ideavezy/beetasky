<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class DocumentPdfService
{
    /**
     * Generate PDF for a contract.
     */
    public function generateContractPdf(Contract $contract): string
    {
        $html = $this->renderContractHtml($contract);
        
        $pdf = $this->createPdfFromHtml($html);
        
        // Generate filename
        $filename = 'contracts/' . $contract->id . '_' . time() . '.pdf';
        
        // Save to storage
        Storage::put($filename, $pdf);
        
        return $filename;
    }

    /**
     * Generate PDF for an invoice.
     */
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $html = $this->renderInvoiceHtml($invoice);
        
        $pdf = $this->createPdfFromHtml($html);
        
        // Generate filename
        $filename = 'invoices/' . $invoice->id . '_' . time() . '.pdf';
        
        // Save to storage
        Storage::put($filename, $pdf);
        
        return $filename;
    }

    /**
     * Create PDF from HTML.
     */
    private function createPdfFromHtml(string $html): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }

    /**
     * Render contract HTML.
     */
    private function renderContractHtml(Contract $contract): string
    {
        $company = $contract->company;
        $contact = $contract->contact;
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contract - ' . e($contract->title) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24pt;
        }
        .contract-number {
            color: #666;
            font-size: 10pt;
            margin-top: 10px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            font-size: 16pt;
            margin-bottom: 15px;
            color: #333;
        }
        .section h3 {
            font-size: 14pt;
            margin-bottom: 10px;
        }
        .section p {
            margin-bottom: 10px;
        }
        .signature-section {
            margin-top: 60px;
            page-break-inside: avoid;
        }
        .signature-block {
            margin-top: 40px;
            display: inline-block;
            width: 45%;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 5px;
        }
        .footer {
            margin-top: 60px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
        ul, ol {
            margin-left: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . e($contract->title) . '</h1>
        <div class="contract-number">Contract #' . e($contract->contract_number) . '</div>
        <div class="contract-number">Date: ' . now()->format('F j, Y') . '</div>
    </div>';

        // Render sections
        foreach ($contract->rendered_sections as $section) {
            $html .= $this->renderSection($section);
        }

        // Pricing section
        if ($contract->pricing_data) {
            $html .= '<div class="section">
                <h2>Pricing</h2>';
            
            if ($contract->contract_type === 'fixed_price') {
                $html .= '<p><strong>Total Amount:</strong> $' . number_format($contract->pricing_data['amount'] ?? 0, 2) . ' ' . ($contract->pricing_data['currency'] ?? 'USD') . '</p>';
            } elseif ($contract->contract_type === 'milestone') {
                $html .= '<table>
                    <thead>
                        <tr>
                            <th>Milestone</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($contract->pricing_data['milestones'] ?? [] as $milestone) {
                    $html .= '<tr>
                        <td>' . e($milestone['name']) . '</td>
                        <td>$' . number_format($milestone['amount'], 2) . '</td>
                        <td>' . e($milestone['due_date'] ?? 'TBD') . '</td>
                    </tr>';
                }
                $html .= '</tbody>
                </table>';
            } elseif ($contract->contract_type === 'subscription') {
                $html .= '<p><strong>Subscription Amount:</strong> $' . number_format($contract->pricing_data['amount'] ?? 0, 2) . ' / ' . ($contract->pricing_data['interval'] ?? 'month') . '</p>';
                $html .= '<p><strong>Subscription Period:</strong> ' . ($contract->pricing_data['period'] ?? 12) . ' months</p>';
            }
            
            $html .= '</div>';
        }

        // Signature section
        $html .= '<div class="signature-section">
            <h2>Signatures</h2>';
        
        if ($contract->client_signed_at) {
            $html .= '<div class="signature-block">
                <div><strong>Client:</strong></div>
                <div class="signature-line">
                    ' . e($contract->client_signed_by) . '<br>
                    Signed on: ' . $contract->client_signed_at->format('F j, Y \a\t g:i A') . '
                </div>
            </div>';
        }
        
        if ($contract->provider_signed_at) {
            $html .= '<div class="signature-block" style="float: right;">
                <div><strong>Provider:</strong></div>
                <div class="signature-line">
                    ' . e($contract->providerSigner->first_name . ' ' . $contract->providerSigner->last_name) . '<br>
                    Signed on: ' . $contract->provider_signed_at->format('F j, Y \a\t g:i A') . '
                </div>
            </div>';
        }
        
        $html .= '</div>';

        $html .= '<div class="footer">
        <p>' . e($company->name) . '</p>
        <p>This contract is legally binding. Please read carefully before signing.</p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Render a single section.
     */
    private function renderSection(array $section): string
    {
        $html = '<div class="section">';
        
        $type = $section['type'] ?? 'paragraph';
        $content = $section['content'] ?? '';
        
        switch ($type) {
            case 'heading':
                $level = $section['level'] ?? 2;
                $html .= '<h' . $level . '>' . e($content) . '</h' . $level . '>';
                break;
                
            case 'paragraph':
                $html .= '<p>' . $content . '</p>';
                break;
                
            case 'list':
                $listType = $section['listType'] ?? 'ul';
                $html .= '<' . $listType . '>';
                foreach ($content as $item) {
                    $html .= '<li>' . e($item) . '</li>';
                }
                $html .= '</' . $listType . '>';
                break;
                
            case 'table':
                $html .= '<table>';
                if (isset($content['headers'])) {
                    $html .= '<thead><tr>';
                    foreach ($content['headers'] as $header) {
                        $html .= '<th>' . e($header) . '</th>';
                    }
                    $html .= '</tr></thead>';
                }
                if (isset($content['rows'])) {
                    $html .= '<tbody>';
                    foreach ($content['rows'] as $row) {
                        $html .= '<tr>';
                        foreach ($row as $cell) {
                            $html .= '<td>' . e($cell) . '</td>';
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</tbody>';
                }
                $html .= '</table>';
                break;
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render invoice HTML.
     */
    private function renderInvoiceHtml(Invoice $invoice): string
    {
        $company = $invoice->company;
        $contact = $invoice->contact;
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - ' . e($invoice->invoice_number) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            color: #333;
            margin: 40px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 40px;
        }
        .header-left, .header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .header-right {
            text-align: right;
        }
        .invoice-title {
            font-size: 32pt;
            font-weight: bold;
            color: #333;
        }
        .invoice-number {
            font-size: 14pt;
            color: #666;
            margin-top: 5px;
        }
        .company-info {
            margin-top: 10px;
        }
        .bill-to {
            margin: 40px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-left: 4px solid #333;
        }
        .bill-to h3 {
            margin: 0 0 10px 0;
        }
        table.line-items {
            width: 100%;
            border-collapse: collapse;
            margin: 40px 0;
        }
        table.line-items th {
            background-color: #333;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: normal;
        }
        table.line-items td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        table.line-items tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            width: 300px;
            margin-left: auto;
            margin-top: 20px;
        }
        .totals table {
            width: 100%;
        }
        .totals td {
            padding: 8px 0;
        }
        .totals tr.total {
            font-size: 14pt;
            font-weight: bold;
            border-top: 2px solid #333;
        }
        .payment-terms {
            margin-top: 40px;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 60px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-number">' . e($invoice->invoice_number) . '</div>
            <div class="company-info">
                <strong>' . e($company->name) . '</strong>
            </div>
        </div>
        <div class="header-right">
            <div><strong>Issue Date:</strong> ' . $invoice->issue_date->format('F j, Y') . '</div>
            <div><strong>Due Date:</strong> ' . $invoice->due_date->format('F j, Y') . '</div>
            <div><strong>Status:</strong> ' . ucfirst($invoice->status) . '</div>
        </div>
    </div>';

        if ($contact) {
            $html .= '<div class="bill-to">
        <h3>Bill To:</h3>
        <div>' . e($contact->full_name) . '</div>';
            if ($contact->organization) {
                $html .= '<div>' . e($contact->organization) . '</div>';
            }
            if ($contact->email) {
                $html .= '<div>' . e($contact->email) . '</div>';
            }
            $html .= '</div>';
        }

        // Line items
        $html .= '<table class="line-items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($invoice->lineItems as $item) {
            $html .= '<tr>
            <td>' . nl2br(e($item->description)) . '</td>
            <td class="text-right">' . $item->quantity . '</td>
            <td class="text-right">$' . number_format($item->unit_price, 2) . '</td>
            <td class="text-right">$' . number_format($item->amount, 2) . '</td>
        </tr>';
        }
        
        $html .= '</tbody>
    </table>';

        // Totals
        $html .= '<div class="totals">
        <table>
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">$' . number_format($invoice->subtotal, 2) . '</td>
            </tr>';
        
        if ($invoice->discount_amount > 0) {
            $html .= '<tr>
            <td>Discount (' . $invoice->discount_rate . '%):</td>
            <td class="text-right">-$' . number_format($invoice->discount_amount, 2) . '</td>
        </tr>';
        }
        
        if ($invoice->tax_amount > 0) {
            $html .= '<tr>
            <td>Tax (' . $invoice->tax_rate . '%):</td>
            <td class="text-right">$' . number_format($invoice->tax_amount, 2) . '</td>
        </tr>';
        }
        
        $html .= '<tr class="total">
            <td>Total:</td>
            <td class="text-right">$' . number_format($invoice->total, 2) . ' ' . $invoice->currency . '</td>
        </tr>';
        
        if ($invoice->amount_paid > 0) {
            $html .= '<tr>
            <td>Amount Paid:</td>
            <td class="text-right">$' . number_format($invoice->amount_paid, 2) . '</td>
        </tr>
        <tr class="total">
            <td>Amount Due:</td>
            <td class="text-right">$' . number_format($invoice->amount_due, 2) . '</td>
        </tr>';
        }
        
        $html .= '</table>
    </div>';

        // Payment terms
        if ($invoice->payment_terms) {
            $html .= '<div class="payment-terms">
        <strong>Payment Terms:</strong><br>
        ' . nl2br(e($invoice->payment_terms)) . '
    </div>';
        }

        // Notes
        if ($invoice->notes) {
            $html .= '<div class="payment-terms">
        <strong>Notes:</strong><br>
        ' . nl2br(e($invoice->notes)) . '
    </div>';
        }

        $html .= '<div class="footer">
        <p>' . e($company->name) . '</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html>';

        return $html;
    }
}

