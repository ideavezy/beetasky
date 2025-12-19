import { useState, useEffect, useRef } from 'react'
import { X, CheckSquare, Loader2, Calendar, Flag } from 'lucide-react'
import { api } from '../../lib/api'

const PRIORITIES = [
  { value: 'low', label: 'Low', color: 'bg-base-content/30' },
  { value: 'medium', label: 'Medium', color: 'bg-info' },
  { value: 'high', label: 'High', color: 'bg-warning' },
  { value: 'urgent', label: 'Urgent', color: 'bg-error' },
]

interface CreateTaskModalProps {
  topicId: string
  projectId: string
  topicName?: string
  onClose: () => void
  onSuccess?: (task: any) => void
}

export default function CreateTaskModal({
  topicId,
  projectId: _projectId,
  topicName,
  onClose,
  onSuccess,
}: CreateTaskModalProps) {
  // projectId is passed for potential future use but not currently needed
  void _projectId
  const [taskTitle, setTaskTitle] = useState('')
  const [description, setDescription] = useState('')
  const [priority, setPriority] = useState('medium') // Default to Medium
  const [dueDate, setDueDate] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  // Focus input when modal opens
  useEffect(() => {
    setTaskTitle('')
    setDescription('')
    setPriority('medium')
    setDueDate('')
    setError(null)
    setTimeout(() => inputRef.current?.focus(), 100)
  }, [])

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

    if (!taskTitle.trim()) {
      setError('Task title is required')
      return
    }

    if (taskTitle.trim().length < 2) {
      setError('Task title must be at least 2 characters')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const response = await api.post(`/api/v1/topics/${topicId}/tasks`, {
        title: taskTitle.trim(),
        description: description.trim() || null,
        priority,
        due_date: dueDate || null,
      })

      if (response.data.success) {
        const task = response.data.data

        if (onSuccess) {
          onSuccess(task)
        }

        onClose()
      } else {
        setError(response.data.message || 'Failed to create task')
      }
    } catch (err: any) {
      console.error('Failed to create task:', err)
      setError(
        err.response?.data?.error ||
          err.response?.data?.message ||
          err.response?.data?.errors?.title?.[0] ||
          'Failed to create task. Please try again.'
      )
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={() => !isLoading && onClose()}
      />

      {/* Modal */}
      <div className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-base-300">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-primary/20 flex items-center justify-center">
              <CheckSquare className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-base-content">Create Task</h2>
              {topicName && (
                <p className="text-sm text-base-content/60">
                  Adding to <span className="font-medium">{topicName}</span>
                </p>
              )}
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
            {/* Task title */}
            <div>
              <label htmlFor="taskTitle" className="block text-sm font-medium text-base-content mb-2">
                Task Title
              </label>
              <input
                ref={inputRef}
                id="taskTitle"
                type="text"
                value={taskTitle}
                onChange={(e) => setTaskTitle(e.target.value)}
                placeholder="What needs to be done?"
                className="input input-bordered w-full"
                disabled={isLoading}
                maxLength={255}
              />
            </div>

            {/* Description */}
            <div>
              <label
                htmlFor="description"
                className="block text-sm font-medium text-base-content mb-2"
              >
                Description <span className="text-base-content/50">(optional)</span>
              </label>
              <textarea
                id="description"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Add more details about this task"
                className="textarea textarea-bordered w-full resize-none"
                rows={3}
                disabled={isLoading}
                maxLength={2000}
              />
            </div>

            {/* Priority and Due Date row */}
            <div className="grid grid-cols-2 gap-4">
              {/* Priority */}
              <div>
                <label className="block text-sm font-medium text-base-content mb-2">
                  <Flag className="w-4 h-4 inline mr-1" />
                  Priority
                </label>
                <div className="flex gap-1">
                  {PRIORITIES.map((p) => (
                    <button
                      key={p.value}
                      type="button"
                      onClick={() => setPriority(p.value)}
                      disabled={isLoading}
                      className={`flex-1 py-2 px-2 rounded-lg text-xs font-medium transition-all ${
                        priority === p.value
                          ? 'ring-2 ring-primary bg-base-100'
                          : 'bg-base-300 hover:bg-base-100'
                      }`}
                    >
                      <div className={`w-2 h-2 rounded-full ${p.color} mx-auto mb-1`} />
                      {p.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Due Date */}
              <div>
                <label htmlFor="dueDate" className="block text-sm font-medium text-base-content mb-2">
                  <Calendar className="w-4 h-4 inline mr-1" />
                  Due Date <span className="text-base-content/50">(optional)</span>
                </label>
                <input
                  id="dueDate"
                  type="date"
                  value={dueDate}
                  onChange={(e) => setDueDate(e.target.value)}
                  className="input input-bordered w-full"
                  disabled={isLoading}
                  min={new Date().toISOString().split('T')[0]}
                />
              </div>
            </div>

            {error && <p className="text-sm text-error">{error}</p>}
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
              disabled={isLoading || !taskTitle.trim()}
              className="btn btn-primary flex-1 gap-2"
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Creating...
                </>
              ) : (
                <>
                  <CheckSquare className="w-4 h-4" />
                  Create Task
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

