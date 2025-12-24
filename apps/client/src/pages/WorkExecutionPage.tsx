import { useState, useEffect, useMemo } from 'react'
import { Link } from 'react-router-dom'
import {
  List,
  LayoutGrid,
  Search,
  Calendar,
  CheckCircle2,
  Circle,
  Clock,
  AlertTriangle,
  X,
  Flame,
  Zap,
  User,
  MessageSquare,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
} from 'lucide-react'
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
  DragStartEvent,
  DragOverlay,
  useDroppable,
  rectIntersection,
} from '@dnd-kit/core'
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import Layout from '../components/Layout'
import TaskDetailDrawer from '../components/TaskDetailDrawer'
import { useAuthStore } from '../stores/auth'
import {
  useWorkExecutionStore,
  KANBAN_COLUMNS,
  groupTasksByStatus,
  WorkExecutionTask,
  QuickFilter,
} from '../stores/workExecution'

// Priority colors
const getPriorityColor = (priority: string) => {
  switch (priority) {
    case 'urgent':
      return 'bg-error'
    case 'high':
      return 'bg-warning'
    case 'medium':
      return 'bg-info'
    case 'low':
    default:
      return 'bg-base-content/30'
  }
}

const getPriorityLabel = (priority: string) => {
  switch (priority) {
    case 'urgent':
      return 'Urgent'
    case 'high':
      return 'High'
    case 'medium':
      return 'Medium'
    case 'low':
    default:
      return 'Low'
  }
}

// Format date helper
const formatDate = (dateStr: string | null) => {
  if (!dateStr) return null
  const date = new Date(dateStr)
  const now = new Date()
  const diffDays = Math.floor((date.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
  
  if (diffDays < 0) {
    return { text: `${Math.abs(diffDays)}d overdue`, className: 'text-error' }
  } else if (diffDays === 0) {
    return { text: 'Today', className: 'text-warning' }
  } else if (diffDays === 1) {
    return { text: 'Tomorrow', className: 'text-info' }
  } else if (diffDays <= 7) {
    return { text: `${diffDays}d`, className: 'text-base-content/60' }
  }
  
  return {
    text: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
    className: 'text-base-content/60',
  }
}

// Quick filter options
const QUICK_FILTERS: { id: QuickFilter; label: string; icon: typeof Clock }[] = [
  { id: 'all', label: 'All Tasks', icon: List },
  { id: 'today', label: 'Today', icon: Calendar },
  { id: 'this_week', label: 'This Week', icon: Clock },
  { id: 'overdue', label: 'Overdue', icon: AlertTriangle },
  { id: 'high_priority', label: 'High Priority', icon: Flame },
  { id: 'assigned_to_me', label: 'Assigned to Me', icon: User },
]

// Execution Task Card for Kanban
function ExecutionTaskCard({
  task,
  onTaskClick,
  onToggleComplete,
  isSelected,
}: {
  task: WorkExecutionTask
  onTaskClick: (task: WorkExecutionTask) => void
  onToggleComplete: (taskId: string, completed: boolean) => void
  isSelected?: boolean
}) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({
    id: task.id,
    data: { type: 'task', task },
  })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  }

  const dateInfo = formatDate(task.due_date)

  const handleCheckboxClick = (e: React.MouseEvent) => {
    e.stopPropagation()
    onToggleComplete(task.id, !task.completed)
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...attributes}
      {...listeners}
      onClick={() => onTaskClick(task)}
      className="touch-none"
    >
      <div
        className={`bg-base-100 rounded-lg p-3 border-l-4 transition-all cursor-pointer ${
          isDragging
            ? 'shadow-xl ring-2 ring-primary'
            : isSelected
              ? 'border-l-primary bg-primary/5'
              : 'border-l-transparent hover:border-l-primary/50 hover:shadow-md'
        }`}
        style={{
          borderLeftColor: task.topic?.color || 'transparent',
        }}
      >
        {/* Task Title with Checkbox */}
        <div className="flex items-start gap-2 mb-2">
          <button
            className="flex-shrink-0 mt-0.5"
            onClick={handleCheckboxClick}
            onPointerDown={(e) => e.stopPropagation()}
          >
            {task.completed ? (
              <CheckCircle2 className="w-4 h-4 text-success" />
            ) : (
              <Circle className="w-4 h-4 text-base-content/40 hover:text-primary transition-colors" />
            )}
          </button>
          <span
            className={`flex-1 text-sm font-medium line-clamp-2 ${
              task.completed ? 'line-through text-base-content/50' : ''
            }`}
          >
            {task.title}
          </span>
          <div
            className={`w-2 h-2 rounded-full flex-shrink-0 ${getPriorityColor(task.priority)}`}
            title={getPriorityLabel(task.priority)}
          />
        </div>

        {/* Project & Topic badges */}
        <div className="flex items-center gap-1.5 mb-2">
          <span className="badge badge-xs badge-ghost truncate max-w-[120px]">
            {task.project.name}
          </span>
          {task.topic && (
            <span
              className="badge badge-xs truncate max-w-[80px]"
              style={{
                backgroundColor: task.topic.color ? `${task.topic.color}20` : undefined,
                color: task.topic.color || undefined,
              }}
            >
              {task.topic.name}
            </span>
          )}
        </div>

        {/* Meta info */}
        <div className="flex items-center gap-2 flex-wrap text-xs">
          {dateInfo && (
            <span className={`flex items-center gap-1 ${dateInfo.className}`}>
              <Clock className="w-3 h-3" />
              {dateInfo.text}
            </span>
          )}

          {task.comments_count > 0 && (
            <span className="flex items-center gap-1 text-base-content/60">
              <MessageSquare className="w-3 h-3" />
              {task.comments_count}
            </span>
          )}

          {task.assignees && task.assignees.length > 0 && (
            <div className="flex -space-x-1 ml-auto">
              {task.assignees.slice(0, 2).map((assignee) => (
                <div
                  key={assignee.id}
                  className="w-5 h-5 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-medium text-primary ring-1 ring-base-100"
                  title={assignee.name}
                >
                  {assignee.name.charAt(0)}
                </div>
              ))}
              {task.assignees.length > 2 && (
                <div className="w-5 h-5 rounded-full bg-base-300 flex items-center justify-center text-[10px] ring-1 ring-base-100">
                  +{task.assignees.length - 2}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

// Drag overlay card
function DragOverlayCard({ task }: { task: WorkExecutionTask }) {
  const dateInfo = formatDate(task.due_date)

  return (
    <div
      className="bg-base-100 rounded-lg p-3 border-l-4 shadow-xl ring-2 ring-primary w-72"
      style={{ borderLeftColor: task.topic?.color || 'transparent' }}
    >
      <div className="flex items-start gap-2 mb-2">
        <Circle className="w-4 h-4 text-base-content/40 flex-shrink-0 mt-0.5" />
        <span className="flex-1 text-sm font-medium line-clamp-2">{task.title}</span>
        <div className={`w-2 h-2 rounded-full flex-shrink-0 ${getPriorityColor(task.priority)}`} />
      </div>
      <div className="flex items-center gap-1.5 mb-2">
        <span className="badge badge-xs badge-ghost truncate max-w-[120px]">
          {task.project.name}
        </span>
        {task.topic && (
          <span className="badge badge-xs truncate max-w-[80px]">
            {task.topic.name}
          </span>
        )}
      </div>
      {dateInfo && (
        <span className={`flex items-center gap-1 text-xs ${dateInfo.className}`}>
          <Clock className="w-3 h-3" />
          {dateInfo.text}
        </span>
      )}
    </div>
  )
}

// Droppable Kanban Column
function KanbanColumn({
  status,
  title,
  color,
  tasks,
  onTaskClick,
  onToggleComplete,
  selectedTaskId,
  wipLimit = 10,
}: {
  status: string
  title: string
  color: string
  tasks: WorkExecutionTask[]
  onTaskClick: (task: WorkExecutionTask) => void
  onToggleComplete: (taskId: string, completed: boolean) => void
  selectedTaskId?: string | null
  wipLimit?: number
}) {
  const { setNodeRef, isOver } = useDroppable({
    id: `column-${status}`,
    data: { type: 'column', status },
  })

  const isOverLimit = tasks.length > wipLimit
  const isDoneColumn = status === 'done'

  return (
    <div
      ref={setNodeRef}
      className={`flex-shrink-0 w-72 bg-base-200 rounded-xl overflow-hidden transition-all flex flex-col ${
        isOver ? 'ring-2 ring-primary bg-primary/5' : ''
      }`}
    >
      {/* Column Header */}
      <div className="flex items-center gap-2 p-3 border-b border-base-300">
        <div className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: color }} />
        <h3 className="font-medium text-sm flex-1">{title}</h3>
        <span
          className={`badge badge-sm ${
            isOverLimit && !isDoneColumn ? 'badge-warning' : 'badge-ghost'
          }`}
        >
          {tasks.length}
          {!isDoneColumn && wipLimit && ` / ${wipLimit}`}
        </span>
      </div>

      {/* Tasks */}
      <div className="p-2 space-y-2 flex-1 overflow-y-auto max-h-[calc(100vh-280px)]">
        <SortableContext items={tasks.map((t) => t.id)} strategy={verticalListSortingStrategy}>
          {tasks.length === 0 ? (
            <div
              className={`text-center py-8 text-base-content/40 border-2 border-dashed rounded-lg transition-colors ${
                isOver ? 'border-primary bg-primary/10' : 'border-transparent'
              }`}
            >
              <p className="text-xs">{isOver ? 'Drop here' : 'No tasks'}</p>
            </div>
          ) : (
            tasks.map((task) => (
              <ExecutionTaskCard
                key={task.id}
                task={task}
                onTaskClick={onTaskClick}
                onToggleComplete={onToggleComplete}
                isSelected={task.id === selectedTaskId}
              />
            ))
          )}
        </SortableContext>
      </div>
    </div>
  )
}

// List View Row
function ListViewRow({
  task,
  isSelected,
  onSelect,
  onTaskClick,
  onToggleComplete,
  onStatusChange,
}: {
  task: WorkExecutionTask
  isSelected: boolean
  onSelect: (taskId: string) => void
  onTaskClick: (task: WorkExecutionTask) => void
  onToggleComplete: (taskId: string, completed: boolean) => void
  onStatusChange: (taskId: string, status: string) => void
}) {
  const dateInfo = formatDate(task.due_date)
  const column = KANBAN_COLUMNS.find((c) => c.id === task.status)

  return (
    <tr
      className={`hover:bg-base-200/50 cursor-pointer transition-colors ${
        isSelected ? 'bg-primary/10' : ''
      }`}
      onClick={() => onTaskClick(task)}
    >
      {/* Checkbox */}
      <td className="w-12">
        <input
          type="checkbox"
          className="checkbox checkbox-sm"
          checked={isSelected}
          onChange={(e) => {
            e.stopPropagation()
            onSelect(task.id)
          }}
          onClick={(e) => e.stopPropagation()}
        />
      </td>

      {/* Complete toggle */}
      <td className="w-10">
        <button
          onClick={(e) => {
            e.stopPropagation()
            onToggleComplete(task.id, !task.completed)
          }}
        >
          {task.completed ? (
            <CheckCircle2 className="w-5 h-5 text-success" />
          ) : (
            <Circle className="w-5 h-5 text-base-content/40 hover:text-primary transition-colors" />
          )}
        </button>
      </td>

      {/* Task Title */}
      <td className="min-w-[200px]">
        <div className="flex items-center gap-2">
          <div
            className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${getPriorityColor(task.priority)}`}
          />
          <span
            className={`font-medium ${task.completed ? 'line-through text-base-content/50' : ''}`}
          >
            {task.title}
          </span>
        </div>
      </td>

      {/* Project / Topic */}
      <td>
        <div className="flex items-center gap-1.5">
          <span className="badge badge-sm badge-ghost">{task.project.name}</span>
          {task.topic && (
            <span
              className="badge badge-sm"
              style={{
                backgroundColor: task.topic.color ? `${task.topic.color}20` : undefined,
                color: task.topic.color || undefined,
              }}
            >
              {task.topic.name}
            </span>
          )}
        </div>
      </td>

      {/* Due Date */}
      <td>
        {dateInfo ? (
          <span className={`flex items-center gap-1 text-sm ${dateInfo.className}`}>
            <Clock className="w-3.5 h-3.5" />
            {dateInfo.text}
          </span>
        ) : (
          <span className="text-base-content/30">—</span>
        )}
      </td>

      {/* Priority */}
      <td>
        <span className={`badge badge-sm ${
          task.priority === 'urgent' ? 'badge-error' :
          task.priority === 'high' ? 'badge-warning' :
          task.priority === 'medium' ? 'badge-info' : 'badge-ghost'
        }`}>
          {getPriorityLabel(task.priority)}
        </span>
      </td>

      {/* Status */}
      <td>
        <select
          className="select select-xs select-bordered bg-transparent pr-8"
          value={task.status}
          onClick={(e) => e.stopPropagation()}
          onChange={(e) => {
            e.stopPropagation()
            onStatusChange(task.id, e.target.value)
          }}
          style={{
            borderColor: column?.color || undefined,
            color: column?.color || undefined,
          }}
        >
          {KANBAN_COLUMNS.map((col) => (
            <option key={col.id} value={col.id} style={{ color: 'inherit' }}>
              {col.title}
            </option>
          ))}
        </select>
      </td>

      {/* Assignees */}
      <td>
        {task.assignees.length > 0 ? (
          <div className="flex -space-x-1">
            {task.assignees.slice(0, 3).map((assignee) => (
              <div
                key={assignee.id}
                className="w-6 h-6 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-medium text-primary ring-1 ring-base-100"
                title={assignee.name}
              >
                {assignee.name.charAt(0)}
              </div>
            ))}
            {task.assignees.length > 3 && (
              <div className="w-6 h-6 rounded-full bg-base-300 flex items-center justify-center text-[10px] ring-1 ring-base-100">
                +{task.assignees.length - 3}
              </div>
            )}
          </div>
        ) : (
          <span className="text-base-content/30">—</span>
        )}
      </td>
    </tr>
  )
}

export default function WorkExecutionPage() {
  const { company, isLoading: authLoading } = useAuthStore()
  const {
    tasks,
    projects,
    viewMode,
    filters,
    sort,
    selectedTaskIds,
    isLoading,
    pagination,
    setViewMode,
    setFilters,
    setSort,
    setQuickFilter,
    toggleTaskSelection,
    selectAllTasks,
    clearSelection,
    fetchTasks,
    fetchProjects,
    updateTaskStatus,
    toggleTaskComplete,
    bulkUpdateStatus,
  } = useWorkExecutionStore()

  const [selectedTask, setSelectedTask] = useState<WorkExecutionTask | null>(null)
  const [activeDragTask, setActiveDragTask] = useState<WorkExecutionTask | null>(null)
  const [searchInput, setSearchInput] = useState(filters.search)

  // DnD sensors
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  )

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchInput !== filters.search) {
        setFilters({ search: searchInput })
      }
    }, 300)
    return () => clearTimeout(timer)
  }, [searchInput])

  // Fetch data on mount and when filters change
  useEffect(() => {
    if (authLoading) return
    if (company?.id) {
      fetchTasks(company.id)
      fetchProjects(company.id)
    }
  }, [company?.id, authLoading, filters, sort])

  // Group tasks by status for Kanban
  const tasksByStatus = useMemo(() => groupTasksByStatus(tasks), [tasks])

  // Handle drag start
  const handleDragStart = (event: DragStartEvent) => {
    const taskId = event.active.id as string
    const task = tasks.find((t) => t.id === taskId)
    if (task) {
      setActiveDragTask(task)
    }
  }

  // Handle drag end
  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event
    setActiveDragTask(null)

    if (!over || !company?.id) return

    const taskId = active.id as string
    const overId = over.id as string

    // Dropped on a column
    if (overId.startsWith('column-')) {
      const newStatus = overId.replace('column-', '')
      const task = tasks.find((t) => t.id === taskId)

      if (task && task.status !== newStatus) {
        await updateTaskStatus(taskId, newStatus, company.id)
      }
    } else {
      // Dropped on another task - get that task's column
      const overTask = tasks.find((t) => t.id === overId)
      if (overTask) {
        const task = tasks.find((t) => t.id === taskId)
        if (task && task.status !== overTask.status) {
          await updateTaskStatus(taskId, overTask.status, company.id)
        }
      }
    }
  }

  // Handle task click
  const handleTaskClick = (task: WorkExecutionTask) => {
    setSelectedTask(task)
  }

  // Handle toggle complete
  const handleToggleComplete = async (taskId: string, completed: boolean) => {
    if (!company?.id) return
    await toggleTaskComplete(taskId, completed, company.id)
  }

  // Handle bulk status update
  const handleBulkStatusUpdate = async (status: string) => {
    if (!company?.id || selectedTaskIds.length === 0) return
    await bulkUpdateStatus(selectedTaskIds, status, company.id)
  }

  // Sort handler for list view
  const handleSort = (column: typeof sort.sortBy) => {
    if (sort.sortBy === column) {
      setSort({ sortOrder: sort.sortOrder === 'asc' ? 'desc' : 'asc' })
    } else {
      setSort({ sortBy: column, sortOrder: 'asc' })
    }
  }

  const getSortIcon = (column: string) => {
    if (sort.sortBy !== column) return <ArrowUpDown className="w-3 h-3" />
    return sort.sortOrder === 'asc' ? (
      <ArrowUp className="w-3 h-3" />
    ) : (
      <ArrowDown className="w-3 h-3" />
    )
  }

  if (isLoading && tasks.length === 0) {
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
      <div className="h-full flex flex-col">
        {/* Header */}
        <div className="border-b border-base-300 bg-base-100 sticky top-0 z-20 px-6 py-4">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-primary to-accent flex items-center justify-center">
                <Zap className="w-5 h-5 text-primary-content" />
              </div>
              <div>
                <h1 className="text-xl font-semibold">Work Execution</h1>
                <p className="text-sm text-base-content/60">
                  {pagination?.total || 0} tasks across all projects
                </p>
              </div>
            </div>

            {/* View Toggle */}
            <div className="flex items-center gap-3">
              <div className="join">
                <button
                  className={`join-item btn btn-sm ${viewMode === 'kanban' ? 'btn-primary' : 'btn-ghost'}`}
                  onClick={() => setViewMode('kanban')}
                >
                  <LayoutGrid className="w-4 h-4" />
                  <span className="hidden sm:inline ml-1">Kanban</span>
                </button>
                <button
                  className={`join-item btn btn-sm ${viewMode === 'list' ? 'btn-primary' : 'btn-ghost'}`}
                  onClick={() => setViewMode('list')}
                >
                  <List className="w-4 h-4" />
                  <span className="hidden sm:inline ml-1">List</span>
                </button>
              </div>
            </div>
          </div>

          {/* Filter Bar */}
          <div className="flex flex-wrap items-center gap-3">
            {/* Search */}
            <div className="relative flex-1 min-w-[200px] max-w-md">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
              <input
                type="text"
                placeholder="Search tasks..."
                className="input input-bordered input-sm w-full pl-9"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
              />
              {searchInput && (
                <button
                  className="absolute right-2 top-1/2 -translate-y-1/2"
                  onClick={() => setSearchInput('')}
                >
                  <X className="w-4 h-4 text-base-content/40 hover:text-base-content" />
                </button>
              )}
            </div>

            {/* Quick Filters */}
            <div className="flex items-center gap-1 flex-wrap">
              {QUICK_FILTERS.map((qf) => {
                const Icon = qf.icon
                return (
                  <button
                    key={qf.id}
                    className={`btn btn-xs ${
                      filters.quickFilter === qf.id ? 'btn-primary' : 'btn-ghost'
                    }`}
                    onClick={() => setQuickFilter(qf.id)}
                  >
                    <Icon className="w-3 h-3" />
                    <span className="hidden lg:inline">{qf.label}</span>
                  </button>
                )
              })}
            </div>

            {/* Project Filter */}
            <select
              className="select select-bordered select-sm w-40"
              value={filters.projectId || ''}
              onChange={(e) => setFilters({ projectId: e.target.value || null })}
            >
              <option value="">All Projects</option>
              {projects.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>

            {/* Exclude Completed Toggle */}
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                className="checkbox checkbox-sm checkbox-primary"
                checked={filters.excludeCompleted}
                onChange={(e) => setFilters({ excludeCompleted: e.target.checked })}
              />
              <span className="text-sm">Hide done</span>
            </label>
          </div>

          {/* Bulk Actions (List View) */}
          {viewMode === 'list' && selectedTaskIds.length > 0 && (
            <div className="flex items-center gap-3 mt-3 p-3 bg-primary/10 rounded-lg">
              <span className="text-sm font-medium">
                {selectedTaskIds.length} selected
              </span>
              <div className="flex items-center gap-2">
                {KANBAN_COLUMNS.filter((c) => c.id !== 'done').map((col) => (
                  <button
                    key={col.id}
                    className="btn btn-xs btn-ghost"
                    onClick={() => handleBulkStatusUpdate(col.id)}
                  >
                    Move to {col.title}
                  </button>
                ))}
                <button
                  className="btn btn-xs btn-success"
                  onClick={() => handleBulkStatusUpdate('done')}
                >
                  Mark Done
                </button>
              </div>
              <button className="btn btn-xs btn-ghost ml-auto" onClick={clearSelection}>
                Clear
              </button>
            </div>
          )}
        </div>

        {/* Content */}
        <div className="flex-1 overflow-hidden">
          {tasks.length === 0 && !isLoading ? (
            <div className="flex flex-col items-center justify-center h-full text-center px-4">
              <div className="w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mb-4">
                <CheckCircle2 className="w-8 h-8 text-base-content/40" />
              </div>
              <h3 className="text-lg font-medium mb-2">No tasks found</h3>
              <p className="text-base-content/60 max-w-md mb-4">
                {filters.search || filters.quickFilter !== 'all' || filters.projectId
                  ? 'Try adjusting your filters'
                  : 'Create tasks in your projects to see them here'}
              </p>
              <Link to="/projects" className="btn btn-primary">
                Go to Projects
              </Link>
            </div>
          ) : viewMode === 'kanban' ? (
            /* Kanban View */
            <DndContext
              sensors={sensors}
              collisionDetection={rectIntersection}
              onDragStart={handleDragStart}
              onDragEnd={handleDragEnd}
            >
              <div className="h-full overflow-x-auto p-4">
                <div className="flex gap-4 h-full min-w-max">
                  {KANBAN_COLUMNS.map((col) => (
                    <KanbanColumn
                      key={col.id}
                      status={col.id}
                      title={col.title}
                      color={col.color}
                      tasks={tasksByStatus[col.id] || []}
                      onTaskClick={handleTaskClick}
                      onToggleComplete={handleToggleComplete}
                      selectedTaskId={selectedTask?.id}
                      wipLimit={col.id === 'done' ? undefined : 10}
                    />
                  ))}
                </div>
              </div>

              {/* Drag Overlay */}
              <DragOverlay>
                {activeDragTask ? <DragOverlayCard task={activeDragTask} /> : null}
              </DragOverlay>
            </DndContext>
          ) : (
            /* List View */
            <div className="h-full overflow-auto p-4">
              <table className="table table-sm w-full">
                <thead className="bg-base-200 sticky top-0 z-10">
                  <tr>
                    <th className="w-12">
                      <input
                        type="checkbox"
                        className="checkbox checkbox-sm"
                        checked={selectedTaskIds.length === tasks.length && tasks.length > 0}
                        onChange={(e) => {
                          if (e.target.checked) {
                            selectAllTasks()
                          } else {
                            clearSelection()
                          }
                        }}
                      />
                    </th>
                    <th className="w-10"></th>
                    <th>
                      <button
                        className="flex items-center gap-1 hover:text-primary"
                        onClick={() => handleSort('title')}
                      >
                        Task {getSortIcon('title')}
                      </button>
                    </th>
                    <th>Project / Topic</th>
                    <th>
                      <button
                        className="flex items-center gap-1 hover:text-primary"
                        onClick={() => handleSort('due_date')}
                      >
                        Due {getSortIcon('due_date')}
                      </button>
                    </th>
                    <th>
                      <button
                        className="flex items-center gap-1 hover:text-primary"
                        onClick={() => handleSort('priority')}
                      >
                        Priority {getSortIcon('priority')}
                      </button>
                    </th>
                    <th>
                      <button
                        className="flex items-center gap-1 hover:text-primary"
                        onClick={() => handleSort('status')}
                      >
                        Status {getSortIcon('status')}
                      </button>
                    </th>
                    <th>Assignee</th>
                  </tr>
                </thead>
                <tbody>
                  {tasks.map((task) => (
                    <ListViewRow
                      key={task.id}
                      task={task}
                      isSelected={selectedTaskIds.includes(task.id)}
                      onSelect={toggleTaskSelection}
                      onTaskClick={handleTaskClick}
                      onToggleComplete={handleToggleComplete}
                      onStatusChange={(taskId, status) => {
                        if (company?.id) {
                          updateTaskStatus(taskId, status, company.id)
                        }
                      }}
                    />
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Task Detail Drawer */}
      <TaskDetailDrawer
        task={
          selectedTask
            ? {
                ...selectedTask,
                topic_id: selectedTask.topic?.id || '',
                project_id: selectedTask.project.id,
                content: null,
              }
            : null
        }
        isOpen={!!selectedTask}
        onClose={() => setSelectedTask(null)}
        onUpdateTask={(taskId, data) => {
          // Update task in store
          const store = useWorkExecutionStore.getState()
          store.updateTaskLocally(taskId, data)
          if (selectedTask?.id === taskId) {
            setSelectedTask((prev) => (prev ? { ...prev, ...data } : null))
          }
        }}
        projectMembers={[]}
      />
    </Layout>
  )
}

