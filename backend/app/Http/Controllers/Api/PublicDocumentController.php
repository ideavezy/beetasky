<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Invoice;
use Illuminate\Http\Request;

class PublicDocumentController extends Controller
{
    /**
     * Show a contract by token (public access).
     */
    public function showContract(string $token)
    {
        $contract = Contract::with(['template', 'contact', 'project'])
            ->where('token', $token)
            ->firstOrFail();

        // Log view event
        $contract->events()->create([
            'event_type' => 'viewed',
            'event_data' => [
                'viewed_at' => now()->toISOString(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Update status to viewed if currently sent
        if ($contract->status === 'sent') {
            $contract->update(['status' => 'viewed']);
        }

        return response()->json([
            'data' => $contract,
        ]);
    }

    /**
     * Sign a contract (clickwrap).
     */
    public function signContract(Request $request, string $token)
    {
        $validated = $request->validate([
            'signed_by' => 'required|string|max:255',
        ]);

        $contract = Contract::where('token', $token)->firstOrFail();

        // Check if already signed
        if ($contract->status === 'signed') {
            return response()->json([
                'message' => 'Contract is already signed',
            ], 400);
        }

        // Check if expired
        if ($contract->expires_at && now()->isAfter($contract->expires_at)) {
            $contract->update(['status' => 'expired']);
            return response()->json([
                'message' => 'Contract has expired',
            ], 400);
        }

        // Sign the contract
        $contract->update([
            'status' => 'signed',
            'client_signed_at' => now(),
            'client_signed_by' => $validated['signed_by'],
            'client_ip_address' => $request->ip(),
        ]);

        // Log signed event
        $contract->events()->create([
            'event_type' => 'signed',
            'event_data' => [
                'signed_by' => $validated['signed_by'],
                'signed_at' => now()->toISOString(),
            ],
            'actor_type' => 'client',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Queue PDF generation and email notifications
        // TODO: Dispatch jobs when ready

        return response()->json([
            'data' => $contract->fresh(),
            'message' => 'Contract signed successfully',
        ]);
    }

    /**
     * Decline a contract.
     */
    public function declineContract(Request $request, string $token)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $contract = Contract::where('token', $token)->firstOrFail();

        $contract->update(['status' => 'declined']);

        // Log declined event
        $contract->events()->create([
            'event_type' => 'declined',
            'event_data' => [
                'reason' => $validated['reason'] ?? null,
                'declined_at' => now()->toISOString(),
            ],
            'actor_type' => 'client',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'data' => $contract->fresh(),
            'message' => 'Contract declined',
        ]);
    }

    /**
     * Show an invoice by token (public access).
     */
    public function showInvoice(string $token)
    {
        $invoice = Invoice::with(['template', 'contact', 'project', 'lineItems'])
            ->where('token', $token)
            ->firstOrFail();

        // Log view event
        $invoice->events()->create([
            'event_type' => 'viewed',
            'event_data' => [
                'viewed_at' => now()->toISOString(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Update status to viewed if currently sent
        if ($invoice->status === 'sent') {
            $invoice->update(['status' => 'viewed']);
        }

        return response()->json([
            'data' => $invoice,
        ]);
    }

    /**
     * Create Stripe Payment Intent for invoice payment.
     */
    public function createPaymentIntent(Request $request, string $token)
    {
        $invoice = Invoice::where('token', $token)->firstOrFail();

        // Check if already paid
        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'Invoice is already paid',
            ], 400);
        }

        // TODO: Create Stripe Payment Intent using StripePaymentService
        // For now, return mock data
        
        return response()->json([
            'data' => [
                'client_secret' => 'pi_mock_secret_' . uniqid(),
                'publishable_key' => config('services.stripe.publishable_key', 'pk_test_mock'),
            ],
        ]);
    }
}


