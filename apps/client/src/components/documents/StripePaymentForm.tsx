import { useState } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import {
  Elements,
  PaymentElement,
  useStripe,
  useElements,
} from '@stripe/react-stripe-js';

interface StripePaymentFormProps {
  clientSecret: string;
  publishableKey: string;
  amount: number;
  currency: string;
  onSuccess: () => void;
  onError: (error: string) => void;
}

function PaymentForm({
  amount,
  currency,
  onSuccess,
  onError,
}: Omit<StripePaymentFormProps, 'clientSecret' | 'publishableKey'>) {
  const stripe = useStripe();
  const elements = useElements();
  const [isProcessing, setIsProcessing] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!stripe || !elements) {
      return;
    }

    setIsProcessing(true);

    try {
      const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
          return_url: window.location.href,
        },
        redirect: 'if_required',
      });

      if (error) {
        onError(error.message || 'Payment failed');
      } else {
        onSuccess();
      }
    } catch (err: any) {
      onError(err.message || 'An error occurred');
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="card bg-base-200">
        <div className="card-body p-4">
          <div className="flex justify-between items-center mb-4">
            <span className="text-base-content/70">Amount Due:</span>
            <span className="text-2xl font-bold">
              ${amount.toFixed(2)} {currency}
            </span>
          </div>

          <PaymentElement />
        </div>
      </div>

      <button
        type="submit"
        disabled={!stripe || isProcessing}
        className="btn btn-primary w-full"
      >
        {isProcessing ? (
          <>
            <span className="loading loading-spinner"></span>
            Processing...
          </>
        ) : (
          `Pay $${amount.toFixed(2)}`
        )}
      </button>

      <div className="text-xs text-center text-base-content/60">
        Your payment is secured by Stripe. We never store your card details.
      </div>
    </form>
  );
}

export function StripePaymentForm({
  clientSecret,
  publishableKey,
  amount,
  currency,
  onSuccess,
  onError,
}: StripePaymentFormProps) {
  const [stripePromise] = useState(() => loadStripe(publishableKey));

  const options = {
    clientSecret,
    appearance: {
      theme: 'night' as const,
      variables: {
        colorPrimary: '#fbbf24',
        colorBackground: '#1f2937',
        colorText: '#f3f4f6',
        colorDanger: '#ef4444',
        fontFamily: 'Poppins, system-ui, sans-serif',
        spacingUnit: '4px',
        borderRadius: '8px',
      },
    },
  };

  return (
    <Elements stripe={stripePromise} options={options}>
      <PaymentForm
        amount={amount}
        currency={currency}
        onSuccess={onSuccess}
        onError={onError}
      />
    </Elements>
  );
}

