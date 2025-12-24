import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ChevronLeft, Send, FileDown, Eye, Clock, CheckCircle, CreditCard } from 'lucide-react';
import Layout from '../../components/Layout';
import { useInvoiceStore } from '../../stores/invoices';
import InvoiceStatusBadge from '../../components/documents/InvoiceStatusBadge';

export default function InvoiceDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { fetchInvoiceById, sendInvoice, isLoading } = useInvoiceStore();

  const [invoice, setInvoice] = useState<any>(null);
  const [events, setEvents] = useState<any[]>([]);
  const [payments, setPayments] = useState<any[]>([]);

  useEffect(() => {
    if (id) {
      loadInvoice();
      loadEvents();
    }
  }, [id]);

  const loadInvoice = async () => {
    if (!id) return;
    try {
      const data = await fetchInvoiceById(id);
      setInvoice(data);
      setPayments(data.payments || []);
    } catch (error) {
      console.error('Failed to load invoice:', error);
    }
  };

  const loadEvents = async () => {
    if (!id) return;
    try {
      // TODO: Implement fetchInvoiceEvents in store
      // const data = await fetchInvoiceEvents(id);
      // setEvents(data);
      
      // Mock events for now
      setEvents([
        {
          id: '1',
          event_type: 'created',
          created_at: new Date().toISOString(),
          actor_type: 'user',
        },
      ]);
    } catch (error) {
      console.error('Failed to load events:', error);
    }
  };

  const handleSendInvoice = async () => {
    if (!id) return;
    try {
      await sendInvoice(id);
      loadInvoice();
      loadEvents();
    } catch (error) {
      console.error('Failed to send invoice:', error);
    }
  };

  const handleDownloadPdf = () => {
    if (!invoice?.pdf_path) {
      alert('PDF not available yet. Please generate it first.');
      return;
    }
    window.open(invoice.pdf_path, '_blank');
  };

  const getEventIcon = (eventType: string) => {
    switch (eventType) {
      case 'created':
        return <Clock className="w-5 h-5 text-info" />;
      case 'sent':
        return <Send className="w-5 h-5 text-primary" />;
      case 'viewed':
        return <Eye className="w-5 h-5 text-secondary" />;
      case 'paid':
      case 'partially_paid':
        return <CheckCircle className="w-5 h-5 text-success" />;
      case 'overdue':
        return <Clock className="w-5 h-5 text-error" />;
      default:
        return <Clock className="w-5 h-5 text-base-content" />;
    }
  };

  const formatEventType = (eventType: string) => {
    return eventType
      .split('_')
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  };

  if (isLoading || !invoice) {
    return (
      <Layout>
        <div className="flex items-center justify-center h-64">
          <span className="loading loading-spinner loading-lg text-primary"></span>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
    <div className="p-6">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-4">
          <button onClick={() => navigate('/documents/invoices')} className="btn btn-ghost btn-sm">
            <ChevronLeft className="w-5 h-5" /> Back
          </button>
          <div>
            <h1 className="text-3xl font-bold text-base-content">
              {invoice.title || `Invoice ${invoice.invoice_number}`}
            </h1>
            <p className="text-sm text-base-content/70 mt-1">{invoice.invoice_number}</p>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <InvoiceStatusBadge status={invoice.status} />
          {invoice.status === 'draft' && (
            <button onClick={handleSendInvoice} className="btn btn-primary btn-sm">
              <Send className="w-4 h-4" /> Send Invoice
            </button>
          )}
          {invoice.pdf_path && (
            <button onClick={handleDownloadPdf} className="btn btn-ghost btn-sm">
              <FileDown className="w-4 h-4" /> Download PDF
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Invoice Info */}
          <div className="card bg-base-200 shadow-xl p-6">
            <h2 className="text-xl font-semibold mb-4 text-base-content">Invoice Details</h2>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-base-content/70">Client</p>
                <p className="font-medium text-base-content">{invoice.contact?.full_name || 'N/A'}</p>
              </div>
              <div>
                <p className="text-sm text-base-content/70">Project</p>
                <p className="font-medium text-base-content">{invoice.project?.name || 'N/A'}</p>
              </div>
              <div>
                <p className="text-sm text-base-content/70">Issue Date</p>
                <p className="font-medium text-base-content">
                  {new Date(invoice.issue_date).toLocaleDateString()}
                </p>
              </div>
              <div>
                <p className="text-sm text-base-content/70">Due Date</p>
                <p className="font-medium text-base-content">
                  {new Date(invoice.due_date).toLocaleDateString()}
                </p>
              </div>
            </div>
          </div>

          {/* Line Items */}
          <div className="card bg-base-200 shadow-xl p-6">
            <h2 className="text-xl font-semibold mb-4 text-base-content">Line Items</h2>
            <div className="overflow-x-auto">
              <table className="table w-full">
                <thead>
                  <tr>
                    <th className="text-base-content">Description</th>
                    <th className="text-base-content text-right">Qty</th>
                    <th className="text-base-content text-right">Unit Price</th>
                    <th className="text-base-content text-right">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {(invoice.lineItems || []).map((item: any) => (
                    <tr key={item.id}>
                      <td className="text-base-content">{item.description}</td>
                      <td className="text-base-content text-right">{item.quantity}</td>
                      <td className="text-base-content text-right">
                        ${parseFloat(item.unit_price).toFixed(2)}
                      </td>
                      <td className="text-base-content text-right">
                        ${parseFloat(item.amount).toFixed(2)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Totals */}
            <div className="mt-6 pt-4 border-t border-base-300 space-y-2">
              <div className="flex justify-between text-base-content">
                <span>Subtotal</span>
                <span className="font-medium">${parseFloat(invoice.subtotal).toFixed(2)}</span>
              </div>
              {invoice.tax_rate > 0 && (
                <div className="flex justify-between text-base-content">
                  <span>Tax ({invoice.tax_rate}%)</span>
                  <span className="font-medium">${parseFloat(invoice.tax_amount).toFixed(2)}</span>
                </div>
              )}
              {invoice.discount_rate > 0 && (
                <div className="flex justify-between text-base-content">
                  <span>Discount ({invoice.discount_rate}%)</span>
                  <span className="font-medium text-error">
                    -${parseFloat(invoice.discount_amount).toFixed(2)}
                  </span>
                </div>
              )}
              <div className="flex justify-between text-lg font-bold text-base-content pt-2 border-t border-base-300">
                <span>Total</span>
                <span>${parseFloat(invoice.total).toFixed(2)}</span>
              </div>
              {invoice.amount_paid > 0 && (
                <>
                  <div className="flex justify-between text-success">
                    <span>Amount Paid</span>
                    <span className="font-medium">${parseFloat(invoice.amount_paid).toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between text-lg font-bold text-primary">
                    <span>Amount Due</span>
                    <span>${parseFloat(invoice.amount_due).toFixed(2)}</span>
                  </div>
                </>
              )}
            </div>
          </div>

          {/* Payment Terms & Notes */}
          {(invoice.payment_terms || invoice.notes) && (
            <div className="card bg-base-200 shadow-xl p-6">
              {invoice.payment_terms && (
                <div className="mb-4">
                  <h3 className="text-lg font-semibold mb-2 text-base-content">Payment Terms</h3>
                  <p className="text-base-content/80">{invoice.payment_terms}</p>
                </div>
              )}
              {invoice.notes && (
                <div>
                  <h3 className="text-lg font-semibold mb-2 text-base-content">Notes</h3>
                  <p className="text-base-content/80">{invoice.notes}</p>
                </div>
              )}
            </div>
          )}

          {/* Public Link */}
          {invoice.token && invoice.status !== 'draft' && (
            <div className="card bg-base-200 shadow-xl p-6">
              <h2 className="text-xl font-semibold mb-4 text-base-content">Public Payment Link</h2>
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  value={`${window.location.origin}/public/invoices/${invoice.token}`}
                  readOnly
                  className="input input-bordered flex-1 text-sm"
                />
                <button
                  onClick={() => {
                    navigator.clipboard.writeText(
                      `${window.location.origin}/public/invoices/${invoice.token}`
                    );
                    alert('Link copied to clipboard!');
                  }}
                  className="btn btn-secondary btn-sm"
                >
                  Copy
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Payments */}
          {payments.length > 0 && (
            <div className="card bg-base-200 shadow-xl p-6">
              <h2 className="text-xl font-semibold mb-4 text-base-content">
                <CreditCard className="w-5 h-5 inline mr-2" />
                Payments
              </h2>
              <div className="space-y-3">
                {payments.map((payment: any) => (
                  <div key={payment.id} className="p-3 bg-base-300 rounded-lg">
                    <div className="flex justify-between items-start mb-1">
                      <span className="font-medium text-base-content">
                        ${parseFloat(payment.amount).toFixed(2)}
                      </span>
                      <span
                        className={`badge badge-sm ${
                          payment.status === 'succeeded'
                            ? 'badge-success'
                            : payment.status === 'failed'
                            ? 'badge-error'
                            : 'badge-warning'
                        }`}
                      >
                        {payment.status}
                      </span>
                    </div>
                    <p className="text-xs text-base-content/70">
                      {payment.processed_at
                        ? new Date(payment.processed_at).toLocaleString()
                        : 'Processing...'}
                    </p>
                    {payment.receipt_url && (
                      <a
                        href={payment.receipt_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-xs text-primary hover:underline mt-1 block"
                      >
                        View Receipt
                      </a>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Activity Timeline */}
          <div className="card bg-base-200 shadow-xl p-6">
            <h2 className="text-xl font-semibold mb-4 text-base-content">Activity Timeline</h2>
            <div className="space-y-4">
              {events.length === 0 ? (
                <p className="text-sm text-base-content/70">No events yet</p>
              ) : (
                events.map((event) => (
                  <div key={event.id} className="flex items-start gap-3">
                    <div className="mt-0.5">{getEventIcon(event.event_type)}</div>
                    <div className="flex-1">
                      <p className="font-medium text-base-content">{formatEventType(event.event_type)}</p>
                      <p className="text-sm text-base-content/70">
                        {new Date(event.created_at).toLocaleString()}
                      </p>
                      {event.actor_type === 'user' && (
                        <p className="text-xs text-base-content/50">By internal user</p>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
    </Layout>
  );
}
