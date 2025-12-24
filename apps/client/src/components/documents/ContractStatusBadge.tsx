import type { Contract } from '../../types/documents';

interface ContractStatusBadgeProps {
  status: Contract['status'];
  className?: string;
}

const STATUS_CONFIG = {
  draft: { label: 'Draft', className: 'badge-ghost' },
  sent: { label: 'Sent', className: 'badge-info' },
  viewed: { label: 'Viewed', className: 'badge-primary' },
  signed: { label: 'Signed', className: 'badge-success' },
  declined: { label: 'Declined', className: 'badge-error' },
  expired: { label: 'Expired', className: 'badge-warning' },
  cancelled: { label: 'Cancelled', className: 'badge-ghost' },
};

function ContractStatusBadge({ status, className = '' }: ContractStatusBadgeProps) {
  const config = STATUS_CONFIG[status] || STATUS_CONFIG.draft;

  return (
    <span className={`badge ${config.className} ${className}`}>
      {config.label}
    </span>
  );
}

export { ContractStatusBadge };
export default ContractStatusBadge;

