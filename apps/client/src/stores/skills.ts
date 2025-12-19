import { create } from 'zustand'
import { api } from '../lib/api'

// Types
export interface ApiConfig {
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  url: string
  headers: Record<string, string>
  body_template?: Record<string, any>
  timeout?: number
}

export interface InputSchema {
  type: 'object'
  properties: Record<string, {
    type: string
    description?: string
    enum?: string[]
  }>
  required: string[]
}

export interface Skill {
  id: string
  slug: string
  name: string
  description: string | null
  category: string
  skill_type: 'mcp_tool' | 'api_call' | 'composite' | 'webhook'
  icon: string | null
  is_system: boolean
  is_active: boolean
  input_schema: InputSchema | null
  secret_fields: string[]
  api_config: ApiConfig | null
  // User-specific settings
  is_enabled: boolean
  custom_config: Record<string, any>
  has_secrets_configured: boolean
  usage_count: number
  last_used_at: string | null
}

export interface SkillExecution {
  id: string
  status: 'pending' | 'success' | 'error' | 'timeout'
  input_params: Record<string, any>
  output_result: Record<string, any> | null
  error_message: string | null
  latency_ms: number | null
  created_at: string
}

export interface SkillsState {
  // State
  skills: Skill[]
  skillsByCategory: Record<string, Skill[]>
  categories: Record<string, string>
  isLoading: boolean
  error: string | null

  // Selected skill for modals
  selectedSkill: Skill | null
  isSettingsModalOpen: boolean
  isFormModalOpen: boolean
  isExecuting: boolean

  // Actions
  fetchSkills: () => Promise<void>
  toggleSkill: (slug: string, enabled: boolean) => Promise<void>
  updateSkillConfig: (slug: string, config: Record<string, any>) => Promise<void>
  executeSkill: (slug: string, params: Record<string, any>) => Promise<{ success: boolean; data?: any; error?: string }>
  getExecutionHistory: (slug: string) => Promise<SkillExecution[]>

  // Admin actions
  createSkill: (data: CreateSkillData) => Promise<{ success: boolean; skill?: Skill; error?: string }>
  updateSkill: (id: string, data: Partial<CreateSkillData>) => Promise<{ success: boolean; skill?: Skill; error?: string }>
  deleteSkill: (id: string) => Promise<{ success: boolean; error?: string }>

  // Modal controls
  openSettingsModal: (skill: Skill) => void
  closeSettingsModal: () => void
  openFormModal: (skill?: Skill) => void
  closeFormModal: () => void

  // Utilities
  getSkillBySlug: (slug: string) => Skill | undefined
  getEnabledSkills: () => Skill[]
}

export interface CreateSkillData {
  name: string
  description?: string
  category: string
  icon?: string
  skill_type: 'api_call' | 'webhook'
  api_config: ApiConfig
  input_schema?: InputSchema
  secret_fields?: string[]
}

export const useSkillsStore = create<SkillsState>((set, get) => ({
  // Initial state
  skills: [],
  skillsByCategory: {},
  categories: {},
  isLoading: false,
  error: null,
  selectedSkill: null,
  isSettingsModalOpen: false,
  isFormModalOpen: false,
  isExecuting: false,

  // Fetch all skills
  fetchSkills: async () => {
    set({ isLoading: true, error: null })

    try {
      const response = await api.get('/api/v1/skills')

      if (response.data.success) {
        const { skills, grouped, categories } = response.data.data

        set({
          skills: skills || [],
          skillsByCategory: grouped || {},
          categories: categories || {},
          isLoading: false,
        })
      } else {
        set({ error: response.data.error, isLoading: false })
      }
    } catch (error: any) {
      set({
        error: error.response?.data?.error || error.message || 'Failed to fetch skills',
        isLoading: false,
      })
    }
  },

  // Toggle skill enabled/disabled
  toggleSkill: async (slug: string, enabled: boolean) => {
    try {
      const response = await api.patch(`/api/v1/skills/${slug}/settings`, {
        is_enabled: enabled,
      })

      if (response.data.success) {
        // Update local state
        set((state) => ({
          skills: state.skills.map((skill) =>
            skill.slug === slug ? { ...skill, is_enabled: enabled } : skill
          ),
          skillsByCategory: Object.fromEntries(
            Object.entries(state.skillsByCategory).map(([category, skills]) => [
              category,
              skills.map((skill) =>
                skill.slug === slug ? { ...skill, is_enabled: enabled } : skill
              ),
            ])
          ),
        }))
      }
    } catch (error: any) {
      console.error('Failed to toggle skill:', error)
      throw error
    }
  },

  // Update skill user config (secrets, preferences)
  updateSkillConfig: async (slug: string, config: Record<string, any>) => {
    try {
      const response = await api.patch(`/api/v1/skills/${slug}/settings`, {
        custom_config: config,
      })

      if (response.data.success) {
        // Update local state
        set((state) => ({
          skills: state.skills.map((skill) =>
            skill.slug === slug
              ? {
                  ...skill,
                  custom_config: response.data.data.custom_config,
                  has_secrets_configured: true,
                }
              : skill
          ),
        }))
      }
    } catch (error: any) {
      console.error('Failed to update skill config:', error)
      throw error
    }
  },

  // Execute a skill
  executeSkill: async (slug: string, params: Record<string, any>) => {
    set({ isExecuting: true })

    try {
      const response = await api.post(`/api/v1/skills/${slug}/execute`, { params })

      set({ isExecuting: false })

      return {
        success: response.data.success,
        data: response.data.data,
        error: response.data.error,
      }
    } catch (error: any) {
      set({ isExecuting: false })

      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Execution failed',
      }
    }
  },

  // Get execution history
  getExecutionHistory: async (slug: string) => {
    try {
      const response = await api.get(`/api/v1/skills/${slug}/history`)

      if (response.data.success) {
        return response.data.data as SkillExecution[]
      }

      return []
    } catch (error) {
      console.error('Failed to fetch execution history:', error)
      return []
    }
  },

  // Create a new skill (admin)
  createSkill: async (data: CreateSkillData) => {
    try {
      const response = await api.post('/api/v1/admin/skills', data)

      if (response.data.success) {
        // Refresh skills list
        await get().fetchSkills()

        return {
          success: true,
          skill: response.data.data,
        }
      }

      return {
        success: false,
        error: response.data.error,
      }
    } catch (error: any) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Failed to create skill',
      }
    }
  },

  // Update a skill (admin)
  updateSkill: async (id: string, data: Partial<CreateSkillData>) => {
    try {
      const response = await api.put(`/api/v1/admin/skills/${id}`, data)

      if (response.data.success) {
        // Refresh skills list
        await get().fetchSkills()

        return {
          success: true,
          skill: response.data.data,
        }
      }

      return {
        success: false,
        error: response.data.error,
      }
    } catch (error: any) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Failed to update skill',
      }
    }
  },

  // Delete a skill (admin)
  deleteSkill: async (id: string) => {
    try {
      const response = await api.delete(`/api/v1/admin/skills/${id}`)

      if (response.data.success) {
        // Remove from local state
        set((state) => ({
          skills: state.skills.filter((s) => s.id !== id),
        }))

        return { success: true }
      }

      return {
        success: false,
        error: response.data.error,
      }
    } catch (error: any) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Failed to delete skill',
      }
    }
  },

  // Modal controls
  openSettingsModal: (skill: Skill) => {
    set({ selectedSkill: skill, isSettingsModalOpen: true })
  },

  closeSettingsModal: () => {
    set({ selectedSkill: null, isSettingsModalOpen: false })
  },

  openFormModal: (skill?: Skill) => {
    set({ selectedSkill: skill || null, isFormModalOpen: true })
  },

  closeFormModal: () => {
    set({ selectedSkill: null, isFormModalOpen: false })
  },

  // Utilities
  getSkillBySlug: (slug: string) => {
    return get().skills.find((s) => s.slug === slug)
  },

  getEnabledSkills: () => {
    return get().skills.filter((s) => s.is_enabled && s.is_active)
  },
}))

