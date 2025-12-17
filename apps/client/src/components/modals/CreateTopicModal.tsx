import { useState, useEffect, useRef } from 'react'
import { X, Layers, Loader2 } from 'lucide-react'
import { api } from '../../lib/api'

// Predefined colors for topics
const TOPIC_COLORS = [
  { name: 'Gray', value: '#6b7280' },
  { name: 'Red', value: '#ef4444' },
  { name: 'Orange', value: '#f97316' },
  { name: 'Amber', value: '#f59e0b' },
  { name: 'Yellow', value: '#eab308' },
  { name: 'Lime', value: '#84cc16' },
  { name: 'Green', value: '#22c55e' },
  { name: 'Emerald', value: '#10b981' },
  { name: 'Teal', value: '#14b8a6' },
  { name: 'Cyan', value: '#06b6d4' },
  { name: 'Sky', value: '#0ea5e9' },
  { name: 'Blue', value: '#3b82f6' },
  { name: 'Indigo', value: '#6366f1' },
  { name: 'Violet', value: '#8b5cf6' },
  { name: 'Purple', value: '#a855f7' },
  { name: 'Fuchsia', value: '#d946ef' },
  { name: 'Pink', value: '#ec4899' },
  { name: 'Rose', value: '#f43f5e' },
]

interface CreateTopicModalProps {
  projectId: string
  onClose: () => void
  onSuccess?: (topic: any) => void
}

export default function CreateTopicModal({ projectId, onClose, onSuccess }: CreateTopicModalProps) {
  const [topicName, setTopicName] = useState('')
  const [description, setDescription] = useState('')
  const [selectedColor, setSelectedColor] = useState(TOPIC_COLORS[0].value)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  // Focus input when modal opens
  useEffect(() => {
    setTopicName('')
    setDescription('')
    setSelectedColor(TOPIC_COLORS[Math.floor(Math.random() * TOPIC_COLORS.length)].value)
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

    if (!topicName.trim()) {
      setError('Topic name is required')
      return
    }

    if (topicName.trim().length < 2) {
      setError('Topic name must be at least 2 characters')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const response = await api.post(`/api/v1/projects/${projectId}/topics`, {
        name: topicName.trim(),
        description: description.trim() || null,
        color: selectedColor,
      })

      if (response.data.success) {
        const topic = response.data.data

        if (onSuccess) {
          onSuccess(topic)
        }

        onClose()
      } else {
        setError(response.data.message || 'Failed to create topic')
      }
    } catch (err: any) {
      console.error('Failed to create topic:', err)
      setError(
        err.response?.data?.error ||
          err.response?.data?.message ||
          err.response?.data?.errors?.name?.[0] ||
          'Failed to create topic. Please try again.'
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
      <div className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-base-300">
          <div className="flex items-center gap-3">
            <div
              className="w-10 h-10 rounded-xl flex items-center justify-center"
              style={{ backgroundColor: `${selectedColor}20` }}
            >
              <Layers className="w-5 h-5" style={{ color: selectedColor }} />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-base-content">Create Topic</h2>
              <p className="text-sm text-base-content/60">Add a new topic to organize tasks</p>
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
            {/* Topic name */}
            <div>
              <label htmlFor="topicName" className="block text-sm font-medium text-base-content mb-2">
                Topic Name
              </label>
              <input
                ref={inputRef}
                id="topicName"
                type="text"
                value={topicName}
                onChange={(e) => setTopicName(e.target.value)}
                placeholder="e.g., Design, Development, Testing"
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
                placeholder="Brief description of this topic"
                className="textarea textarea-bordered w-full resize-none"
                rows={2}
                disabled={isLoading}
                maxLength={500}
              />
            </div>

            {/* Color picker */}
            <div>
              <label className="block text-sm font-medium text-base-content mb-2">Color</label>
              <div className="flex flex-wrap gap-2">
                {TOPIC_COLORS.map((color) => (
                  <button
                    key={color.value}
                    type="button"
                    onClick={() => setSelectedColor(color.value)}
                    className={`w-7 h-7 rounded-full transition-all ${
                      selectedColor === color.value
                        ? 'ring-2 ring-offset-2 ring-offset-base-200 ring-primary scale-110'
                        : 'hover:scale-110'
                    }`}
                    style={{ backgroundColor: color.value }}
                    title={color.name}
                    disabled={isLoading}
                  />
                ))}
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
              disabled={isLoading || !topicName.trim()}
              className="btn btn-primary flex-1 gap-2"
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Creating...
                </>
              ) : (
                <>
                  <Layers className="w-4 h-4" />
                  Create Topic
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

