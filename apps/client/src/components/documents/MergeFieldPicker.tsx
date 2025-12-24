import { useState, useRef, useEffect } from 'react';
import { ChevronDownIcon } from '@heroicons/react/24/outline';
import type { MergeField } from '../../types/documents';

interface MergeFieldPickerProps {
  onSelect: (field: MergeField) => void;
}

const MERGE_FIELDS: MergeField[] = [
  { key: 'client.first_name', label: 'Client First Name', type: 'text', category: 'client' },
  { key: 'client.last_name', label: 'Client Last Name', type: 'text', category: 'client' },
  { key: 'client.full_name', label: 'Client Full Name', type: 'text', category: 'client' },
  { key: 'client.email', label: 'Client Email', type: 'text', category: 'client' },
  { key: 'client.phone', label: 'Client Phone', type: 'text', category: 'client' },
  { key: 'client.organization', label: 'Client Organization', type: 'text', category: 'client' },
  { key: 'project.name', label: 'Project Name', type: 'text', category: 'project' },
  { key: 'project.description', label: 'Project Description', type: 'text', category: 'project' },
  { key: 'project.start_date', label: 'Project Start Date', type: 'date', category: 'project' },
  { key: 'project.due_date', label: 'Project Due Date', type: 'date', category: 'project' },
  { key: 'project.budget', label: 'Project Budget', type: 'currency', category: 'project' },
  { key: 'company.name', label: 'Company Name', type: 'text', category: 'company' },
  { key: 'company.email', label: 'Company Email', type: 'text', category: 'company' },
  { key: 'company.phone', label: 'Company Phone', type: 'text', category: 'company' },
  { key: 'company.address', label: 'Company Address', type: 'text', category: 'company' },
  { key: 'today', label: "Today's Date", type: 'date', category: 'system' },
  { key: 'contract.created_date', label: 'Contract Created Date', type: 'date', category: 'contract' },
  { key: 'contract.expires_at', label: 'Contract Expiry Date', type: 'date', category: 'contract' },
];

const CATEGORIES = {
  client: 'Client',
  project: 'Project',
  company: 'Company',
  system: 'System',
  contract: 'Contract',
};

export function MergeFieldPicker({ onSelect }: MergeFieldPickerProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const groupedFields = MERGE_FIELDS.reduce((acc, field) => {
    const category = field.category;
    if (!acc[category]) {
      acc[category] = [];
    }
    acc[category].push(field);
    return acc;
  }, {} as Record<string, MergeField[]>);

  const filteredFields = searchTerm
    ? MERGE_FIELDS.filter(
        (field) =>
          field.label.toLowerCase().includes(searchTerm.toLowerCase()) ||
          field.key.toLowerCase().includes(searchTerm.toLowerCase())
      )
    : null;

  const handleSelect = (field: MergeField) => {
    onSelect(field);
    setSearchTerm('');
    setIsOpen(false);
  };

  return (
    <div className="relative inline-block text-left" ref={dropdownRef}>
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="btn btn-sm btn-ghost"
      >
        Insert Merge Field
        <ChevronDownIcon className="w-4 h-4 ml-1" />
      </button>

      {isOpen && (
        <div className="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-lg bg-base-200 shadow-lg ring-1 ring-base-300 focus:outline-none">
          <div className="p-2">
            <input
              type="text"
              placeholder="Search fields..."
              className="input input-sm input-bordered w-full"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              autoFocus
            />
          </div>

          <div className="max-h-96 overflow-y-auto p-2">
            {filteredFields ? (
              // Show filtered results
              filteredFields.length > 0 ? (
                filteredFields.map((field) => (
                      <button
                    key={field.key}
                    onClick={() => handleSelect(field)}
                    className="w-full text-left px-3 py-2 rounded hover:bg-base-300 transition-colors"
                      >
                    <div className="font-medium text-sm">{field.label}</div>
                        <div className="text-xs text-base-content/60">
                          {'{{' + field.key + '}}'}
                        </div>
                      </button>
                ))
              ) : (
                <div className="px-3 py-2 text-sm text-base-content/60">No fields found</div>
              )
            ) : (
              // Show grouped by category
              Object.entries(groupedFields).map(([category, fields]) => (
                <div key={category} className="mb-3 last:mb-0">
                  <div className="px-3 py-1 text-xs font-semibold text-base-content/60 uppercase">
                    {CATEGORIES[category as keyof typeof CATEGORIES]}
                  </div>
                  {fields.map((field) => (
                        <button
                      key={field.key}
                      onClick={() => handleSelect(field)}
                      className="w-full text-left px-3 py-2 rounded hover:bg-base-300 transition-colors"
                        >
                      <div className="font-medium text-sm">{field.label}</div>
                          <div className="text-xs text-base-content/60">
                            {'{{' + field.key + '}}'}
                          </div>
                        </button>
                  ))}
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}
