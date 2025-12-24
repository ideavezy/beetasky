import { useEffect, useState, useRef, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeftIcon,
  TrashIcon,
  Bars2Icon,
  PlusIcon,
  DocumentTextIcon,
  TableCellsIcon,
  PencilSquareIcon,
  EyeIcon,
  ChevronDownIcon,
  SparklesIcon,
  XMarkIcon,
  ArrowUturnLeftIcon,
  ArrowUturnRightIcon,
} from '@heroicons/react/24/outline';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import Layout from '../../components/Layout';
import { TiptapEditor, TiptapEditorHandle } from '../../components/documents/TiptapEditor';
import { MergeFieldPicker } from '../../components/documents/MergeFieldPicker';
import { useContractStore } from '../../stores/contracts';
import type { TemplateSection, MergeField } from '../../types/documents';

const SECTION_TYPES = [
  { type: 'heading', label: 'Heading', icon: DocumentTextIcon, description: 'Large section title' },
  { type: 'paragraph', label: 'Text', icon: PencilSquareIcon, description: 'Rich text with formatting, lists, and more' },
  { type: 'table', label: 'Table', icon: TableCellsIcon, description: 'Simple table layout' },
  { type: 'signature', label: 'Signature', icon: PencilSquareIcon, description: 'Signature block for signing' },
] as const;

// Editable Table Component
interface TableEditorProps {
  rows: number;
  cols: number;
  cells: string[][];
  hasHeader: boolean;
  onChange: (updates: { rows?: number; cols?: number; cells?: string[][]; hasHeader?: boolean }) => void;
}

function TableEditor({ rows, cols, cells, hasHeader, onChange }: TableEditorProps) {
  // Initialize cells if empty or resize
  const initializeCells = (newRows: number, newCols: number, existingCells: string[][]) => {
    const newCells: string[][] = [];
    for (let r = 0; r < newRows; r++) {
      const row: string[] = [];
      for (let c = 0; c < newCols; c++) {
        // Preserve existing data or use empty string
        row.push(existingCells[r]?.[c] ?? '');
      }
      newCells.push(row);
    }
    return newCells;
  };

  // Ensure cells array matches dimensions
  const currentCells = initializeCells(rows, cols, cells);

  const updateCell = (rowIndex: number, colIndex: number, value: string) => {
    const newCells = currentCells.map((row, r) =>
      row.map((cell, c) => (r === rowIndex && c === colIndex ? value : cell))
    );
    onChange({ cells: newCells });
  };

  const handleRowsChange = (newRows: number) => {
    const clampedRows = Math.max(1, Math.min(20, newRows));
    const newCells = initializeCells(clampedRows, cols, currentCells);
    onChange({ rows: clampedRows, cells: newCells });
  };

  const handleColsChange = (newCols: number) => {
    const clampedCols = Math.max(1, Math.min(10, newCols));
    const newCells = initializeCells(rows, clampedCols, currentCells);
    onChange({ cols: clampedCols, cells: newCells });
  };

  return (
    <div className="space-y-3 p-3 border border-dashed border-base-300 rounded-lg bg-base-200/30">
      {/* Table controls */}
      <div className="flex items-center gap-4 text-sm flex-wrap">
        <label className="flex items-center gap-2">
          <span className="text-base-content/60">Rows:</span>
          <input
            type="number"
            className="input input-sm input-bordered w-16 bg-transparent"
            min={1}
            max={20}
            value={rows}
            onChange={(e) => handleRowsChange(parseInt(e.target.value) || 2)}
          />
        </label>
        <label className="flex items-center gap-2">
          <span className="text-base-content/60">Columns:</span>
          <input
            type="number"
            className="input input-sm input-bordered w-16 bg-transparent"
            min={1}
            max={10}
            value={cols}
            onChange={(e) => handleColsChange(parseInt(e.target.value) || 2)}
          />
        </label>
        <label className="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            className="checkbox checkbox-sm checkbox-primary"
            checked={hasHeader}
            onChange={(e) => onChange({ hasHeader: e.target.checked })}
          />
          <span className="text-base-content/60">Header row</span>
        </label>
      </div>

      {/* Editable table */}
      <div className="overflow-x-auto">
        <table className="table table-sm w-full bg-base-100 border-collapse">
          <tbody>
            {currentCells.map((row, rowIndex) => (
              <tr key={rowIndex}>
                {row.map((cell, colIndex) => (
                  <td
                    key={colIndex}
                    className={`border border-base-300 p-0 ${
                      hasHeader && rowIndex === 0 ? 'bg-base-200 font-semibold' : ''
                    }`}
                  >
                    <input
                      type="text"
                      className={`w-full h-full px-3 py-2 bg-transparent border-none outline-none focus:ring-2 focus:ring-primary focus:ring-inset text-sm ${
                        hasHeader && rowIndex === 0 ? 'font-semibold' : ''
                      }`}
                      placeholder={hasHeader && rowIndex === 0 ? `Header ${colIndex + 1}` : 'Enter text...'}
                      value={cell}
                      onChange={(e) => updateCell(rowIndex, colIndex, e.target.value)}
                    />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-base-content/50">
        Tip: Click any cell to edit. Use merge fields like {'{{client.name}}'} for dynamic content.
      </p>
    </div>
  );
}

// Paragraph section with integrated merge field picker
interface ParagraphSectionProps {
  content: string;
  onChange: (html: string) => void;
  autoFocus?: boolean;
}

function ParagraphSection({ content, onChange, autoFocus }: ParagraphSectionProps) {
  const editorRef = useRef<TiptapEditorHandle>(null);

  const handleInsertMergeField = (field: { key: string; label: string }) => {
    if (editorRef.current) {
      editorRef.current.insertText(`{{${field.key}}}`);
    }
  };

  // Auto-focus the editor when autoFocus is true
  useEffect(() => {
    if (autoFocus && editorRef.current) {
      setTimeout(() => {
        const editor = editorRef.current?.getEditor();
        if (editor) {
          editor.commands.focus('end');
        }
      }, 100);
    }
  }, [autoFocus]);

  return (
    <div className="relative">
      <div className="absolute right-0 -top-1 z-10">
        <MergeFieldPicker onSelect={handleInsertMergeField} />
      </div>
      <TiptapEditor
        ref={editorRef}
        content={content}
        onChange={onChange}
        placeholder="Type your content here... Use the 'Insert Merge Field' button to add dynamic fields."
      />
    </div>
  );
}

interface SortableSectionProps {
  section: TemplateSection;
  onUpdate: (id: string, content: any) => void;
  onDelete: (id: string) => void;
  onChangeType: (id: string, newType: TemplateSection['type']) => void;
  onAddBelow: (id: string) => void;
  onAiWrite?: (id: string) => void;
  shouldFocus?: boolean;
  onFocused?: () => void;
}

function SortableSection({ section, onUpdate, onDelete, onChangeType, onAddBelow, onAiWrite, shouldFocus, onFocused }: SortableSectionProps) {
  const [isHovered, setIsHovered] = useState(false);
  const [showTypeMenu, setShowTypeMenu] = useState(false);
  const headingInputRef = useRef<HTMLInputElement>(null);

  // Auto-focus when this section should be focused
  useEffect(() => {
    if (shouldFocus) {
      // Small delay to ensure DOM is ready
      setTimeout(() => {
        if (section.type === 'heading' && headingInputRef.current) {
          headingInputRef.current.focus();
        }
        // For paragraph, the TiptapEditor will auto-focus via its own mechanism
        onFocused?.();
      }, 100);
    }
  }, [shouldFocus, section.type, onFocused]);
  
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: section.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  const currentType = SECTION_TYPES.find(t => t.type === section.type);

  return (
    <div
      ref={setNodeRef}
      style={style}
      className="group relative"
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => {
        setIsHovered(false);
        setShowTypeMenu(false);
      }}
    >
      {/* Left toolbar - appears on hover */}
      <div 
        className={`absolute -left-12 top-1 flex items-center gap-0.5 transition-opacity duration-150 ${
          isHovered ? 'opacity-100' : 'opacity-0'
        }`}
      >
        {/* Add block button */}
        <button
          onClick={() => onAddBelow(section.id)}
          className="p-1.5 rounded hover:bg-base-300 text-base-content/40 hover:text-base-content transition-colors"
          title="Add block below"
        >
          <PlusIcon className="w-4 h-4" />
        </button>
        
        {/* Drag handle */}
        <button
          {...attributes}
          {...listeners}
          className="p-1.5 rounded hover:bg-base-300 text-base-content/40 hover:text-base-content cursor-grab active:cursor-grabbing transition-colors"
          title="Drag to reorder"
        >
          <Bars2Icon className="w-4 h-4" />
        </button>
      </div>

      {/* Block content */}
      <div className={`relative rounded-lg transition-all duration-150 ${
        isDragging ? 'ring-2 ring-primary bg-base-200' : ''
      } ${isHovered ? 'bg-base-200/50' : ''}`}>
        
        {/* Block type indicator & actions - top right on hover */}
        <div className={`absolute -top-2 right-2 flex items-center gap-1 z-10 transition-opacity duration-150 ${
          isHovered ? 'opacity-100' : 'opacity-0'
        }`}>
          {/* AI write button (heading/paragraph only) */}
          {(section.type === 'heading' || section.type === 'paragraph') && onAiWrite && (
            <button
              onClick={() => onAiWrite(section.id)}
              className="p-1.5 bg-base-300 hover:bg-secondary/20 hover:text-secondary rounded transition-colors"
              title="Write with AI"
              type="button"
            >
              <SparklesIcon className="w-3.5 h-3.5" />
            </button>
          )}

          {/* Type selector */}
          <div className="relative">
            <button
              onClick={() => setShowTypeMenu(!showTypeMenu)}
              className="flex items-center gap-1 px-2 py-1 bg-base-300 hover:bg-base-100 rounded text-xs font-medium transition-colors"
            >
              {currentType && <currentType.icon className="w-3 h-3" />}
              <span className="capitalize">{section.type}</span>
              <ChevronDownIcon className="w-3 h-3" />
            </button>
            
            {showTypeMenu && (
              <div className="absolute right-0 top-full mt-1 w-48 bg-base-200 rounded-lg shadow-xl border border-base-300 py-1 z-50">
                {SECTION_TYPES.map(({ type, label, icon: Icon, description }) => (
                  <button
                    key={type}
                    onClick={() => {
                      onChangeType(section.id, type);
                      setShowTypeMenu(false);
                    }}
                    className={`w-full flex items-start gap-2 px-3 py-2 hover:bg-base-300 transition-colors text-left ${
                      section.type === type ? 'bg-base-300/50' : ''
                    }`}
                  >
                    <Icon className="w-4 h-4 mt-0.5 text-base-content/60" />
                    <div>
                      <div className="text-sm font-medium">{label}</div>
                      <div className="text-xs text-base-content/50">{description}</div>
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Delete button */}
          <button
            onClick={() => onDelete(section.id)}
            className="p-1.5 bg-base-300 hover:bg-error/20 hover:text-error rounded transition-colors"
            title="Delete block"
            type="button"
          >
            <TrashIcon className="w-3.5 h-3.5" />
          </button>
        </div>

        {/* Content area */}
        <div className="py-2 px-1">
          {section.type === 'heading' && (
            <input
              ref={headingInputRef}
              type="text"
              className="w-full bg-transparent border-none outline-none text-2xl font-semibold placeholder:text-base-content/30 focus:ring-0"
              placeholder="Heading"
              value={section.content?.text || ''}
              onChange={(e) => onUpdate(section.id, { text: e.target.value })}
            />
          )}

          {section.type === 'paragraph' && (
            <ParagraphSection
              content={section.content?.html || ''}
              onChange={(html) => onUpdate(section.id, { html })}
              autoFocus={shouldFocus}
            />
          )}

          {section.type === 'table' && (
            <TableEditor
              rows={section.content?.rows || 2}
              cols={section.content?.cols || 2}
              cells={section.content?.cells || []}
              hasHeader={section.content?.hasHeader ?? true}
              onChange={(updates) => onUpdate(section.id, { ...section.content, ...updates })}
            />
          )}

          {section.type === 'signature' && (
            <div className="p-4 border border-dashed border-base-300 rounded-lg bg-base-200/30">
              <div className="grid grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="text-xs text-base-content/60 mb-1 block">Signer Label</label>
                  <input
                    type="text"
                    className="input input-sm input-bordered w-full bg-transparent"
                    placeholder="e.g., Client Signature"
                    value={section.content?.label || ''}
                    onChange={(e) => onUpdate(section.id, {
                      ...section.content,
                      label: e.target.value,
                    })}
                  />
                </div>
                <div>
                  <label className="text-xs text-base-content/60 mb-1 block">Name Merge Field</label>
                  <input
                    type="text"
                    className="input input-sm input-bordered w-full bg-transparent"
                    placeholder="{{client.full_name}}"
                    value={section.content?.nameField || ''}
                    onChange={(e) => onUpdate(section.id, {
                      ...section.content,
                      nameField: e.target.value,
                    })}
                  />
                </div>
              </div>
              <div className="border-t-2 border-base-content/30 pt-3 mt-4">
                <p className="font-medium">{section.content?.label || 'Signature'}</p>
                <div className="flex justify-between text-sm text-base-content/50 mt-2">
                  <span>Name: {section.content?.nameField || '_________________'}</span>
                  <span>Date: _________________</span>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Add block indicator line between sections */}
      <div className="relative h-4 -my-1 group/add">
        <div className={`absolute inset-x-0 top-1/2 h-0.5 bg-primary/30 rounded transition-opacity duration-150 ${
          isHovered ? 'opacity-0 group-hover/add:opacity-100' : 'opacity-0'
        }`} />
      </div>
    </div>
  );
}

// Add block menu component
function AddBlockMenu({ onAdd, className = '' }: { onAdd: (type: TemplateSection['type']) => void; className?: string }) {
  const [isOpen, setIsOpen] = useState(false);
  
  return (
    <div className={`relative ${className}`}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-4 py-2 text-base-content/50 hover:text-base-content hover:bg-base-200 rounded-lg transition-colors w-full"
      >
        <PlusIcon className="w-5 h-5" />
        <span>Add a block</span>
      </button>
      
      {isOpen && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setIsOpen(false)} />
          <div className="absolute left-0 top-full mt-1 w-64 bg-base-200 rounded-xl shadow-xl border border-base-300 py-2 z-50">
            <div className="px-3 py-1.5 text-xs font-semibold text-base-content/50 uppercase">
              Basic Blocks
            </div>
            {SECTION_TYPES.map(({ type, label, icon: Icon, description }) => (
              <button
                key={type}
                onClick={() => {
                  onAdd(type);
                  setIsOpen(false);
                }}
                className="w-full flex items-start gap-3 px-3 py-2.5 hover:bg-base-300 transition-colors text-left"
              >
                <div className="w-8 h-8 rounded bg-base-300 flex items-center justify-center flex-shrink-0">
                  <Icon className="w-4 h-4 text-base-content/70" />
                </div>
                <div>
                  <div className="font-medium">{label}</div>
                  <div className="text-xs text-base-content/50">{description}</div>
                </div>
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

export default function ContractTemplateBuilderPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const isNew = id === 'new';
  
  const { 
    fetchTemplateById, 
    createTemplate, 
    updateTemplate,
    generateContractWithAI,
    generateContractSectionWithAI,
    pendingAISections,
    setPendingAISections,
    isLoading: isSaving 
  } = useContractStore();

  const [isLoading, setIsLoading] = useState(!isNew);
  const [showPreview, setShowPreview] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [focusedSectionId, setFocusedSectionId] = useState<string | null>(null);
  
  // AI Generation state
  const [showAIModal, setShowAIModal] = useState(false);
  const [aiPrompt, setAiPrompt] = useState('');
  const [isGenerating, setIsGenerating] = useState(false);
  const [aiError, setAiError] = useState<string | null>(null);
  const [aiSuccessMessage, setAiSuccessMessage] = useState<string | null>(null);

  // Per-section AI writer state
  const [showSectionAIModal, setShowSectionAIModal] = useState(false);
  const [sectionAISectionId, setSectionAISectionId] = useState<string | null>(null);
  const [sectionAIPrompt, setSectionAIPrompt] = useState('');
  const [sectionAIError, setSectionAIError] = useState<string | null>(null);
  const [isGeneratingSection, setIsGeneratingSection] = useState(false);

  // Auto-save state
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [isSavingAuto, setIsSavingAuto] = useState(false);
  const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);
  const [showNameRequired, setShowNameRequired] = useState(false);
  const [templateId, setTemplateId] = useState<string | null>(id !== 'new' ? id! : null);
  const nameInputRef = useRef<HTMLInputElement>(null);

  const [formData, setFormData] = useState({
    name: '',
    description: '',
    clickwrap_text: 'I agree to the terms and conditions outlined above',
    default_contract_type: 'fixed_price' as 'fixed_price' | 'milestone' | 'subscription',
    is_active: true,
  });

  const [sections, setSections] = useState<TemplateSection[]>([]);

  // Undo/Redo history (max 10 steps)
  const MAX_HISTORY = 10;
  const [undoHistory, setUndoHistory] = useState<TemplateSection[][]>([]);
  const [redoHistory, setRedoHistory] = useState<TemplateSection[][]>([]);
  const isUndoRedoAction = useRef(false);

  // Wrap setSections to track history
  const updateSections = useCallback((newSections: TemplateSection[] | ((prev: TemplateSection[]) => TemplateSection[])) => {
    setSections((prevSections) => {
      const nextSections = typeof newSections === 'function' ? newSections(prevSections) : newSections;
      
      // Only record history if this isn't an undo/redo action
      if (!isUndoRedoAction.current) {
        setUndoHistory((prev) => {
          const newHistory = [...prev, prevSections];
          // Keep only the last MAX_HISTORY entries
          return newHistory.slice(-MAX_HISTORY);
        });
        // Clear redo history on new action
        setRedoHistory([]);
      }
      
      return nextSections;
    });
  }, []);

  const canUndo = undoHistory.length > 0;
  const canRedo = redoHistory.length > 0;

  const handleUndo = useCallback(() => {
    if (undoHistory.length === 0) return;
    
    isUndoRedoAction.current = true;
    
    const previousState = undoHistory[undoHistory.length - 1];
    const newUndoHistory = undoHistory.slice(0, -1);
    
    setRedoHistory((prev) => [...prev, sections].slice(-MAX_HISTORY));
    setUndoHistory(newUndoHistory);
    setSections(previousState);
    setHasUnsavedChanges(true);
    
    // Reset flag after state update
    setTimeout(() => {
      isUndoRedoAction.current = false;
    }, 0);
  }, [undoHistory, sections]);

  const handleRedo = useCallback(() => {
    if (redoHistory.length === 0) return;
    
    isUndoRedoAction.current = true;
    
    const nextState = redoHistory[redoHistory.length - 1];
    const newRedoHistory = redoHistory.slice(0, -1);
    
    setUndoHistory((prev) => [...prev, sections].slice(-MAX_HISTORY));
    setRedoHistory(newRedoHistory);
    setSections(nextState);
    setHasUnsavedChanges(true);
    
    // Reset flag after state update
    setTimeout(() => {
      isUndoRedoAction.current = false;
    }, 0);
  }, [redoHistory, sections]);

  // Keyboard shortcuts for undo/redo
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Check if we're in an input/textarea that should handle its own undo
      const target = e.target as HTMLElement;
      if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') {
        return;
      }
      
      if ((e.metaKey || e.ctrlKey) && e.key === 'z') {
        if (e.shiftKey) {
          // Redo: Ctrl+Shift+Z or Cmd+Shift+Z
          e.preventDefault();
          handleRedo();
        } else {
          // Undo: Ctrl+Z or Cmd+Z
          e.preventDefault();
          handleUndo();
        }
      } else if ((e.metaKey || e.ctrlKey) && e.key === 'y') {
        // Redo: Ctrl+Y or Cmd+Y
        e.preventDefault();
        handleRedo();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [handleUndo, handleRedo]);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  useEffect(() => {
    if (!isNew && id) {
      loadTemplate();
    }
  }, [id, isNew]);

  const loadTemplate = async () => {
    if (!id) return;
    setIsLoading(true);
    try {
      const template = await fetchTemplateById(id);
      setFormData({
        name: template.name,
        description: template.description || '',
        clickwrap_text: template.clickwrap_text || 'I agree to the terms and conditions outlined above',
        default_contract_type: template.default_contract_type || 'fixed_price',
        is_active: template.is_active,
      });
      setSections(template.sections || []);
      setLastSavedAt(new Date(template.updated_at));
    } catch (error) {
      console.error('Failed to load template:', error);
      setError('Failed to load template');
    } finally {
      setIsLoading(false);
    }
  };

  // Check if name is required before any action
  const requireName = useCallback(() => {
    if (!formData.name.trim()) {
      setShowNameRequired(true);
      nameInputRef.current?.focus();
      return true;
    }
    setShowNameRequired(false);
    return false;
  }, [formData.name]);

  // Extract merge fields from sections
  const extractMergeFields = useCallback((sectionsList: TemplateSection[]): MergeField[] => {
    const fields: MergeField[] = [];
    const fieldRegex = /\{\{([^}]+)\}\}/g;
    
    sectionsList.forEach((section) => {
      const content = JSON.stringify(section.content);
      let match;
      while ((match = fieldRegex.exec(content)) !== null) {
        const key = match[1];
        if (!fields.find((f) => f.key === key)) {
          const [category] = key.split('.');
          fields.push({
            key,
            label: key.split('.').map((s) => s.charAt(0).toUpperCase() + s.slice(1)).join(' '),
            type: 'text',
            category: category as MergeField['category'],
          });
        }
      }
    });
    
    return fields;
  }, []);

  // Auto-save function
  const performAutoSave = useCallback(async () => {
    // Don't save if no name or no changes
    if (!formData.name.trim() || !hasUnsavedChanges) {
      return;
    }

    // Don't save if already saving
    if (isSavingAuto || isSaving) {
      return;
    }

    setIsSavingAuto(true);
    setError(null);

    try {
      const data = {
        ...formData,
        sections,
        merge_fields: extractMergeFields(sections),
      };

      if (templateId) {
        // Update existing template
        await updateTemplate(templateId, data);
      } else {
        // Create new template
        const template = await createTemplate(data);
        setTemplateId(template.id);
        // Update URL without reload
        window.history.replaceState(null, '', `/documents/contract-templates/${template.id}`);
      }

      setHasUnsavedChanges(false);
      setLastSavedAt(new Date());
    } catch (error: any) {
      console.error('Auto-save failed:', error);
      // Don't show error for auto-save, just log it
    } finally {
      setIsSavingAuto(false);
    }
  }, [formData, sections, hasUnsavedChanges, templateId, isSavingAuto, isSaving, createTemplate, updateTemplate, extractMergeFields]);

  // Mark as having unsaved changes when formData or sections change
  useEffect(() => {
    if (!isLoading) {
      setHasUnsavedChanges(true);
    }
  }, [formData, sections]);

  // Auto-save every 5 minutes
  useEffect(() => {
    const interval = setInterval(() => {
      performAutoSave();
    }, 5 * 60 * 1000); // 5 minutes

    return () => clearInterval(interval);
  }, [performAutoSave]);

  // Save on page unload if there are unsaved changes
  useEffect(() => {
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      if (hasUnsavedChanges && formData.name.trim()) {
        e.preventDefault();
        e.returnValue = '';
      }
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [hasUnsavedChanges, formData.name]);

  // Listen for AI-generated sections from the chat assistant
  useEffect(() => {
    if (pendingAISections) {
      // Import the AI-generated sections (with history)
      const sectionCount = pendingAISections.sections?.length || 0;
      updateSections(pendingAISections.sections);
      setHasUnsavedChanges(true);
      
      // Update clickwrap text if provided
      if (pendingAISections.clickwrap_text) {
        setFormData(prev => ({ ...prev, clickwrap_text: pendingAISections.clickwrap_text }));
      }
      
      // Clear the pending state
      setPendingAISections(null);
      
      // Show success message
      setError(null);
      setAiError(null);
      setAiSuccessMessage(`✨ AI generated ${sectionCount} contract sections! You can now edit them below.`);
      
      // Auto-hide success message after 5 seconds
      setTimeout(() => setAiSuccessMessage(null), 5000);
      
      // Focus on name if empty
      if (!formData.name.trim()) {
        setShowNameRequired(true);
        nameInputRef.current?.focus();
      }
    }
  }, [pendingAISections, setPendingAISections, formData.name, updateSections]);

  const stripHtmlToText = (html: string) => {
    try {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
    } catch {
      return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    }
  };

  const buildTemplateContextForAI = (excludeSectionId?: string) => {
    const contextSections = sections
      .filter((s) => s.id !== excludeSectionId)
      .slice()
      .sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
      .map((s) => {
        if (s.type === 'heading') {
          return { type: 'heading', text: String(s.content?.text ?? '').trim(), order: s.order ?? 0 };
        }
        if (s.type === 'paragraph') {
          const html = String(s.content?.html ?? '');
          return { type: 'paragraph', text: stripHtmlToText(html).slice(0, 1200), order: s.order ?? 0 };
        }
        if (s.type === 'table') {
          const rows = s.content?.rows ?? (Array.isArray(s.content?.cells) ? s.content?.cells.length : undefined);
          const cols = s.content?.cols ?? (Array.isArray(s.content?.cells?.[0]) ? s.content?.cells?.[0].length : undefined);
          return { type: 'table', text: `Table (${rows ?? '?'}x${cols ?? '?'})`, order: s.order ?? 0 };
        }
        if (s.type === 'signature') {
          const label = String((s as any).label ?? s.content?.label ?? 'Signature').trim();
          return { type: 'signature', text: label, order: s.order ?? 0 };
        }
        return { type: String(s.type), text: '', order: s.order ?? 0 };
      })
      .filter((s) => s.text && s.text.length > 0)
      .slice(0, 20);

    return {
      template_name: formData.name || undefined,
      contract_type: (formData as any).default_contract_type || undefined,
      sections: contextSections,
    };
  };

  const handleOpenSectionAIModal = (sectionId: string) => {
    setSectionAISectionId(sectionId);
    setSectionAIPrompt('');
    setSectionAIError(null);
    setShowSectionAIModal(true);
  };

  const handleGenerateSection = async () => {
    if (!sectionAISectionId) return;
    const target = sections.find((s) => s.id === sectionAISectionId);
    if (!target) return;
    if (target.type !== 'heading' && target.type !== 'paragraph') return;

    if (!sectionAIPrompt.trim()) {
      setSectionAIError('Please describe what you want for this section.');
      return;
    }

    setIsGeneratingSection(true);
    setSectionAIError(null);

    try {
      const ctx = buildTemplateContextForAI(sectionAISectionId);
      const result = await generateContractSectionWithAI(sectionAIPrompt.trim(), target.type, ctx);

      // Use updateSections for AI-generated content so it's recorded in history
      const newContent = result.type === 'heading' 
        ? { text: result.content?.text ?? '' }
        : { html: result.content?.html ?? '' };
      
      updateSections((prev) => prev.map((s) => 
        s.id === sectionAISectionId ? { ...s, content: newContent } : s
      ));

      setHasUnsavedChanges(true);
      setFocusedSectionId(sectionAISectionId);
      setShowSectionAIModal(false);
    } catch (e: any) {
      setSectionAIError(e?.response?.data?.message || e?.message || 'Failed to generate section.');
    } finally {
      setIsGeneratingSection(false);
    }
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      updateSections((items) => {
        const oldIndex = items.findIndex((i) => i.id === active.id);
        const newIndex = items.findIndex((i) => i.id === over.id);
        return arrayMove(items, oldIndex, newIndex).map((s, i) => ({ ...s, order: i }));
      });
      setHasUnsavedChanges(true);
    }
  };

  const handleAddSection = (type: TemplateSection['type'], afterId?: string) => {
    // Require name before adding sections
    if (requireName()) {
      return;
    }

    const newSectionId = `section-${Date.now()}`;
    const newSection: TemplateSection = {
      id: newSectionId,
      type,
      content: type === 'heading' ? { text: '' } : {},
      order: sections.length,
    };
    
    if (afterId) {
      const index = sections.findIndex(s => s.id === afterId);
      updateSections((prev) => {
        const newSections = [...prev];
        newSections.splice(index + 1, 0, newSection);
        return newSections.map((s, i) => ({ ...s, order: i }));
      });
    } else {
      updateSections((prev) => [...prev, newSection]);
    }
    
    setHasUnsavedChanges(true);
    // Set focus to the new section
    setFocusedSectionId(newSectionId);
  };

  const handleUpdateSection = (id: string, content: any) => {
    setSections(sections.map((s) => 
      s.id === id ? { ...s, content } : s
    ));
  };

  const handleDeleteSection = (id: string) => {
    updateSections((prev) => prev.filter((s) => s.id !== id));
    setHasUnsavedChanges(true);
  };

  const handleChangeType = (id: string, newType: TemplateSection['type']) => {
    updateSections((prev) => prev.map((s) => 
      s.id === id ? { ...s, type: newType, content: {} } : s
    ));
    setHasUnsavedChanges(true);
  };

  const handleGenerateWithAI = async () => {
    // Require name before generating
    if (requireName()) {
      setShowAIModal(false);
      return;
    }

    if (!aiPrompt.trim()) {
      setAiError('Please describe the contract you want to create');
      return;
    }

    setIsGenerating(true);
    setAiError(null);

    try {
      const result = await generateContractWithAI(aiPrompt, {
        contract_type: formData.default_contract_type,
      });

      // Replace sections with AI-generated ones (with history)
      updateSections(result.sections);
      setHasUnsavedChanges(true);
      
      // Update clickwrap text if provided
      if (result.clickwrap_text) {
        setFormData(prev => ({ ...prev, clickwrap_text: result.clickwrap_text }));
      }

      // Close modal and reset
      setShowAIModal(false);
      setAiPrompt('');
    } catch (error: any) {
      setAiError(error?.message || 'Failed to generate contract. Please try again.');
    } finally {
      setIsGenerating(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (requireName()) {
      return;
    }

    if (sections.length === 0) {
      setError('Please add at least one section');
      return;
    }

    try {
      const data = {
        ...formData,
        sections,
        merge_fields: extractMergeFields(sections),
      };

      if (templateId) {
        await updateTemplate(templateId, data);
      } else {
        const template = await createTemplate(data);
        setTemplateId(template.id);
        window.history.replaceState(null, '', `/documents/contract-templates/${template.id}`);
      }
      
      setHasUnsavedChanges(false);
      setLastSavedAt(new Date());
    } catch (error: any) {
      console.error('Failed to save template:', error);
      setError(error.response?.data?.message || 'Failed to save template');
    }
  };

  if (isLoading) {
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
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
          <div className="flex items-center gap-4">
            <button
              onClick={() => navigate('/documents/contracts/templates')}
              className="btn btn-ghost btn-sm"
            >
              <ArrowLeftIcon className="w-4 h-4" />
              Back
            </button>
            <div>
              <h1 className="text-2xl font-semibold">
                {isNew && !templateId ? 'Create Template' : 'Edit Template'}
              </h1>
              {/* Auto-save status */}
              <div className="flex items-center gap-2 text-sm mt-1">
                {isSavingAuto && (
                  <span className="text-base-content/50 flex items-center gap-1">
                    <span className="loading loading-spinner loading-xs"></span>
                    Saving...
                  </span>
                )}
                {!isSavingAuto && lastSavedAt && (
                  <span className="text-base-content/50">
                    Last saved {lastSavedAt.toLocaleTimeString()}
                  </span>
                )}
                {!isSavingAuto && hasUnsavedChanges && formData.name.trim() && (
                  <span className="text-warning text-xs">• Unsaved changes</span>
                )}
              </div>
            </div>
          </div>
          <div className="flex gap-2 items-center">
            {/* Undo/Redo buttons */}
            <div className="flex gap-1 mr-2">
              <div className="tooltip tooltip-bottom" data-tip={`Undo (${navigator.platform.includes('Mac') ? '⌘' : 'Ctrl'}+Z)`}>
                <button
                  onClick={handleUndo}
                  disabled={!canUndo}
                  className="btn btn-ghost btn-sm btn-square"
                >
                  <ArrowUturnLeftIcon className="w-4 h-4" />
                </button>
              </div>
              <div className="tooltip tooltip-bottom" data-tip={`Redo (${navigator.platform.includes('Mac') ? '⌘' : 'Ctrl'}+Y)`}>
                <button
                  onClick={handleRedo}
                  disabled={!canRedo}
                  className="btn btn-ghost btn-sm btn-square"
                >
                  <ArrowUturnRightIcon className="w-4 h-4" />
                </button>
              </div>
              {(canUndo || canRedo) && (
                <span className="text-xs text-base-content/40 self-center ml-1">
                  {undoHistory.length}/10
                </span>
              )}
            </div>

            <button
              onClick={() => setShowAIModal(true)}
              className="btn btn-secondary"
            >
              <SparklesIcon className="w-5 h-5" />
              Generate with AI
            </button>
            <button
              onClick={() => setShowPreview(!showPreview)}
              className={`btn btn-ghost ${showPreview ? 'btn-active' : ''}`}
            >
              <EyeIcon className="w-5 h-5" />
              {showPreview ? 'Edit' : 'Preview'}
            </button>
            <button
              onClick={handleSubmit}
              disabled={isSaving}
              className="btn btn-primary"
            >
              {isSaving ? (
                <span className="loading loading-spinner loading-sm"></span>
              ) : (
                'Save Template'
              )}
            </button>
          </div>
        </div>

        {error && (
          <div className="alert alert-error mb-6">
            <span>{error}</span>
          </div>
        )}

        {aiSuccessMessage && (
          <div className="alert alert-success mb-6">
            <span>{aiSuccessMessage}</span>
            <button 
              onClick={() => setAiSuccessMessage(null)} 
              className="btn btn-ghost btn-sm"
            >
              Dismiss
            </button>
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
          {/* Left Sidebar - Settings */}
          <div className="lg:col-span-1 space-y-6">
            <div className="card bg-base-200">
              <div className="card-body p-5">
                <h2 className="font-semibold mb-4">Settings</h2>
                
                <div className="form-control">
                  <label className="label py-1">
                    <span className="label-text text-xs font-medium">
                      Template Name <span className="text-error">*</span>
                    </span>
                  </label>
                  <input
                    ref={nameInputRef}
                    type="text"
                    className={`input input-bordered input-sm ${showNameRequired && !formData.name.trim() ? 'input-error' : ''}`}
                    placeholder="e.g., Standard Agreement"
                    value={formData.name}
                    onChange={(e) => {
                      setFormData({ ...formData, name: e.target.value });
                      if (e.target.value.trim()) {
                        setShowNameRequired(false);
                      }
                    }}
                  />
                  {showNameRequired && !formData.name.trim() && (
                    <label className="label py-1">
                      <span className="label-text-alt text-error">
                        Please enter a template name first
                      </span>
                    </label>
                  )}
                </div>

                <div className="form-control">
                  <label className="label py-1">
                    <span className="label-text text-xs font-medium">Description</span>
                  </label>
                  <textarea
                    className="textarea textarea-bordered textarea-sm h-16"
                    placeholder="Brief description..."
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  />
                </div>

                <div className="form-control">
                  <label className="label py-1">
                    <span className="label-text text-xs font-medium">Default Contract Type</span>
                  </label>
                  <select
                    className="select select-bordered select-sm"
                    value={formData.default_contract_type}
                    onChange={(e) => setFormData({ 
                      ...formData, 
                      default_contract_type: e.target.value as any 
                    })}
                  >
                    <option value="fixed_price">Fixed Price</option>
                    <option value="milestone">Milestone</option>
                    <option value="subscription">Subscription</option>
                  </select>
                </div>

                <div className="form-control mt-2">
                  <label className="label cursor-pointer justify-start gap-3 py-1">
                    <input
                      type="checkbox"
                      className="toggle toggle-primary toggle-sm"
                      checked={formData.is_active}
                      onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                    />
                    <span className="label-text text-xs">Active</span>
                  </label>
                </div>
              </div>
            </div>

            <div className="card bg-base-200">
              <div className="card-body p-5">
                <h2 className="font-semibold mb-3">Agreement Text</h2>
                <textarea
                  className="textarea textarea-bordered textarea-sm h-20 text-xs"
                  placeholder="I agree to the terms..."
                  value={formData.clickwrap_text}
                  onChange={(e) => setFormData({ ...formData, clickwrap_text: e.target.value })}
                />
                <p className="text-xs text-base-content/50 mt-1">
                  Shown above the signature checkbox
                </p>
              </div>
            </div>
          </div>

          {/* Main Content - Sections */}
          <div className="lg:col-span-3">
            {showPreview ? (
              // Preview Mode
              <div className="card bg-base-200">
                <div className="card-body p-8">
                  <div className="max-w-3xl mx-auto">
                    <div className="prose prose-sm max-w-none">
                      {sections.map((section) => (
                        <div key={section.id} className="mb-6">
                          {section.type === 'heading' && (
                            <h2 className="text-2xl font-semibold mt-8 mb-4">{section.content?.text || 'Untitled'}</h2>
                          )}
                          {section.type === 'paragraph' && (
                            <div dangerouslySetInnerHTML={{ __html: section.content?.html || '<p class="text-base-content/40">Empty paragraph</p>' }} />
                          )}
                          {section.type === 'table' && (
                            <table className="table table-bordered w-full">
                              {section.content?.hasHeader && section.content?.cells?.[0] && (
                                <thead>
                                  <tr>
                                    {section.content.cells[0].map((cell: string, colIndex: number) => (
                                      <th key={colIndex} className="border border-base-300 p-2 bg-base-200">
                                        {cell || `Header ${colIndex + 1}`}
                                      </th>
                                    ))}
                                  </tr>
                                </thead>
                              )}
                              <tbody>
                                {(section.content?.cells || []).slice(section.content?.hasHeader ? 1 : 0).map((row: string[], rowIndex: number) => (
                                  <tr key={rowIndex}>
                                    {row.map((cell: string, colIndex: number) => (
                                      <td key={colIndex} className="border border-base-300 p-2">
                                        {cell || '—'}
                                      </td>
                                    ))}
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          )}
                          {section.type === 'signature' && (
                            <div className="border-t-2 border-base-content/30 pt-4 mt-8">
                              <p className="font-semibold">{section.content?.label || 'Signature'}</p>
                              <p className="text-sm text-base-content/60 mt-2">
                                Name: {section.content?.nameField || '_______________'}
                              </p>
                              <p className="text-sm text-base-content/60">
                                Date: _______________
                              </p>
                            </div>
                          )}
                        </div>
                      ))}
                      
                      {sections.length > 0 && (
                        <div className="mt-8 p-4 bg-base-300 rounded-lg">
                          <label className="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" className="checkbox checkbox-primary" disabled />
                            <span className="text-sm">{formData.clickwrap_text}</span>
                          </label>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              // Edit Mode
              <div className="pl-12">
                {sections.length === 0 ? (
                  <div className="text-center py-16">
                    <div className="w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mx-auto mb-4">
                      <DocumentTextIcon className="w-8 h-8 text-base-content/30" />
                    </div>
                    <h3 className="text-lg font-medium mb-2">Start building your template</h3>
                    <p className="text-base-content/60 mb-6">
                      Add blocks to create your contract structure
                    </p>
                    <AddBlockMenu onAdd={(type) => handleAddSection(type)} className="inline-block" />
                  </div>
                ) : (
                  <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                  >
                    <SortableContext
                      items={sections.map((s) => s.id)}
                      strategy={verticalListSortingStrategy}
                    >
                      <div className="space-y-1">
                        {sections.map((section) => (
                          <SortableSection
                            key={section.id}
                            section={section}
                            onUpdate={handleUpdateSection}
                            onDelete={handleDeleteSection}
                            onChangeType={handleChangeType}
                            onAddBelow={(id) => handleAddSection('paragraph', id)}
                            onAiWrite={handleOpenSectionAIModal}
                            shouldFocus={focusedSectionId === section.id}
                            onFocused={() => setFocusedSectionId(null)}
                          />
                        ))}
                      </div>
                    </SortableContext>
                  </DndContext>
                )}
                
                {sections.length > 0 && (
                  <AddBlockMenu 
                    onAdd={(type) => handleAddSection(type)} 
                    className="mt-4"
                  />
                )}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* AI Generation Modal */}
      {showAIModal && (
        <div className="modal modal-open">
          <div className="modal-box max-w-2xl">
            <div className="flex justify-between items-center mb-4">
              <h3 className="font-bold text-lg flex items-center gap-2">
                <SparklesIcon className="w-6 h-6 text-secondary" />
                Generate Contract with AI
              </h3>
              <button
                onClick={() => {
                  setShowAIModal(false);
                  setAiError(null);
                }}
                className="btn btn-ghost btn-sm btn-circle"
              >
                <XMarkIcon className="w-5 h-5" />
              </button>
            </div>

            <p className="text-base-content/70 mb-4">
              Describe the type of contract you want to create. Be specific about the service, 
              deliverables, and any special terms you need.
            </p>

            {aiError && (
              <div className="alert alert-error mb-4">
                <span>{aiError}</span>
              </div>
            )}

            <div className="form-control mb-4">
              <label className="label">
                <span className="label-text">Contract Description</span>
              </label>
              <textarea
                className="textarea textarea-bordered h-32"
                placeholder="Example: A web design contract for a 5-page business website. Includes homepage, about page, services page, portfolio, and contact page. Timeline is 4 weeks. Payment is 50% upfront and 50% on completion. Include clauses for revision limits (3 rounds), ownership transfer, and confidentiality."
                value={aiPrompt}
                onChange={(e) => setAiPrompt(e.target.value)}
                disabled={isGenerating}
              />
            </div>

            <div className="bg-base-200 rounded-lg p-4 mb-4">
              <h4 className="font-medium mb-2">Tips for better results:</h4>
              <ul className="text-sm text-base-content/70 space-y-1">
                <li>• Specify the type of service (web design, consulting, development, etc.)</li>
                <li>• Mention deliverables and timeline</li>
                <li>• Include payment terms you want</li>
                <li>• Note any special clauses (NDA, revisions, warranties)</li>
              </ul>
            </div>

            <div className="modal-action">
              <button
                onClick={() => {
                  setShowAIModal(false);
                  setAiError(null);
                }}
                className="btn btn-ghost"
                disabled={isGenerating}
              >
                Cancel
              </button>
              <button
                onClick={handleGenerateWithAI}
                className="btn btn-secondary"
                disabled={isGenerating || !aiPrompt.trim()}
              >
                {isGenerating ? (
                  <>
                    <span className="loading loading-spinner loading-sm"></span>
                    Generating...
                  </>
                ) : (
                  <>
                    <SparklesIcon className="w-5 h-5" />
                    Generate Contract
                  </>
                )}
              </button>
            </div>
          </div>
          <div className="modal-backdrop" onClick={() => !isGenerating && setShowAIModal(false)} />
        </div>
      )}

      {/* Section AI Writer Modal (per block) */}
      {showSectionAIModal && (
        <div className="modal modal-open">
          <div className="modal-box max-w-2xl">
            <div className="flex justify-between items-center mb-4">
              <h3 className="font-bold text-lg flex items-center gap-2">
                <SparklesIcon className="w-6 h-6 text-secondary" />
                Write this block with AI
              </h3>
              <button
                onClick={() => {
                  setShowSectionAIModal(false);
                  setSectionAIError(null);
                }}
                className="btn btn-ghost btn-sm btn-circle"
                type="button"
              >
                <XMarkIcon className="w-5 h-5" />
              </button>
            </div>

            <p className="text-base-content/70 mb-4">
              Describe what you want for this specific block. We’ll include the other sections in this template as context so the writing stays consistent.
            </p>

            {sectionAIError && (
              <div className="alert alert-error mb-4">
                <span>{sectionAIError}</span>
              </div>
            )}

            <div className="form-control mb-4">
              <label className="label">
                <span className="label-text">What should this block say?</span>
              </label>
              <textarea
                className="textarea textarea-bordered h-28"
                placeholder="Example: Write a short heading for payment terms. Or: Write a paragraph explaining that payment is 50% upfront and 50% on delivery, and late payments incur a 2% monthly fee."
                value={sectionAIPrompt}
                onChange={(e) => setSectionAIPrompt(e.target.value)}
                disabled={isGeneratingSection}
              />
            </div>

            <div className="bg-base-200 rounded-lg p-4 mb-4">
              <h4 className="font-medium mb-2">Tips</h4>
              <ul className="text-sm text-base-content/70 space-y-1">
                <li>• Mention tone (strict, friendly, neutral) and region if needed.</li>
                <li>• Mention specifics: scope, timeline, payment schedule, revisions, ownership.</li>
                <li>• You can reference merge fields like {'{{client.full_name}}'} or {'{{project.name}}'}.</li>
              </ul>
            </div>

            <div className="modal-action">
              <button
                onClick={() => {
                  setShowSectionAIModal(false);
                  setSectionAIError(null);
                }}
                className="btn btn-ghost"
                disabled={isGeneratingSection}
                type="button"
              >
                Cancel
              </button>
              <button
                onClick={handleGenerateSection}
                className="btn btn-secondary"
                disabled={isGeneratingSection || !sectionAIPrompt.trim()}
                type="button"
              >
                {isGeneratingSection ? (
                  <>
                    <span className="loading loading-spinner loading-sm"></span>
                    Writing...
                  </>
                ) : (
                  <>
                    <SparklesIcon className="w-5 h-5" />
                    Generate
                  </>
                )}
              </button>
            </div>
          </div>
          <div
            className="modal-backdrop"
            onClick={() => !isGeneratingSection && setShowSectionAIModal(false)}
          />
        </div>
      )}
    </Layout>
  );
}
