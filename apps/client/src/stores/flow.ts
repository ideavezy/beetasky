import { create } from 'zustand'
import { api } from '../lib/api'

// Flow step type
export interface FlowStep {
  id: string
  position: number
  type: 'tool_call' | 'user_prompt' | 'ai_decision' | 'conditional' | 'parallel' | 'wait'
  skillSlug: string | null
  title: string
  description: string | null
  status: 'pending' | 'running' | 'completed' | 'failed' | 'skipped' | 'awaiting_user' | 'cancelled'
  result: Record<string, unknown> | null
  errorMessage: string | null
  promptType: 'choice' | 'text' | 'confirm' | 'search' | null
  promptMessage: string | null
  promptOptions: Array<{
    value: string
    label: string
    data: Record<string, unknown>
  }> | null
  userResponse: Record<string, unknown> | null
  startedAt: string | null
  completedAt: string | null
}

// Flow type
export interface Flow {
  id: string
  title: string
  status: 'planning' | 'pending' | 'running' | 'paused' | 'awaiting_user' | 'completed' | 'failed' | 'cancelled'
  originalRequest: string
  totalSteps: number
  completedSteps: number
  progressPercentage: number
  currentStepId: string | null
  steps: FlowStep[]
  flowContext: Record<string, unknown>
  lastError: string | null
  createdAt: string
  startedAt: string | null
  completedAt: string | null
}

// Flow log entry
export interface FlowLog {
  id: string
  type: string
  message: string | null
  data: Record<string, unknown>
  stepId: string | null
  actorType: 'system' | 'ai' | 'user' | null
  createdAt: string
}

interface FlowState {
  // State
  activeFlows: Flow[]
  currentFlow: Flow | null
  pendingPrompt: {
    flowId: string
    stepId: string
    promptType: string
    message: string
    options: Array<{ value: string; label: string; data: Record<string, unknown> }> | null
  } | null
  isLoading: boolean
  isSubmitting: boolean
  error: string | null
  showPromptModal: boolean

  // Actions
  loadActiveFlows: (companyId: string) => Promise<void>
  loadFlow: (flowId: string) => Promise<void>
  createFlow: (message: string, companyId: string, conversationId?: string) => Promise<Flow | null>
  submitResponse: (flowId: string, stepId: string, response: unknown) => Promise<void>
  cancelFlow: (flowId: string) => Promise<void>
  retryFlow: (flowId: string) => Promise<void>

  // Event handlers (called from WebSocket/SSE)
  handleStepCompleted: (data: {
    flowId: string
    stepId: string
    stepPosition: number
    status: string
    result: Record<string, unknown>
    completedSteps: number
    totalSteps: number
    flowStatus: string
  }) => void
  handleUserInputRequired: (data: {
    flowId: string
    flowTitle: string
    stepId: string
    stepPosition: number
    stepTitle: string
    promptType: string
    promptMessage: string
    promptOptions: Array<{ value: string; label: string; data: Record<string, unknown> }> | null
  }) => void
  handleFlowCompleted: (data: {
    flowId: string
    status: string
    completedSteps: number
    totalSteps: number
    suggestions: Array<{ message: string; action: string; params: Record<string, unknown> }>
  }) => void

  // UI state
  openPromptModal: () => void
  closePromptModal: () => void
  clearPendingPrompt: () => void
  clearError: () => void
}

export const useFlowStore = create<FlowState>((set, _get) => ({
  // Initial state
  activeFlows: [],
  currentFlow: null,
  pendingPrompt: null,
  isLoading: false,
  isSubmitting: false,
  error: null,
  showPromptModal: false,

  loadActiveFlows: async (companyId: string) => {
    set({ isLoading: true, error: null })

    try {
      const response = await api.get('/v1/ai/flows', {
        headers: { 'X-Company-ID': companyId },
      })

      if (response.data.success) {
        const flows = response.data.data.map(mapFlowFromApi)
        set({ activeFlows: flows })
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { error?: string } }; message?: string }
      set({ error: error.response?.data?.error || error.message || 'Failed to load flows' })
    } finally {
      set({ isLoading: false })
    }
  },

  loadFlow: async (flowId: string) => {
    set({ isLoading: true, error: null })

    try {
      const response = await api.get(`/v1/ai/flows/${flowId}`)

      if (response.data.success) {
        const flow = mapFlowFromApi(response.data.data)
        set({ currentFlow: flow })

        // Check if any step needs user input
        const awaitingStep = flow.steps.find(s => s.status === 'awaiting_user')
        if (awaitingStep && awaitingStep.promptMessage) {
          set({
            pendingPrompt: {
              flowId: flow.id,
              stepId: awaitingStep.id,
              promptType: awaitingStep.promptType || 'text',
              message: awaitingStep.promptMessage,
              options: awaitingStep.promptOptions,
            },
            showPromptModal: true,
          })
        }
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { error?: string } }; message?: string }
      set({ error: error.response?.data?.error || error.message || 'Failed to load flow' })
    } finally {
      set({ isLoading: false })
    }
  },

  createFlow: async (message: string, companyId: string, conversationId?: string) => {
    set({ isLoading: true, error: null })

    try {
      const response = await api.post('/v1/ai/flows', {
        message,
        conversation_id: conversationId,
      }, {
        headers: { 'X-Company-ID': companyId },
      })

      if (response.data.success) {
        const flow = mapFlowFromApi(response.data.data)
        set(state => ({
          activeFlows: [flow, ...state.activeFlows],
          currentFlow: flow,
        }))
        return flow
      }
      return null
    } catch (err: unknown) {
      const error = err as { response?: { data?: { error?: string } }; message?: string }
      set({ error: error.response?.data?.error || error.message || 'Failed to create flow' })
      return null
    } finally {
      set({ isLoading: false })
    }
  },

  submitResponse: async (flowId: string, stepId: string, response: unknown) => {
    set({ isSubmitting: true, error: null })

    try {
      const apiResponse = await api.post(`/v1/ai/flows/${flowId}/steps/${stepId}/respond`, {
        response,
      })

      if (apiResponse.data.success) {
        const flow = mapFlowFromApi(apiResponse.data.data)
        set(state => ({
          currentFlow: flow,
          activeFlows: state.activeFlows.map(f => f.id === flow.id ? flow : f),
          pendingPrompt: null,
          showPromptModal: false,
        }))
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { error?: string } }; message?: string }
      set({ error: error.response?.data?.error || error.message || 'Failed to submit response' })
    } finally {
      set({ isSubmitting: false })
    }
  },

  cancelFlow: async (flowId: string) => {
    try {
      await api.post(`/v1/ai/flows/${flowId}/cancel`)
      set(state => ({
        activeFlows: state.activeFlows.filter(f => f.id !== flowId),
        currentFlow: state.currentFlow?.id === flowId ? null : state.currentFlow,
        pendingPrompt: state.pendingPrompt?.flowId === flowId ? null : state.pendingPrompt,
        showPromptModal: state.pendingPrompt?.flowId === flowId ? false : state.showPromptModal,
      }))
    } catch (err: unknown) {
      const error = err as { response?: { data?: { error?: string } }; message?: string }
      set({ error: error.response?.data?.error || error.message || 'Failed to cancel flow' })
    }
  },

  retryFlow: async (flowId: string) => {
    set({ isLoading: true, error: null })

    try {
      const response = await api.post(`/v1/ai/flows/${flowId}/retry`)

      if (response.data.success) {
        const flow = mapFlowFromApi(response.data.data)
        set(state => ({
          currentFlow: flow,
          activeFlows: state.activeFlows.map(f => f.id === flow.id ? flow : f),
        }))
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { error?: string } }; message?: string }
      set({ error: error.response?.data?.error || error.message || 'Failed to retry flow' })
    } finally {
      set({ isLoading: false })
    }
  },

  // WebSocket/SSE event handlers
  handleStepCompleted: (data) => {
    set(state => {
      // Update flow in active flows
      const updatedFlows = state.activeFlows.map(flow => {
        if (flow.id !== data.flowId) return flow

        return {
          ...flow,
          status: data.flowStatus as Flow['status'],
          completedSteps: data.completedSteps,
          progressPercentage: Math.round((data.completedSteps / data.totalSteps) * 100),
          steps: flow.steps.map(step =>
            step.id === data.stepId
              ? { ...step, status: data.status as FlowStep['status'], result: data.result }
              : step
          ),
        }
      })

      // Update current flow if it matches
      const updatedCurrent = state.currentFlow?.id === data.flowId
        ? updatedFlows.find(f => f.id === data.flowId) || state.currentFlow
        : state.currentFlow

      return {
        activeFlows: updatedFlows,
        currentFlow: updatedCurrent,
      }
    })
  },

  handleUserInputRequired: (data) => {
    set({
      pendingPrompt: {
        flowId: data.flowId,
        stepId: data.stepId,
        promptType: data.promptType,
        message: data.promptMessage,
        options: data.promptOptions,
      },
      showPromptModal: true,
    })

    // Update flow status
    set(state => ({
      activeFlows: state.activeFlows.map(flow =>
        flow.id === data.flowId
          ? {
              ...flow,
              status: 'awaiting_user' as const,
              steps: flow.steps.map(step =>
                step.id === data.stepId
                  ? {
                      ...step,
                      status: 'awaiting_user' as const,
                      promptType: data.promptType as FlowStep['promptType'],
                      promptMessage: data.promptMessage,
                      promptOptions: data.promptOptions,
                    }
                  : step
              ),
            }
          : flow
      ),
    }))
  },

  handleFlowCompleted: (data) => {
    set(state => {
      const updatedFlows = state.activeFlows.map(flow => {
        if (flow.id !== data.flowId) return flow

        return {
          ...flow,
          status: data.status as Flow['status'],
          completedSteps: data.completedSteps,
          progressPercentage: 100,
          flowContext: {
            ...(flow.flowContext || {}),
            suggestions: data.suggestions,
          },
        }
      })

      // Move completed flows out of active (or keep for history)
      const completedFlow = updatedFlows.find(f => f.id === data.flowId)

      return {
        activeFlows: updatedFlows.filter(f => f.status !== 'completed' && f.status !== 'failed'),
        currentFlow: state.currentFlow?.id === data.flowId ? completedFlow || null : state.currentFlow,
      }
    })
  },

  openPromptModal: () => set({ showPromptModal: true }),
  closePromptModal: () => set({ showPromptModal: false }),
  clearPendingPrompt: () => set({ pendingPrompt: null, showPromptModal: false }),
  clearError: () => set({ error: null }),
}))

// Helper function to map API response to our types
function mapFlowFromApi(data: Record<string, unknown>): Flow {
  return {
    id: data.id as string,
    title: data.title as string,
    status: data.status as Flow['status'],
    originalRequest: data.original_request as string,
    totalSteps: data.total_steps as number,
    completedSteps: data.completed_steps as number,
    progressPercentage: data.progress_percentage as number,
    currentStepId: (data.current_step_id as string) || null,
    steps: ((data.steps as Array<Record<string, unknown>>) || []).map(mapStepFromApi),
    flowContext: (data.flow_context as Record<string, unknown>) || {},
    lastError: (data.last_error as string) || null,
    createdAt: data.created_at as string,
    startedAt: (data.started_at as string) || null,
    completedAt: (data.completed_at as string) || null,
  }
}

function mapStepFromApi(data: Record<string, unknown>): FlowStep {
  return {
    id: data.id as string,
    position: data.position as number,
    type: data.type as FlowStep['type'],
    skillSlug: (data.skill_slug as string) || null,
    title: data.title as string,
    description: (data.description as string) || null,
    status: data.status as FlowStep['status'],
    result: (data.result as Record<string, unknown>) || null,
    errorMessage: (data.error_message as string) || null,
    promptType: (data.prompt_type as FlowStep['promptType']) || null,
    promptMessage: (data.prompt_message as string) || null,
    promptOptions: (data.prompt_options as FlowStep['promptOptions']) || null,
    userResponse: (data.user_response as Record<string, unknown>) || null,
    startedAt: (data.started_at as string) || null,
    completedAt: (data.completed_at as string) || null,
  }
}

