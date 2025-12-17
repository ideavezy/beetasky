import { useState, useEffect, useRef } from 'react'
import { X, Folder, Loader2, ChevronDown } from 'lucide-react'
import { api } from '../../lib/api'
import { useAuthStore } from '../../stores/auth'
import { useNavigate } from 'react-router-dom'

interface CreateProjectModalProps {
  companyId?: string
  onClose: () => void
  onSuccess?: (project: any) => void
}

export default function CreateProjectModal({ companyId, onClose, onSuccess }: CreateProjectModalProps) {
  const navigate = useNavigate()
  const { company, user } = useAuthStore()
  const [projectName, setProjectName] = useState('')
  const [selectedCompanyId, setSelectedCompanyId] = useState(companyId || company?.id || '')
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  // Get user's companies for dropdown
  const userCompanies = user?.companies || []
  const hasMultipleCompanies = userCompanies.length > 1

  // Focus input when modal opens
  useEffect(() => {
    setProjectName('')
    setError(null)
    // Set default company
    if (companyId) {
      setSelectedCompanyId(companyId)
    } else if (company?.id) {
      setSelectedCompanyId(company.id)
    }
    setTimeout(() => inputRef.current?.focus(), 100)
  }, [companyId, company?.id])

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !isLoading) {
        onClose()
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isLoading, onClose])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!projectName.trim()) {
      setError('Project name is required')
      return
    }

    if (projectName.trim().length < 2) {
      setError('Project name must be at least 2 characters')
      return
    }

    if (!selectedCompanyId) {
      setError('Please select a company')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const response = await api.post('/api/v1/projects', {
        name: projectName.trim(),
        company_id: selectedCompanyId,
      })

      if (response.data.success) {
        const project = response.data.data
        
        if (onSuccess) {
          onSuccess(project)
        } else {
          // Default behavior: navigate to the new project
          navigate(`/projects/${project.id}`)
        }
        
        onClose()
      } else {
        setError(response.data.message || 'Failed to create project')
      }
    } catch (err: any) {
      console.error('Failed to create project:', err)
      console.error('Error response:', err.response?.data)
      setError(
        err.response?.data?.error || 
        err.response?.data?.message || 
        err.response?.data?.errors?.name?.[0] ||
        'Failed to create project. Please try again.'
      )
    } finally {
      setIsLoading(false)
    }
  }

  // Get selected company name for display
  const selectedCompany = userCompanies.find(c => c.id === selectedCompanyId) || company

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div 
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={() => !isLoading && onClose()}
      />
      
      {/* Modal */}
      <div className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-base-300">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-secondary/20 flex items-center justify-center">
              <Folder className="w-5 h-5 text-secondary" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-base-content">Create Project</h2>
              <p className="text-sm text-base-content/60">Add a new project to your workspace</p>
            </div>
          </div>
          <button
            onClick={onClose}
            disabled={isLoading}
            className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/60 hover:text-base-content disabled:opacity-50"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-5">
          <div className="space-y-4">
            {/* Company selector (only show if multiple companies) */}
            {hasMultipleCompanies && (
              <div>
                <label htmlFor="company" className="block text-sm font-medium text-base-content mb-2">
                  Company
                </label>
                <div className="relative">
                  <select
                    id="company"
                    value={selectedCompanyId}
                    onChange={(e) => setSelectedCompanyId(e.target.value)}
                    disabled={isLoading}
                    className="select select-bordered w-full appearance-none"
                  >
                    {userCompanies.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                  <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50 pointer-events-none" />
                </div>
              </div>
            )}

            {/* Company badge (show when single company) */}
            {!hasMultipleCompanies && selectedCompany && (
              <div className="flex items-center gap-2 p-2 bg-base-300 rounded-lg">
                <div className="w-6 h-6 rounded bg-primary/20 flex items-center justify-center text-xs font-medium text-primary">
                  {selectedCompany.name?.charAt(0).toUpperCase()}
                </div>
                <span className="text-sm text-base-content">{selectedCompany.name}</span>
              </div>
            )}

            {/* Project name */}
            <div>
              <label htmlFor="projectName" className="block text-sm font-medium text-base-content mb-2">
                Project Name
              </label>
              <input
                ref={inputRef}
                id="projectName"
                type="text"
                value={projectName}
                onChange={(e) => setProjectName(e.target.value)}
                placeholder="Enter your project name"
                className="input input-bordered w-full"
                disabled={isLoading}
                maxLength={255}
              />
              {error && (
                <p className="mt-2 text-sm text-error">{error}</p>
              )}
            </div>

            <p className="text-xs text-base-content/50">
              You can add more details like description, due date, and team members after creating the project.
            </p>
          </div>

          {/* Actions */}
          <div className="flex gap-3 mt-6">
            <button
              type="button"
              onClick={onClose}
              disabled={isLoading}
              className="btn btn-ghost flex-1"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isLoading || !projectName.trim() || !selectedCompanyId}
              className="btn btn-primary flex-1 gap-2"
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Creating...
                </>
              ) : (
                <>
                  <Folder className="w-4 h-4" />
                  Create Project
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

