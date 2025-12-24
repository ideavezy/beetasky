import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { CheckCircleIcon, DocumentTextIcon } from '@heroicons/react/24/outline';
import { useInvoiceStore } from '../../stores/invoices';
import { InvoiceStatusBadge } from '../../components/documents/InvoiceStatusBadge';
import { StripePaymentForm } from '../../components/documents/StripePaymentForm';

export function PublicInvoicePage() {
  const { token } = useParams<{ token: string }>();
  const { selectedInvoice, isLoading, createPaymentIntent } = useInvoiceStore();
  const [paymentData, setPaymentData] = useState<{
    client_secret: string;
    publishable_key: string;
  } | null>(null);
  const [paid, setPaid] = useState(false);

  useEffect(() => {
    if (token) {
      // Fetch invoice by token (you'd need to add this to your store)
      // For now, using a placeholder
    }
  }, [token]);

  const handlePayNow = async () => {
    if (!token) return;

    try {
      const data = await createPaymentIntent(token);
      setPaymentData(data);
    } catch (error) {
      console.error('Failed to create payment intent:', error);
    }
  };

  const handlePaymentSuccess = () => {
    setPaid(true);
  };

  const handlePaymentError = (error: string) => {
    alert(error);
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100">
        <span className="loading loading-spinner loading-lg"></span>
      </div>
    );
  }

  if (paid) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100 p-6">
        <div className="card bg-base-200 max-w-2xl w-full">
          <div className="card-body items-center text-center">
            <CheckCircleIcon className="w-24 h-24 text-success" />
            <h1 className="text-3xl font-bold mt-6">Payment Successful!</h1>
            <p className="text-base-content/70 mt-4">
              Thank you for your payment. You will receive a receipt via email shortly.
            </p>
            <button
              onClick={() => window.print()}
              className="btn btn-outline mt-6"
            >
              Download Receipt
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (!selectedInvoice) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100 p-6">
        <div className="card bg-base-200 max-w-2xl w-full">
          <div className="card-body items-center text-center">
            <DocumentTextIcon className="w-24 h-24 text-base-content/30" />
            <h1 className="text-3xl font-bold mt-6">Invoice Not Found</h1>
            <p className="text-base-content/70 mt-4">
              The invoice you're looking for doesn't exist or has been removed.
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-base-100 p-6">
      <div className="max-w-4xl mx-auto space-y-6">
        <div className="card bg-base-200">
          <div className="card-body p-6">
            <div className="flex justify-between items-start">
              <div>
                <h1 className="text-3xl font-bold">Invoice {selectedInvoice.invoice_number}</h1>
                {selectedInvoice.title && (
                  <p className="text-base-content/70 mt-2">{selectedInvoice.title}</p>
                )}
              </div>
              <InvoiceStatusBadge status={selectedInvoice.status} />
            </div>

            <div className="grid grid-cols-2 gap-4 mt-6">
              <div>
                <div className="text-sm text-base-content/60">Issue Date</div>
                <div className="font-semibold">
                  {new Date(selectedInvoice.issue_date).toLocaleDateString()}
                </div>
              </div>
              <div>
                <div className="text-sm text-base-content/60">Due Date</div>
                <div className="font-semibold">
                  {new Date(selectedInvoice.due_date).toLocaleDateString()}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="card bg-base-200">
          <div className="card-body p-6">
            <h2 className="text-xl font-semibold mb-4">Line Items</h2>
            <div className="overflow-x-auto">
              <table className="table">
                <thead>
                  <tr>
                    <th>Description</th>
                    <th className="text-right">Quantity</th>
                    <th className="text-right">Unit Price</th>
                    <th className="text-right">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {selectedInvoice.line_items?.map((item) => (
                    <tr key={item.id}>
                      <td>{item.description}</td>
                      <td className="text-right">{item.quantity}</td>
                      <td className="text-right">${item.unit_price.toFixed(2)}</td>
                      <td className="text-right font-semibold">
                        ${item.amount.toFixed(2)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="mt-6 space-y-2">
              <div className="flex justify-between">
                <span>Subtotal:</span>
                <span>${selectedInvoice.subtotal.toFixed(2)}</span>
              </div>
              {selectedInvoice.discount_amount > 0 && (
                <div className="flex justify-between text-warning">
                  <span>Discount ({selectedInvoice.discount_rate}%):</span>
                  <span>-${selectedInvoice.discount_amount.toFixed(2)}</span>
                </div>
              )}
              {selectedInvoice.tax_amount > 0 && (
                <div className="flex justify-between">
                  <span>Tax ({selectedInvoice.tax_rate}%):</span>
                  <span>${selectedInvoice.tax_amount.toFixed(2)}</span>
                </div>
              )}
              <div className="flex justify-between text-2xl font-bold pt-4 border-t border-base-300">
                <span>Total:</span>
                <span>
                  ${selectedInvoice.total.toFixed(2)} {selectedInvoice.currency}
                </span>
              </div>
              {selectedInvoice.amount_paid > 0 && (
                <>
                  <div className="flex justify-between text-success">
                    <span>Amount Paid:</span>
                    <span>${selectedInvoice.amount_paid.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between text-xl font-bold">
                    <span>Amount Due:</span>
                    <span>${selectedInvoice.amount_due.toFixed(2)}</span>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>

        {selectedInvoice.status !== 'paid' && selectedInvoice.amount_due > 0 && (
          <div className="card bg-base-200">
            <div className="card-body p-6">
              <h2 className="text-2xl font-semibold mb-6">Payment</h2>

              {paymentData ? (
                <StripePaymentForm
                  clientSecret={paymentData.client_secret}
                  publishableKey={paymentData.publishable_key}
                  amount={selectedInvoice.amount_due}
                  currency={selectedInvoice.currency}
                  onSuccess={handlePaymentSuccess}
                  onError={handlePaymentError}
                />
              ) : (
                <button onClick={handlePayNow} className="btn btn-primary w-full">
                  Pay ${selectedInvoice.amount_due.toFixed(2)} Now
                </button>
              )}
            </div>
          </div>
        )}

        {selectedInvoice.payment_terms && (
          <div className="card bg-base-200">
            <div className="card-body p-6">
              <h3 className="text-lg font-semibold mb-2">Payment Terms</h3>
              <p className="text-base-content/70">{selectedInvoice.payment_terms}</p>
            </div>
          </div>
        )}

        {selectedInvoice.notes && (
          <div className="card bg-base-200">
            <div className="card-body p-6">
              <h3 className="text-lg font-semibold mb-2">Notes</h3>
              <p className="text-base-content/70">{selectedInvoice.notes}</p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

