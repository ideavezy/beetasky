import { 
  CheckCircle, 
  Circle, 
  Loader2, 
  XCircle, 
  Clock, 
  ChevronDown, 
  ChevronUp,
  PlayCircle,
  PauseCircle,
  RotateCcw,
  X as XIcon
} from 'lucide-react'
import { useState } from 'react'
import type { Flow, FlowStep } from '../stores/flow'
import { useFlowStore } from '../stores/flow'

interface FlowProgressCardProps {
  flow: Flow
  onSelect?: () => void
  compact?: boolean
}

export default function FlowProgressCard({ flow, onSelect, compact = false }: FlowProgressCardProps) {
  const [expanded, setExpanded] = useState(!compact)
  const { cancelFlow, retryFlow, openPromptModal } = useFlowStore()

  const getStatusColor = (status: Flow['status']) => {
    switch (status) {
      case 'completed':
        return 'text-success'
      case 'failed':
        return 'text-error'
      case 'running':
        return 'text-info'
      case 'awaiting_user':
        return 'text-warning'
      case 'cancelled':
        return 'text-base-content/50'
      default:
        return 'text-base-content/70'
    }
  }

  const getStatusIcon = (status: Flow['status']) => {
    switch (status) {
      case 'completed':
        return <CheckCircle className="w-5 h-5" />
      case 'failed':
        return <XCircle className="w-5 h-5" />
      case 'running':
        return <Loader2 className="w-5 h-5 animate-spin" />
      case 'awaiting_user':
        return <PauseCircle className="w-5 h-5" />
      case 'cancelled':
        return <XIcon className="w-5 h-5" />
      default:
        return <Clock className="w-5 h-5" />
    }
  }

  const getStepIcon = (step: FlowStep) => {
    switch (step.status) {
      case 'completed':
        return <CheckCircle className="w-4 h-4 text-success" />
      case 'failed':
        return <XCircle className="w-4 h-4 text-error" />
      case 'running':
        return <Loader2 className="w-4 h-4 text-info animate-spin" />
      case 'awaiting_user':
        return <PauseCircle className="w-4 h-4 text-warning" />
      case 'skipped':
        return <Circle className="w-4 h-4 text-base-content/30" />
      default:
        return <Circle className="w-4 h-4 text-base-content/40" />
    }
  }

  const handleRetry = (e: React.MouseEvent) => {
    e.stopPropagation()
    retryFlow(flow.id)
  }

  const handleCancel = (e: React.MouseEvent) => {
    e.stopPropagation()
    cancelFlow(flow.id)
  }

  const handlePrompt = (e: React.MouseEvent) => {
    e.stopPropagation()
    openPromptModal()
  }

  return (
    <div 
      className="bg-base-200 rounded-xl border border-base-300 overflow-hidden"
      onClick={onSelect}
    >
      {/* Header */}
      <div 
        className={`p-4 flex items-center gap-3 ${onSelect ? 'cursor-pointer hover:bg-base-300/50' : ''}`}
        onClick={() => setExpanded(!expanded)}
      >
        {/* Status Icon */}
        <div className={`shrink-0 ${getStatusColor(flow.status)}`}>
          {getStatusIcon(flow.status)}
        </div>

        {/* Title & Progress */}
        <div className="flex-1 min-w-0">
          <h3 className="font-medium text-base-content truncate">{flow.title}</h3>
          <div className="flex items-center gap-2 mt-1">
            <progress 
              className={`progress progress-sm w-24 ${
                flow.status === 'completed' ? 'progress-success' :
                flow.status === 'failed' ? 'progress-error' :
                flow.status === 'running' ? 'progress-info' :
                'progress-warning'
              }`}
              value={flow.progressPercentage} 
              max="100"
            />
            <span className="text-xs text-base-content/60">
              {flow.completedSteps}/{flow.totalSteps} steps
            </span>
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center gap-2">
          {flow.status === 'awaiting_user' && (
            <button
              onClick={handlePrompt}
              className="btn btn-xs btn-warning"
            >
              Respond
            </button>
          )}
          {flow.status === 'failed' && (
            <button
              onClick={handleRetry}
              className="btn btn-xs btn-ghost"
              title="Retry"
            >
              <RotateCcw className="w-3 h-3" />
            </button>
          )}
          {(flow.status === 'running' || flow.status === 'pending' || flow.status === 'awaiting_user') && (
            <button
              onClick={handleCancel}
              className="btn btn-xs btn-ghost text-error"
              title="Cancel"
            >
              <XIcon className="w-3 h-3" />
            </button>
          )}
          <button className="btn btn-xs btn-ghost">
            {expanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
          </button>
        </div>
      </div>

      {/* Steps List */}
      {expanded && flow.steps.length > 0 && (
        <div className="border-t border-base-300 p-4">
          <div className="space-y-2">
            {flow.steps.map((step, index) => (
              <div 
                key={step.id}
                className={`flex items-start gap-3 p-2 rounded-lg transition-colors ${
                  step.status === 'running' ? 'bg-info/10' :
                  step.status === 'awaiting_user' ? 'bg-warning/10' :
                  step.status === 'failed' ? 'bg-error/10' :
                  ''
                }`}
              >
                {/* Step Number & Connector */}
                <div className="flex flex-col items-center">
                  <div className="w-6 h-6 rounded-full bg-base-300 flex items-center justify-center text-xs font-medium">
                    {index + 1}
                  </div>
                  {index < flow.steps.length - 1 && (
                    <div className={`w-0.5 h-4 mt-1 ${
                      step.status === 'completed' ? 'bg-success/40' : 'bg-base-300'
                    }`} />
                  )}
                </div>

                {/* Step Content */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    {getStepIcon(step)}
                    <span className={`text-sm font-medium ${
                      step.status === 'completed' ? 'text-success' :
                      step.status === 'failed' ? 'text-error' :
                      step.status === 'running' ? 'text-info' :
                      step.status === 'awaiting_user' ? 'text-warning' :
                      'text-base-content/70'
                    }`}>
                      {step.title}
                    </span>
                  </div>

                  {/* Step Details */}
                  {step.status === 'running' && (
                    <p className="text-xs text-info mt-1 flex items-center gap-1">
                      <PlayCircle className="w-3 h-3" /> In progress...
                    </p>
                  )}
                  {step.status === 'awaiting_user' && step.promptMessage && (
                    <p className="text-xs text-warning mt-1 line-clamp-2">
                      {step.promptMessage}
                    </p>
                  )}
                  {step.status === 'failed' && step.errorMessage && (
                    <p className="text-xs text-error mt-1 line-clamp-2">
                      {step.errorMessage}
                    </p>
                  )}
                  {step.status === 'completed' && step.result !== null && (
                    <p className="text-xs text-success/70 mt-1">
                      ✓ Completed
                    </p>
                  )}
                </div>
              </div>
            ))}
          </div>

          {/* Error Message */}
          {flow.status === 'failed' && flow.lastError ? (
            <div className="mt-4 p-3 bg-error/10 border border-error/20 rounded-lg">
              <p className="text-sm text-error">{flow.lastError}</p>
            </div>
          ) : null}

          {/* Suggestions */}
          {flow.status === 'completed' && Array.isArray(flow.flowContext?.suggestions) ? (
            <div className="mt-4">
              {(flow.flowContext.suggestions as Array<{ message: string; action: string }>).map((suggestion: { message: string; action: string }, i: number) => (
                <div 
                  key={i}
                  className="p-3 bg-success/10 border border-success/20 rounded-lg flex items-center justify-between"
                >
                  <p className="text-sm text-success">{suggestion.message}</p>
                  <button className="btn btn-xs btn-success btn-outline">
                    Yes
                  </button>
                </div>
              ))}
            </div>
          ) : null}
        </div>
      )}
    </div>
  )
}

