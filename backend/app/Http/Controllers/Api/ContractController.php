<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateContractPdf;
use App\Jobs\SendContractEmail;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractTemplate;
use App\Models\Contact;
use App\Models\Project;
use App\Services\MergeFieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    protected MergeFieldService $mergeFieldService;

    public function __construct(MergeFieldService $mergeFieldService)
    {
        $this->mergeFieldService = $mergeFieldService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Contract::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['contact:id,full_name,email', 'project:id,name', 'template:id,name']);

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

        // Search by title or contract number
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('contract_number', 'ilike', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $contracts = $query->paginate($request->input('per_page', 20));

        return response()->json($contracts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:contract_templates,id',
            'contact_id' => 'required|exists:contacts,id',
            'project_id' => 'nullable|exists:projects,id',
            'title' => 'required|string|max:255',
            'contract_type' => 'required|in:fixed_price,milestone,subscription',
            'pricing_data' => 'required|array',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Get template
        $template = ContractTemplate::where('company_id', $request->user()->company_id)
            ->findOrFail($validated['template_id']);

        // Get contact and project
        $contact = Contact::where('company_id', $request->user()->company_id)
            ->findOrFail($validated['contact_id']);

        $project = null;
        if (!empty($validated['project_id'])) {
            $project = Project::where('company_id', $request->user()->company_id)
                ->findOrFail($validated['project_id']);
        }

        // Extract merge field values
        $company = $request->user()->company;
        $mergeValues = $this->mergeFieldService->extractValues($contact, $project, $company);

        // Replace merge fields in template sections
        $renderedSections = $this->mergeFieldService->replaceSections($template->sections, $mergeValues);

        // Generate contract number
        $prefix = $company->settings['documents']['contract_number_prefix'] ?? 'CNT';
        $number = $prefix . '-' . date('Y') . '-' . str_pad(
            Contract::where('company_id', $company->id)->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );

        // Create contract
        $contract = Contract::create([
            'company_id' => $company->id,
            'template_id' => $template->id,
            'contact_id' => $contact->id,
            'project_id' => $project?->id,
            'title' => $validated['title'],
            'contract_number' => $number,
            'contract_type' => $validated['contract_type'],
            'pricing_data' => $validated['pricing_data'],
            'rendered_sections' => $renderedSections,
            'merge_field_values' => $mergeValues,
            'clickwrap_text' => $template->clickwrap_text,
            'status' => 'draft',
            'token' => Str::random(64),
            'expires_at' => $validated['expires_at'] ?? now()->addDays($company->settings['documents']['contract_auto_expire_days'] ?? 30),
            'notes' => $validated['notes'] ?? null,
        ]);

        // Create event
        ContractEvent::create([
            'contract_id' => $contract->id,
            'event_type' => 'created',
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $contract->load(['contact:id,full_name,email', 'project:id,name', 'template:id,name']);

        return response()->json([
            'message' => 'Contract created successfully',
            'data' => $contract,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $contract = Contract::where('company_id', $request->user()->company_id)
            ->with(['contact', 'project', 'template', 'providerSigner:id,name,email'])
            ->findOrFail($id);

        return response()->json(['data' => $contract]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $contract = Contract::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        // Cannot update signed contracts
        if (in_array($contract->status, ['signed', 'declined'])) {
            return response()->json([
                'message' => 'Cannot update a contract that has been signed or declined.',
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'pricing_data' => 'sometimes|required|array',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $contract->update($validated);

        $contract->load(['contact:id,full_name,email', 'project:id,name', 'template:id,name']);

        return response()->json([
            'message' => 'Contract updated successfully',
            'data' => $contract,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $contract = Contract::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        // Cannot delete signed contracts
        if ($contract->status === 'signed') {
            return response()->json([
                'message' => 'Cannot delete a signed contract.',
            ], 422);
        }

        $contract->delete();

        return response()->json([
            'message' => 'Contract deleted successfully',
        ]);
    }

    /**
     * Send contract to client.
     */
    public function send(Request $request, string $id)
    {
        $contract = Contract::where('company_id', $request->user()->company_id)
            ->with(['contact'])
            ->findOrFail($id);

        if ($contract->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft contracts can be sent.',
            ], 422);
        }

        // Update status
        $contract->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by' => $request->user()->id,
        ]);

        // Create event
        ContractEvent::create([
            'contract_id' => $contract->id,
            'event_type' => 'sent',
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Queue email and PDF generation
        SendContractEmail::dispatch($contract);
        GenerateContractPdf::dispatch($contract);

        return response()->json([
            'message' => 'Contract sent successfully',
            'data' => $contract,
        ]);
    }

    /**
     * Generate PDF for contract.
     */
    public function generatePdf(Request $request, string $id)
    {
        $contract = Contract::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        GenerateContractPdf::dispatch($contract);

        return response()->json([
            'message' => 'PDF generation queued',
        ]);
    }

    /**
     * Get contract events (audit trail).
     */
    public function events(Request $request, string $id)
    {
        $contract = Contract::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $events = ContractEvent::where('contract_id', $contract->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json($events);
    }
}
