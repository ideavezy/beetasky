import { useState, useEffect } from 'react'
import { X, FolderOpen, Loader2, Search, Check, AlertTriangle } from 'lucide-react'
import { api } from '../../lib/api'

interface Project {
  id: string
  name: string
  status: string
  is_assigned?: boolean
}

interface LinkProjectModalProps {
  contactId: string
  contactName: string
  onClose: () => void
  onSuccess?: (assignedProjects: string[]) => void
}

export default function LinkProjectModal({
  contactId,
  contactName,
  onClose,
  onSuccess,
}: LinkProjectModalProps) {
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [projects, setProjects] = useState<Project[]>([])
  const [selectedProjects, setSelectedProjects] = useState<Set<string>>(new Set())
  const [initialSelection, setInitialSelection] = useState<Set<string>>(new Set())
  const [searchQuery, setSearchQuery] = useState('')

  // Fetch projects
  useEffect(() => {
    const fetchProjects = async () => {
      setIsLoading(true)
      setError(null)

      try {
        // Fetch all projects for this company
        const projectsResponse = await api.get('/api/v1/projects')
        
        if (projectsResponse.data.success) {
          const allProjects = projectsResponse.data.data || []
          
          // Fetch contact's assigned projects
          const contactProjectsResponse = await api.get(`/api/v1/contacts/${contactId}/projects`)
          
          const assignedProjectIds = new Set<string>(
            contactProjectsResponse.data.success
              ? (contactProjectsResponse.data.data || []).map((p: Project) => p.id)
              : []
          )

          // Mark projects as assigned
          const projectsWithAssignment = allProjects.map((p: Project) => ({
            ...p,
            is_assigned: assignedProjectIds.has(p.id),
          }))

          setProjects(projectsWithAssignment)
          setSelectedProjects(assignedProjectIds)
          setInitialSelection(assignedProjectIds)
        } else {
          setError(projectsResponse.data.message || 'Failed to load projects')
        }
      } catch (err: any) {
        console.error('Failed to fetch projects:', err)
        setError(err.response?.data?.message || 'Failed to load projects')
      } finally {
        setIsLoading(false)
      }
    }

    fetchProjects()
  }, [contactId])

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !isSaving) {
        onClose()
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isSaving, onClose])

  const toggleProject = (projectId: string) => {
    setSelectedProjects((prev) => {
      const next = new Set(prev)
      if (next.has(projectId)) {
        next.delete(projectId)
      } else {
        next.add(projectId)
      }
      return next
    })
  }

  const handleSave = async () => {
    setIsSaving(true)
    setError(null)

    try {
      // Determine which projects to add and remove
      const toAdd = [...selectedProjects].filter((id) => !initialSelection.has(id))
      const toRemove = [...initialSelection].filter((id) => !selectedProjects.has(id))

      // Add new project assignments
      for (const projectId of toAdd) {
        await api.post(`/api/v1/contacts/${contactId}/projects/${projectId}`)
      }

      // Remove project assignments
      for (const projectId of toRemove) {
        await api.delete(`/api/v1/contacts/${contactId}/projects/${projectId}`)
      }

      if (onSuccess) {
        onSuccess([...selectedProjects])
      }
      onClose()
    } catch (err: any) {
      console.error('Failed to update project assignments:', err)
      setError(err.response?.data?.message || 'Failed to update project assignments')
    } finally {
      setIsSaving(false)
    }
  }

  const filteredProjects = projects.filter((project) =>
    project.name.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const hasChanges =
    selectedProjects.size !== initialSelection.size ||
    [...selectedProjects].some((id) => !initialSelection.has(id))

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'active':
        return <span className="badge badge-success badge-xs">Active</span>
      case 'completed':
        return <span className="badge badge-info badge-xs">Completed</span>
      case 'on_hold':
        return <span className="badge badge-warning badge-xs">On Hold</span>
      case 'cancelled':
        return <span className="badge badge-error badge-xs">Cancelled</span>
      default:
        return <span className="badge badge-ghost badge-xs">{status}</span>
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={() => !isSaving && onClose()}
      />

      {/* Modal */}
      <div className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden animate-in fade-in zoom-in-95 duration-200 max-h-[85vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-base-300">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-primary/20 flex items-center justify-center">
              <FolderOpen className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-base-content">Link Projects</h2>
              <p className="text-sm text-base-content/60">
                Assign <span className="font-medium">{contactName}</span> to projects
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            disabled={isSaving}
            className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/60 hover:text-base-content disabled:opacity-50"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Search */}
        {!isLoading && projects.length > 0 && (
          <div className="px-5 pt-4">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50" />
              <input
                type="text"
                placeholder="Search projects..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="input input-bordered w-full pl-10"
                disabled={isSaving}
              />
            </div>
          </div>
        )}

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-5">
          {isLoading ? (
            <div className="flex flex-col items-center justify-center py-12">
              <Loader2 className="w-8 h-8 animate-spin text-primary mb-4" />
              <p className="text-base-content/60">Loading projects...</p>
            </div>
          ) : error ? (
            <div className="flex flex-col items-center justify-center py-12">
              <AlertTriangle className="w-12 h-12 text-error mb-4" />
              <p className="text-error mb-4">{error}</p>
              <button onClick={onClose} className="btn btn-ghost">
                Close
              </button>
            </div>
          ) : projects.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12">
              <FolderOpen className="w-16 h-16 text-base-content/30 mb-4" />
              <h3 className="text-lg font-medium mb-2">No projects available</h3>
              <p className="text-base-content/60 text-center">
                Create a project first before linking contacts.
              </p>
            </div>
          ) : filteredProjects.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12">
              <Search className="w-12 h-12 text-base-content/30 mb-4" />
              <p className="text-base-content/60">No projects match your search</p>
            </div>
          ) : (
            <div className="space-y-2">
              {filteredProjects.map((project) => (
                <label
                  key={project.id}
                  className={`flex items-center gap-4 p-4 rounded-xl cursor-pointer transition-all ${
                    selectedProjects.has(project.id)
                      ? 'bg-primary/10 border border-primary/30'
                      : 'bg-base-100 border border-transparent hover:border-base-300'
                  }`}
                >
                  <div
                    className={`w-5 h-5 rounded border-2 flex items-center justify-center transition-all ${
                      selectedProjects.has(project.id)
                        ? 'bg-primary border-primary'
                        : 'border-base-content/30'
                    }`}
                  >
                    {selectedProjects.has(project.id) && (
                      <Check className="w-3 h-3 text-primary-content" />
                    )}
                  </div>
                  <input
                    type="checkbox"
                    checked={selectedProjects.has(project.id)}
                    onChange={() => toggleProject(project.id)}
                    className="hidden"
                    disabled={isSaving}
                  />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="font-medium truncate">{project.name}</span>
                      {getStatusBadge(project.status)}
                    </div>
                  </div>
                </label>
              ))}
            </div>
          )}
        </div>

        {/* Footer */}
        {!isLoading && !error && projects.length > 0 && (
          <div className="flex items-center justify-between p-5 border-t border-base-300">
            <p className="text-sm text-base-content/60">
              {selectedProjects.size} project{selectedProjects.size !== 1 ? 's' : ''} selected
            </p>
            <div className="flex gap-3">
              <button
                onClick={onClose}
                disabled={isSaving}
                className="btn btn-ghost"
              >
                Cancel
              </button>
              <button
                onClick={handleSave}
                disabled={isSaving || !hasChanges}
                className="btn btn-primary gap-2"
              >
                {isSaving ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    Saving...
                  </>
                ) : (
                  'Save Changes'
                )}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}



