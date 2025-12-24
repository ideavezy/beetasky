import type { Invoice } from '../../types/documents';

interface InvoiceStatusBadgeProps {
  status: Invoice['status'];
  className?: string;
}

const STATUS_CONFIG = {
  draft: { label: 'Draft', className: 'badge-ghost' },
  sent: { label: 'Sent', className: 'badge-info' },
  viewed: { label: 'Viewed', className: 'badge-primary' },
  partially_paid: { label: 'Partially Paid', className: 'badge-warning' },
  paid: { label: 'Paid', className: 'badge-success' },
  overdue: { label: 'Overdue', className: 'badge-error' },
  cancelled: { label: 'Cancelled', className: 'badge-ghost' },
};

function InvoiceStatusBadge({ status, className = '' }: InvoiceStatusBadgeProps) {
  const config = STATUS_CONFIG[status] || STATUS_CONFIG.draft;

  return (
    <span className={`badge ${config.className} ${className}`}>
      {config.label}
    </span>
  );
}

export { InvoiceStatusBadge };
export default InvoiceStatusBadge;

