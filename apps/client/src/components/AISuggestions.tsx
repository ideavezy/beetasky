import { useNavigate } from 'react-router-dom'
import {
  AlertTriangle,
  Lightbulb,
  Zap,
  Settings,
  ChevronRight,
  CheckCircle,
  RefreshCw,
  Sparkles,
  Building2,
} from 'lucide-react'
import { useDashboardStore, type AISuggestion, type AISuggestionAction } from '../stores/dashboard'
import { useAuthStore } from '../stores/auth'
import { useModalStore, MODAL_NAMES } from '../stores/modal'

interface AISuggestionsProps {
  onActionComplete?: (message: string) => void
}

export default function AISuggestions({ onActionComplete }: AISuggestionsProps) {
  const navigate = useNavigate()
  const { company, isInitialized } = useAuthStore()
  const { aiSuggestions, isLoadingAI, aiError, fetchAISuggestions, fetchProjects, executeAIAction } =
    useDashboardStore()
  const { openModal } = useModalStore()

  const handleRefresh = () => {
    if (company?.id) {
      fetchAISuggestions(company.id, !!company)
    }
  }

  const handleAction = async (action: AISuggestionAction) => {
    if (!company?.id) return

    switch (action.type) {
      case 'navigate':
        if (action.path) {
          navigate(action.path)
        }
        break
      case 'create_company':
        openModal(MODAL_NAMES.CREATE_COMPANY, {
          onSuccess: () => {
            // Refresh AI suggestions after company is created
            if (company?.id) {
              fetchAISuggestions(company.id, true)
            }
          }
        })
        break
      case 'create_project':
        openModal(MODAL_NAMES.CREATE_PROJECT, {
          onSuccess: () => {
            // Refresh dashboard data after project is created
            if (company?.id) {
              fetchProjects(company.id)
              fetchAISuggestions(company.id, !!company)
            }
          }
        })
        break
      case 'create_task':
        navigate('/tasks?action=create')
        break
      case 'complete_task':
        if (action.task_id) {
          const result = await executeAIAction(company.id, action)
          if (result.success && result.message) {
            onActionComplete?.(result.message)
          }
        }
        break
      case 'prioritize':
        if (action.project_id) {
          navigate(`/projects/${action.project_id}?view=priority`)
        }
        break
    }
  }

  const getSuggestionIcon = (type: AISuggestion['type']) => {
    switch (type) {
      case 'warning':
        return <AlertTriangle className="w-5 h-5 text-warning" />
      case 'tip':
        return <Lightbulb className="w-5 h-5 text-info" />
      case 'action':
        return <Zap className="w-5 h-5 text-primary" />
      case 'setup':
        return <Settings className="w-5 h-5 text-secondary" />
      default:
        return <Sparkles className="w-5 h-5 text-primary" />
    }
  }

  const getSuggestionBg = (type: AISuggestion['type']) => {
    switch (type) {
      case 'warning':
        return 'bg-warning/10 border-warning/20'
      case 'tip':
        return 'bg-info/10 border-info/20'
      case 'action':
        return 'bg-primary/10 border-primary/20'
      case 'setup':
        return 'bg-secondary/10 border-secondary/20'
      default:
        return 'bg-base-300'
    }
  }

  const getPriorityBadge = (priority: AISuggestion['priority']) => {
    switch (priority) {
      case 'high':
        return <span className="badge badge-error badge-xs">High</span>
      case 'medium':
        return <span className="badge badge-warning badge-xs">Medium</span>
      case 'low':
        return <span className="badge badge-ghost badge-xs">Low</span>
    }
  }

  // Wait for auth to initialize
  if (!isInitialized) {
    return (
      <div className="flex flex-col items-center justify-center py-8 gap-3">
        <span className="loading loading-spinner loading-md text-base-content/40"></span>
        <p className="text-sm text-base-content/40">Initializing...</p>
      </div>
    )
  }

  // No company - show setup suggestion
  if (!company?.id) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <div className="p-4 bg-gradient-to-r from-primary/20 to-accent/20 rounded-xl border border-primary/30">
          <div className="flex items-start gap-3">
            <Sparkles className="w-6 h-6 text-primary flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="font-semibold text-base-content mb-1">Welcome!</h3>
              <p className="text-sm text-base-content/70 mb-3">
                To get started with project management and AI suggestions, you need to create or join a company first.
              </p>
              <button
                onClick={() => openModal(MODAL_NAMES.CREATE_COMPANY)}
                className="btn btn-primary btn-sm gap-2"
              >
                <Building2 className="w-4 h-4" />
                Create Company
              </button>
            </div>
          </div>
        </div>
        
        <div className="text-center">
          <p className="text-xs text-base-content/40">
            A company is your workspace for managing projects, tasks, and team members.
          </p>
        </div>
      </div>
    )
  }

  if (isLoadingAI) {
    return (
      <div className="flex flex-col items-center justify-center py-8 gap-3">
        <span className="loading loading-spinner loading-lg text-primary"></span>
        <p className="text-sm text-base-content/60">Analyzing your workspace...</p>
      </div>
    )
  }

  if (aiError) {
    return (
      <div className="p-4">
        <div className="alert alert-error">
          <AlertTriangle className="w-5 h-5" />
          <div>
            <p className="text-sm">{aiError}</p>
          </div>
        </div>
        <button onClick={handleRefresh} className="btn btn-ghost btn-sm mt-2 w-full gap-2">
          <RefreshCw className="w-4 h-4" />
          Try Again
        </button>
      </div>
    )
  }

  if (!aiSuggestions) {
    return (
      <div className="flex flex-col items-center justify-center py-8 text-center px-4">
        <Sparkles className="w-12 h-12 text-base-content/20 mb-3" />
        <p className="text-base-content/60 text-sm">
          AI suggestions will appear here after loading your dashboard.
        </p>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      {/* Summary */}
      {aiSuggestions.summary && (
        <div className="p-3 bg-gradient-to-r from-primary/10 to-accent/10 rounded-xl">
          <p className="text-sm text-base-content">{aiSuggestions.summary}</p>
        </div>
      )}

      {/* Suggestions */}
      <div className="space-y-3">
        {aiSuggestions.suggestions.map((suggestion) => (
          <div
            key={suggestion.id}
            className={`p-3 rounded-xl border ${getSuggestionBg(suggestion.type)}`}
          >
            {/* Header */}
            <div className="flex items-start gap-3 mb-2">
              <div className="flex-shrink-0 mt-0.5">{getSuggestionIcon(suggestion.type)}</div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <h4 className="font-medium text-sm text-base-content truncate">
                    {suggestion.title}
                  </h4>
                  {getPriorityBadge(suggestion.priority)}
                </div>
                <p className="text-xs text-base-content/70 line-clamp-2">{suggestion.description}</p>
              </div>
            </div>

            {/* Actions */}
            {suggestion.actions.length > 0 && (
              <div className="flex flex-wrap gap-2 mt-3 pl-8">
                {suggestion.actions.map((action, idx) => (
                  <button
                    key={idx}
                    onClick={() => handleAction(action)}
                    className="btn btn-xs btn-ghost bg-base-100/50 hover:bg-base-100 gap-1"
                  >
                    {action.type === 'complete_task' && <CheckCircle className="w-3 h-3" />}
                    {action.label}
                    {action.type === 'navigate' && <ChevronRight className="w-3 h-3" />}
                  </button>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Refresh button */}
      <button onClick={handleRefresh} className="btn btn-ghost btn-sm gap-2 self-center mt-2">
        <RefreshCw className="w-4 h-4" />
        Refresh Suggestions
      </button>

      {/* Fallback indicator */}
      {aiSuggestions.fallback && (
        <p className="text-xs text-center text-base-content/40">
          Basic suggestions (AI service unavailable)
        </p>
      )}
    </div>
  )
}
