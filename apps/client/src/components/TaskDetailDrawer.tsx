import { useState, useEffect, useRef } from 'react'
import {
  X,
  Clock,
  Flag,
  Calendar,
  User,
  UserPlus,
  Send,
  Paperclip,
  Image as ImageIcon,
  MessageSquare,
  Activity,
  FileIcon,
  Loader2,
  Check,
  CheckCircle,
  RefreshCw,
} from 'lucide-react'
import { api } from '../lib/api'
import FileUploadButton from './FileUploadButton'
import AttachmentList, { type Attachment } from './AttachmentList'
import { type UploadedFile } from '../hooks/useFileUpload'

interface TaskComment {
  id: string
  content: string
  ai_generated?: boolean
  user: {
    id: string
    name: string
    avatar: string | null
  } | null
  attachments: Array<{
    id: string
    filename: string
    mime_type: string
    size: number
    path: string
  }>
  time: string
  created_at: string
}

interface TaskActivity {
  id: string
  action: string
  description: string
  user: {
    id: string
    name: string
    avatar: string | null
  } | null
  relative_time: string
  formatted_date: string
  old_values?: Record<string, any>
  new_values?: Record<string, any>
}

interface TaskAssignee {
  id: string
  name: string
  avatar: string | null
}

interface Task {
  id: string
  title: string
  description: string | null
  content: string | null
  status: string
  priority: string
  completed: boolean
  due_date: string | null
  order: number
  topic_id: string
  project_id: string
  assignees?: TaskAssignee[]
  comments?: TaskComment[]
  comments_count?: number
  created_at: string
  updated_at: string
}

interface TaskDetailDrawerProps {
  task: Task | null
  isOpen: boolean
  onClose: () => void
  onUpdateTask?: (taskId: string, data: Partial<Task>) => void
  projectMembers?: Array<{ id: string; name: string; avatar: string | null }>
}

const STATUS_OPTIONS = [
  { value: 'backlog', label: 'Backlog', color: 'badge-ghost' },
  { value: 'todo', label: 'To Do', color: 'badge-warning' },
  { value: 'in_progress', label: 'In Progress', color: 'badge-info' },
  { value: 'on_hold', label: 'On Hold', color: 'badge-error' },
  { value: 'in_review', label: 'Review', color: 'badge-accent' },
  { value: 'done', label: 'Done', color: 'badge-success' },
]

const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Low', color: 'text-base-content/50' },
  { value: 'medium', label: 'Medium', color: 'text-info' },
  { value: 'high', label: 'High', color: 'text-warning' },
  { value: 'urgent', label: 'Urgent', color: 'text-error' },
]

export default function TaskDetailDrawer({
  task,
  isOpen,
  onClose,
  onUpdateTask,
  projectMembers = [],
}: TaskDetailDrawerProps) {
  const drawerRef = useRef<HTMLDivElement>(null)
  const commentInputRef = useRef<HTMLTextAreaElement>(null)
  
  const [activeTab, setActiveTab] = useState<'comments' | 'details' | 'activity'>('comments')
  const [taskData, setTaskData] = useState({
    title: '',
    description: '',
    status: 'todo',
    priority: 'medium',
    due_date: '',
  })
  
  // Comments state
  const [comments, setComments] = useState<TaskComment[]>([])
  const [commentsLoading, setCommentsLoading] = useState(false)
  const [commentContent, setCommentContent] = useState('')
  const [commentSubmitting, setCommentSubmitting] = useState(false)
  const [pendingAttachments, setPendingAttachments] = useState<UploadedFile[]>([])
  
  // Activity state
  const [activities, setActivities] = useState<TaskActivity[]>([])
  const [activityLoading, setActivityLoading] = useState(false)
  const [activityHasMore, setActivityHasMore] = useState(false)
  const [activityOffset, setActivityOffset] = useState(0)
  
  // Assignee modal state
  const [showAssignModal, setShowAssignModal] = useState(false)
  
  // Saving indicator
  const [isSaving, setIsSaving] = useState(false)
  
  // Update task data when task changes
  useEffect(() => {
    if (task) {
      setTaskData({
        title: task.title || '',
        description: task.description || '',
        status: task.status || 'todo',
        priority: task.priority || 'medium',
        due_date: task.due_date || '',
      })
      
      // Load comments when opening
      if (isOpen) {
        loadComments()
      }
    }
  }, [task, isOpen])
  
  // Load activity when switching to activity tab
  useEffect(() => {
    if (activeTab === 'activity' && task && isOpen) {
      loadActivity(true)
    }
  }, [activeTab, task?.id, isOpen])
  
  // Handle click outside to close
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (isOpen && drawerRef.current && !drawerRef.current.contains(e.target as Node)) {
        onClose()
      }
    }
    
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside)
    }
    
    return () => {
      document.removeEventListener('mousedown', handleClickOutside)
    }
  }, [isOpen, onClose])
  
  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose()
      }
    }
    
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isOpen, onClose])
  
  const loadComments = async () => {
    if (!task) return
    
    setCommentsLoading(true)
    try {
      const response = await api.get(`/api/v1/tasks/${task.id}/comments`)
      if (response.data.success) {
        setComments(response.data.comments || [])
      }
    } catch (error) {
      console.error('Failed to load comments:', error)
    } finally {
      setCommentsLoading(false)
    }
  }
  
  const loadActivity = async (reset = false) => {
    if (!task) return
    
    setActivityLoading(true)
    try {
      const currentOffset = reset ? 0 : activityOffset
      const response = await api.get(`/api/v1/tasks/${task.id}/activity`, {
        params: { limit: 20, offset: currentOffset },
      })
      
      if (response.data.success) {
        const newActivities = response.data.data || []
        if (reset) {
          setActivities(newActivities)
          setActivityOffset(newActivities.length)
        } else {
          setActivities((prev) => [...prev, ...newActivities])
          setActivityOffset((prev) => prev + newActivities.length)
        }
        setActivityHasMore(response.data.meta?.has_more || false)
      }
    } catch (error) {
      console.error('Failed to load activity:', error)
    } finally {
      setActivityLoading(false)
    }
  }
  
  const handlePostComment = async () => {
    if (!task || !commentContent.trim()) return
    
    setCommentSubmitting(true)
    try {
      const response = await api.post(`/api/v1/tasks/${task.id}/comments`, {
        content: commentContent.trim(),
        attachments: pendingAttachments.map(file => ({
          filename: file.filename,
          path: file.path,
          mime_type: file.mime_type,
          size: file.size,
        })),
      })
      
      if (response.data.success) {
        // Add the new comment to the top of the list
        setComments((prev) => [response.data.comment, ...prev])
        setCommentContent('')
        setPendingAttachments([])
      }
    } catch (error) {
      console.error('Failed to post comment:', error)
    } finally {
      setCommentSubmitting(false)
    }
  }
  
  const handleUpdateField = async (field: string, value: string) => {
    if (!task) return
    
    // Skip if value hasn't changed
    if (task[field as keyof Task] === value) return
    
    const updateData: Record<string, any> = { [field]: value }
    
    // Handle completion
    if (field === 'status' && value === 'done') {
      updateData.completed = true
    } else if (field === 'status' && taskData.status === 'done') {
      updateData.completed = false
    }
    
    setIsSaving(true)
    try {
      const response = await api.put(`/api/v1/tasks/${task.id}`, updateData)
      if (response.data.success && onUpdateTask) {
        onUpdateTask(task.id, response.data.data)
      }
    } catch (error) {
      console.error('Failed to update task:', error)
    } finally {
      setIsSaving(false)
    }
  }
  
  const handleAssignUser = async (userId: string) => {
    if (!task) return
    
    try {
      const response = await api.post(`/api/v1/tasks/${task.id}/assign`, {
        user_id: userId,
      })
      
      if (response.data.success && onUpdateTask) {
        onUpdateTask(task.id, { assignees: response.data.data })
      }
      setShowAssignModal(false)
    } catch (error) {
      console.error('Failed to assign user:', error)
    }
  }
  
  const handleUnassignUser = async (userId: string) => {
    if (!task) return
    
    try {
      await api.delete(`/api/v1/tasks/${task.id}/assign/${userId}`)
      
      if (onUpdateTask) {
        const newAssignees = (task.assignees || []).filter((a) => a.id !== userId)
        onUpdateTask(task.id, { assignees: newAssignees })
      }
    } catch (error) {
      console.error('Failed to unassign user:', error)
    }
  }
  
  const getStatusBadgeClass = (status: string) => {
    const option = STATUS_OPTIONS.find((o) => o.value === status)
    return option?.color || 'badge-ghost'
  }
  
  const getPriorityColor = (priority: string) => {
    const option = PRIORITY_OPTIONS.find((o) => o.value === priority)
    return option?.color || 'text-base-content/50'
  }
  
  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return 'No due date'
    return new Date(dateStr).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  }
  
  const getActivityIcon = (action: string) => {
    switch (action) {
      case 'create':
        return <Check className="w-4 h-4 text-success" />
      case 'update':
        return <RefreshCw className="w-4 h-4 text-info" />
      case 'complete':
        return <CheckCircle className="w-4 h-4 text-success" />
      case 'status_change':
        return <RefreshCw className="w-4 h-4 text-info" />
      case 'assign':
        return <UserPlus className="w-4 h-4 text-primary" />
      case 'comment':
        return <MessageSquare className="w-4 h-4 text-primary" />
      default:
        return <Activity className="w-4 h-4 text-base-content/60" />
    }
  }
  
  if (!task) return null
  
  return (
    <>
      {/* Overlay */}
      {isOpen && (
        <div
          className="fixed inset-0 bg-black/20 z-30 transition-opacity duration-300"
          onClick={onClose}
        />
      )}
      
      {/* Drawer */}
      <div
        ref={drawerRef}
        className={`fixed inset-y-0 right-0 z-40 w-full md:w-[500px] lg:w-[600px] bg-base-100 border-l border-base-300 shadow-2xl transform transition-transform duration-300 ease-out ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        <div className="h-full flex flex-col">
          {/* Header */}
          <div className="px-4 py-3 border-b border-base-300 sticky top-0 bg-base-100 z-10">
            <div className="flex justify-between items-start gap-3">
              <div className="flex items-start gap-2 flex-1 min-w-0">
                <div
                  className={`w-2 h-2 rounded-full mt-2 flex-shrink-0 ${
                    taskData.status === 'done' ? 'bg-success' : 'bg-primary'
                  }`}
                />
                <h3 className="text-lg font-semibold leading-tight break-words">
                  {taskData.title || task.title}
                </h3>
                {isSaving && (
                  <span className="badge badge-sm badge-ghost gap-1 flex-shrink-0">
                    <Loader2 className="w-3 h-3 animate-spin" />
                    Saving
                  </span>
                )}
              </div>
              <button
                onClick={onClose}
                className="p-2 rounded-lg hover:bg-base-200 transition-colors text-base-content/70 hover:text-base-content flex-shrink-0"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
          </div>
          
          {/* Tab Navigation */}
          <div className="px-4 pt-2 border-b border-base-300 bg-base-100 flex gap-4">
            <button
              onClick={() => setActiveTab('comments')}
              className={`pb-2 px-1 text-sm font-medium transition-colors border-b-2 ${
                activeTab === 'comments'
                  ? 'text-primary border-primary'
                  : 'text-base-content/70 hover:text-base-content border-transparent'
              }`}
            >
              Comments
            </button>
            <button
              onClick={() => setActiveTab('details')}
              className={`pb-2 px-1 text-sm font-medium transition-colors border-b-2 ${
                activeTab === 'details'
                  ? 'text-primary border-primary'
                  : 'text-base-content/70 hover:text-base-content border-transparent'
              }`}
            >
              Details
            </button>
            <button
              onClick={() => setActiveTab('activity')}
              className={`pb-2 px-1 text-sm font-medium transition-colors border-b-2 ${
                activeTab === 'activity'
                  ? 'text-primary border-primary'
                  : 'text-base-content/70 hover:text-base-content border-transparent'
              }`}
            >
              Activity
            </button>
          </div>
          
          {/* Content */}
          <div className="flex-1 overflow-y-auto p-4">
            {/* Comments Tab */}
            {activeTab === 'comments' && (
              <div className="space-y-4">
                {/* Task Summary Card */}
                <div className="bg-base-200 rounded-xl p-4">
                  {/* Status Badge */}
                  <div className="flex justify-between items-center mb-3">
                    <div className="flex items-center gap-2 text-sm text-base-content/70">
                      <Clock className="w-4 h-4" />
                      <span>{formatDate(taskData.due_date || task.due_date)}</span>
                    </div>
                    <span className={`badge ${getStatusBadgeClass(taskData.status)}`}>
                      {STATUS_OPTIONS.find((o) => o.value === taskData.status)?.label || 'New'}
                    </span>
                  </div>
                  
                  {/* Description */}
                  <div className="mb-3">
                    {taskData.description || task.description ? (
                      <p className="text-sm text-base-content/80 leading-relaxed">
                        {taskData.description || task.description}
                      </p>
                    ) : (
                      <p className="text-sm text-base-content/50 italic">No description provided</p>
                    )}
                  </div>
                  
                  {/* Compact Info Row */}
                  <div className="flex flex-wrap items-center gap-4 text-xs text-base-content/60">
                    <div className="flex items-center gap-1">
                      <Flag className={`w-3 h-3 ${getPriorityColor(taskData.priority)}`} />
                      <span className="capitalize">{taskData.priority} priority</span>
                    </div>
                    {task.assignees && task.assignees.length > 0 && (
                      <div className="flex items-center gap-1">
                        <User className="w-3 h-3" />
                        <span>
                          {task.assignees.length} assignee{task.assignees.length > 1 ? 's' : ''}
                        </span>
                      </div>
                    )}
                  </div>
                </div>
                
                {/* Comment Input */}
                <div className="bg-base-200 rounded-xl p-3">
                  <textarea
                    ref={commentInputRef}
                    value={commentContent}
                    onChange={(e) => setCommentContent(e.target.value)}
                    placeholder="Add a comment..."
                    className="textarea textarea-ghost w-full resize-none bg-transparent p-0 min-h-[60px] focus:outline-none"
                    rows={2}
                  />
                  
                  {/* File Upload Preview */}
                  {pendingAttachments.length > 0 && (
                    <div className="mt-3 pt-3 border-t border-base-300">
                      <AttachmentList
                        attachments={pendingAttachments.map(file => ({
                          id: file.path,
                          filename: file.filename,
                          mime_type: file.mime_type,
                          size: file.size,
                          path: file.path,
                          public_url: file.public_url,
                        }))}
                        canDelete={true}
                        onDelete={(id) => {
                          setPendingAttachments(prev => prev.filter(f => f.path !== id))
                        }}
                        compact={true}
                      />
                    </div>
                  )}
                  
                  <div className="flex justify-between items-center mt-2">
                    <FileUploadButton
                      entityType="task_comment"
                      entityId={task?.id}
                      multiple={true}
                      onUploadComplete={(files) => {
                        setPendingAttachments(prev => [...prev, ...files])
                      }}
                      disabled={commentSubmitting}
                    />
                    <button
                      onClick={handlePostComment}
                      disabled={!commentContent.trim() || commentSubmitting}
                      className="btn btn-primary btn-sm gap-1"
                    >
                      {commentSubmitting ? (
                        <Loader2 className="w-4 h-4 animate-spin" />
                      ) : (
                        <Send className="w-4 h-4" />
                      )}
                    </button>
                  </div>
                </div>
                
                {/* Comments List */}
                <div className="flex justify-between items-center">
                  <h4 className="text-sm font-medium text-base-content/80">
                    Comments ({comments.length})
                  </h4>
                  {commentsLoading && (
                    <Loader2 className="w-4 h-4 animate-spin text-primary" />
                  )}
                </div>
                
                {comments.length > 0 ? (
                  <div className="space-y-3">
                    {comments.map((comment) => (
                      <div key={comment.id} className="bg-base-200 rounded-xl p-4">
                        <div className="flex items-center gap-2 mb-2">
                          <div className="avatar">
                            <div className="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
                              {comment.user?.avatar ? (
                                <img
                                  src={comment.user.avatar}
                                  alt={comment.user.name}
                                  className="w-full h-full object-cover rounded-full"
                                />
                              ) : (
                                <span className="text-xs font-medium text-primary">
                                  {comment.user?.name?.charAt(0) || 'U'}
                                </span>
                              )}
                            </div>
                          </div>
                          <div>
                            <div className="font-medium text-sm">{comment.user?.name || 'User'}</div>
                            <div className="text-xs text-base-content/60">{comment.time}</div>
                          </div>
                        </div>
                        <div
                          className="text-sm prose prose-sm max-w-none"
                          dangerouslySetInnerHTML={{ __html: comment.content }}
                        />
                        
                        {/* Attachments */}
                        {comment.attachments && comment.attachments.length > 0 && (
                          <div className="mt-3 pt-3 border-t border-base-300">
                            <AttachmentList
                              attachments={comment.attachments.map(file => ({
                                id: file.id,
                                filename: file.filename,
                                mime_type: file.mime_type,
                                size: file.size,
                                path: file.path,
                              }))}
                              compact={true}
                            />
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="flex flex-col items-center justify-center bg-base-200 rounded-xl p-8 text-base-content/60">
                    {commentsLoading ? (
                      <>
                        <Loader2 className="w-8 h-8 animate-spin text-primary mb-2" />
                        <p className="text-sm">Loading comments...</p>
                      </>
                    ) : (
                      <>
                        <MessageSquare className="w-10 h-10 mb-2 opacity-40" />
                        <p className="text-sm">No comments yet</p>
                        <p className="text-xs">Be the first to add a comment</p>
                      </>
                    )}
                  </div>
                )}
              </div>
            )}
            
            {/* Details Tab */}
            {activeTab === 'details' && (
              <div className="space-y-6">
                {/* Title */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-base-content/80">
                    Title
                  </label>
                  <input
                    type="text"
                    value={taskData.title}
                    onChange={(e) => setTaskData((prev) => ({ ...prev, title: e.target.value }))}
                    onBlur={() => handleUpdateField('title', taskData.title)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault()
                        handleUpdateField('title', taskData.title)
                        ;(e.target as HTMLInputElement).blur()
                      }
                    }}
                    className="input input-bordered w-full"
                    placeholder="Task title"
                  />
                </div>
                
                {/* Description */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-base-content/80">
                    Description
                    <span className="text-xs font-normal text-base-content/50 ml-2">
                      (Ctrl+Enter to save)
                    </span>
                  </label>
                  <textarea
                    value={taskData.description}
                    onChange={(e) =>
                      setTaskData((prev) => ({ ...prev, description: e.target.value }))
                    }
                    onBlur={() => handleUpdateField('description', taskData.description)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault()
                        handleUpdateField('description', taskData.description)
                        ;(e.target as HTMLTextAreaElement).blur()
                      }
                    }}
                    className="textarea textarea-bordered w-full resize-none"
                    rows={4}
                    placeholder="Describe the task..."
                  />
                </div>
                
                {/* Due Date & Priority */}
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-2 text-base-content/80">
                      <Calendar className="w-4 h-4 inline mr-1" />
                      Due Date
                    </label>
                    <input
                      type="date"
                      value={taskData.due_date}
                      onChange={(e) => {
                        setTaskData((prev) => ({ ...prev, due_date: e.target.value }))
                        handleUpdateField('due_date', e.target.value)
                      }}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault()
                          ;(e.target as HTMLInputElement).blur()
                        }
                      }}
                      className="input input-bordered w-full"
                    />
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium mb-2 text-base-content/80">
                      <Flag className="w-4 h-4 inline mr-1" />
                      Priority
                    </label>
                    <select
                      value={taskData.priority}
                      onChange={(e) => {
                        setTaskData((prev) => ({ ...prev, priority: e.target.value }))
                        handleUpdateField('priority', e.target.value)
                      }}
                      className="select select-bordered w-full"
                    >
                      {PRIORITY_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
                
                {/* Status */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-base-content/80">
                    Status
                  </label>
                  <div className="flex flex-wrap gap-2">
                    {STATUS_OPTIONS.map((option) => (
                      <button
                        key={option.value}
                        onClick={() => {
                          setTaskData((prev) => ({ ...prev, status: option.value }))
                          handleUpdateField('status', option.value)
                        }}
                        className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${
                          taskData.status === option.value
                            ? 'bg-primary text-primary-content'
                            : 'bg-base-200 hover:bg-base-300 text-base-content'
                        }`}
                      >
                        {option.label}
                      </button>
                    ))}
                  </div>
                </div>
                
                {/* Assignees */}
                <div className="border-t border-base-300 pt-6">
                  <div className="flex justify-between items-center mb-3">
                    <label className="text-sm font-medium text-base-content/80">Assignees</label>
                    <button
                      onClick={() => setShowAssignModal(true)}
                      className="btn btn-sm btn-outline btn-primary gap-1"
                    >
                      <UserPlus className="w-4 h-4" />
                      Assign
                    </button>
                  </div>
                  
                  {task.assignees && task.assignees.length > 0 ? (
                    <div className="space-y-2">
                      {task.assignees.map((assignee) => (
                        <div
                          key={assignee.id}
                          className="flex items-center justify-between bg-base-200 p-3 rounded-lg hover:bg-base-300 transition-colors"
                        >
                          <div className="flex items-center gap-3">
                            <div className="avatar">
                              <div className="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
                                {assignee.avatar ? (
                                  <img
                                    src={assignee.avatar}
                                    alt={assignee.name}
                                    className="w-full h-full object-cover rounded-full"
                                  />
                                ) : (
                                  <span className="text-xs font-medium text-primary">
                                    {assignee.name?.charAt(0) || 'U'}
                                  </span>
                                )}
                              </div>
                            </div>
                            <span className="font-medium">{assignee.name}</span>
                          </div>
                          <button
                            onClick={() => handleUnassignUser(assignee.id)}
                            className="btn btn-ghost btn-sm btn-circle text-base-content/60 hover:text-error"
                            title="Remove assignee"
                          >
                            <X className="w-4 h-4" />
                          </button>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="flex flex-col items-center justify-center bg-base-200 p-6 rounded-lg text-base-content/60">
                      <User className="w-8 h-8 mb-2 opacity-50" />
                      <span>No assignees yet</span>
                      <span className="text-xs mt-1">Click Assign to add team members</span>
                    </div>
                  )}
                </div>
              </div>
            )}
            
            {/* Activity Tab */}
            {activeTab === 'activity' && (
              <div className="space-y-4">
                <div className="flex justify-between items-center">
                  <h4 className="text-sm font-medium text-base-content/80 flex items-center gap-2">
                    <Activity className="w-4 h-4" />
                    Activity Log
                  </h4>
                  {activityLoading && activities.length === 0 && (
                    <Loader2 className="w-4 h-4 animate-spin text-primary" />
                  )}
                </div>
                
                {activities.length > 0 ? (
                  <div className="relative pl-6 border-l-2 border-base-300 space-y-4">
                    {activities.map((activity) => (
                      <div key={activity.id} className="relative">
                        {/* Activity dot */}
                        <div className="absolute -left-[25px] w-4 h-4 rounded-full bg-base-100 border-2 border-base-300 flex items-center justify-center">
                          {getActivityIcon(activity.action)}
                        </div>
                        
                        <div className="bg-base-200 p-3 rounded-lg">
                          <div className="flex items-center justify-between mb-1">
                            <div className="flex items-center gap-2">
                              <div className="avatar">
                                <div className="w-6 h-6 rounded-full bg-primary/20 flex items-center justify-center">
                                  {activity.user?.avatar ? (
                                    <img
                                      src={activity.user.avatar}
                                      alt={activity.user.name}
                                      className="w-full h-full object-cover rounded-full"
                                    />
                                  ) : (
                                    <span className="text-xs font-medium text-primary">
                                      {activity.user?.name?.charAt(0) || 'S'}
                                    </span>
                                  )}
                                </div>
                              </div>
                              <span className="text-sm font-medium">
                                {activity.user?.name || 'System'}
                              </span>
                            </div>
                            <span
                              className="text-xs text-base-content/60"
                              title={activity.formatted_date}
                            >
                              {activity.relative_time}
                            </span>
                          </div>
                          <p className="text-sm text-base-content/80">{activity.description}</p>
                        </div>
                      </div>
                    ))}
                    
                    {/* Load More */}
                    {activityHasMore && (
                      <div className="flex justify-center pt-2">
                        <button
                          onClick={() => loadActivity(false)}
                          disabled={activityLoading}
                          className="btn btn-sm btn-outline"
                        >
                          {activityLoading ? (
                            <>
                              <Loader2 className="w-4 h-4 animate-spin" />
                              Loading...
                            </>
                          ) : (
                            'Load More'
                          )}
                        </button>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="flex flex-col items-center justify-center bg-base-200 rounded-xl p-8 text-base-content/60">
                    {activityLoading ? (
                      <>
                        <Loader2 className="w-8 h-8 animate-spin text-primary mb-2" />
                        <p className="text-sm">Loading activity...</p>
                      </>
                    ) : (
                      <>
                        <Clock className="w-10 h-10 mb-2 opacity-40" />
                        <p className="text-sm">No activity recorded yet</p>
                      </>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
      
      {/* Assign User Modal */}
      {showAssignModal && (
        <div 
          className="fixed inset-0 z-50 flex items-center justify-center"
          onMouseDown={(e) => e.stopPropagation()}
        >
          <div
            className="absolute inset-0 bg-black/60 backdrop-blur-sm"
            onClick={(e) => {
              e.stopPropagation()
              setShowAssignModal(false)
            }}
          />
          <div 
            className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-md mx-4 animate-in fade-in zoom-in-95 duration-200"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between p-4 border-b border-base-300">
              <h3 className="text-lg font-semibold">Assign Team Member</h3>
              <button
                onClick={(e) => {
                  e.stopPropagation()
                  setShowAssignModal(false)
                }}
                className="p-2 rounded-lg hover:bg-base-300 transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="p-4 max-h-[400px] overflow-y-auto">
              {projectMembers.length > 0 ? (
                <div className="space-y-2">
                  {projectMembers
                    .filter((member) => !task.assignees?.find((a) => a.id === member.id))
                    .map((member) => (
                      <button
                        key={member.id}
                        onClick={() => handleAssignUser(member.id)}
                        className="w-full flex items-center gap-3 p-3 rounded-lg bg-base-100 hover:bg-base-300 transition-colors"
                      >
                        <div className="avatar">
                          <div className="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center">
                            {member.avatar ? (
                              <img
                                src={member.avatar}
                                alt={member.name}
                                className="w-full h-full object-cover rounded-full"
                              />
                            ) : (
                              <span className="text-sm font-medium text-primary">
                                {member.name?.charAt(0) || 'U'}
                              </span>
                            )}
                          </div>
                        </div>
                        <span className="font-medium">{member.name}</span>
                      </button>
                    ))}
                </div>
              ) : (
                <div className="text-center py-8 text-base-content/60">
                  <User className="w-12 h-12 mx-auto mb-2 opacity-50" />
                  <p>No team members available</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </>
  )
}

