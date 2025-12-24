<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripePaymentService
{
    /**
     * Create a PaymentIntent for an invoice.
     */
    public function createPaymentIntent(Invoice $invoice, Company $company): array
    {
        $this->setStripeKey($company);

        $paymentIntent = PaymentIntent::create([
            'amount' => $this->convertToStripeAmount($invoice->amount_due),
            'currency' => strtolower($invoice->currency),
            'metadata' => [
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
                'invoice_number' => $invoice->invoice_number,
            ],
            'description' => "Invoice {$invoice->invoice_number} - {$company->name}",
        ]);

        // Update invoice with payment intent ID
        $invoice->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
        ]);

        return [
            'client_secret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
        ];
    }

    /**
     * Handle Stripe webhook events.
     */
    public function handleWebhook(string $payload, string $signature, Company $company): array
    {
        $webhookSecret = $this->getWebhookSecret($company);

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (\Exception $e) {
            throw new \Exception('Webhook signature verification failed: ' . $e->getMessage());
        }

        $result = [
            'handled' => false,
            'event_type' => $event->type,
        ];

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object, $company);
                $result['handled'] = true;
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object, $company);
                $result['handled'] = true;
                break;

            case 'charge.refunded':
                $this->handleChargeRefunded($event->data->object, $company);
                $result['handled'] = true;
                break;

            default:
                $result['message'] = 'Unhandled event type';
        }

        return $result;
    }

    /**
     * Process a refund for a payment.
     */
    public function refundPayment(Payment $payment, Company $company, ?float $amount = null): Payment
    {
        $this->setStripeKey($company);

        $refundAmount = $amount ?? $payment->amount;

        try {
            \Stripe\Refund::create([
                'payment_intent' => $payment->stripe_payment_intent_id,
                'amount' => $this->convertToStripeAmount($refundAmount),
            ]);

            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_amount' => $refundAmount,
            ]);

            // Update invoice
            if ($payment->invoice) {
                $payment->invoice->amount_paid -= $refundAmount;
                $payment->invoice->amount_due += $refundAmount;
                
                if ($payment->invoice->amount_paid <= 0) {
                    $payment->invoice->status = 'sent';
                } else {
                    $payment->invoice->status = 'partially_paid';
                }
                
                $payment->invoice->save();
            }
        } catch (\Exception $e) {
            throw new \Exception('Refund failed: ' . $e->getMessage());
        }

        return $payment;
    }

    /**
     * Handle payment_intent.succeeded event.
     */
    private function handlePaymentIntentSucceeded($paymentIntent, Company $company): void
    {
        $invoice = Invoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$invoice) {
            \Log::warning('Invoice not found for payment intent', ['payment_intent_id' => $paymentIntent->id]);
            return;
        }

        // Create payment record
        $payment = Payment::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'amount' => $this->convertFromStripeAmount($paymentIntent->amount_received),
            'currency' => strtoupper($paymentIntent->currency),
            'payment_method' => 'stripe',
            'status' => 'succeeded',
            'stripe_payment_intent_id' => $paymentIntent->id,
            'stripe_charge_id' => $paymentIntent->charges->data[0]->id ?? null,
            'stripe_customer_id' => $paymentIntent->customer ?? null,
            'stripe_payment_method_id' => $paymentIntent->payment_method ?? null,
            'receipt_url' => $paymentIntent->charges->data[0]->receipt_url ?? null,
            'processed_at' => now(),
        ]);

        // Update invoice
        $invoice->amount_paid += $payment->amount;
        $invoice->amount_due = $invoice->total - $invoice->amount_paid;
        
        if ($invoice->amount_due <= 0) {
            $invoice->status = 'paid';
        } else {
            $invoice->status = 'partially_paid';
        }
        
        $invoice->save();

        // Create invoice event
        $invoice->events()->create([
            'event_type' => 'paid',
            'event_data' => [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ],
            'actor_type' => 'system',
        ]);
    }

    /**
     * Handle payment_intent.payment_failed event.
     */
    private function handlePaymentIntentFailed($paymentIntent, Company $company): void
    {
        $invoice = Invoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$invoice) {
            return;
        }

        // Create failed payment record
        Payment::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'amount' => $this->convertFromStripeAmount($paymentIntent->amount),
            'currency' => strtoupper($paymentIntent->currency),
            'payment_method' => 'stripe',
            'status' => 'failed',
            'stripe_payment_intent_id' => $paymentIntent->id,
            'failed_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
            'processed_at' => now(),
        ]);
    }

    /**
     * Handle charge.refunded event.
     */
    private function handleChargeRefunded($charge, Company $company): void
    {
        $payment = Payment::where('stripe_charge_id', $charge->id)->first();

        if (!$payment) {
            return;
        }

        $refundAmount = $this->convertFromStripeAmount($charge->amount_refunded);

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_amount' => $refundAmount,
        ]);

        // Update invoice
        if ($payment->invoice) {
            $payment->invoice->amount_paid -= $refundAmount;
            $payment->invoice->amount_due += $refundAmount;
            
            if ($payment->invoice->amount_paid <= 0) {
                $payment->invoice->status = 'sent';
            } else {
                $payment->invoice->status = 'partially_paid';
            }
            
            $payment->invoice->save();
        }
    }

    /**
     * Set Stripe API key from company settings.
     */
    private function setStripeKey(Company $company): void
    {
        $secretKey = $company->settings['stripe']['secret_key'] ?? null;

        if (!$secretKey) {
            throw new \Exception('Stripe secret key not configured for this company');
        }

        Stripe::setApiKey($secretKey);
    }

    /**
     * Get webhook secret from company settings.
     */
    private function getWebhookSecret(Company $company): string
    {
        $webhookSecret = $company->settings['stripe']['webhook_secret'] ?? null;

        if (!$webhookSecret) {
            throw new \Exception('Stripe webhook secret not configured for this company');
        }

        return $webhookSecret;
    }

    /**
     * Convert amount to Stripe format (cents).
     */
    private function convertToStripeAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert amount from Stripe format (cents).
     */
    private function convertFromStripeAmount(int $amount): float
    {
        return $amount / 100;
    }
}

