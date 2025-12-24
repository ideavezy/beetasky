import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { CheckCircleIcon, DocumentTextIcon, XCircleIcon, ClockIcon } from '@heroicons/react/24/outline';
import { useContractStore } from '../../stores/contracts';
import { ContractStatusBadge } from '../../components/documents/ContractStatusBadge';
import type { Contract, TemplateSection } from '../../types/documents';

export function PublicContractPage() {
  const { token } = useParams<{ token: string }>();
  const { getPublicContract, signContract, isLoading } = useContractStore();
  const [contract, setContract] = useState<Contract | null>(null);
  const [signedBy, setSignedBy] = useState('');
  const [agreed, setAgreed] = useState(false);
  const [isSigning, setIsSigning] = useState(false);
  const [signed, setSigned] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (token) {
      loadContract();
    }
  }, [token]);

  const loadContract = async () => {
    if (!token) return;
    try {
      const data = await getPublicContract(token);
      setContract(data);
      if (data.status === 'signed') {
        setSigned(true);
      }
    } catch (error) {
      console.error('Failed to load contract:', error);
      setError('Contract not found or has expired');
    }
  };

  const handleSign = async () => {
    if (!agreed || !signedBy.trim() || !token) {
      return;
    }

    setIsSigning(true);
    setError(null);
    try {
      await signContract(token, signedBy);
      setSigned(true);
    } catch (error: any) {
      console.error('Failed to sign contract:', error);
      setError(error.response?.data?.message || 'Failed to sign contract');
    } finally {
      setIsSigning(false);
    }
  };

  const renderSection = (section: TemplateSection, index: number) => {
    switch (section.type) {
      case 'heading':
        return (
          <h2 key={index} className="text-2xl font-semibold mt-6 mb-4">
            {section.content?.text || ''}
          </h2>
        );
      case 'paragraph':
        return (
          <div
            key={index}
            className="mb-4"
            dangerouslySetInnerHTML={{ __html: section.content?.html || '' }}
          />
        );
      case 'list':
        const ListTag = section.content?.listType === 'numbered' ? 'ol' : 'ul';
        return (
          <ListTag key={index} className={`mb-4 ${section.content?.listType === 'numbered' ? 'list-decimal' : 'list-disc'} pl-6`}>
            {(section.content?.items || []).map((item: string, i: number) => (
              <li key={i} className="mb-1">{item}</li>
            ))}
          </ListTag>
        );
      case 'table':
        return (
          <div key={index} className="overflow-x-auto mb-4">
            <table className="table table-bordered">
              <tbody>
                {Array.from({ length: section.content?.rows || 3 }).map((_, rowIndex) => (
                  <tr key={rowIndex}>
                    {Array.from({ length: section.content?.cols || 2 }).map((_, colIndex) => (
                      <td key={colIndex} className="border border-base-300 p-2">
                        {section.content?.cells?.[rowIndex]?.[colIndex] || ''}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        );
      case 'signature':
        return (
          <div key={index} className="border-t-2 border-base-content/30 pt-4 mt-8">
            <p className="font-semibold">{section.content?.label || 'Signature'}</p>
            <p className="text-sm text-base-content/60 mt-2">
              Name: {section.content?.nameField || '_______________'}
            </p>
            <p className="text-sm text-base-content/60">
              Date: _______________
            </p>
          </div>
        );
      default:
        return null;
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100">
        <span className="loading loading-spinner loading-lg"></span>
      </div>
    );
  }

  if (error || !contract) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100 p-6">
        <div className="card bg-base-200 max-w-2xl w-full">
          <div className="card-body items-center text-center">
            <DocumentTextIcon className="w-24 h-24 text-base-content/30" />
            <h1 className="text-3xl font-bold mt-6">Contract Not Found</h1>
            <p className="text-base-content/70 mt-4">
              {error || 'The contract you\'re looking for doesn\'t exist or has been removed.'}
            </p>
          </div>
        </div>
      </div>
    );
  }

  if (contract.status === 'expired') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100 p-6">
        <div className="card bg-base-200 max-w-2xl w-full">
          <div className="card-body items-center text-center">
            <ClockIcon className="w-24 h-24 text-warning" />
            <h1 className="text-3xl font-bold mt-6">Contract Expired</h1>
            <p className="text-base-content/70 mt-4">
              This contract has expired. Please contact the sender for a new contract.
            </p>
          </div>
        </div>
      </div>
    );
  }

  if (contract.status === 'declined') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100 p-6">
        <div className="card bg-base-200 max-w-2xl w-full">
          <div className="card-body items-center text-center">
            <XCircleIcon className="w-24 h-24 text-error" />
            <h1 className="text-3xl font-bold mt-6">Contract Declined</h1>
            <p className="text-base-content/70 mt-4">
              This contract has been declined.
            </p>
          </div>
        </div>
      </div>
    );
  }

  if (signed || contract.status === 'signed') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100 p-6">
        <div className="card bg-base-200 max-w-2xl w-full">
          <div className="card-body items-center text-center">
            <CheckCircleIcon className="w-24 h-24 text-success" />
            <h1 className="text-3xl font-bold mt-6">Contract Signed Successfully!</h1>
            <p className="text-base-content/70 mt-4">
              Thank you for signing the contract. You will receive a confirmation email with a copy
              of the signed document.
            </p>
            {contract.client_signed_at && (
              <p className="text-sm text-base-content/60 mt-2">
                Signed on {new Date(contract.client_signed_at).toLocaleString()}
              </p>
            )}
            {contract.pdf_path && (
              <a
                href={contract.pdf_path}
                target="_blank"
                rel="noopener noreferrer"
                className="btn btn-outline mt-6"
              >
                Download Signed Contract
              </a>
            )}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-base-100 p-6">
      <div className="max-w-4xl mx-auto space-y-6">
        {/* Header */}
        <div className="card bg-base-200">
          <div className="card-body p-6">
            <div className="flex justify-between items-start flex-wrap gap-4">
              <div>
                <h1 className="text-3xl font-bold">{contract.title}</h1>
                <p className="text-base-content/70 mt-2">
                  Contract #{contract.contract_number}
                </p>
                {contract.expires_at && (
                  <p className="text-sm text-warning mt-2">
                    Expires: {new Date(contract.expires_at).toLocaleDateString()}
                  </p>
                )}
              </div>
              <ContractStatusBadge status={contract.status} />
            </div>
          </div>
        </div>

        {/* Contract Content */}
        <div className="card bg-base-200">
          <div className="card-body p-6">
            <div className="prose max-w-none prose-headings:text-base-content prose-p:text-base-content/80">
              {(contract.rendered_sections || []).map((section, index) => 
                renderSection(section, index)
              )}
            </div>

            {/* Pricing */}
            {contract.pricing_data && (
              <div className="mt-8 pt-8 border-t border-base-300">
                <h3 className="text-xl font-semibold mb-4">Pricing Details</h3>
                
                {contract.contract_type === 'fixed_price' && (
                  <div className="text-2xl font-bold text-primary">
                    ${Number(contract.pricing_data.amount || 0).toLocaleString()} {contract.pricing_data.currency || 'USD'}
                  </div>
                )}
                
                {contract.contract_type === 'milestone' && (
                  <div className="space-y-3">
                    {(contract.pricing_data.milestones || []).map((milestone: any, index: number) => (
                      <div key={index} className="flex justify-between items-center p-3 bg-base-300 rounded-lg">
                        <div>
                          <p className="font-medium">{milestone.name}</p>
                          {milestone.due_date && (
                            <p className="text-sm text-base-content/60">
                              Due: {new Date(milestone.due_date).toLocaleDateString()}
                            </p>
                          )}
                        </div>
                        <span className="font-semibold">
                          ${Number(milestone.amount || 0).toLocaleString()}
                        </span>
                      </div>
                    ))}
                    <div className="flex justify-between items-center pt-3 border-t border-base-300">
                      <span className="font-semibold">Total</span>
                      <span className="text-xl font-bold text-primary">
                        ${(contract.pricing_data.milestones || []).reduce((sum: number, m: any) => sum + Number(m.amount || 0), 0).toLocaleString()} {contract.pricing_data.currency || 'USD'}
                      </span>
                    </div>
                  </div>
                )}
                
                {contract.contract_type === 'subscription' && (
                  <div>
                    <div className="text-2xl font-bold text-primary">
                      ${Number(contract.pricing_data.amount || 0).toLocaleString()} / {contract.pricing_data.interval}
                    </div>
                    <p className="text-sm text-base-content/60 mt-1">
                      For {contract.pricing_data.period} {contract.pricing_data.interval}s
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Sign Contract Section */}
        {(contract.status === 'draft' || contract.status === 'sent' || contract.status === 'viewed') && (
          <div className="card bg-base-200">
            <div className="card-body p-6">
              <h2 className="text-2xl font-semibold mb-4">Sign Contract</h2>

              {error && (
                <div className="alert alert-error mb-4">
                  <span>{error}</span>
                </div>
              )}

              <div className="space-y-4">
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-medium">Your Full Name *</span>
                  </label>
                  <input
                    type="text"
                    placeholder="Enter your full name"
                    className="input input-bordered"
                    value={signedBy}
                    onChange={(e) => setSignedBy(e.target.value)}
                  />
                </div>

                <div className="p-4 bg-base-300 rounded-lg">
                  <label className="flex items-start gap-3 cursor-pointer">
                    <input
                      type="checkbox"
                      className="checkbox checkbox-primary mt-1"
                      checked={agreed}
                      onChange={(e) => setAgreed(e.target.checked)}
                    />
                    <span className="text-sm">
                      {contract.clickwrap_text || 'I agree to the terms and conditions outlined above'}
                    </span>
                  </label>
                </div>

                <button
                  onClick={handleSign}
                  disabled={!agreed || !signedBy.trim() || isSigning}
                  className="btn btn-primary w-full"
                >
                  {isSigning ? (
                    <>
                      <span className="loading loading-spinner"></span>
                      Signing...
                    </>
                  ) : (
                    'Sign Contract'
                  )}
                </button>

                <p className="text-xs text-base-content/60 text-center">
                  By clicking "Sign Contract", you agree that this constitutes a legal electronic signature.
                  Your IP address and timestamp will be recorded.
                </p>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default PublicContractPage;
