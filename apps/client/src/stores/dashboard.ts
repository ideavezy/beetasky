import { create } from 'zustand'
import { api } from '../lib/api'

// Task type from dashboard API
export interface DashboardTask {
  id: string
  title: string
  description: string | null
  status: string
  priority: string
  completed: boolean
  due_date: string | null
  project: {
    id: string
    name: string
  }
  topic: {
    id: string
    name: string
  } | null
  assignees: {
    id: string
    name: string
    avatar: string | null
  }[]
  created_at: string
}

// Project type from projects API
export interface DashboardProject {
  id: string
  name: string
  description: string | null
  status: string
  code: string | null
  ai_enabled: boolean
  start_date: string | null
  due_date: string | null
  members: {
    id: string
    name: string
    avatar: string | null
    role: string
  }[]
  tasks_count: number
  topics_count: number
  completed_tasks_count: number
  completion_percentage: number
  created_at: string
  updated_at: string
}

// Contact stats
export interface ContactStats {
  total: number
  leads: number
  customers: number
  prospects: number
  vendors: number
  partners: number
  active: number
}

// AI Suggestion types
export interface AISuggestionAction {
  type: 'navigate' | 'create_company' | 'create_project' | 'create_task' | 'complete_task' | 'prioritize'
  label: string
  path?: string
  task_id?: string
  project_id?: string
}

export interface AISuggestion {
  id: string
  type: 'warning' | 'tip' | 'action' | 'setup'
  title: string
  description: string
  priority: 'high' | 'medium' | 'low'
  actions: AISuggestionAction[]
}

export interface AISuggestionsResponse {
  summary: string
  suggestions: AISuggestion[]
  generated_at: string
  fallback?: boolean
}

interface DashboardState {
  // Data
  tasks: DashboardTask[]
  projects: DashboardProject[]
  contactStats: ContactStats | null
  aiSuggestions: AISuggestionsResponse | null

  // Loading states
  isLoadingTasks: boolean
  isLoadingProjects: boolean
  isLoadingContacts: boolean
  isLoadingAI: boolean

  // Error states
  tasksError: string | null
  projectsError: string | null
  contactsError: string | null
  aiError: string | null

  // Pagination
  tasksPagination: {
    current_page: number
    last_page: number
    total: number
    has_more: boolean
  } | null
  projectsPagination: {
    current_page: number
    last_page: number
    total: number
    has_more: boolean
  } | null

  // Actions
  fetchTasks: (companyId: string, options?: { status?: string; assignedOnly?: boolean }) => Promise<void>
  fetchProjects: (companyId: string, options?: { status?: string }) => Promise<void>
  fetchContactStats: (companyId: string) => Promise<void>
  fetchAISuggestions: (companyId: string, hasCompany: boolean) => Promise<void>
  fetchDashboardData: (companyId: string, hasCompany: boolean) => Promise<void>
  executeAIAction: (companyId: string, action: AISuggestionAction) => Promise<{ success: boolean; message?: string }>
  clearDashboard: () => void
}

export const useDashboardStore = create<DashboardState>((set, get) => ({
  // Initial state
  tasks: [],
  projects: [],
  contactStats: null,
  aiSuggestions: null,

  isLoadingTasks: false,
  isLoadingProjects: false,
  isLoadingContacts: false,
  isLoadingAI: false,

  tasksError: null,
  projectsError: null,
  contactsError: null,
  aiError: null,

  tasksPagination: null,
  projectsPagination: null,

  fetchTasks: async (companyId, options = {}) => {
    set({ isLoadingTasks: true, tasksError: null })

    try {
      const params = new URLSearchParams()
      if (options.status) params.append('status', options.status)
      if (options.assignedOnly) params.append('assigned_only', 'true')
      params.append('per_page', '10')

      const response = await api.get(`/api/v1/tasks/dashboard?${params.toString()}`, {
        headers: { 'X-Company-ID': companyId },
      })

      if (response.data.success) {
        set({
          tasks: response.data.data,
          tasksPagination: response.data.pagination,
          isLoadingTasks: false,
        })
      } else {
        set({
          tasksError: response.data.message || 'Failed to fetch tasks',
          isLoadingTasks: false,
        })
      }
    } catch (error: any) {
      console.error('[Dashboard] Failed to fetch tasks:', error)
      set({
        tasksError: error.response?.data?.message || error.message || 'Failed to fetch tasks',
        isLoadingTasks: false,
      })
    }
  },

  fetchProjects: async (companyId, options = {}) => {
    set({ isLoadingProjects: true, projectsError: null })

    try {
      const params = new URLSearchParams()
      if (options.status) params.append('status', options.status)
      params.append('per_page', '10')

      const response = await api.get(`/api/v1/projects?${params.toString()}`, {
        headers: { 'X-Company-ID': companyId },
      })

      if (response.data.success) {
        set({
          projects: response.data.data,
          projectsPagination: response.data.pagination,
          isLoadingProjects: false,
        })
      } else {
        set({
          projectsError: response.data.message || 'Failed to fetch projects',
          isLoadingProjects: false,
        })
      }
    } catch (error: any) {
      console.error('[Dashboard] Failed to fetch projects:', error)
      set({
        projectsError: error.response?.data?.message || error.message || 'Failed to fetch projects',
        isLoadingProjects: false,
      })
    }
  },

  fetchContactStats: async (companyId) => {
    set({ isLoadingContacts: true, contactsError: null })

    try {
      const response = await api.get('/api/v1/contacts/stats', {
        headers: { 'X-Company-ID': companyId },
      })

      if (response.data.success) {
        set({
          contactStats: response.data.data,
          isLoadingContacts: false,
        })
      } else {
        set({
          contactsError: response.data.message || 'Failed to fetch contacts',
          isLoadingContacts: false,
        })
      }
    } catch (error: any) {
      console.error('[Dashboard] Failed to fetch contact stats:', error)
      set({
        contactsError: error.response?.data?.message || error.message || 'Failed to fetch contacts',
        isLoadingContacts: false,
      })
    }
  },

  fetchAISuggestions: async (companyId, _hasCompany) => {
    set({ isLoadingAI: true, aiError: null })

    try {
      // New optimized endpoint - backend fetches all data directly from DB
      // No need to send context from frontend, backend handles everything
      console.log('[AI] Fetching dashboard suggestions from backend...')
      
      const response = await api.get('/api/v1/ai/dashboard-suggestions', {
        headers: { 'X-Company-ID': companyId },
      })

      console.log('[AI] Response:', response.data)

      if (response.data.success) {
        console.log('[AI] ✅ Suggestions received:', response.data.data)
        set({
          aiSuggestions: response.data.data,
          isLoadingAI: false,
        })
      } else {
        console.warn('[AI] ❌ API returned failure:', response.data.message)
        set({
          aiError: response.data.message || 'Failed to get AI suggestions',
          isLoadingAI: false,
        })
      }
    } catch (error: any) {
      console.error('[AI] ❌ Failed to fetch suggestions:', error)
      console.error('[AI] Error response:', error.response?.data)
      set({
        aiError: error.response?.data?.message || error.message || 'Failed to get AI suggestions',
        isLoadingAI: false,
      })
    }
  },

  fetchDashboardData: async (companyId, hasCompany = true) => {
    const state = get()
    
    // Fetch all data in parallel - AI suggestions no longer depend on frontend data
    // Backend now fetches tasks/projects/contacts directly from DB
    await Promise.all([
      state.fetchTasks(companyId),
      state.fetchProjects(companyId),
      state.fetchContactStats(companyId),
      state.fetchAISuggestions(companyId, hasCompany),
    ])
  },

  executeAIAction: async (companyId, action) => {
    try {
      if (action.type === 'complete_task' && action.task_id) {
        const response = await api.post(
          '/api/v1/ai/execute-action',
          {
            action_type: 'complete_task',
            task_id: action.task_id,
          },
          { headers: { 'X-Company-ID': companyId } }
        )

        if (response.data.success) {
          // Refresh tasks
          get().fetchTasks(companyId)
          return { success: true, message: response.data.data?.message }
        }
        return { success: false, message: response.data.message }
      }

      // For navigation actions, return success (handling is done in component)
      return { success: true }
    } catch (error: any) {
      console.error('[Dashboard] Failed to execute AI action:', error)
      return {
        success: false,
        message: error.response?.data?.message || error.message || 'Action failed',
      }
    }
  },

  clearDashboard: () => {
    set({
      tasks: [],
      projects: [],
      contactStats: null,
      aiSuggestions: null,
      tasksPagination: null,
      projectsPagination: null,
      tasksError: null,
      projectsError: null,
      contactsError: null,
      aiError: null,
    })
  },
}))
