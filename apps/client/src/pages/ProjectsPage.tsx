import { useState, useEffect, useCallback } from 'react'
import { Link } from 'react-router-dom'
import {
  FolderOpen,
  Plus,
  Search,
  Calendar,
  CheckCircle2,
} from 'lucide-react'
import Layout from '../components/Layout'
import { useAuthStore } from '../stores/auth'
import { useModalStore, MODAL_NAMES } from '../stores/modal'
import { useRefreshStore } from '../stores/refresh'
import { api } from '../lib/api'

interface Project {
  id: string
  name: string
  description: string | null
  status: string
  code: string | null
  due_date: string | null
  tasks_count: number
  topics_count: number
  completion_percentage: number
  members: {
    id: string
    first_name: string
    last_name: string | null
    avatar_url: string | null
  }[]
}

interface Pagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export default function ProjectsPage() {
  const { company, isLoading: authLoading } = useAuthStore()
  const { openModal } = useModalStore()
  const projectsVersion = useRefreshStore((state) => state.projectsVersion)
  const [projects, setProjects] = useState<Project[]>([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [pagination, setPagination] = useState<Pagination | null>(null)

  const fetchProjects = useCallback(async () => {
    if (!company?.id) return

    setLoading(true)
    try {
      const response = await api.get('/api/v1/projects', {
        params: {
          per_page: 50,
        },
      })

      if (response.data.success) {
        setProjects(response.data.data || [])
        setPagination(response.data.pagination || null)
      }
    } catch (err) {
      console.error('Failed to fetch projects:', err)
    } finally {
      setLoading(false)
    }
  }, [company?.id])

  // Initial load
  useEffect(() => {
    if (authLoading) return
    if (company?.id) {
      fetchProjects()
    } else {
      setLoading(false)
    }
  }, [company?.id, authLoading, fetchProjects])

  // Refresh when AI makes changes via skills
  useEffect(() => {
    if (projectsVersion > 0 && company?.id) {
      fetchProjects()
    }
  }, [projectsVersion, company?.id, fetchProjects])

  const handleCreateProject = () => {
    openModal(MODAL_NAMES.CREATE_PROJECT, {
      companyId: company?.id,
      onSuccess: (newProject: Project) => {
        setProjects((prev) => [newProject, ...prev])
      },
    })
  }

  // Filter projects based on search and status
  const filteredProjects = projects.filter((project) => {
    const matchesSearch =
      searchQuery === '' ||
      project.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      project.description?.toLowerCase().includes(searchQuery.toLowerCase())

    const matchesStatus =
      statusFilter === 'all' || project.status === statusFilter

    return matchesSearch && matchesStatus
  })

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'active':
      case 'working':
        return 'badge-success'
      case 'planning':
      case 'new':
        return 'badge-warning'
      case 'completed':
      case 'done':
        return 'badge-info'
      case 'on_hold':
        return 'badge-neutral'
      case 'cancelled':
      case 'canceled':
        return 'badge-error'
      default:
        return 'badge-ghost'
    }
  }

  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return null
    const date = new Date(dateStr)
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  }

  if (loading || authLoading) {
    return (
      <Layout>
        <div className="flex items-center justify-center h-96">
          <span className="loading loading-spinner loading-lg"></span>
        </div>
      </Layout>
    )
  }

  return (
    <Layout>
      <div className="p-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 className="text-2xl font-semibold">Projects</h1>
            <p className="text-base-content/60 mt-1">
              {pagination ? `${pagination.total} projects` : 'Manage your projects'}
            </p>
          </div>
          <button className="btn btn-primary gap-2" onClick={handleCreateProject}>
            <Plus className="w-4 h-4" />
            New Project
          </button>
        </div>

        {/* Filters */}
        <div className="flex flex-col sm:flex-row gap-4 mb-6">
          {/* Search */}
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
            <input
              type="text"
              placeholder="Search projects..."
              className="input input-bordered w-full pl-10"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>

          {/* Status Filter */}
          <select
            className="select select-bordered w-full sm:w-48"
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
          >
            <option value="all">All Status</option>
            <option value="new">New</option>
            <option value="working">Working</option>
            <option value="active">Active</option>
            <option value="planning">Planning</option>
            <option value="on_hold">On Hold</option>
            <option value="done">Done</option>
            <option value="completed">Completed</option>
          </select>
        </div>

        {/* Projects Grid */}
        {filteredProjects.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mb-4">
              <FolderOpen className="w-8 h-8 text-base-content/40" />
            </div>
            <h3 className="text-lg font-medium mb-2">
              {searchQuery || statusFilter !== 'all'
                ? 'No projects found'
                : 'No projects yet'}
            </h3>
            <p className="text-base-content/60 max-w-md mb-4">
              {searchQuery || statusFilter !== 'all'
                ? 'Try adjusting your search or filters'
                : 'Create your first project to get started'}
            </p>
            {!searchQuery && statusFilter === 'all' && (
              <button className="btn btn-primary gap-2" onClick={handleCreateProject}>
                <Plus className="w-4 h-4" />
                Create Project
              </button>
            )}
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredProjects.map((project) => (
              <Link
                key={project.id}
                to={`/projects/${project.id}`}
                className="card bg-base-200 hover:bg-base-300/50 hover:shadow-lg transition-all cursor-pointer group h-full"
              >
                <div className="card-body p-5">
                  {/* Header with Icon and Status */}
                  <div className="flex items-start justify-between gap-3 mb-3">
                    <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center flex-shrink-0">
                      <FolderOpen className="w-5 h-5 text-primary" />
                    </div>
                    <span className={`badge badge-sm ${getStatusBadge(project.status)}`}>
                      {project.status}
                    </span>
                  </div>

                  {/* Project Name */}
                  <h3 className="font-semibold text-lg line-clamp-1 group-hover:text-primary transition-colors">
                    {project.name}
                  </h3>

                  {/* Code Badge */}
                  {project.code && (
                    <span className="badge badge-sm badge-ghost w-fit">{project.code}</span>
                  )}

                  {/* Description */}
                  {project.description && (
                    <p className="text-sm text-base-content/60 line-clamp-2 mt-1">
                      {project.description}
                    </p>
                  )}

                  {/* Stats */}
                  <div className="flex flex-wrap items-center gap-3 text-sm mt-3">
                    <div className="flex items-center gap-1 text-base-content/60">
                      <CheckCircle2 className="w-4 h-4" />
                      <span>{project.tasks_count}</span>
                    </div>

                    <div className="flex items-center gap-1 text-base-content/60">
                      <FolderOpen className="w-4 h-4" />
                      <span>{project.topics_count}</span>
                    </div>

                    {project.due_date && (
                      <div className="flex items-center gap-1 text-base-content/60">
                        <Calendar className="w-4 h-4" />
                        <span>{formatDate(project.due_date)}</span>
                      </div>
                    )}
                  </div>

                  {/* Progress Bar */}
                  {project.tasks_count > 0 && (
                    <div className="mt-3">
                      <div className="flex items-center justify-between text-xs mb-1">
                        <span className="text-base-content/60">Progress</span>
                        <span className="font-medium">
                          {project.completion_percentage || 0}%
                        </span>
                      </div>
                      <progress
                        className="progress progress-primary w-full h-2"
                        value={project.completion_percentage || 0}
                        max="100"
                      ></progress>
                    </div>
                  )}

                  {/* Team Members - at bottom */}
                  {project.members && project.members.length > 0 && (
                    <div className="flex items-center gap-2 mt-auto pt-3 border-t border-base-300">
                      <div className="flex -space-x-2">
                        {project.members.slice(0, 4).map((member) => {
                          const memberName =
                            `${member.first_name || ''} ${member.last_name || ''}`.trim() ||
                            'User'
                          return (
                            <div
                              key={member.id}
                              className="w-7 h-7 rounded-full bg-primary/20 flex items-center justify-center text-xs font-medium text-primary ring-2 ring-base-200"
                              title={memberName}
                            >
                              {memberName.charAt(0)}
                            </div>
                          )
                        })}
                        {project.members.length > 4 && (
                          <div className="w-7 h-7 rounded-full bg-base-300 flex items-center justify-center text-xs ring-2 ring-base-200">
                            +{project.members.length - 4}
                          </div>
                        )}
                      </div>
                      <span className="text-xs text-base-content/50">
                        {project.members.length} member{project.members.length !== 1 ? 's' : ''}
                      </span>
                    </div>
                  )}
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </Layout>
  )
}

