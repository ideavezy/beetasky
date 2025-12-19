import { useEffect, useState } from 'react'
import { X, MessageSquare, CheckCircle, Search, Loader2 } from 'lucide-react'
import { useFlowStore } from '../../stores/flow'

export interface FlowPromptModalProps {
  onClose: () => void
}

export default function FlowPromptModal({ onClose }: FlowPromptModalProps) {
  const { pendingPrompt, isSubmitting, submitResponse, clearPendingPrompt } = useFlowStore()
  const [selectedValue, setSelectedValue] = useState<string>('')
  const [textInput, setTextInput] = useState('')

  // Reset state when prompt changes
  useEffect(() => {
    setSelectedValue('')
    setTextInput('')
  }, [pendingPrompt?.stepId])

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !isSubmitting) {
        handleClose()
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isSubmitting])

  const handleClose = () => {
    clearPendingPrompt()
    onClose()
  }

  const handleSubmit = async () => {
    if (!pendingPrompt) return

    let response: unknown

    if (pendingPrompt.promptType === 'choice') {
      response = { value: selectedValue }
    } else if (pendingPrompt.promptType === 'text' || pendingPrompt.promptType === 'search') {
      response = { value: textInput }
    } else if (pendingPrompt.promptType === 'confirm') {
      response = { value: selectedValue === 'yes' }
    }

    await submitResponse(pendingPrompt.flowId, pendingPrompt.stepId, response)
  }

  if (!pendingPrompt) return null

  const isValid = () => {
    if (pendingPrompt.promptType === 'choice') {
      return selectedValue !== ''
    }
    if (pendingPrompt.promptType === 'text' || pendingPrompt.promptType === 'search') {
      return textInput.trim() !== ''
    }
    if (pendingPrompt.promptType === 'confirm') {
      return selectedValue !== ''
    }
    return false
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={!isSubmitting ? handleClose : undefined}
      />

      {/* Modal */}
      <div className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-base-300">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-primary/20 flex items-center justify-center">
              <MessageSquare className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-base-content">Input Required</h2>
              <p className="text-sm text-base-content/60">AI Flow needs your input</p>
            </div>
          </div>
          <button
            onClick={handleClose}
            disabled={isSubmitting}
            className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/60 hover:text-base-content disabled:opacity-50"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Prompt Message */}
          <p className="text-base-content mb-4 whitespace-pre-wrap">
            {pendingPrompt.message}
          </p>

          {/* Choice Prompt */}
          {pendingPrompt.promptType === 'choice' && pendingPrompt.options && (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {pendingPrompt.options.map((option, index) => (
                <button
                  key={option.value}
                  onClick={() => setSelectedValue(option.value)}
                  disabled={isSubmitting}
                  className={`w-full p-3 rounded-lg border text-left transition-all flex items-center gap-3 ${
                    selectedValue === option.value
                      ? 'border-primary bg-primary/10 text-base-content'
                      : 'border-base-300 bg-base-100 hover:border-base-content/30 text-base-content/80'
                  } disabled:opacity-50`}
                >
                  <span className="w-6 h-6 rounded-full bg-base-300 flex items-center justify-center text-sm font-medium shrink-0">
                    {index + 1}
                  </span>
                  <span className="flex-1">{option.label}</span>
                  {selectedValue === option.value && (
                    <CheckCircle className="w-5 h-5 text-primary shrink-0" />
                  )}
                </button>
              ))}
            </div>
          )}

          {/* Text Input Prompt */}
          {(pendingPrompt.promptType === 'text' || pendingPrompt.promptType === 'search') && (
            <div className="relative">
              {pendingPrompt.promptType === 'search' && (
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40" />
              )}
              <input
                type="text"
                value={textInput}
                onChange={(e) => setTextInput(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && isValid() && !isSubmitting) {
                    handleSubmit()
                  }
                }}
                disabled={isSubmitting}
                placeholder={
                  pendingPrompt.promptType === 'search'
                    ? 'Enter search terms...'
                    : 'Enter your response...'
                }
                className={`input input-bordered w-full bg-base-100 ${
                  pendingPrompt.promptType === 'search' ? 'pl-10' : ''
                }`}
                autoFocus
              />
            </div>
          )}

          {/* Confirm Prompt */}
          {pendingPrompt.promptType === 'confirm' && (
            <div className="flex gap-3">
              <button
                onClick={() => setSelectedValue('yes')}
                disabled={isSubmitting}
                className={`flex-1 p-3 rounded-lg border transition-all ${
                  selectedValue === 'yes'
                    ? 'border-success bg-success/10 text-success'
                    : 'border-base-300 bg-base-100 hover:border-success/50'
                } disabled:opacity-50`}
              >
                Yes
              </button>
              <button
                onClick={() => setSelectedValue('no')}
                disabled={isSubmitting}
                className={`flex-1 p-3 rounded-lg border transition-all ${
                  selectedValue === 'no'
                    ? 'border-error bg-error/10 text-error'
                    : 'border-base-300 bg-base-100 hover:border-error/50'
                } disabled:opacity-50`}
              >
                No
              </button>
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="flex gap-3 justify-end p-4 border-t border-base-300 bg-base-300/30">
          <button
            onClick={handleClose}
            disabled={isSubmitting}
            className="btn btn-ghost"
          >
            Cancel
          </button>
          <button
            onClick={handleSubmit}
            disabled={!isValid() || isSubmitting}
            className="btn btn-primary min-w-[100px]"
          >
            {isSubmitting ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Submitting...
              </>
            ) : (
              'Continue'
            )}
          </button>
        </div>
      </div>
    </div>
  )
}

