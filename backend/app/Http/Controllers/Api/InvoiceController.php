<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateInvoicePdf;
use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceLineItem;
use App\Models\InvoiceTemplate;
use App\Models\Contact;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Invoice::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['contact:id,full_name,email', 'project:id,name']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by contact
        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        // Filter by project
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('issue_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('issue_date', '<=', $request->input('to_date'));
        }

        // Search by invoice number or title
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'ilike', "%{$search}%")
                  ->orWhere('title', 'ilike', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $invoices = $query->paginate($request->input('per_page', 20));

        return response()->json($invoices);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'nullable|exists:invoice_templates,id',
            'contact_id' => 'required|exists:contacts,id',
            'project_id' => 'nullable|exists:projects,id',
            'contract_id' => 'nullable|exists:contracts,id',
            'title' => 'nullable|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'payment_terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.task_id' => 'nullable|exists:tasks,id',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        // Get template if provided
        $template = null;
        if (!empty($validated['template_id'])) {
            $template = InvoiceTemplate::where('company_id', $request->user()->company_id)
                ->findOrFail($validated['template_id']);
        }

        // Verify contact belongs to company
        Contact::where('company_id', $request->user()->company_id)
            ->findOrFail($validated['contact_id']);

        $company = $request->user()->company;

        // Generate invoice number
        $prefix = $company->settings['documents']['invoice_number_prefix'] ?? 'INV';
        $number = $prefix . '-' . date('Y') . '-' . str_pad(
            Invoice::where('company_id', $company->id)->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );

        // Calculate amounts
        $subtotal = 0;
        foreach ($validated['line_items'] as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }

        $taxRate = $validated['tax_rate'] ?? ($template?->default_tax_rate ?? 0);
        $discountRate = $validated['discount_rate'] ?? 0;

        $taxAmount = $subtotal * ($taxRate / 100);
        $discountAmount = $subtotal * ($discountRate / 100);
        $total = $subtotal + $taxAmount - $discountAmount;

        // Create invoice
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'template_id' => $validated['template_id'] ?? null,
            'contact_id' => $validated['contact_id'],
            'project_id' => $validated['project_id'] ?? null,
            'contract_id' => $validated['contract_id'] ?? null,
            'invoice_number' => $number,
            'title' => $validated['title'] ?? null,
            'issue_date' => $validated['issue_date'],
            'due_date' => $validated['due_date'],
            'status' => 'draft',
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'discount_rate' => $discountRate,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'amount_due' => $total,
            'payment_terms' => $validated['payment_terms'] ?? $template?->default_terms,
            'notes' => $validated['notes'] ?? $template?->default_notes,
            'token' => Str::random(64),
        ]);

        // Create line items
        foreach ($validated['line_items'] as $index => $item) {
            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => $item['quantity'] * $item['unit_price'],
                'task_id' => $item['task_id'] ?? null,
                'order' => $index,
            ]);
        }

        // Create event
        InvoiceEvent::create([
            'invoice_id' => $invoice->id,
            'event_type' => 'created',
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $invoice->load(['contact:id,full_name,email', 'project:id,name', 'lineItems']);

        return response()->json([
            'message' => 'Invoice created successfully',
            'data' => $invoice,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->with(['contact', 'project', 'contract', 'template', 'lineItems', 'payments'])
            ->findOrFail($id);

        return response()->json(['data' => $invoice]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        // Cannot update paid invoices
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return response()->json([
                'message' => 'Cannot update a paid or cancelled invoice.',
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|nullable|string|max:255',
            'due_date' => 'sometimes|required|date',
            'payment_terms' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $invoice->update($validated);

        $invoice->load(['contact:id,full_name,email', 'project:id,name', 'lineItems']);

        return response()->json([
            'message' => 'Invoice updated successfully',
            'data' => $invoice,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        // Cannot delete paid invoices
        if ($invoice->status === 'paid' || $invoice->amount_paid > 0) {
            return response()->json([
                'message' => 'Cannot delete a paid invoice or invoice with payments.',
            ], 422);
        }

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }

    /**
     * Add a line item to the invoice.
     */
    public function addLineItem(Request $request, string $id)
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Cannot add line items to a non-draft invoice.',
            ], 422);
        }

        $validated = $request->validate([
            'description' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'task_id' => 'nullable|exists:tasks,id',
        ]);

        $lineItem = InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'description' => $validated['description'],
            'quantity' => $validated['quantity'],
            'unit_price' => $validated['unit_price'],
            'amount' => $validated['quantity'] * $validated['unit_price'],
            'task_id' => $validated['task_id'] ?? null,
            'order' => $invoice->lineItems()->count(),
        ]);

        // Recalculate totals
        $this->recalculateInvoice($invoice);

        return response()->json([
            'message' => 'Line item added successfully',
            'data' => $lineItem,
        ], 201);
    }

    /**
     * Update a line item.
     */
    public function updateLineItem(Request $request, string $id, string $lineItemId)
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Cannot update line items of a non-draft invoice.',
            ], 422);
        }

        $lineItem = InvoiceLineItem::where('invoice_id', $invoice->id)
            ->findOrFail($lineItemId);

        $validated = $request->validate([
            'description' => 'sometimes|required|string',
            'quantity' => 'sometimes|required|numeric|min:0',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'task_id' => 'nullable|exists:tasks,id',
        ]);

        $lineItem->update($validated);

        if (isset($validated['quantity']) || isset($validated['unit_price'])) {
            $lineItem->update([
                'amount' => $lineItem->quantity * $lineItem->unit_price,
            ]);
        }

        // Recalculate totals
        $this->recalculateInvoice($invoice);

        return response()->json([
            'message' => 'Line item updated successfully',
            'data' => $lineItem,
        ]);
    }

    /**
     * Remove a line item.
     */
    public function removeLineItem(Request $request, string $id, string $lineItemId)
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Cannot remove line items from a non-draft invoice.',
            ], 422);
        }

        $lineItem = InvoiceLineItem::where('invoice_id', $invoice->id)
            ->findOrFail($lineItemId);

        $lineItem->delete();

        // Recalculate totals
        $this->recalculateInvoice($invoice);

        return response()->json([
            'message' => 'Line item removed successfully',
        ]);
    }

    /**
     * Send invoice to client.
     */
    public function send(Request $request, string $id)
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->with(['contact'])
            ->findOrFail($id);

        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft invoices can be sent.',
            ], 422);
        }

        // Update status
        $invoice->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by' => $request->user()->id,
        ]);

        // Create event
        InvoiceEvent::create([
            'invoice_id' => $invoice->id,
            'event_type' => 'sent',
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Queue email and PDF generation
        SendInvoiceEmail::dispatch($invoice);
        GenerateInvoicePdf::dispatch($invoice);

        return response()->json([
            'message' => 'Invoice sent successfully',
            'data' => $invoice,
        ]);
    }

    /**
     * Generate PDF for invoice.
     */
    public function generatePdf(Request $request, string $id)
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        GenerateInvoicePdf::dispatch($invoice);

        return response()->json([
            'message' => 'PDF generation queued',
        ]);
    }

    /**
     * Get invoice events (audit trail).
     */
    public function events(Request $request, string $id)
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $events = InvoiceEvent::where('invoice_id', $invoice->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json($events);
    }

    /**
     * Recalculate invoice totals.
     */
    private function recalculateInvoice(Invoice $invoice): void
    {
        $subtotal = $invoice->lineItems()->sum('amount');
        
        $taxAmount = $subtotal * ($invoice->tax_rate / 100);
        $discountAmount = $subtotal * ($invoice->discount_rate / 100);
        $total = $subtotal + $taxAmount - $discountAmount;

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'amount_due' => $total - $invoice->amount_paid,
        ]);
    }
}

