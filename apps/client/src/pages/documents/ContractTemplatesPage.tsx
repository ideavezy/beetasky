import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  DocumentDuplicateIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  PencilIcon,
  TrashIcon,
  ArrowLeftIcon,
  DocumentTextIcon,
  EllipsisVerticalIcon,
} from '@heroicons/react/24/outline';
import Layout from '../../components/Layout';
import { useContractStore } from '../../stores/contracts';
import type { ContractTemplate } from '../../types/documents';

export default function ContractTemplatesPage() {
  const navigate = useNavigate();
  const { templates, isLoading, fetchTemplates, deleteTemplate, duplicateTemplate } = useContractStore();
  const [searchTerm, setSearchTerm] = useState('');
  const [activeFilter, setActiveFilter] = useState<'all' | 'active' | 'inactive'>('all');
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [duplicatingId, setDuplicatingId] = useState<string | null>(null);

  useEffect(() => {
    fetchTemplates();
  }, [fetchTemplates]);

  const filteredTemplates = (templates || []).filter((template) => {
    const matchesSearch =
      template.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      template.description?.toLowerCase().includes(searchTerm.toLowerCase());

    const matchesFilter =
      activeFilter === 'all' ||
      (activeFilter === 'active' && template.is_active) ||
      (activeFilter === 'inactive' && !template.is_active);

    return matchesSearch && matchesFilter;
  });

  const handleDelete = async (id: string) => {
    if (!confirm('Are you sure you want to delete this template?')) return;
    
    setDeletingId(id);
    try {
      await deleteTemplate(id);
    } catch (error: any) {
      alert(error.response?.data?.message || 'Failed to delete template');
    } finally {
      setDeletingId(null);
    }
  };

  const handleDuplicate = async (id: string) => {
    setDuplicatingId(id);
    try {
      const newTemplate = await duplicateTemplate(id);
      navigate(`/documents/contracts/templates/${newTemplate.id}`);
    } catch (error) {
      console.error('Failed to duplicate template:', error);
    } finally {
      setDuplicatingId(null);
    }
  };

  return (
    <Layout>
      <div className="p-6 space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div className="flex items-center gap-4">
            <button
              onClick={() => navigate('/documents/contracts')}
              className="btn btn-ghost btn-sm"
            >
              <ArrowLeftIcon className="w-4 h-4" />
              Back
            </button>
            <div>
              <h1 className="text-2xl font-semibold">Contract Templates</h1>
              <p className="text-base-content/70 mt-1">
                Create and manage your contract templates
              </p>
            </div>
          </div>
          <Link to="/documents/contracts/templates/new" className="btn btn-primary">
            <PlusIcon className="w-5 h-5" />
            New Template
          </Link>
        </div>

        {/* Filters */}
        <div className="card bg-base-200">
          <div className="card-body p-4">
            <div className="flex flex-col sm:flex-row gap-4">
              <div className="flex-1 relative">
                <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/50" />
                <input
                  type="text"
                  placeholder="Search templates..."
                  className="input input-bordered w-full pl-10"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
              <select
                className="select select-bordered"
                value={activeFilter}
                onChange={(e) => setActiveFilter(e.target.value as any)}
              >
                <option value="all">All Templates</option>
                <option value="active">Active Only</option>
                <option value="inactive">Inactive Only</option>
              </select>
            </div>
          </div>
        </div>

        {/* Content */}
        {isLoading ? (
          <div className="flex justify-center items-center py-12">
            <span className="loading loading-spinner loading-lg"></span>
          </div>
        ) : filteredTemplates.length === 0 ? (
          <div className="card bg-base-200">
            <div className="card-body items-center text-center py-12">
              <DocumentDuplicateIcon className="w-16 h-16 text-base-content/30" />
              <h3 className="text-xl font-semibold mt-4">No templates found</h3>
              <p className="text-base-content/70 mt-2">
                {searchTerm || activeFilter !== 'all'
                  ? 'Try adjusting your filters'
                  : 'Get started by creating your first template'}
              </p>
              {!searchTerm && activeFilter === 'all' && (
                <Link to="/documents/contracts/templates/new" className="btn btn-primary mt-4">
                  <PlusIcon className="w-5 h-5" />
                  Create Template
                </Link>
              )}
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredTemplates.map((template: ContractTemplate) => (
              <div
                key={template.id}
                className="card bg-base-200 hover:bg-base-300/50 transition-colors"
              >
                <div className="card-body p-5">
                  <div className="flex justify-between items-start">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                        <DocumentTextIcon className="w-5 h-5 text-primary" />
                      </div>
                      <div>
                        <h3 className="font-semibold">{template.name}</h3>
                        {!template.is_active && (
                          <span className="badge badge-ghost badge-sm">Inactive</span>
                        )}
                      </div>
                    </div>
                    
                    <div className="dropdown dropdown-end">
                      <label tabIndex={0} className="btn btn-ghost btn-sm btn-square">
                        <EllipsisVerticalIcon className="w-5 h-5" />
                      </label>
                      <ul tabIndex={0} className="dropdown-content z-10 menu p-2 shadow bg-base-300 rounded-box w-52">
                        <li>
                          <Link to={`/documents/contracts/templates/${template.id}`}>
                            <PencilIcon className="w-4 h-4" />
                            Edit
                          </Link>
                        </li>
                        <li>
                          <button
                            onClick={() => handleDuplicate(template.id)}
                            disabled={duplicatingId === template.id}
                          >
                            {duplicatingId === template.id ? (
                              <span className="loading loading-spinner loading-xs"></span>
                            ) : (
                              <DocumentDuplicateIcon className="w-4 h-4" />
                            )}
                            Duplicate
                          </button>
                        </li>
                        <li>
                          <button
                            onClick={() => handleDelete(template.id)}
                            disabled={deletingId === template.id}
                            className="text-error"
                          >
                            {deletingId === template.id ? (
                              <span className="loading loading-spinner loading-xs"></span>
                            ) : (
                              <TrashIcon className="w-4 h-4" />
                            )}
                            Delete
                          </button>
                        </li>
                      </ul>
                    </div>
                  </div>
                  
                  {template.description && (
                    <p className="text-sm text-base-content/70 mt-2 line-clamp-2">
                      {template.description}
                    </p>
                  )}
                  
                  <div className="flex items-center gap-4 mt-4 text-sm text-base-content/60">
                    <span>
                      {template.sections?.length || 0} sections
                    </span>
                    <span className="capitalize">
                      {template.default_contract_type?.replace('_', ' ') || 'Fixed Price'}
                    </span>
                  </div>
                  
                  <div className="card-actions justify-end mt-4 pt-4 border-t border-base-300">
                    <Link
                      to={`/documents/contracts/templates/${template.id}`}
                      className="btn btn-ghost btn-sm"
                    >
                      Edit Template
                    </Link>
                    <Link
                      to={`/documents/contracts/create?template=${template.id}`}
                      className="btn btn-primary btn-sm"
                    >
                      Use Template
                    </Link>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </Layout>
  );
}

