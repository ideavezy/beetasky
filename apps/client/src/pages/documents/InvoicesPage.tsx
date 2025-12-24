import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  DocumentTextIcon,
  PlusIcon,
  MagnifyingGlassIcon,
} from '@heroicons/react/24/outline';
import Layout from '../../components/Layout';
import { useInvoiceStore } from '../../stores/invoices';
import { InvoiceStatusBadge } from '../../components/documents/InvoiceStatusBadge';
import type { Invoice } from '../../types/documents';

export default function InvoicesPage() {
  const { invoices, isLoading, fetchInvoices } = useInvoiceStore();
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<Invoice['status'] | 'all'>('all');

  useEffect(() => {
    fetchInvoices();
  }, [fetchInvoices]);

  const filteredInvoices = (invoices || []).filter((invoice) => {
    const matchesSearch =
      invoice.invoice_number.toLowerCase().includes(searchTerm.toLowerCase()) ||
      invoice.title?.toLowerCase().includes(searchTerm.toLowerCase());

    const matchesStatus = statusFilter === 'all' || invoice.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  const totalOutstanding = (invoices || [])
    .filter((inv) => inv.status !== 'paid' && inv.status !== 'cancelled')
    .reduce((sum, inv) => sum + inv.amount_due, 0);

  const totalPaid = (invoices || [])
    .filter((inv) => inv.status === 'paid')
    .reduce((sum, inv) => sum + inv.total, 0);

  return (
    <Layout>
    <div className="p-6 space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-semibold">Invoices</h1>
          <p className="text-base-content/70 mt-1">
            Manage and track your invoices
          </p>
        </div>
        <Link to="/documents/invoices/create" className="btn btn-primary">
          <PlusIcon className="w-5 h-5" />
          New Invoice
        </Link>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="card bg-base-200">
          <div className="card-body p-4">
            <div className="text-sm text-base-content/60">Total Outstanding</div>
            <div className="text-2xl font-bold text-warning">
              ${totalOutstanding.toFixed(2)}
            </div>
          </div>
        </div>
        <div className="card bg-base-200">
          <div className="card-body p-4">
            <div className="text-sm text-base-content/60">Total Paid</div>
            <div className="text-2xl font-bold text-success">
              ${totalPaid.toFixed(2)}
            </div>
          </div>
        </div>
        <div className="card bg-base-200">
          <div className="card-body p-4">
            <div className="text-sm text-base-content/60">Total Invoices</div>
            <div className="text-2xl font-bold">{(invoices || []).length}</div>
          </div>
        </div>
      </div>

      <div className="card bg-base-200">
        <div className="card-body p-4">
          <div className="flex gap-4">
            <div className="flex-1 relative">
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/50" />
              <input
                type="text"
                placeholder="Search invoices..."
                className="input input-bordered w-full pl-10"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <select
              className="select select-bordered"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value as any)}
            >
              <option value="all">All Statuses</option>
              <option value="draft">Draft</option>
              <option value="sent">Sent</option>
              <option value="viewed">Viewed</option>
              <option value="partially_paid">Partially Paid</option>
              <option value="paid">Paid</option>
              <option value="overdue">Overdue</option>
            </select>
          </div>
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center items-center py-12">
          <span className="loading loading-spinner loading-lg"></span>
        </div>
      ) : filteredInvoices.length === 0 ? (
        <div className="card bg-base-200">
          <div className="card-body items-center text-center py-12">
            <DocumentTextIcon className="w-16 h-16 text-base-content/30" />
            <h3 className="text-xl font-semibold mt-4">No invoices found</h3>
            <p className="text-base-content/70 mt-2">
              {searchTerm || statusFilter !== 'all'
                ? 'Try adjusting your filters'
                : 'Get started by creating your first invoice'}
            </p>
            {!searchTerm && statusFilter === 'all' && (
              <Link to="/documents/invoices/create" className="btn btn-primary mt-4">
                <PlusIcon className="w-5 h-5" />
                Create Invoice
              </Link>
            )}
          </div>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Client</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th className="text-right">Amount</th>
                <th className="text-right">Amount Due</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {filteredInvoices.map((invoice) => (
                <tr key={invoice.id} className="hover">
                  <td>
                    <Link
                      to={`/documents/invoices/${invoice.id}`}
                      className="font-semibold hover:text-primary"
                    >
                      {invoice.invoice_number}
                    </Link>
                    {invoice.title && (
                      <div className="text-sm text-base-content/60">{invoice.title}</div>
                    )}
                  </td>
                  <td>
                    {invoice.contact ? (
                      <div>
                        <div>{invoice.contact.full_name}</div>
                        {invoice.project && (
                          <div className="text-sm text-base-content/60">
                            {invoice.project.name}
                          </div>
                        )}
                      </div>
                    ) : (
                      <span className="text-base-content/40">No client</span>
                    )}
                  </td>
                  <td>{new Date(invoice.issue_date).toLocaleDateString()}</td>
                  <td>
                    <div
                      className={
                        invoice.status === 'overdue'
                          ? 'text-error font-semibold'
                          : ''
                      }
                    >
                      {new Date(invoice.due_date).toLocaleDateString()}
                    </div>
                  </td>
                  <td className="text-right font-semibold">
                    ${invoice.total.toFixed(2)}
                  </td>
                  <td className="text-right">
                    {invoice.amount_due > 0 ? (
                      <span className="font-semibold">
                        ${invoice.amount_due.toFixed(2)}
                      </span>
                    ) : (
                      <span className="text-success">Paid</span>
                    )}
                  </td>
                  <td>
                    <InvoiceStatusBadge status={invoice.status} />
                  </td>
                  <td>
                    <Link
                      to={`/documents/invoices/${invoice.id}`}
                      className="btn btn-ghost btn-sm"
                    >
                      View
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
    </Layout>
  );
}

