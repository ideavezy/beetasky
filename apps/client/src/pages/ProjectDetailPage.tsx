import { useState, useEffect, useRef } from 'react'
import { useParams, Link } from 'react-router-dom'
import {
  ChevronLeft,
  ChevronDown,
  ChevronUp,
  List,
  LayoutGrid,
  Plus,
  MoreVertical,
  Calendar,
  Users,
  CheckCircle2,
  Circle,
  Clock,
  GripVertical,
  MessageSquare,
  Paperclip,
  AlertCircle,
  Pencil,
  Trash2,
} from 'lucide-react'
import {
  DndContext,
  closestCenter,
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
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import Layout from '../components/Layout'
import TaskDetailDrawer from '../components/TaskDetailDrawer'
import { api } from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { useModalStore, MODAL_NAMES } from '../stores/modal'

type ViewMode = 'list' | 'kanban'

interface Assignee {
  id: string
  name: string
  avatar: string | null
}

interface Task {
  id: string
  title: string
  description: string | null
  status: string
  priority: string // 'low' | 'medium' | 'high' | 'urgent'
  completed: boolean
  due_date: string | null
  order: number
  assignees: Assignee[]
  comments_count?: number
  attachments_count?: number
}

interface Topic {
  id: string
  name: string
  description: string | null
  color: string | null
  position: number
  is_locked: boolean
  tasks: Task[]
  completion_percentage: number
}

interface Member {
  id: string
  first_name: string
  last_name: string | null
  avatar_url: string | null
  pivot?: {
    role: string
  }
}

interface Project {
  id: string
  name: string
  description: string | null
  status: string
  code: string | null
  due_date: string | null
  members: Member[]
  topics: Topic[]
  tasks_count: number
  topics_count: number
}

// Helper functions for task display
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

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'done':
      return 'badge-success'
    case 'working':
      return 'badge-info'
    case 'in_review':
      return 'badge-warning'
    case 'on_hold':
      return 'badge-neutral'
    case 'question':
      return 'badge-error'
    default:
      return 'badge-ghost'
  }
}

// Task Card Content Component (shared between draggable and overlay)
function TaskCardContent({ 
  task, 
  isDragging = false,
  isSelected = false,
}: { 
  task: Task
  isDragging?: boolean
  isSelected?: boolean
}) {
  return (
    <div className={`bg-base-100 rounded-lg p-3 border transition-all ${
      isDragging 
        ? 'shadow-xl ring-2 ring-primary opacity-90' 
        : isSelected 
          ? 'border-primary bg-primary/5' 
          : 'border-base-300 hover:border-primary/50 hover:shadow-md cursor-pointer'
    }`}>
      <div className="flex items-start gap-2">
        <div className="flex-1 min-w-0">
          <div className="flex items-start gap-2 mb-2">
            <div className="flex-shrink-0 mt-0.5">
              {task.completed ? (
                <CheckCircle2 className="w-5 h-5 text-success" />
              ) : (
                <Circle className="w-5 h-5 text-base-content/40" />
              )}
            </div>
            <span
              className={`flex-1 text-sm font-medium ${task.completed ? 'line-through text-base-content/50' : ''}`}
            >
              {task.title}
            </span>
            <div
              className={`w-2 h-2 rounded-full flex-shrink-0 ${getPriorityColor(task.priority)}`}
              title={getPriorityLabel(task.priority)}
            />
          </div>

          <div className="flex items-center gap-2 flex-wrap ml-7">
            {task.due_date && (
              <span className="flex items-center gap-1 text-xs text-base-content/60">
                <Calendar className="w-3 h-3" />
                {new Date(task.due_date).toLocaleDateString('en-US', {
                  month: 'short',
                  day: 'numeric',
                })}
              </span>
            )}

            {task.comments_count && task.comments_count > 0 && (
              <span className="flex items-center gap-1 text-xs text-base-content/60">
                <MessageSquare className="w-3 h-3" />
                {task.comments_count}
              </span>
            )}

            {task.attachments_count && task.attachments_count > 0 && (
              <span className="flex items-center gap-1 text-xs text-base-content/60">
                <Paperclip className="w-3 h-3" />
                {task.attachments_count}
              </span>
            )}

            {task.assignees && task.assignees.length > 0 && (
              <div className="flex -space-x-1 ml-auto">
                {task.assignees.slice(0, 3).map((assignee) => {
                  const assigneeName = assignee.name || 'User'
                  return (
                    <div
                      key={assignee.id}
                      className="w-5 h-5 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-medium text-primary ring-1 ring-base-100"
                      title={assigneeName}
                    >
                      {assigneeName.charAt(0)}
                    </div>
                  )
                })}
                {task.assignees.length > 3 && (
                  <div className="w-5 h-5 rounded-full bg-base-300 flex items-center justify-center text-[10px] ring-1 ring-base-100">
                    +{task.assignees.length - 3}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

// Draggable Kanban Task Card using useSortable
function DraggableKanbanTaskCard({
  task,
  onTaskClick,
  onToggleComplete,
  isSelected,
}: {
  task: Task
  onTaskClick: (task: Task) => void
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
    data: {
      type: 'task',
      task,
    }
  })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  }

  const handleCardClick = () => {
    // Don't trigger click when dragging
    if (isDragging) return
    onTaskClick(task)
  }

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
      onClick={handleCardClick}
      className="touch-none"
    >
      <div className={`bg-base-100 rounded-lg p-3 border transition-all ${
        isDragging 
          ? 'shadow-xl ring-2 ring-primary' 
          : isSelected 
            ? 'border-primary bg-primary/5' 
            : 'border-base-300 hover:border-primary/50 hover:shadow-md cursor-pointer'
      }`}>
        <div className="flex items-start gap-2">
          <div className="flex-1 min-w-0">
            <div className="flex items-start gap-2 mb-2">
              <button 
                className="flex-shrink-0 mt-0.5"
                onClick={handleCheckboxClick}
                onPointerDown={(e) => e.stopPropagation()}
              >
                {task.completed ? (
                  <CheckCircle2 className="w-5 h-5 text-success" />
                ) : (
                  <Circle className="w-5 h-5 text-base-content/40 hover:text-primary transition-colors" />
                )}
              </button>
              <span
                className={`flex-1 text-sm font-medium ${task.completed ? 'line-through text-base-content/50' : ''}`}
              >
                {task.title}
              </span>
              <div
                className={`w-2 h-2 rounded-full flex-shrink-0 ${getPriorityColor(task.priority)}`}
                title={getPriorityLabel(task.priority)}
              />
            </div>

            <div className="flex items-center gap-2 flex-wrap ml-7">
              {task.due_date && (
                <span className="flex items-center gap-1 text-xs text-base-content/60">
                  <Calendar className="w-3 h-3" />
                  {new Date(task.due_date).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                  })}
                </span>
              )}

              {task.comments_count && task.comments_count > 0 && (
                <span className="flex items-center gap-1 text-xs text-base-content/60">
                  <MessageSquare className="w-3 h-3" />
                  {task.comments_count}
                </span>
              )}

              {task.attachments_count && task.attachments_count > 0 && (
                <span className="flex items-center gap-1 text-xs text-base-content/60">
                  <Paperclip className="w-3 h-3" />
                  {task.attachments_count}
                </span>
              )}

              {task.assignees && task.assignees.length > 0 && (
                <div className="flex -space-x-1 ml-auto">
                  {task.assignees.slice(0, 3).map((assignee) => {
                    const assigneeName = assignee.name || 'User'
                    return (
                      <div
                        key={assignee.id}
                        className="w-5 h-5 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-medium text-primary ring-1 ring-base-100"
                        title={assigneeName}
                      >
                        {assigneeName.charAt(0)}
                      </div>
                    )
                  })}
                  {task.assignees.length > 3 && (
                    <div className="w-5 h-5 rounded-full bg-base-300 flex items-center justify-center text-[10px] ring-1 ring-base-100">
                      +{task.assignees.length - 3}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

// Sortable Task Item Component
function SortableTaskCard({
  task,
  onToggleComplete,
  onTaskClick,
  isSelected,
}: {
  task: Task
  onToggleComplete: (taskId: string, completed: boolean) => void
  onTaskClick: (task: Task) => void
  isSelected?: boolean
}) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: task.id })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    zIndex: isDragging ? 1000 : 'auto',
  }

  const handleCheckboxClick = (e: React.MouseEvent) => {
    e.stopPropagation()
    onToggleComplete(task.id, !task.completed)
  }

  const handleCardClick = () => {
    onTaskClick(task)
  }

  return (
    <div
      ref={setNodeRef}
      style={style as React.CSSProperties}
      onClick={handleCardClick}
      className={`group bg-base-100 rounded-lg p-3 border transition-all cursor-pointer ${
        isDragging ? 'shadow-xl ring-2 ring-primary' : ''
      } ${isSelected ? 'border-primary bg-primary/5' : 'border-base-300 hover:border-primary/50 hover:shadow-md'}`}
    >
      <div className="flex items-start gap-2">
        <div
          {...attributes}
          {...listeners}
          className="opacity-0 group-hover:opacity-100 transition-opacity cursor-grab active:cursor-grabbing pt-1 touch-none"
          onClick={(e) => e.stopPropagation()}
        >
          <GripVertical className="w-4 h-4 text-base-content/40 hover:text-base-content" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-start gap-2 mb-2">
            <button
              className="flex-shrink-0 mt-0.5"
              onClick={handleCheckboxClick}
            >
              {task.completed ? (
                <CheckCircle2 className="w-5 h-5 text-success" />
              ) : (
                <Circle className="w-5 h-5 text-base-content/40 hover:text-primary transition-colors" />
              )}
            </button>
            <span
              className={`flex-1 text-sm font-medium ${task.completed ? 'line-through text-base-content/50' : ''}`}
            >
              {task.title}
            </span>
            <div
              className={`w-2 h-2 rounded-full flex-shrink-0 ${getPriorityColor(task.priority)}`}
              title={getPriorityLabel(task.priority)}
            />
          </div>

          <div className="flex items-center gap-2 flex-wrap ml-7">
            <span className={`badge badge-xs ${getStatusBadge(task.status)}`}>
              {task.status.replace('_', ' ')}
            </span>

            {task.due_date && (
              <span className="flex items-center gap-1 text-xs text-base-content/60">
                <Calendar className="w-3 h-3" />
                {new Date(task.due_date).toLocaleDateString('en-US', {
                  month: 'short',
                  day: 'numeric',
                })}
              </span>
            )}

            {task.comments_count && task.comments_count > 0 && (
              <span className="flex items-center gap-1 text-xs text-base-content/60">
                <MessageSquare className="w-3 h-3" />
                {task.comments_count}
              </span>
            )}

            {task.attachments_count && task.attachments_count > 0 && (
              <span className="flex items-center gap-1 text-xs text-base-content/60">
                <Paperclip className="w-3 h-3" />
                {task.attachments_count}
              </span>
            )}

            {task.assignees && task.assignees.length > 0 && (
              <div className="flex -space-x-1 ml-auto">
                {task.assignees.slice(0, 3).map((assignee) => {
                  const assigneeName = assignee.name || 'User'
                  return (
                    <div
                      key={assignee.id}
                      className="w-5 h-5 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-medium text-primary ring-1 ring-base-100"
                      title={assigneeName}
                    >
                      {assigneeName.charAt(0)}
                    </div>
                  )
                })}
                {task.assignees.length > 3 && (
                  <div className="w-5 h-5 rounded-full bg-base-300 flex items-center justify-center text-[10px] ring-1 ring-base-100">
                    +{task.assignees.length - 3}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

// Sortable Topic Column for List View
function SortableTopicSection({
  topic,
  onAddTask,
  onTaskReorder,
  onToggleTaskComplete,
  onTaskClick,
  onEditTopic,
  onDeleteTopic,
  selectedTaskId,
  taskSensors,
}: {
  topic: Topic
  onAddTask: (topicId: string, topicName: string) => void
  onTaskReorder: (topicId: string, oldIndex: number, newIndex: number) => void
  onToggleTaskComplete: (taskId: string, completed: boolean) => void
  onTaskClick: (task: Task) => void
  onEditTopic: (topic: Topic) => void
  onDeleteTopic: (topicId: string) => void
  selectedTaskId?: string | null
  taskSensors: ReturnType<typeof useSensors>
}) {
  const [isCollapsed, setIsCollapsed] = useState(false)
  const [showDropdown, setShowDropdown] = useState(false)
  const dropdownRef = useRef<HTMLDivElement>(null)
  const completedTasks = topic.tasks.filter((t) => t.completed).length
  const sortedTasks = [...topic.tasks].sort((a, b) => a.order - b.order)

  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: topic.id })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    zIndex: isDragging ? 1000 : 'auto',
  }

  const handleAddTask = (e: React.MouseEvent) => {
    e.stopPropagation()
    onAddTask(topic.id, topic.name)
  }

  const handleTaskDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    if (!over || active.id === over.id) return

    const oldIndex = sortedTasks.findIndex((t) => t.id === active.id)
    const newIndex = sortedTasks.findIndex((t) => t.id === over.id)

    if (oldIndex !== -1 && newIndex !== -1) {
      onTaskReorder(topic.id, oldIndex, newIndex)
    }
  }

  const handleDropdownToggle = (e: React.MouseEvent) => {
    e.stopPropagation()
    setShowDropdown(!showDropdown)
  }

  const handleEdit = (e: React.MouseEvent) => {
    e.stopPropagation()
    setShowDropdown(false)
    onEditTopic(topic)
  }

  const handleDelete = (e: React.MouseEvent) => {
    e.stopPropagation()
    setShowDropdown(false)
    onDeleteTopic(topic.id)
  }

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setShowDropdown(false)
      }
    }

    if (showDropdown) {
      document.addEventListener('mousedown', handleClickOutside)
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside)
    }
  }, [showDropdown])

  return (
    <div
      ref={setNodeRef}
      style={style as React.CSSProperties}
      className={`bg-base-200 rounded-xl overflow-hidden ${isDragging ? 'shadow-2xl ring-2 ring-primary' : ''}`}
    >
      {/* Topic Header */}
      <div
        className="flex items-center gap-3 p-4 cursor-pointer hover:bg-base-300/50 transition-colors"
        onClick={() => setIsCollapsed(!isCollapsed)}
      >
        <div
          {...attributes}
          {...listeners}
          className="cursor-grab active:cursor-grabbing touch-none"
          onClick={(e) => e.stopPropagation()}
        >
          <GripVertical className="w-5 h-5 text-base-content/40 hover:text-base-content" />
        </div>
        <div
          className="w-3 h-3 rounded-full flex-shrink-0"
          style={{ backgroundColor: topic.color || '#6b7280' }}
        />
        <h3 className="font-semibold text-base-content flex-1">{topic.name}</h3>
        <span className="text-sm text-base-content/60">
          {completedTasks}/{topic.tasks.length} tasks
        </span>
        <div className="w-24">
          <progress
            className="progress progress-primary h-2"
            value={topic.completion_percentage || 0}
            max="100"
          />
        </div>
        {/* Collapse/Expand Arrow */}
        <button
          className="btn btn-ghost btn-xs btn-square"
          onClick={(e) => {
            e.stopPropagation()
            setIsCollapsed(!isCollapsed)
          }}
          title={isCollapsed ? 'Expand' : 'Collapse'}
        >
          {isCollapsed ? (
            <ChevronDown className="w-4 h-4" />
          ) : (
            <ChevronUp className="w-4 h-4" />
          )}
        </button>
        {/* Dropdown Menu */}
        <div className="relative" ref={dropdownRef}>
          <button
            className="btn btn-ghost btn-xs btn-square"
            onClick={handleDropdownToggle}
          >
            <MoreVertical className="w-4 h-4" />
          </button>
          {showDropdown && (
            <div className="absolute right-0 top-full mt-1 w-36 bg-base-100 rounded-lg shadow-xl border border-base-300 z-50 overflow-hidden">
              <button
                className="w-full px-4 py-2 text-sm text-left hover:bg-base-200 flex items-center gap-2 transition-colors"
                onClick={handleEdit}
              >
                <Pencil className="w-4 h-4" />
                Edit
              </button>
              <button
                className="w-full px-4 py-2 text-sm text-left hover:bg-error/20 text-error flex items-center gap-2 transition-colors"
                onClick={handleDelete}
              >
                <Trash2 className="w-4 h-4" />
                Delete
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Tasks */}
      {!isCollapsed && (
        <div className="p-4 pt-0 space-y-2">
          {topic.tasks.length === 0 ? (
            <div className="text-center py-6 text-base-content/50">
              <p className="text-sm">No tasks in this topic</p>
              <button className="btn btn-ghost btn-sm mt-2 gap-1" onClick={handleAddTask}>
                <Plus className="w-4 h-4" />
                Add Task
              </button>
            </div>
          ) : (
            <DndContext
              sensors={taskSensors}
              collisionDetection={closestCenter}
              onDragEnd={handleTaskDragEnd}
            >
              <SortableContext
                items={sortedTasks.map((t) => t.id)}
                strategy={verticalListSortingStrategy}
              >
                <div className="space-y-2">
                  {sortedTasks.map((task) => (
                    <SortableTaskCard
                      key={task.id}
                      task={task}
                      onToggleComplete={onToggleTaskComplete}
                      onTaskClick={onTaskClick}
                      isSelected={task.id === selectedTaskId}
                    />
                  ))}
                </div>
              </SortableContext>
            </DndContext>
          )}
          <button
            className="btn btn-ghost btn-sm w-full justify-start gap-2 text-base-content/60 hover:text-base-content"
            onClick={handleAddTask}
          >
            <Plus className="w-4 h-4" />
            Add Task
          </button>
        </div>
      )}
    </div>
  )
}


// Kanban status configuration - matches Work Execution page
const KANBAN_STATUSES = [
  { id: 'on_hold', title: 'Backlog', color: '#6b7280' },
  { id: 'new', title: 'To Do', color: '#f59e0b' },
  { id: 'working', title: 'In Progress', color: '#3b82f6' },
  { id: 'question', title: 'Blocked', color: '#ef4444' },
  { id: 'in_review', title: 'Review', color: '#8b5cf6' },
  { id: 'done', title: 'Done', color: '#10b981' },
] as const

// Droppable Kanban Column
function DroppableKanbanColumn({ 
  status, 
  title, 
  tasks, 
  color,
  onTaskClick,
  onToggleComplete,
  selectedTaskId,
}: { 
  status: string
  title: string
  tasks: Task[]
  color: string
  onTaskClick: (task: Task) => void
  onToggleComplete: (taskId: string, completed: boolean) => void
  selectedTaskId?: string | null
}) {
  const { setNodeRef, isOver } = useDroppable({
    id: `column-${status}`,
    data: {
      type: 'column',
      status,
    }
  })

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
        <span className="badge badge-sm badge-ghost">{tasks.length}</span>
      </div>

      {/* Tasks */}
      <div className="p-2 space-y-2 min-h-[200px] max-h-[calc(100vh-300px)] overflow-y-auto flex-1">
        <SortableContext
          items={tasks.map(t => t.id)}
          strategy={verticalListSortingStrategy}
        >
          {tasks.length === 0 ? (
            <div className={`text-center py-8 text-base-content/50 border-2 border-dashed rounded-lg transition-colors ${
              isOver ? 'border-primary bg-primary/10' : 'border-transparent'
            }`}>
              <p className="text-sm">{isOver ? 'Drop here' : 'No tasks'}</p>
            </div>
          ) : (
            tasks.map((task) => (
              <DraggableKanbanTaskCard 
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

export default function ProjectDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { company, isLoading: authLoading } = useAuthStore()
  const { openModal } = useModalStore()
  const [viewMode, setViewMode] = useState<ViewMode>('list')
  const [project, setProject] = useState<Project | null>(null)
  const [topics, setTopics] = useState<Topic[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedTask, setSelectedTask] = useState<Task | null>(null)
  const [activeDragTask, setActiveDragTask] = useState<Task | null>(null)

  // DnD sensors
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  )

  // Handle toggle task completion
  const handleToggleTaskComplete = async (taskId: string, completed: boolean) => {
    // Optimistically update UI
    setTopics((prev) =>
      prev.map((topic) => ({
        ...topic,
        tasks: topic.tasks.map((task) =>
          task.id === taskId
            ? { ...task, completed, status: completed ? 'done' : 'new' }
            : task
        ),
      }))
    )

    // Also update selected task if it's the one being toggled
    if (selectedTask?.id === taskId) {
      setSelectedTask((prev) =>
        prev ? { ...prev, completed, status: completed ? 'done' : 'new' } : null
      )
    }

    // Persist to API
    try {
      await api.patch(`/api/v1/tasks/${taskId}`, { completed })
    } catch (err) {
      console.error('Failed to update task:', err)
      // Revert on error - refetch data
      if (id && company?.id) {
        const topicsRes = await api.get(`/api/v1/projects/${id}/topics`)
        if (topicsRes.data.success) {
          setTopics(topicsRes.data.data || [])
        }
      }
    }
  }

  // Handle task click to show detail sidebar
  const handleTaskClick = (task: Task) => {
    setSelectedTask(task)
  }

  // Handle edit topic
  const handleEditTopic = (topic: Topic) => {
    // TODO: Implement edit topic modal
    console.log('Edit topic:', topic)
    alert('Edit topic feature coming soon!')
  }

  // Handle delete topic
  const handleDeleteTopic = async (topicId: string) => {
    if (!confirm('Are you sure you want to delete this topic? All tasks in it will also be deleted.')) {
      return
    }

    // Optimistically update UI
    const previousTopics = topics
    setTopics((prev) => prev.filter((t) => t.id !== topicId))

    // Persist to API
    try {
      await api.delete(`/api/v1/topics/${topicId}`)
    } catch (err) {
      console.error('Failed to delete topic:', err)
      // Revert on error
      setTopics(previousTopics)
    }
  }

  // Handle topic drag end
  const handleTopicDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event

    if (!over || active.id === over.id || !id) return

    const oldIndex = topics.findIndex((t) => t.id === active.id)
    const newIndex = topics.findIndex((t) => t.id === over.id)

    if (oldIndex === -1 || newIndex === -1) return

    // Optimistically update UI
    const newTopics = arrayMove(topics, oldIndex, newIndex).map((topic, index) => ({
      ...topic,
      position: index,
    }))
    setTopics(newTopics)

    // Persist to API
    try {
      await api.put(`/api/v1/projects/${id}/topics/positions`, {
        positions: newTopics.map((t, index) => ({
          id: t.id,
          position: index,
        })),
      })
    } catch (err) {
      console.error('Failed to update topic positions:', err)
      // Revert on error
      setTopics(topics)
    }
  }

  // Handle task reorder within a topic
  const handleTaskReorder = async (topicId: string, oldIndex: number, newIndex: number) => {
    // Find the topic
    const topicIndex = topics.findIndex((t) => t.id === topicId)
    if (topicIndex === -1) return

    const topic = topics[topicIndex]
    const sortedTasks = [...topic.tasks].sort((a, b) => a.order - b.order)
    const reorderedTasks = arrayMove(sortedTasks, oldIndex, newIndex).map((task, index) => ({
      ...task,
      order: index,
    }))

    // Optimistically update UI
    const newTopics = [...topics]
    newTopics[topicIndex] = {
      ...topic,
      tasks: reorderedTasks,
    }
    setTopics(newTopics)

    // Persist to API
    try {
      await api.put('/api/v1/tasks/positions', {
        positions: reorderedTasks.map((t, index) => ({
          id: t.id,
          order: index,
        })),
      })
    } catch (err) {
      console.error('Failed to update task positions:', err)
      // Revert on error
      setTopics(topics)
    }
  }

  // Handle kanban drag start
  const handleKanbanDragStart = (event: DragStartEvent) => {
    const { active } = event
    const taskId = active.id as string
    
    // Find the task across all topics
    for (const topic of topics) {
      const task = topic.tasks.find(t => t.id === taskId)
      if (task) {
        setActiveDragTask(task)
        break
      }
    }
  }

  // Handle kanban drag end - updates task status when dropped in a different column
  const handleKanbanDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event
    setActiveDragTask(null)

    if (!over) return

    const taskId = active.id as string
    const overId = over.id as string

    // Check if dropped on a column
    if (overId.startsWith('column-')) {
      const newStatus = overId.replace('column-', '')
      
      // Find the task and its current status
      let taskToMove: Task | null = null
      
      for (const topic of topics) {
        const task = topic.tasks.find(t => t.id === taskId)
        if (task) {
          taskToMove = task
          break
        }
      }

      if (!taskToMove || taskToMove.status === newStatus) return

      // Store previous state for rollback
      const previousTopics = topics

      // Optimistically update UI - change task status
      setTopics((prev) =>
        prev.map((topic) => ({
          ...topic,
          tasks: topic.tasks.map((task) =>
            task.id === taskId
              ? { 
                  ...task, 
                  status: newStatus,
                  completed: newStatus === 'done',
                }
              : task
          ),
        }))
      )

      // Also update selected task if it's the one being moved
      if (selectedTask?.id === taskId) {
        setSelectedTask((prev) =>
          prev ? { 
            ...prev, 
            status: newStatus,
            completed: newStatus === 'done',
          } : null
        )
      }

      // Persist to API
      try {
        await api.put(`/api/v1/tasks/${taskId}`, { 
          status: newStatus,
          completed: newStatus === 'done',
        })
      } catch (err) {
        console.error('Failed to update task status:', err)
        // Revert on error
        setTopics(previousTopics)
        if (selectedTask?.id === taskId) {
          setSelectedTask(taskToMove)
        }
      }
    } else {
      // Dropped on another task - could implement reordering within column here
      // For now, just get the column from the over task
      let targetStatus: string | null = null
      
      for (const topic of topics) {
        const task = topic.tasks.find(t => t.id === overId)
        if (task) {
          targetStatus = task.status
          break
        }
      }

      if (targetStatus) {
        // Find the dragged task
        let taskToMove: Task | null = null
        
        for (const topic of topics) {
          const task = topic.tasks.find(t => t.id === taskId)
          if (task) {
            taskToMove = task
            break
          }
        }

        if (taskToMove && taskToMove.status !== targetStatus) {
          // Store previous state for rollback
          const previousTopics = topics

          // Optimistically update UI
          setTopics((prev) =>
            prev.map((topic) => ({
              ...topic,
              tasks: topic.tasks.map((task) =>
                task.id === taskId
                  ? { 
                      ...task, 
                      status: targetStatus!,
                      completed: targetStatus === 'done',
                    }
                  : task
              ),
            }))
          )

          // Update selected task if needed
          if (selectedTask?.id === taskId) {
            setSelectedTask((prev) =>
              prev ? { 
                ...prev, 
                status: targetStatus!,
                completed: targetStatus === 'done',
              } : null
            )
          }

          // Persist to API
          try {
            await api.put(`/api/v1/tasks/${taskId}`, { 
              status: targetStatus,
              completed: targetStatus === 'done',
            })
          } catch (err) {
            console.error('Failed to update task status:', err)
            // Revert on error
            setTopics(previousTopics)
            if (selectedTask?.id === taskId) {
              setSelectedTask(taskToMove)
            }
          }
        }
      }
    }
  }

  useEffect(() => {
    // Wait for auth to finish loading before fetching
    if (authLoading) return
    
    if (id && company?.id) {
      fetchProjectData()
    } else if (!authLoading && !company?.id) {
      // Auth finished but no company - stop loading
      setLoading(false)
      setError('No company selected')
    }
  }, [id, company?.id, authLoading])

  // Handler to open the Create Topic modal
  const handleAddTopic = () => {
    if (!id) return
    openModal(MODAL_NAMES.CREATE_TOPIC, {
      projectId: id,
      onSuccess: (newTopic: any) => {
        // Add new topic to local state
        setTopics((prev) => [
          ...prev,
          {
            ...newTopic,
            tasks: newTopic.tasks || [],
            completion_percentage: 0,
          },
        ])
      },
    })
  }

  // Handler to open the Create Task modal
  const handleAddTask = (topicId: string, topicName: string) => {
    if (!id) return
    openModal(MODAL_NAMES.CREATE_TASK, {
      topicId,
      projectId: id,
      topicName,
      onSuccess: (newTask: any) => {
        // Add new task to the specific topic's task list
        setTopics((prev) =>
          prev.map((topic) =>
            topic.id === topicId
              ? {
                  ...topic,
                  tasks: [
                    ...topic.tasks,
                    {
                      id: newTask.id,
                      title: newTask.title,
                      description: newTask.description,
                      status: newTask.status || 'new',
                      priority: newTask.priority,
                      completed: newTask.completed || false,
                      due_date: newTask.due_date,
                      order: newTask.order || topic.tasks.length,
                      assignees: newTask.assignees || [],
                    },
                  ],
                }
              : topic
          )
        )
      },
    })
  }

  const fetchProjectData = async () => {
    if (!id || !company?.id) return

    setLoading(true)
    setError(null)

    try {
      // Fetch project and topics in parallel
      const [projectRes, topicsRes] = await Promise.all([
        api.get(`/api/v1/projects/${id}`),
        api.get(`/api/v1/projects/${id}/topics`),
      ])

      if (projectRes.data.success) {
        setProject(projectRes.data.data)
      } else {
        setError(projectRes.data.message || 'Failed to load project')
      }

      if (topicsRes.data.success) {
        setTopics(topicsRes.data.data || [])
      }
    } catch (err: any) {
      console.error('Failed to fetch project:', err)
      setError(err.response?.data?.message || 'Failed to load project')
    } finally {
      setLoading(false)
    }
  }

  // Group tasks by status for Kanban view - matches Work Execution page columns
  const getTasksByStatus = () => {
    const tasksByStatus: Record<string, Task[]> = {
      on_hold: [],   // Backlog
      new: [],       // To Do
      working: [],   // In Progress
      question: [],  // Blocked
      in_review: [], // Review
      done: [],      // Done
    }

    topics.forEach((topic) => {
      topic.tasks.forEach((task) => {
        const status = task.status
        if (tasksByStatus[status]) {
          tasksByStatus[status].push(task)
        } else {
          // Default to 'new' (To Do) if status doesn't match
          tasksByStatus.new.push(task)
        }
      })
    })

    return tasksByStatus
  }

  const totalTasks = topics.reduce((sum, t) => sum + t.tasks.length, 0)
  const completedTasks = topics.reduce(
    (sum, t) => sum + t.tasks.filter((task) => task.completed).length,
    0
  )

  if (loading || authLoading) {
    return (
      <Layout>
        <div className="flex items-center justify-center h-96">
          <span className="loading loading-spinner loading-lg"></span>
        </div>
      </Layout>
    )
  }

  if (error || !project) {
    return (
      <Layout>
        <div className="flex flex-col items-center justify-center h-96">
          <AlertCircle className="w-16 h-16 text-error mb-4" />
          <h2 className="text-xl font-semibold mb-2">Project not found</h2>
          <p className="text-base-content/60 mb-4">{error || 'Unable to load project'}</p>
          <Link to="/projects" className="btn btn-primary">
            Back to Projects
          </Link>
        </div>
      </Layout>
    )
  }

  const tasksByStatus = getTasksByStatus()

  return (
    <Layout>
      <div className="min-h-screen">
        {/* Header */}
        <div className="border-b border-base-300 bg-base-100 sticky top-0 z-10">
          <div className="px-6 py-4">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-3">
                <Link to="/projects" className="btn btn-ghost btn-sm btn-square" title="Back to Projects">
                  <ChevronLeft className="w-5 h-5" />
                </Link>
                <div>
                  <h1 className="text-xl font-semibold">{project.name}</h1>
                  {project.description && (
                    <p className="text-sm text-base-content/60 mt-0.5">{project.description}</p>
                  )}
                </div>
              </div>

              <div className="flex items-center gap-3">
                {/* View Toggle */}
                <div className="join">
                  <button
                    className={`join-item btn btn-sm ${viewMode === 'list' ? 'btn-primary' : 'btn-ghost'}`}
                    onClick={() => setViewMode('list')}
                  >
                    <List className="w-4 h-4" />
                    <span className="hidden sm:inline ml-1">List</span>
                  </button>
                  <button
                    className={`join-item btn btn-sm ${viewMode === 'kanban' ? 'btn-primary' : 'btn-ghost'}`}
                    onClick={() => setViewMode('kanban')}
                  >
                    <LayoutGrid className="w-4 h-4" />
                    <span className="hidden sm:inline ml-1">Kanban</span>
                  </button>
                </div>

                {topics.length > 0 ? (
                  <div className="dropdown dropdown-end">
                    <button tabIndex={0} className="btn btn-primary btn-sm gap-1">
                      <Plus className="w-4 h-4" />
                      Add Task
                    </button>
                    <ul
                      tabIndex={0}
                      className="dropdown-content z-[1] menu p-2 shadow-lg bg-base-200 rounded-box w-52 mt-2"
                    >
                      <li className="menu-title">
                        <span>Select Topic</span>
                      </li>
                      {topics.map((topic) => (
                        <li key={topic.id}>
                          <button
                            onClick={() => handleAddTask(topic.id, topic.name)}
                            className="flex items-center gap-2"
                          >
                            <div
                              className="w-2 h-2 rounded-full"
                              style={{ backgroundColor: topic.color || '#6b7280' }}
                            />
                            {topic.name}
                          </button>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : (
                  <button className="btn btn-primary btn-sm gap-1" onClick={handleAddTopic}>
                    <Plus className="w-4 h-4" />
                    Add Topic
                  </button>
                )}
              </div>
            </div>

            {/* Project Stats */}
            <div className="flex flex-wrap items-center gap-6 text-sm">
              <div className="flex items-center gap-2">
                <span
                  className={`badge ${
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

              <div className="flex items-center gap-1 text-base-content/60">
                <List className="w-4 h-4" />
                <span>{topics.length} topics</span>
              </div>

              <div className="flex items-center gap-1 text-base-content/60">
                <CheckCircle2 className="w-4 h-4" />
                <span>
                  {completedTasks}/{totalTasks} tasks
                </span>
              </div>

              {project.due_date && (
                <div className="flex items-center gap-1 text-base-content/60">
                  <Clock className="w-4 h-4" />
                  <span>
                    Due{' '}
                    {new Date(project.due_date).toLocaleDateString('en-US', {
                      month: 'short',
                      day: 'numeric',
                      year: 'numeric',
                    })}
                  </span>
                </div>
              )}

              {project.members && project.members.length > 0 && (
                <div className="flex items-center gap-2">
                  <Users className="w-4 h-4 text-base-content/60" />
                  <div className="flex -space-x-2">
                    {project.members.slice(0, 4).map((member) => {
                      const memberName = `${member.first_name || ''} ${member.last_name || ''}`.trim() || 'User'
                      return (
                        <div
                          key={member.id}
                          className="w-7 h-7 rounded-full bg-primary/20 flex items-center justify-center text-xs font-medium text-primary ring-2 ring-base-100"
                          title={memberName}
                        >
                          {memberName.charAt(0)}
                        </div>
                      )
                    })}
                    {project.members.length > 4 && (
                      <div className="w-7 h-7 rounded-full bg-base-300 flex items-center justify-center text-xs ring-2 ring-base-100">
                        +{project.members.length - 4}
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="p-6">
          {viewMode === 'list' ? (
            /* List View */
            <div className="space-y-4">
              {topics.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-16 text-center">
                  <div className="w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mb-4">
                    <List className="w-8 h-8 text-base-content/40" />
                  </div>
                  <h3 className="text-lg font-medium mb-2">No topics yet</h3>
                  <p className="text-base-content/60 max-w-md mb-4">
                    Create topics to organize your tasks into groups.
                  </p>
                  <button className="btn btn-primary gap-2" onClick={handleAddTopic}>
                    <Plus className="w-4 h-4" />
                    Create Topic
                  </button>
                </div>
              ) : (
                <DndContext
                  sensors={sensors}
                  collisionDetection={closestCenter}
                  onDragEnd={handleTopicDragEnd}
                >
                  <SortableContext
                    items={topics.map((t) => t.id)}
                    strategy={verticalListSortingStrategy}
                  >
                    <div className="space-y-4">
                      {topics
                        .sort((a, b) => a.position - b.position)
                        .map((topic) => (
                          <SortableTopicSection
                            key={topic.id}
                            topic={topic}
                            onAddTask={handleAddTask}
                            onTaskReorder={handleTaskReorder}
                            onToggleTaskComplete={handleToggleTaskComplete}
                            onTaskClick={handleTaskClick}
                            onEditTopic={handleEditTopic}
                            onDeleteTopic={handleDeleteTopic}
                            selectedTaskId={selectedTask?.id}
                            taskSensors={sensors}
                          />
                        ))}
                    </div>
                  </SortableContext>
                  <button
                    className="btn btn-ghost btn-block justify-start gap-2 border-2 border-dashed border-base-300 hover:border-primary/50 mt-4"
                    onClick={handleAddTopic}
                  >
                    <Plus className="w-4 h-4" />
                    Add Topic
                  </button>
                </DndContext>
              )}
            </div>
          ) : (
            /* Kanban View with Drag and Drop */
            <DndContext
              sensors={sensors}
              collisionDetection={rectIntersection}
              onDragStart={handleKanbanDragStart}
              onDragEnd={handleKanbanDragEnd}
            >
              <div className="overflow-x-auto pb-4">
                <div className="flex gap-4 min-w-max">
                  {KANBAN_STATUSES.map((statusConfig) => (
                    <DroppableKanbanColumn
                      key={statusConfig.id}
                      status={statusConfig.id}
                      title={statusConfig.title}
                      tasks={tasksByStatus[statusConfig.id] || []}
                      color={statusConfig.color}
                      onTaskClick={handleTaskClick}
                      onToggleComplete={handleToggleTaskComplete}
                      selectedTaskId={selectedTask?.id}
                    />
                  ))}
                </div>
              </div>
              
              {/* Drag Overlay - shows the task being dragged */}
              <DragOverlay>
                {activeDragTask ? (
                  <div className="w-80">
                    <TaskCardContent task={activeDragTask} isDragging />
                  </div>
                ) : null}
              </DragOverlay>
            </DndContext>
          )}
        </div>
      </div>

      {/* Task Detail Drawer */}
      <TaskDetailDrawer
        task={selectedTask ? {
          ...selectedTask,
          topic_id: topics.find(t => t.tasks.some(task => task.id === selectedTask.id))?.id || '',
          project_id: id || '',
          content: null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        } : null}
        isOpen={!!selectedTask}
        onClose={() => setSelectedTask(null)}
        onUpdateTask={(taskId, data) => {
          // Update the task in local state
          setTopics((prev) =>
            prev.map((topic) => ({
              ...topic,
              tasks: topic.tasks.map((task) =>
                task.id === taskId ? { ...task, ...data } : task
              ),
            }))
          )
          // Update selected task if it's the one being updated
          if (selectedTask?.id === taskId) {
            setSelectedTask((prev) => prev ? { ...prev, ...data } : null)
          }
        }}
        projectMembers={project?.members?.map((m) => ({
          id: m.id,
          name: `${m.first_name || ''} ${m.last_name || ''}`.trim() || 'User',
          avatar: m.avatar_url,
        })) || []}
      />
    </Layout>
  )
}
