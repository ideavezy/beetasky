<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\Payment;
use App\Services\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected StripePaymentService $stripeService;

    public function __construct(StripePaymentService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Payment::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['invoice:id,invoice_number,total,contact_id']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by invoice
        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->input('invoice_id'));
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('processed_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('processed_at', '<=', $request->input('to_date'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $payments = $query->paginate($request->input('per_page', 20));

        return response()->json($payments);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $payment = Payment::where('company_id', $request->user()->company_id)
            ->with(['invoice.contact'])
            ->findOrFail($id);

        return response()->json(['data' => $payment]);
    }

    /**
     * Handle Stripe webhook events.
     */
    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            // Note: In production, you'd verify the webhook signature here
            // using the company's webhook secret
            $event = json_decode($payload, true);

            Log::info('Stripe webhook received', [
                'type' => $event['type'] ?? 'unknown',
                'id' => $event['id'] ?? 'unknown',
            ]);

            // Handle different event types
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event['data']['object']);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event['data']['object']);
                    break;

                case 'charge.refunded':
                    $this->handleChargeRefunded($event['data']['object']);
                    break;

                default:
                    Log::info('Unhandled webhook event type', ['type' => $event['type']]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle successful payment intent.
     */
    private function handlePaymentIntentSucceeded(array $paymentIntent): void
    {
        $paymentIntentId = $paymentIntent['id'];

        // Find payment by stripe payment intent ID
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (!$payment) {
            Log::warning('Payment not found for payment intent', ['payment_intent_id' => $paymentIntentId]);
            return;
        }

        // Update payment status
        $payment->update([
            'status' => 'succeeded',
            'processed_at' => now(),
            'stripe_charge_id' => $paymentIntent['latest_charge'] ?? null,
            'receipt_url' => $paymentIntent['charges']['data'][0]['receipt_url'] ?? null,
        ]);

        // Update invoice
        $invoice = $payment->invoice;
        if ($invoice) {
            $invoice->amount_paid += $payment->amount;
            $invoice->amount_due = $invoice->total - $invoice->amount_paid;

            if ($invoice->amount_due <= 0) {
                $invoice->status = 'paid';
            } else {
                $invoice->status = 'partially_paid';
            }

            $invoice->save();

            // Create invoice event
            InvoiceEvent::create([
                'invoice_id' => $invoice->id,
                'event_type' => $invoice->status === 'paid' ? 'paid' : 'partially_paid',
                'event_data' => [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_intent_id' => $paymentIntentId,
                ],
                'actor_type' => 'system',
            ]);

            Log::info('Invoice payment processed', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'status' => $invoice->status,
            ]);
        }
    }

    /**
     * Handle failed payment intent.
     */
    private function handlePaymentIntentFailed(array $paymentIntent): void
    {
        $paymentIntentId = $paymentIntent['id'];

        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (!$payment) {
            Log::warning('Payment not found for failed payment intent', ['payment_intent_id' => $paymentIntentId]);
            return;
        }

        $payment->update([
            'status' => 'failed',
            'failed_reason' => $paymentIntent['last_payment_error']['message'] ?? 'Payment failed',
        ]);

        Log::info('Payment failed', [
            'payment_id' => $payment->id,
            'reason' => $payment->failed_reason,
        ]);
    }

    /**
     * Handle charge refund.
     */
    private function handleChargeRefunded(array $charge): void
    {
        $chargeId = $charge['id'];

        $payment = Payment::where('stripe_charge_id', $chargeId)->first();

        if (!$payment) {
            Log::warning('Payment not found for refunded charge', ['charge_id' => $chargeId]);
            return;
        }

        $refundAmount = $charge['amount_refunded'] / 100; // Convert from cents

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_amount' => $refundAmount,
        ]);

        // Update invoice
        $invoice = $payment->invoice;
        if ($invoice) {
            $invoice->amount_paid -= $refundAmount;
            $invoice->amount_due = $invoice->total - $invoice->amount_paid;

            if ($invoice->amount_due > 0 && $invoice->status === 'paid') {
                $invoice->status = 'partially_paid';
            }

            $invoice->save();

            // Create invoice event
            InvoiceEvent::create([
                'invoice_id' => $invoice->id,
                'event_type' => 'refunded',
                'event_data' => [
                    'payment_id' => $payment->id,
                    'amount' => $refundAmount,
                    'charge_id' => $chargeId,
                ],
                'actor_type' => 'system',
            ]);

            Log::info('Payment refunded', [
                'payment_id' => $payment->id,
                'refund_amount' => $refundAmount,
            ]);
        }
    }
}

