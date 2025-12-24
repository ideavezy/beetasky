import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  ArrowLeftIcon,
  PlusIcon,
  TrashIcon,
  DocumentTextIcon,
  EyeIcon,
  PencilSquareIcon,
} from '@heroicons/react/24/outline';
import Layout from '../../components/Layout';
import { TiptapEditor, TiptapEditorHandle } from '../../components/documents/TiptapEditor';
import { MergeFieldPicker } from '../../components/documents/MergeFieldPicker';
import { useContractStore } from '../../stores/contracts';
import { api } from '../../lib/api';
import type { ContractTemplate, TemplateSection } from '../../types/documents';

interface Contact {
  id: string;
  full_name: string;
  email: string;
  organization?: string;
}

interface Project {
  id: string;
  name: string;
}

interface Milestone {
  name: string;
  amount: string;
  due_date: string;
}

// Paragraph Section with merge field picker
interface ParagraphSectionProps {
  content: string;
  onChange: (html: string) => void;
}

function ParagraphSection({ content, onChange }: ParagraphSectionProps) {
  const editorRef = useRef<TiptapEditorHandle>(null);

  const handleInsertMergeField = (field: { key: string; label: string }) => {
    if (editorRef.current) {
      editorRef.current.insertText(`{{${field.key}}}`);
    }
  };

  return (
    <div className="relative">
      <div className="absolute right-0 -top-1 z-10">
        <MergeFieldPicker onSelect={handleInsertMergeField} />
      </div>
      <TiptapEditor
        ref={editorRef}
        content={content}
        onChange={onChange}
        placeholder="Type your content here..."
      />
    </div>
  );
}

// Table renderer for contract sections
interface TableRendererProps {
  cells: string[][];
  hasHeader: boolean;
}

function TableRenderer({ cells, hasHeader }: TableRendererProps) {
  if (!cells || cells.length === 0) return null;
  
  return (
    <div className="overflow-x-auto">
      <table className="table table-sm w-full">
        <tbody>
          {cells.map((row, rowIndex) => (
            <tr key={rowIndex}>
              {row.map((cell, colIndex) => (
                <td
                  key={colIndex}
                  className={`border border-base-300 px-3 py-2 ${
                    hasHeader && rowIndex === 0 ? 'bg-base-200 font-semibold' : ''
                  }`}
                >
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function CreateContractPage() {
  const navigate = useNavigate();
  const { templates, fetchTemplates, createContract, isLoading: isCreating } = useContractStore();
  
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [isLoadingData, setIsLoadingData] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<'edit' | 'preview'>('edit');
  
  const [formData, setFormData] = useState<{
    title: string;
    contact_id: string;
    project_id: string;
    template_id: string;
    contract_type: 'fixed_price' | 'milestone' | 'subscription';
    notes: string;
    expires_at: string;
  }>({
    title: '',
    contact_id: '',
    project_id: '',
    template_id: '',
    contract_type: 'fixed_price',
    notes: '',
    expires_at: '',
  });
  
  // Editable contract sections (cloned from template)
  const [contractSections, setContractSections] = useState<TemplateSection[]>([]);
  const [clickwrapText, setClickwrapText] = useState('I agree to the terms and conditions outlined above.');
  
  // Pricing data based on contract type
  const [fixedAmount, setFixedAmount] = useState('');
  const [milestones, setMilestones] = useState<Milestone[]>([
    { name: '', amount: '', due_date: '' }
  ]);
  const [subscription, setSubscription] = useState({
    amount: '',
    interval: 'monthly',
    period: '12',
  });

  // Selected template
  const selectedTemplate = templates.find((t: ContractTemplate) => t.id === formData.template_id);

  useEffect(() => {
    loadData();
  }, []);

  // When template changes, clone its sections
  useEffect(() => {
    if (selectedTemplate) {
      // Deep clone sections with new IDs
      const clonedSections = selectedTemplate.sections.map((section, index) => ({
        ...section,
        id: `contract-section-${Date.now()}-${index}`,
        content: { ...section.content },
      }));
      setContractSections(clonedSections);
      setClickwrapText(selectedTemplate.clickwrap_text || 'I agree to the terms and conditions outlined above.');
      setFormData(prev => ({
        ...prev,
        contract_type: selectedTemplate.default_contract_type || 'fixed_price',
      }));
    } else {
      setContractSections([]);
    }
  }, [selectedTemplate]);

  const loadData = async () => {
    setIsLoadingData(true);
    try {
      const [contactsRes, projectsRes] = await Promise.all([
        api.get('/api/v1/contacts'),
        api.get('/api/v1/projects'),
        fetchTemplates(),
      ]);
      
      setContacts(contactsRes.data.data || []);
      setProjects(projectsRes.data.data || []);
    } catch (err) {
      console.error('Failed to load data:', err);
      setError('Failed to load data. Please try again.');
    } finally {
      setIsLoadingData(false);
    }
  };

  const handleAddMilestone = () => {
    setMilestones([...milestones, { name: '', amount: '', due_date: '' }]);
  };

  const handleRemoveMilestone = (index: number) => {
    setMilestones(milestones.filter((_, i) => i !== index));
  };

  const handleMilestoneChange = (index: number, field: keyof Milestone, value: string) => {
    const updated = [...milestones];
    updated[index][field] = value;
    setMilestones(updated);
  };

  const updateSection = (sectionId: string, updates: Partial<TemplateSection>) => {
    setContractSections(sections =>
      sections.map(s => s.id === sectionId ? { ...s, ...updates } : s)
    );
  };

  const buildPricingData = () => {
    switch (formData.contract_type) {
      case 'fixed_price':
        return {
          amount: parseFloat(fixedAmount) || 0,
          currency: 'USD',
        };
      case 'milestone':
        return {
          milestones: milestones.map((m) => ({
            name: m.name,
            amount: parseFloat(m.amount) || 0,
            due_date: m.due_date,
          })),
          currency: 'USD',
        };
      case 'subscription':
        return {
          amount: parseFloat(subscription.amount) || 0,
          currency: 'USD',
          interval: subscription.interval,
          period: parseInt(subscription.period) || 12,
        };
      default:
        return {};
    }
  };

  const calculateTotal = () => {
    switch (formData.contract_type) {
      case 'fixed_price':
        return parseFloat(fixedAmount) || 0;
      case 'milestone':
        return milestones.reduce((sum, m) => sum + (parseFloat(m.amount) || 0), 0);
      case 'subscription':
        return (parseFloat(subscription.amount) || 0) * (parseInt(subscription.period) || 12);
      default:
        return 0;
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!formData.template_id) {
      setError('Please select a template');
      return;
    }
    if (!formData.contact_id) {
      setError('Please select a client');
      return;
    }
    if (!formData.title.trim()) {
      setError('Please enter a contract title');
      return;
    }

    try {
      const contract = await createContract({
        ...formData,
        pricing_data: buildPricingData(),
        rendered_sections: contractSections,
        clickwrap_text: clickwrapText,
        project_id: formData.project_id || undefined,
        expires_at: formData.expires_at || undefined,
      });
      
      navigate(`/documents/contracts/${contract.id}`);
    } catch (err: any) {
      console.error('Failed to create contract:', err);
      setError(err.response?.data?.message || 'Failed to create contract');
    }
  };

  if (isLoadingData) {
    return (
      <Layout>
        <div className="flex items-center justify-center h-96">
          <span className="loading loading-spinner loading-lg"></span>
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
            <button
              onClick={() => navigate('/documents')}
              className="btn btn-ghost btn-sm"
            >
              <ArrowLeftIcon className="w-4 h-4" />
              Back
            </button>
            <div>
              <h1 className="text-2xl font-semibold">Create Contract</h1>
              <p className="text-base-content/70 text-sm mt-1">
                Select a template, customize content, and set pricing
              </p>
            </div>
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => navigate('/documents')}
              className="btn btn-ghost"
            >
              Cancel
            </button>
            <button
              onClick={handleSubmit}
              disabled={isCreating || !formData.template_id}
              className="btn btn-primary"
            >
              {isCreating ? (
                <span className="loading loading-spinner loading-sm"></span>
              ) : (
                'Create Contract'
              )}
            </button>
          </div>
        </div>

        {error && (
          <div className="alert alert-error mb-6">
            <span>{error}</span>
          </div>
        )}

        <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
          {/* Left Column - Settings */}
          <div className="space-y-6">
            {/* Basic Info Card */}
            <div className="card bg-base-200 border border-base-300">
              <div className="card-body p-5">
                <h3 className="font-semibold mb-4">Contract Details</h3>
                
                {/* Template Selection */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-medium">Template *</span>
                  </label>
                  <select
                    className="select select-bordered select-sm"
                    value={formData.template_id}
                    onChange={(e) =>
                      setFormData({ ...formData, template_id: e.target.value })
                    }
                    required
                  >
                    <option value="">Choose a template...</option>
                    {templates.map((template: ContractTemplate) => (
                      <option key={template.id} value={template.id}>
                        {template.name}
                      </option>
                    ))}
                  </select>
                  {templates.length === 0 && (
                    <label className="label">
                      <span className="label-text-alt text-warning">
                        <a href="/documents/contracts/templates/new" className="link link-primary">
                          Create a template first
                        </a>
                      </span>
                    </label>
                  )}
                </div>

                {/* Contract Title */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-medium">Title *</span>
                  </label>
                  <input
                    type="text"
                    placeholder="e.g., Website Development Agreement"
                    className="input input-bordered input-sm"
                    value={formData.title}
                    onChange={(e) =>
                      setFormData({ ...formData, title: e.target.value })
                    }
                    required
                  />
                </div>

                {/* Client Selection */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-medium">Client *</span>
                  </label>
                  <select
                    className="select select-bordered select-sm"
                    value={formData.contact_id}
                    onChange={(e) =>
                      setFormData({ ...formData, contact_id: e.target.value })
                    }
                    required
                  >
                    <option value="">Select a client...</option>
                    {contacts.map((contact) => (
                      <option key={contact.id} value={contact.id}>
                        {contact.full_name} {contact.organization ? `(${contact.organization})` : ''}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Project Selection */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-medium">Project (Optional)</span>
                  </label>
                  <select
                    className="select select-bordered select-sm"
                    value={formData.project_id}
                    onChange={(e) =>
                      setFormData({ ...formData, project_id: e.target.value })
                    }
                  >
                    <option value="">Select a project...</option>
                    {projects.map((project) => (
                      <option key={project.id} value={project.id}>
                        {project.name}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Expiration Date */}
                <div className="form-control">
                  <label className="label">
                    <span className="label-text font-medium">Expires On</span>
                  </label>
                  <input
                    type="date"
                    className="input input-bordered input-sm"
                    value={formData.expires_at}
                    onChange={(e) =>
                      setFormData({ ...formData, expires_at: e.target.value })
                    }
                  />
                  <label className="label">
                    <span className="label-text-alt">Default: 30 days</span>
                  </label>
                </div>
              </div>
            </div>

            {/* Pricing Card */}
            <div className="card bg-base-200 border border-base-300">
              <div className="card-body p-5">
                <h3 className="font-semibold mb-4">Pricing</h3>

                {/* Contract Type */}
                <div className="form-control">
                  <div className="flex flex-wrap gap-2 mb-4">
                    {['fixed_price', 'milestone', 'subscription'].map((type) => (
                      <button
                        key={type}
                        type="button"
                        onClick={() => setFormData({ ...formData, contract_type: type as any })}
                        className={`btn btn-sm ${formData.contract_type === type ? 'btn-primary' : 'btn-ghost'}`}
                      >
                        {type === 'fixed_price' ? 'Fixed Price' : type.charAt(0).toUpperCase() + type.slice(1)}
                      </button>
                    ))}
                  </div>
                </div>

                {/* Fixed Price */}
                {formData.contract_type === 'fixed_price' && (
                  <div className="form-control">
                    <div className="join">
                      <span className="join-item btn btn-sm btn-disabled">$</span>
                      <input
                        type="number"
                        placeholder="10000"
                        className="input input-bordered input-sm join-item flex-1"
                        value={fixedAmount}
                        onChange={(e) => setFixedAmount(e.target.value)}
                      />
                      <span className="join-item btn btn-sm btn-disabled">USD</span>
                    </div>
                  </div>
                )}

                {/* Milestones */}
                {formData.contract_type === 'milestone' && (
                  <div className="space-y-3">
                    {milestones.map((milestone, index) => (
                      <div key={index} className="card bg-base-300/50 p-3">
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-xs font-medium">Milestone {index + 1}</span>
                          {milestones.length > 1 && (
                            <button
                              type="button"
                              onClick={() => handleRemoveMilestone(index)}
                              className="btn btn-ghost btn-xs text-error"
                            >
                              <TrashIcon className="w-3 h-3" />
                            </button>
                          )}
                        </div>
                        <input
                          type="text"
                          placeholder="Milestone name"
                          className="input input-bordered input-xs mb-2 w-full"
                          value={milestone.name}
                          onChange={(e) => handleMilestoneChange(index, 'name', e.target.value)}
                        />
                        <div className="flex gap-2">
                          <div className="join flex-1">
                            <span className="join-item btn btn-xs btn-disabled">$</span>
                            <input
                              type="number"
                              placeholder="Amount"
                              className="input input-bordered input-xs join-item flex-1"
                              value={milestone.amount}
                              onChange={(e) => handleMilestoneChange(index, 'amount', e.target.value)}
                            />
                          </div>
                          <input
                            type="date"
                            className="input input-bordered input-xs w-32"
                            value={milestone.due_date}
                            onChange={(e) => handleMilestoneChange(index, 'due_date', e.target.value)}
                          />
                        </div>
                      </div>
                    ))}
                    <button
                      type="button"
                      onClick={handleAddMilestone}
                      className="btn btn-ghost btn-xs w-full"
                    >
                      <PlusIcon className="w-3 h-3" />
                      Add Milestone
                    </button>
                  </div>
                )}

                {/* Subscription */}
                {formData.contract_type === 'subscription' && (
                  <div className="space-y-3">
                    <div className="join w-full">
                      <span className="join-item btn btn-sm btn-disabled">$</span>
                      <input
                        type="number"
                        placeholder="500"
                        className="input input-bordered input-sm join-item flex-1"
                        value={subscription.amount}
                        onChange={(e) => setSubscription({ ...subscription, amount: e.target.value })}
                      />
                      <span className="join-item btn btn-sm btn-disabled">USD</span>
                    </div>
                    <div className="flex gap-2">
                      <select
                        className="select select-bordered select-sm flex-1"
                        value={subscription.interval}
                        onChange={(e) => setSubscription({ ...subscription, interval: e.target.value })}
                      >
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                      </select>
                      <div className="join">
                        <input
                          type="number"
                          placeholder="12"
                          className="input input-bordered input-sm join-item w-16"
                          value={subscription.period}
                          onChange={(e) => setSubscription({ ...subscription, period: e.target.value })}
                        />
                        <span className="join-item btn btn-sm btn-disabled text-xs">periods</span>
                      </div>
                    </div>
                  </div>
                )}

                {/* Total */}
                <div className="divider my-2"></div>
                <div className="flex justify-between items-center">
                  <span className="text-sm text-base-content/60">Total</span>
                  <span className="text-xl font-semibold text-primary">
                    ${calculateTotal().toLocaleString()}
                  </span>
                </div>
              </div>
            </div>

            {/* Notes */}
            <div className="card bg-base-200 border border-base-300">
              <div className="card-body p-5">
                <h3 className="font-semibold mb-4">Internal Notes</h3>
                <textarea
                  className="textarea textarea-bordered w-full h-20 text-sm"
                  placeholder="Notes visible only to you..."
                  value={formData.notes}
                  onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                ></textarea>
              </div>
            </div>
          </div>

          {/* Right Column - Contract Content */}
          <div className="xl:col-span-2">
            {!formData.template_id ? (
              <div className="card bg-base-200 border border-base-300 h-full min-h-[500px]">
                <div className="card-body items-center justify-center text-center">
                  <DocumentTextIcon className="w-16 h-16 text-base-content/30 mb-4" />
                  <h3 className="text-lg font-medium text-base-content/60">Select a Template</h3>
                  <p className="text-sm text-base-content/50 max-w-sm">
                    Choose a contract template from the left panel to preview and customize the content.
                  </p>
                  <button
                    onClick={() => navigate('/documents/contracts/templates/new')}
                    className="btn btn-primary btn-sm mt-4"
                  >
                    <PlusIcon className="w-4 h-4" />
                    Create New Template
                  </button>
                </div>
              </div>
            ) : (
              <div className="card bg-base-200 border border-base-300">
                <div className="card-body p-0">
                  {/* Tabs */}
                  <div className="flex items-center justify-between border-b border-base-300 px-5 py-3">
                    <div className="tabs tabs-boxed bg-base-300/50 p-1">
                      <button
                        onClick={() => setActiveTab('edit')}
                        className={`tab tab-sm ${activeTab === 'edit' ? 'tab-active' : ''}`}
                      >
                        <PencilSquareIcon className="w-4 h-4 mr-1" />
                        Edit
                      </button>
                      <button
                        onClick={() => setActiveTab('preview')}
                        className={`tab tab-sm ${activeTab === 'preview' ? 'tab-active' : ''}`}
                      >
                        <EyeIcon className="w-4 h-4 mr-1" />
                        Preview
                      </button>
                    </div>
                    <span className="text-xs text-base-content/50">
                      {contractSections.length} sections
                    </span>
                  </div>

                  {/* Contract Content */}
                  <div className="p-5 space-y-4 max-h-[calc(100vh-300px)] overflow-y-auto">
                    {activeTab === 'edit' ? (
                      <>
                        {contractSections.map((section, index) => (
                          <div key={section.id} className="space-y-2">
                            {section.type === 'heading' && (
                              <div>
                                <input
                                  type="text"
                                  className="input input-bordered w-full text-lg font-semibold"
                                  value={section.content?.text || ''}
                                  onChange={(e) => updateSection(section.id, {
                                    content: { ...section.content, text: e.target.value }
                                  })}
                                  placeholder="Section heading..."
                                />
                              </div>
                            )}

                            {section.type === 'paragraph' && (
                              <div className="border border-base-300 rounded-lg p-3 bg-base-100">
                                <ParagraphSection
                                  content={section.content?.html || ''}
                                  onChange={(html) => updateSection(section.id, {
                                    content: { ...section.content, html }
                                  })}
                                />
                              </div>
                            )}

                            {section.type === 'table' && (
                              <div className="border border-base-300 rounded-lg p-3 bg-base-100">
                                <TableRenderer
                                  cells={section.content?.cells || []}
                                  hasHeader={section.content?.hasHeader || false}
                                />
                              </div>
                            )}

                            {section.type === 'signature' && (
                              <div className="border border-dashed border-base-300 rounded-lg p-4 bg-base-100/50">
                                <div className="text-sm text-base-content/60 mb-2">
                                  {section.content?.label || 'Signature'}
                                </div>
                                <div className="h-16 border-b-2 border-base-content/20"></div>
                                <div className="text-xs text-base-content/50 mt-1">
                                  {section.content?.nameField || 'Name'}
                                </div>
                              </div>
                            )}
                          </div>
                        ))}

                        {/* Clickwrap */}
                        <div className="border-t border-base-300 pt-4 mt-6">
                          <label className="label">
                            <span className="label-text font-medium">Agreement Text</span>
                          </label>
                          <input
                            type="text"
                            className="input input-bordered w-full text-sm"
                            value={clickwrapText}
                            onChange={(e) => setClickwrapText(e.target.value)}
                            placeholder="I agree to the terms and conditions..."
                          />
                        </div>
                      </>
                    ) : (
                      // Preview Mode
                      <div className="prose prose-sm max-w-none">
                        {contractSections.map((section) => (
                          <div key={section.id} className="mb-4">
                            {section.type === 'heading' && (
                              <h2 className="text-lg font-semibold mb-2">
                                {section.content?.text || 'Untitled Section'}
                              </h2>
                            )}

                            {section.type === 'paragraph' && (
                              <div
                                className="text-base-content"
                                dangerouslySetInnerHTML={{ __html: section.content?.html || '' }}
                              />
                            )}

                            {section.type === 'table' && (
                              <TableRenderer
                                cells={section.content?.cells || []}
                                hasHeader={section.content?.hasHeader || false}
                              />
                            )}

                            {section.type === 'signature' && (
                              <div className="border border-dashed border-base-300 rounded-lg p-4 my-4">
                                <div className="text-sm font-medium mb-2">
                                  {section.content?.label || 'Signature'}
                                </div>
                                <div className="h-16 border-b-2 border-base-content/20"></div>
                                <div className="text-xs text-base-content/50 mt-1">
                                  {section.content?.nameField || 'Name'}
                                </div>
                              </div>
                            )}
                          </div>
                        ))}

                        {/* Clickwrap Preview */}
                        <div className="border-t border-base-300 pt-4 mt-6">
                          <label className="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" className="checkbox checkbox-primary mt-1" disabled />
                            <span className="text-sm">{clickwrapText}</span>
                          </label>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </Layout>
  );
}
