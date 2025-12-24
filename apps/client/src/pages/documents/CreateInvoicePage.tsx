import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import Layout from '../../components/Layout';
import { InvoiceLineItemForm } from '../../components/documents/InvoiceLineItemForm';
import type { InvoiceLineItem } from '../../types/documents';

export default function CreateInvoicePage() {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    invoice_number: '',
    title: '',
    contact_id: '',
    project_id: '',
    template_id: '',
    issue_date: new Date().toISOString().split('T')[0],
    due_date: '',
    tax_rate: 10,
    discount_rate: 0,
    payment_terms: 'Payment due within 30 days',
    notes: '',
  });
  const [lineItems, setLineItems] = useState<Partial<InvoiceLineItem>[]>([]);

  return (
    <Layout>
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <button
          onClick={() => navigate('/documents')}
          className="btn btn-ghost btn-sm"
        >
          <ArrowLeftIcon className="w-4 h-4" />
          Back
        </button>
        <div>
          <h1 className="text-3xl font-semibold">Create Invoice</h1>
          <p className="text-base-content/70 mt-1">
            Create a new invoice for your client
          </p>
        </div>
      </div>

      {/* Form */}
      <div className="max-w-5xl">
        <div className="space-y-6">
          {/* Basic Info Card */}
          <div className="card bg-base-200">
            <div className="card-body p-6">
              <h2 className="text-xl font-semibold mb-4">Invoice Details</h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Invoice Number */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">Invoice Number</span>
                  </label>
                  <input
                    type="text"
                    placeholder="INV-001"
                    className="input input-bordered"
                    value={formData.invoice_number}
                    onChange={(e) =>
                      setFormData({ ...formData, invoice_number: e.target.value })
                    }
                  />
                </div>

                {/* Title */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">
                      Title (Optional)
                    </span>
                  </label>
                  <input
                    type="text"
                    placeholder="Website Development"
                    className="input input-bordered"
                    value={formData.title}
                    onChange={(e) =>
                      setFormData({ ...formData, title: e.target.value })
                    }
                  />
                </div>

                {/* Client Selection */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">Client</span>
                  </label>
                  <select
                    className="select select-bordered"
                    value={formData.contact_id}
                    onChange={(e) =>
                      setFormData({ ...formData, contact_id: e.target.value })
                    }
                  >
                    <option value="">Select a client...</option>
                    {/* TODO: Load contacts from API */}
                    <option value="contact-1">John Doe</option>
                    <option value="contact-2">Jane Smith</option>
                  </select>
                </div>

                {/* Project Selection */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">
                      Project (Optional)
                    </span>
                  </label>
                  <select
                    className="select select-bordered"
                    value={formData.project_id}
                    onChange={(e) =>
                      setFormData({ ...formData, project_id: e.target.value })
                    }
                  >
                    <option value="">Select a project...</option>
                    {/* TODO: Load projects from API */}
                    <option value="project-1">Website Redesign</option>
                    <option value="project-2">Mobile App</option>
                  </select>
                </div>

                {/* Issue Date */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">Issue Date</span>
                  </label>
                  <input
                    type="date"
                    className="input input-bordered"
                    value={formData.issue_date}
                    onChange={(e) =>
                      setFormData({ ...formData, issue_date: e.target.value })
                    }
                  />
                </div>

                {/* Due Date */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">Due Date</span>
                  </label>
                  <input
                    type="date"
                    className="input input-bordered"
                    value={formData.due_date}
                    onChange={(e) =>
                      setFormData({ ...formData, due_date: e.target.value })
                    }
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Line Items Card */}
          <div className="card bg-base-200">
            <div className="card-body p-6">
              <h2 className="text-xl font-semibold mb-4">Line Items</h2>
              <InvoiceLineItemForm
                lineItems={lineItems}
                onChange={setLineItems}
                currency="USD"
              />
            </div>
          </div>

          {/* Tax & Discount Card */}
          <div className="card bg-base-200">
            <div className="card-body p-6">
              <h2 className="text-xl font-semibold mb-4">Tax & Discount</h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Tax Rate */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">Tax Rate (%)</span>
                  </label>
                  <input
                    type="number"
                    placeholder="10"
                    className="input input-bordered"
                    min="0"
                    max="100"
                    step="0.01"
                    value={formData.tax_rate}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        tax_rate: parseFloat(e.target.value) || 0,
                      })
                    }
                  />
                </div>

                {/* Discount Rate */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">Discount (%)</span>
                  </label>
                  <input
                    type="number"
                    placeholder="0"
                    className="input input-bordered"
                    min="0"
                    max="100"
                    step="0.01"
                    value={formData.discount_rate}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        discount_rate: parseFloat(e.target.value) || 0,
                      })
                    }
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Terms & Notes Card */}
          <div className="card bg-base-200">
            <div className="card-body p-6">
              <h2 className="text-xl font-semibold mb-4">Additional Information</h2>
              <div className="space-y-4">
                {/* Payment Terms */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">Payment Terms</span>
                  </label>
                  <textarea
                    className="textarea textarea-bordered"
                    rows={2}
                    value={formData.payment_terms}
                    onChange={(e) =>
                      setFormData({ ...formData, payment_terms: e.target.value })
                    }
                  />
                </div>

                {/* Notes */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-semibold">Notes (Optional)</span>
                  </label>
                  <textarea
                    className="textarea textarea-bordered"
                    rows={3}
                    placeholder="Thank you for your business!"
                    value={formData.notes}
                    onChange={(e) =>
                      setFormData({ ...formData, notes: e.target.value })
                    }
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Coming Soon Notice */}
          <div className="alert alert-info">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              className="stroke-current shrink-0 w-6 h-6"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              ></path>
            </svg>
            <span>
              Full invoice creation with API integration is coming soon! For now, this
              is a preview of the creation flow.
            </span>
          </div>

          {/* Actions */}
          <div className="flex justify-end gap-3">
            <button
              onClick={() => navigate('/documents/invoices')}
              className="btn btn-ghost"
            >
              Cancel
            </button>
            <button className="btn btn-outline" disabled>
              Save as Draft
            </button>
            <button className="btn btn-primary" disabled>
              Create & Send
            </button>
          </div>
        </div>
      </div>
    </div>
    </Layout>
  );
}

