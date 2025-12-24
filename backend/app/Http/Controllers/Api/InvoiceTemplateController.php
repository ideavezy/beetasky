<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvoiceTemplate;
use Illuminate\Http\Request;

class InvoiceTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = InvoiceTemplate::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['creator:id,name,email']);

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search by name or description
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $templates = $query->paginate($request->input('per_page', 20));

        return response()->json($templates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'layout' => 'nullable|array',
            'default_terms' => 'nullable|string',
            'default_notes' => 'nullable|string',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
            'default_tax_label' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        // Ensure boolean fields are proper booleans for PostgreSQL
        if (isset($validated['is_default'])) {
            $validated['is_default'] = (bool) $validated['is_default'];
        }
        if (isset($validated['is_active'])) {
            $validated['is_active'] = (bool) $validated['is_active'];
        }

        // If setting as default, remove default flag from other templates
        if (!empty($validated['is_default'])) {
            InvoiceTemplate::where('company_id', $request->user()->company_id)
                ->update(['is_default' => false]);
        }

        $template = InvoiceTemplate::create([
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            ...$validated,
        ]);

        $template->load(['creator:id,name,email']);

        return response()->json([
            'message' => 'Invoice template created successfully',
            'data' => $template,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $template = InvoiceTemplate::where('company_id', $request->user()->company_id)
            ->with(['creator:id,name,email', 'invoices' => function ($query) {
                $query->select('id', 'template_id', 'invoice_number', 'status', 'total', 'created_at')
                      ->latest()
                      ->limit(5);
            }])
            ->findOrFail($id);

        return response()->json(['data' => $template]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $template = InvoiceTemplate::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'layout' => 'nullable|array',
            'default_terms' => 'nullable|string',
            'default_notes' => 'nullable|string',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
            'default_tax_label' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        // Ensure boolean fields are proper booleans for PostgreSQL
        if (isset($validated['is_default'])) {
            $validated['is_default'] = (bool) $validated['is_default'];
        }
        if (isset($validated['is_active'])) {
            $validated['is_active'] = (bool) $validated['is_active'];
        }

        // If setting as default, remove default flag from other templates
        if (!empty($validated['is_default'])) {
            InvoiceTemplate::where('company_id', $request->user()->company_id)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $template->update($validated);

        $template->load(['creator:id,name,email']);

        return response()->json([
            'message' => 'Invoice template updated successfully',
            'data' => $template,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $template = InvoiceTemplate::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        // Check if template is being used
        $invoiceCount = $template->invoices()->count();
        if ($invoiceCount > 0) {
            return response()->json([
                'message' => "Cannot delete template. It is being used by {$invoiceCount} invoice(s).",
            ], 422);
        }

        $template->delete();

        return response()->json([
            'message' => 'Invoice template deleted successfully',
        ]);
    }

    /**
     * Duplicate an existing template.
     */
    public function duplicate(Request $request, string $id)
    {
        $template = InvoiceTemplate::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $duplicate = $template->replicate();
        $duplicate->name = $template->name . ' (Copy)';
        $duplicate->is_default = false;
        $duplicate->created_by = $request->user()->id;
        $duplicate->save();

        $duplicate->load(['creator:id,name,email']);

        return response()->json([
            'message' => 'Invoice template duplicated successfully',
            'data' => $duplicate,
        ], 201);
    }
}


