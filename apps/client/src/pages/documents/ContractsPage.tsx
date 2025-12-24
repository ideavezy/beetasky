import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  DocumentTextIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  DocumentDuplicateIcon,
} from '@heroicons/react/24/outline';
import Layout from '../../components/Layout';
import { useContractStore } from '../../stores/contracts';
import { ContractStatusBadge } from '../../components/documents/ContractStatusBadge';
import type { Contract } from '../../types/documents';

export default function ContractsPage() {
  const { contracts, isLoading, fetchContracts } = useContractStore();
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<Contract['status'] | 'all'>('all');

  useEffect(() => {
    fetchContracts();
  }, [fetchContracts]);

  const filteredContracts = (contracts || []).filter((contract) => {
    const matchesSearch =
      contract.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
      contract.contract_number?.toLowerCase().includes(searchTerm.toLowerCase());

    const matchesStatus = statusFilter === 'all' || contract.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  return (
    <Layout>
    <div className="p-6 space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 className="text-2xl font-semibold">Contracts</h1>
          <p className="text-base-content/70 mt-1">
            Manage and track your contract agreements
          </p>
        </div>
          <div className="flex gap-2">
            <Link to="/documents/contracts/templates" className="btn btn-ghost">
              <DocumentDuplicateIcon className="w-5 h-5" />
              Templates
            </Link>
        <Link to="/documents/contracts/create" className="btn btn-primary">
          <PlusIcon className="w-5 h-5" />
          New Contract
        </Link>
          </div>
      </div>

        {/* Filters */}
      <div className="card bg-base-200">
        <div className="card-body p-4">
            <div className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1 relative">
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/50" />
              <input
                type="text"
                placeholder="Search contracts..."
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
              <option value="signed">Signed</option>
              <option value="declined">Declined</option>
              <option value="expired">Expired</option>
            </select>
          </div>
        </div>
      </div>

        {/* Content */}
      {isLoading ? (
        <div className="flex justify-center items-center py-12">
          <span className="loading loading-spinner loading-lg"></span>
        </div>
      ) : filteredContracts.length === 0 ? (
        <div className="card bg-base-200">
          <div className="card-body items-center text-center py-12">
            <DocumentTextIcon className="w-16 h-16 text-base-content/30" />
            <h3 className="text-xl font-semibold mt-4">No contracts found</h3>
            <p className="text-base-content/70 mt-2">
              {searchTerm || statusFilter !== 'all'
                ? 'Try adjusting your filters'
                : 'Get started by creating your first contract'}
            </p>
            {!searchTerm && statusFilter === 'all' && (
              <Link to="/documents/contracts/create" className="btn btn-primary mt-4">
                <PlusIcon className="w-5 h-5" />
                Create Contract
              </Link>
            )}
          </div>
        </div>
      ) : (
        <div className="grid gap-4">
          {filteredContracts.map((contract) => (
            <Link
              key={contract.id}
              to={`/documents/contracts/${contract.id}`}
              className="card bg-base-200 hover:bg-base-300 transition-colors"
            >
              <div className="card-body p-4">
                  <div className="flex flex-col sm:flex-row justify-between items-start gap-4">
                  <div className="flex-1">
                      <div className="flex items-center gap-3 flex-wrap">
                      <h3 className="text-lg font-semibold">{contract.title}</h3>
                      <ContractStatusBadge status={contract.status} />
                    </div>
                      <div className="flex gap-4 mt-2 text-sm text-base-content/70 flex-wrap">
                      {contract.contract_number && (
                        <span>#{contract.contract_number}</span>
                      )}
                      {contract.contact && <span>{contract.contact.full_name}</span>}
                      {contract.project && <span>{contract.project.name}</span>}
                    </div>
                  </div>
                  <div className="text-right text-sm">
                    <div className="text-base-content/70">
                      Created {new Date(contract.created_at).toLocaleDateString()}
                    </div>
                    {contract.sent_at && (
                      <div className="text-base-content/70">
                        Sent {new Date(contract.sent_at).toLocaleDateString()}
                      </div>
                    )}
                    {contract.client_signed_at && (
                      <div className="text-success">
                        Signed {new Date(contract.client_signed_at).toLocaleDateString()}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
    </Layout>
  );
}
