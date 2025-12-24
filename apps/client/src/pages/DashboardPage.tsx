import { useEffect } from 'react'
import {
  CheckSquare,
  Folder,
  Users,
  Clock,
  AlertTriangle,
} from 'lucide-react'
import { Link } from 'react-router-dom'
import Layout from '../components/Layout'
import { useAuthStore } from '../stores/auth'
import { useDashboardStore } from '../stores/dashboard'

export default function DashboardPage() {
  const { user, company } = useAuthStore()
  const {
    tasks,
    projects,
    contactStats,
    isLoadingTasks,
    isLoadingProjects,
    isLoadingContacts,
    tasksPagination,
    projectsPagination,
    fetchDashboardData,
  } = useDashboardStore()

  // Fetch dashboard data when company changes
  useEffect(() => {
    if (company?.id) {
      fetchDashboardData(company.id, !!company)
    }
  }, [company?.id, fetchDashboardData])

  // Calculate stats
  const pendingTasks = tasks.filter((t) => !t.completed && t.status !== 'done').length
  const activeProjects = projects.filter((p) => p.status === 'active').length
  const totalContacts = contactStats?.total ?? 0

  // Get overdue tasks
  const overdueTasks = tasks.filter((t) => {
    if (!t.due_date || t.completed) return false
    return new Date(t.due_date) < new Date()
  })

  // Get tasks due soon (next 7 days)
  const dueSoonTasks = tasks.filter((t) => {
    if (!t.due_date || t.completed) return false
    const dueDate = new Date(t.due_date)
    const now = new Date()
    const weekFromNow = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000)
    return dueDate >= now && dueDate <= weekFromNow
  })

  const getStatusBadgeClass = (status: string) => {
    switch (status) {
      case 'backlog':
        return 'badge-ghost'
      case 'todo':
        return 'badge-warning'
      case 'in_progress':
        return 'badge-info'
      case 'on_hold':
        return 'badge-error'
      case 'in_review':
        return 'badge-secondary'
      case 'done':
        return 'badge-success'
      default:
        return 'badge-ghost'
    }
  }


  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return null
    const date = new Date(dateStr)
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }

  return (
    <Layout>
      {/* Dashboard content */}
      <div className="p-6">
        {/* Welcome header */}
        <div className="mb-8">
          <h1 className="text-2xl font-semibold mb-2">
            Welcome back, {user?.first_name || 'User'}!
          </h1>
          <p className="text-base-content/60">Here's what's happening with your account today.</p>
        </div>


        {/* Quick Stats */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
          <div className="stat bg-base-200 rounded-box">
            <div className="stat-figure text-primary">
              {isLoadingTasks ? (
                <span className="loading loading-spinner loading-md"></span>
              ) : (
                <CheckSquare className="w-8 h-8" />
              )}
            </div>
            <div className="stat-title">Tasks</div>
            <div className="stat-value text-primary">
              {isLoadingTasks ? '-' : pendingTasks}
            </div>
            <div className="stat-desc">
              {tasksPagination ? `of ${tasksPagination.total} total` : 'Pending'}
            </div>
          </div>

          <div className="stat bg-base-200 rounded-box">
            <div className="stat-figure text-secondary">
              {isLoadingProjects ? (
                <span className="loading loading-spinner loading-md"></span>
              ) : (
                <Folder className="w-8 h-8" />
              )}
            </div>
            <div className="stat-title">Projects</div>
            <div className="stat-value text-secondary">
              {isLoadingProjects ? '-' : activeProjects}
            </div>
            <div className="stat-desc">
              {projectsPagination ? `of ${projectsPagination.total} total` : 'Active'}
            </div>
          </div>

          <div className="stat bg-base-200 rounded-box">
            <div className="stat-figure text-accent">
              {isLoadingContacts ? (
                <span className="loading loading-spinner loading-md"></span>
              ) : (
                <Users className="w-8 h-8" />
              )}
            </div>
            <div className="stat-title">Contacts</div>
            <div className="stat-value text-accent">
              {isLoadingContacts ? '-' : totalContacts}
            </div>
            <div className="stat-desc">
              {contactStats ? `${contactStats.customers} customers` : 'Total'}
            </div>
          </div>

          <div className="stat bg-base-200 rounded-box">
            <div className="stat-figure text-warning">
              {isLoadingTasks ? (
                <span className="loading loading-spinner loading-md"></span>
              ) : (
                <Clock className="w-8 h-8" />
              )}
            </div>
            <div className="stat-title">Due Soon</div>
            <div className="stat-value text-warning">
              {isLoadingTasks ? '-' : dueSoonTasks.length}
            </div>
            <div className="stat-desc">Next 7 days</div>
          </div>
        </div>

        {/* Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Recent Tasks */}
          <div className="card bg-base-200">
            <div className="card-body">
              <div className="flex items-center justify-between mb-4">
                <h3 className="card-title text-lg">
                  <CheckSquare className="w-5 h-5" />
                  Recent Tasks
                </h3>
                <Link to="/tasks" className="btn btn-ghost btn-sm">
                  View All
                </Link>
              </div>

              {isLoadingTasks ? (
                <div className="flex items-center justify-center py-8">
                  <span className="loading loading-spinner loading-lg"></span>
                </div>
              ) : tasks.length === 0 ? (
                <div className="text-center py-8 text-base-content/50">
                  <CheckSquare className="w-12 h-12 mx-auto mb-2 opacity-50" />
                  <p>No tasks found</p>
                </div>
              ) : (
                <div className="space-y-2">
                  {tasks.slice(0, 5).map((task) => (
                    <div
                      key={task.id}
                      className="flex items-center gap-3 p-3 rounded-lg bg-base-100 hover:bg-base-300/50 transition-colors"
                    >
                      <div
                        className={`w-2 h-2 rounded-full ${
                          task.completed
                            ? 'bg-success'
                            : task.priority === 'urgent'
                              ? 'bg-error'
                              : task.priority === 'high'
                                ? 'bg-warning'
                                : 'bg-info'
                        }`}
                      ></div>
                      <div className="flex-1 min-w-0">
                        <p
                          className={`font-medium truncate ${task.completed ? 'line-through text-base-content/50' : ''}`}
                        >
                          {task.title}
                        </p>
                        <p className="text-sm text-base-content/50 truncate">{task.project.name}</p>
                      </div>
                      <div className="flex items-center gap-2">
                        {task.due_date && (
                          <span
                            className={`text-xs ${
                              new Date(task.due_date) < new Date() && !task.completed
                                ? 'text-error'
                                : 'text-base-content/50'
                            }`}
                          >
                            {formatDate(task.due_date)}
                          </span>
                        )}
                        <span className={`badge badge-sm ${getStatusBadgeClass(task.status)}`}>
                          {task.status.replace('_', ' ')}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Projects */}
          <div className="card bg-base-200">
            <div className="card-body">
              <div className="flex items-center justify-between mb-4">
                <h3 className="card-title text-lg">
                  <Folder className="w-5 h-5" />
                  Projects
                </h3>
              </div>

              {isLoadingProjects ? (
                <div className="flex items-center justify-center py-8">
                  <span className="loading loading-spinner loading-lg"></span>
                </div>
              ) : projects.length === 0 ? (
                <div className="text-center py-8 text-base-content/50">
                  <Folder className="w-12 h-12 mx-auto mb-2 opacity-50" />
                  <p>No projects found</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {projects.map((project) => (
                    <Link
                      key={project.id}
                      to={`/projects/${project.id}`}
                      className="block p-3 rounded-lg bg-base-100 hover:bg-base-300/50 transition-colors cursor-pointer"
                    >
                      <div className="flex items-center justify-between mb-2">
                        <p className="font-medium truncate">{project.name}</p>
                        <span
                          className={`badge badge-sm ${
                            project.status === 'active'
                              ? 'badge-success'
                              : project.status === 'planning'
                                ? 'badge-warning'
                                : project.status === 'completed'
                                  ? 'badge-info'
                                  : 'badge-ghost'
                          }`}
                        >
                          {project.status}
                        </span>
                      </div>
                      <div className="flex items-center justify-between text-sm text-base-content/50">
                        <span>
                          {project.tasks_count} tasks • {project.topics_count} topics
                        </span>
                        {project.completion_percentage !== undefined && (
                          <span>{project.completion_percentage}% complete</span>
                        )}
                      </div>
                      {project.tasks_count > 0 && (
                        <progress
                          className="progress progress-primary w-full h-1 mt-2"
                          value={project.completion_percentage || 0}
                          max="100"
                        ></progress>
                      )}
                    </Link>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Overdue Tasks Alert */}
          {overdueTasks.length > 0 && (
            <div className="card bg-error/10 border border-error/30">
              <div className="card-body">
                <h3 className="card-title text-lg text-error">
                  <AlertTriangle className="w-5 h-5" />
                  Overdue Tasks ({overdueTasks.length})
                </h3>
                <div className="space-y-2 mt-2">
                  {overdueTasks.slice(0, 3).map((task) => (
                    <div key={task.id} className="flex items-center gap-3 p-2 rounded bg-base-100">
                      <div className="flex-1 min-w-0">
                        <p className="font-medium truncate">{task.title}</p>
                        <p className="text-sm text-base-content/50">{task.project.name}</p>
                      </div>
                      <span className="text-sm text-error">{formatDate(task.due_date)}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

        </div>
      </div>
    </Layout>
  )
}
