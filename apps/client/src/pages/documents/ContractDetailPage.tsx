import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  ChevronLeftIcon,
  PaperAirplaneIcon,
  ArrowDownTrayIcon,
  EyeIcon,
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClipboardDocumentIcon,
  DocumentArrowDownIcon,
} from '@heroicons/react/24/outline';
import Layout from '../../components/Layout';
import { useContractStore } from '../../stores/contracts';
import { ContractStatusBadge } from '../../components/documents/ContractStatusBadge';
import type { ContractEvent } from '../../types/documents';

export default function ContractDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { 
    selectedContract: contract, 
    fetchContractById, 
    fetchContractEvents,
    sendContract, 
    generatePdf,
    contractEvents,
    isLoading 
  } = useContractStore();

  const [isSending, setIsSending] = useState(false);
  const [isGeneratingPdf, setIsGeneratingPdf] = useState(false);
  const [copySuccess, setCopySuccess] = useState(false);

  useEffect(() => {
    if (id) {
      fetchContractById(id);
      fetchContractEvents(id);
    }
  }, [id, fetchContractById, fetchContractEvents]);

  const handleSendContract = async () => {
    if (!id) return;
    setIsSending(true);
    try {
      await sendContract(id);
      await fetchContractById(id);
      await fetchContractEvents(id);
    } catch (error) {
      console.error('Failed to send contract:', error);
    } finally {
      setIsSending(false);
    }
  };

  const handleGeneratePdf = async () => {
    if (!id) return;
    setIsGeneratingPdf(true);
    try {
      await generatePdf(id);
      // Refetch to get updated PDF path
      setTimeout(() => {
        fetchContractById(id);
        setIsGeneratingPdf(false);
      }, 3000);
    } catch (error) {
      console.error('Failed to generate PDF:', error);
      setIsGeneratingPdf(false);
    }
  };

  const handleDownloadPdf = () => {
    if (!contract?.pdf_path) {
      return;
    }
    window.open(contract.pdf_path, '_blank');
  };

  const handleCopyLink = () => {
    if (!contract?.token) return;
    const link = `${window.location.origin}/public/contracts/${contract.token}`;
    navigator.clipboard.writeText(link);
    setCopySuccess(true);
    setTimeout(() => setCopySuccess(false), 2000);
  };

  const getEventIcon = (eventType: string) => {
    switch (eventType) {
      case 'created':
        return <ClockIcon className="w-5 h-5 text-info" />;
      case 'sent':
        return <PaperAirplaneIcon className="w-5 h-5 text-primary" />;
      case 'viewed':
        return <EyeIcon className="w-5 h-5 text-secondary" />;
      case 'signed':
        return <CheckCircleIcon className="w-5 h-5 text-success" />;
      case 'declined':
        return <XCircleIcon className="w-5 h-5 text-error" />;
      case 'pdf_generated':
        return <DocumentArrowDownIcon className="w-5 h-5 text-accent" />;
      default:
        return <ClockIcon className="w-5 h-5 text-base-content" />;
    }
  };

  const formatEventType = (eventType: string) => {
    return eventType
      .split('_')
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  };

  if (isLoading || !contract) {
    return (
      <Layout>
        <div className="flex items-center justify-center h-96">
          <span className="loading loading-spinner loading-lg text-primary"></span>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="p-6 space-y-6">
      {/* Header */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="flex items-center gap-4">
            <button 
              onClick={() => navigate('/documents/contracts')} 
              className="btn btn-ghost btn-sm"
            >
              <ChevronLeftIcon className="w-5 h-5" /> Back
          </button>
          <div>
              <div className="flex items-center gap-3 flex-wrap">
                <h1 className="text-2xl font-semibold">{contract.title}</h1>
                <ContractStatusBadge status={contract.status} />
              </div>
            <p className="text-sm text-base-content/70 mt-1">{contract.contract_number}</p>
          </div>
        </div>
          
          <div className="flex items-center gap-2 flex-wrap">
          {contract.status === 'draft' && (
              <button 
                onClick={handleSendContract} 
                disabled={isSending}
                className="btn btn-primary btn-sm"
              >
                {isSending ? (
                  <span className="loading loading-spinner loading-xs"></span>
                ) : (
                  <PaperAirplaneIcon className="w-4 h-4" />
                )}
                Send Contract
            </button>
          )}
            {!contract.pdf_path ? (
              <button 
                onClick={handleGeneratePdf}
                disabled={isGeneratingPdf}
                className="btn btn-ghost btn-sm"
              >
                {isGeneratingPdf ? (
                  <span className="loading loading-spinner loading-xs"></span>
                ) : (
                  <DocumentArrowDownIcon className="w-4 h-4" />
                )}
                Generate PDF
              </button>
            ) : (
            <button onClick={handleDownloadPdf} className="btn btn-ghost btn-sm">
                <ArrowDownTrayIcon className="w-4 h-4" /> Download PDF
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Contract Info */}
            <div className="card bg-base-200">
              <div className="card-body p-6">
                <h2 className="text-lg font-semibold mb-4">Contract Details</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-base-content/70">Client</p>
                    <p className="font-medium">{contract.contact?.full_name || 'N/A'}</p>
              </div>
              <div>
                <p className="text-sm text-base-content/70">Project</p>
                    <p className="font-medium">{contract.project?.name || 'N/A'}</p>
              </div>
              <div>
                <p className="text-sm text-base-content/70">Contract Type</p>
                    <p className="font-medium capitalize">
                  {contract.contract_type?.replace('_', ' ')}
                </p>
              </div>
              <div>
                <p className="text-sm text-base-content/70">Amount</p>
                    <p className="font-medium">
                  {contract.pricing_data?.amount
                        ? `$${Number(contract.pricing_data.amount).toLocaleString()}`
                    : 'N/A'}
                </p>
              </div>
              <div>
                <p className="text-sm text-base-content/70">Created</p>
                    <p className="font-medium">
                  {new Date(contract.created_at).toLocaleDateString()}
                </p>
              </div>
              <div>
                <p className="text-sm text-base-content/70">Expires</p>
                    <p className="font-medium">
                  {contract.expires_at ? new Date(contract.expires_at).toLocaleDateString() : 'N/A'}
                </p>
              </div>
            </div>

            {contract.notes && (
              <div className="mt-4 pt-4 border-t border-base-300">
                <p className="text-sm text-base-content/70 mb-2">Notes</p>
                    <p>{contract.notes}</p>
              </div>
            )}
              </div>
          </div>

          {/* Signing Info */}
          {(contract.client_signed_at || contract.provider_signed_at) && (
              <div className="card bg-base-200">
                <div className="card-body p-6">
                  <h2 className="text-lg font-semibold mb-4">Signatures</h2>
              <div className="space-y-4">
                {contract.client_signed_at && (
                      <div className="p-4 bg-success/10 border border-success/20 rounded-lg">
                    <p className="text-sm text-base-content/70">Client Signature</p>
                        <p className="font-medium">{contract.client_signed_by}</p>
                        <p className="text-sm text-base-content/70 mt-1">
                      Signed on {new Date(contract.client_signed_at).toLocaleString()}
                    </p>
                  </div>
                )}
                {contract.provider_signed_at && (
                      <div className="p-4 bg-primary/10 border border-primary/20 rounded-lg">
                    <p className="text-sm text-base-content/70">Provider Signature</p>
                        <p className="font-medium">Provider Representative</p>
                        <p className="text-sm text-base-content/70 mt-1">
                      Signed on {new Date(contract.provider_signed_at).toLocaleString()}
                    </p>
                  </div>
                )}
                  </div>
              </div>
            </div>
          )}

          {/* Public Link */}
          {contract.token && contract.status !== 'draft' && (
              <div className="card bg-base-200">
                <div className="card-body p-6">
                  <h2 className="text-lg font-semibold mb-4">Public Signing Link</h2>
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  value={`${window.location.origin}/public/contracts/${contract.token}`}
                  readOnly
                  className="input input-bordered flex-1 text-sm"
                />
                <button
                      onClick={handleCopyLink}
                      className={`btn btn-sm ${copySuccess ? 'btn-success' : 'btn-primary'}`}
                >
                      <ClipboardDocumentIcon className="w-4 h-4" />
                      {copySuccess ? 'Copied!' : 'Copy'}
                </button>
              </div>
                  <p className="text-sm text-base-content/60 mt-2">
                    Share this link with your client to sign the contract
                  </p>
                </div>
            </div>
          )}
        </div>

        {/* Sidebar - Event Timeline */}
        <div className="space-y-6">
            <div className="card bg-base-200">
              <div className="card-body p-6">
                <h2 className="text-lg font-semibold mb-4">Activity Timeline</h2>
            <div className="space-y-4">
                  {contractEvents.length === 0 ? (
                <p className="text-sm text-base-content/70">No events yet</p>
              ) : (
                    contractEvents.map((event: ContractEvent) => (
                  <div key={event.id} className="flex items-start gap-3">
                    <div className="mt-0.5">{getEventIcon(event.event_type)}</div>
                    <div className="flex-1">
                          <p className="font-medium">{formatEventType(event.event_type)}</p>
                      <p className="text-sm text-base-content/70">
                        {new Date(event.created_at).toLocaleString()}
                      </p>
                      {event.actor_type === 'user' && (
                            <p className="text-xs text-base-content/50">By team member</p>
                          )}
                          {event.actor_type === 'client' && (
                            <p className="text-xs text-base-content/50">By client</p>
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
      </div>
    </Layout>
  );
}
