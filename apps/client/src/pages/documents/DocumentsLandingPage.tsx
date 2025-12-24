import { useNavigate } from 'react-router-dom';
import {
  DocumentTextIcon,
  CurrencyDollarIcon,
  DocumentDuplicateIcon,
  PencilSquareIcon,
  PaperAirplaneIcon,
  CheckCircleIcon,
  ArrowRightIcon,
  SparklesIcon,
} from '@heroicons/react/24/outline';
import Layout from '../../components/Layout';

interface StepProps {
  number: number;
  title: string;
  description: string;
  icon: React.ComponentType<{ className?: string }>;
}

function Step({ number, title, description, icon: Icon }: StepProps) {
  return (
    <div className="flex gap-4 items-start">
      <div className="flex-shrink-0 w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center">
        <span className="text-primary font-semibold">{number}</span>
      </div>
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2 mb-1">
          <Icon className="w-5 h-5 text-primary" />
          <h4 className="font-medium">{title}</h4>
        </div>
        <p className="text-sm text-base-content/60">{description}</p>
      </div>
    </div>
  );
}

export default function DocumentsLandingPage() {
  const navigate = useNavigate();

  const contractSteps: StepProps[] = [
    {
      number: 1,
      title: 'Create a Template',
      description: 'Build reusable contract templates with sections, merge fields, and signature blocks. Use AI to generate professional content.',
      icon: DocumentDuplicateIcon,
    },
    {
      number: 2,
      title: 'Create a Contract',
      description: 'Select a template, choose a client, and customize the content. Set pricing details like fixed price, milestones, or subscription.',
      icon: PencilSquareIcon,
    },
    {
      number: 3,
      title: 'Send for Signature',
      description: 'Send the contract to your client via email. They can view, review, and sign it electronically with a single click.',
      icon: PaperAirplaneIcon,
    },
    {
      number: 4,
      title: 'Track & Manage',
      description: 'Monitor contract status, view signing activity, and manage all your agreements in one place.',
      icon: CheckCircleIcon,
    },
  ];

  const invoiceSteps: StepProps[] = [
    {
      number: 1,
      title: 'Create an Invoice Template',
      description: 'Design invoice templates with your branding, payment terms, and line item structure.',
      icon: DocumentDuplicateIcon,
    },
    {
      number: 2,
      title: 'Generate Invoice',
      description: 'Create invoices from templates, add line items, set due dates, and apply taxes or discounts.',
      icon: PencilSquareIcon,
    },
    {
      number: 3,
      title: 'Send to Client',
      description: 'Email invoices directly to clients with a secure payment link for easy online payment.',
      icon: PaperAirplaneIcon,
    },
    {
      number: 4,
      title: 'Track Payments',
      description: 'Monitor payment status, send reminders, and keep your finances organized.',
      icon: CurrencyDollarIcon,
    },
  ];

  return (
    <Layout>
      <div className="p-6 space-y-8 max-w-6xl mx-auto">
        {/* Header */}
        <div className="text-center space-y-3">
          <h1 className="text-3xl font-semibold">Documents</h1>
          <p className="text-base-content/70 max-w-2xl mx-auto">
            Create professional contracts and invoices, send them to clients for e-signature, 
            and track everything in one place.
          </p>
        </div>

        {/* Feature Cards */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Contracts Card */}
          <div className="card bg-base-200 border border-base-300">
            <div className="card-body p-6 flex flex-col">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-12 h-12 rounded-lg bg-primary/20 flex items-center justify-center">
                  <DocumentTextIcon className="w-6 h-6 text-primary" />
                </div>
                <div>
                  <h2 className="text-xl font-semibold">Contracts</h2>
                  <p className="text-sm text-base-content/60">
                    Agreements with e-signature
                  </p>
                </div>
              </div>

              <div className="space-y-4 flex-1">
                {contractSteps.map((step) => (
                  <Step key={step.number} {...step} />
                ))}
              </div>

              <div className="flex flex-col gap-2 mt-6">
                <button
                  onClick={() => navigate('/documents/contracts/create')}
                  className="btn btn-primary w-full"
                >
                  <DocumentTextIcon className="w-5 h-5" />
                  Create Contract
                </button>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    onClick={() => navigate('/documents/contracts')}
                    className="btn btn-ghost btn-sm"
                  >
                    View All
                    <ArrowRightIcon className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => navigate('/documents/contracts/templates')}
                    className="btn btn-ghost btn-sm"
                  >
                    Templates
                    <ArrowRightIcon className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Invoices Card */}
          <div className="card bg-base-200 border border-base-300">
            <div className="card-body p-6 flex flex-col">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-12 h-12 rounded-lg bg-success/20 flex items-center justify-center">
                  <CurrencyDollarIcon className="w-6 h-6 text-success" />
                </div>
                <div>
                  <h2 className="text-xl font-semibold">Invoices</h2>
                  <p className="text-sm text-base-content/60">
                    Billing and payments
                  </p>
                </div>
              </div>

              <div className="space-y-4 flex-1">
                {invoiceSteps.map((step) => (
                  <Step key={step.number} {...step} />
                ))}
              </div>

              <div className="flex flex-col gap-2 mt-6">
                <button
                  onClick={() => navigate('/documents/invoices/create')}
                  className="btn btn-success w-full"
                >
                  <CurrencyDollarIcon className="w-5 h-5" />
                  Create Invoice
                </button>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    onClick={() => navigate('/documents/invoices')}
                    className="btn btn-ghost btn-sm"
                  >
                    View All
                    <ArrowRightIcon className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => navigate('/documents/settings')}
                    className="btn btn-ghost btn-sm"
                  >
                    Settings
                    <ArrowRightIcon className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Tips Section */}
        <div className="card bg-base-200/50 border border-base-300">
          <div className="card-body p-6">
            <div className="flex items-center gap-2 mb-4">
              <SparklesIcon className="w-5 h-5 text-primary" />
              <h3 className="font-semibold">Pro Tips</h3>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-1">
                <h4 className="font-medium text-sm">Use AI to Write Contracts</h4>
                <p className="text-xs text-base-content/60">
                  In the template builder, use the AI assistant to generate professional contract sections based on your description.
                </p>
              </div>
              <div className="space-y-1">
                <h4 className="font-medium text-sm">Merge Fields for Personalization</h4>
                <p className="text-xs text-base-content/60">
                  Use merge fields like {'{{client.name}}'} to automatically insert client and project details.
                </p>
              </div>
              <div className="space-y-1">
                <h4 className="font-medium text-sm">Templates Save Time</h4>
                <p className="text-xs text-base-content/60">
                  Create templates once and reuse them. Each contract clones the template so you can customize without affecting the original.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Quick Stats Placeholder */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="card bg-base-200 border border-base-300">
            <div className="card-body p-4 items-center text-center">
              <span className="text-2xl font-semibold text-primary">0</span>
              <span className="text-xs text-base-content/60">Draft Contracts</span>
            </div>
          </div>
          <div className="card bg-base-200 border border-base-300">
            <div className="card-body p-4 items-center text-center">
              <span className="text-2xl font-semibold text-warning">0</span>
              <span className="text-xs text-base-content/60">Awaiting Signature</span>
            </div>
          </div>
          <div className="card bg-base-200 border border-base-300">
            <div className="card-body p-4 items-center text-center">
              <span className="text-2xl font-semibold text-info">0</span>
              <span className="text-xs text-base-content/60">Pending Invoices</span>
            </div>
          </div>
          <div className="card bg-base-200 border border-base-300">
            <div className="card-body p-4 items-center text-center">
              <span className="text-2xl font-semibold text-success">$0</span>
              <span className="text-xs text-base-content/60">Paid This Month</span>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
}

