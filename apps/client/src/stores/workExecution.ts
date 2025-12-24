import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { api } from '../lib/api'

// Status mapping for Kanban columns
export const KANBAN_COLUMNS = [
  { id: 'backlog', title: 'Backlog', color: '#6b7280' },
  { id: 'todo', title: 'To Do', color: '#f59e0b' },
  { id: 'in_progress', title: 'In Progress', color: '#3b82f6' },
  { id: 'on_hold', title: 'On Hold', color: '#ef4444' },
  { id: 'in_review', title: 'Review', color: '#8b5cf6' },
  { id: 'done', title: 'Done', color: '#10b981' },
] as const

export type KanbanStatus = typeof KANBAN_COLUMNS[number]['id']

export interface WorkExecutionTask {
  id: string
  title: string
  description: string | null
  status: string
  priority: string
  completed: boolean
  due_date: string | null
  order: number
  comments_count: number
  project: {
    id: string
    name: string
    code: string | null
    status: string
  }
  topic: {
    id: string
    name: string
    color: string | null
  } | null
  assignees: {
    id: string
    name: string
    avatar: string | null
  }[]
  created_at: string
  updated_at: string
}

export interface WorkExecutionProject {
  id: string
  name: string
  code: string | null
}

export type ViewMode = 'kanban' | 'list'

export type QuickFilter = 'all' | 'today' | 'this_week' | 'overdue' | 'high_priority' | 'assigned_to_me'

export interface FilterState {
  search: string
  projectId: string | null
  status: string[]
  priority: string[]
  quickFilter: QuickFilter
  excludeCompleted: boolean
}

export interface SortState {
  sortBy: 'created_at' | 'due_date' | 'priority' | 'status' | 'title'
  sortOrder: 'asc' | 'desc'
}

interface WorkExecutionState {
  // Data
  tasks: WorkExecutionTask[]
  projects: WorkExecutionProject[]
  
  // View state
  viewMode: ViewMode
  
  // Filter state
  filters: FilterState
  sort: SortState
  
  // Selection state (for list view bulk actions)
  selectedTaskIds: string[]
  
  // Loading states
  isLoading: boolean
  isUpdating: boolean
  error: string | null
  
  // Pagination
  pagination: {
    currentPage: number
    lastPage: number
    perPage: number
    total: number
    hasMore: boolean
  } | null
  
  // Actions
  setViewMode: (mode: ViewMode) => void
  setFilters: (filters: Partial<FilterState>) => void
  setSort: (sort: Partial<SortState>) => void
  setQuickFilter: (filter: QuickFilter) => void
  resetFilters: () => void
  
  // Selection actions
  toggleTaskSelection: (taskId: string) => void
  selectAllTasks: () => void
  clearSelection: () => void
  
  // Data actions
  fetchTasks: (companyId: string) => Promise<void>
  fetchProjects: (companyId: string) => Promise<void>
  updateTaskStatus: (taskId: string, status: string, companyId: string) => Promise<void>
  updateTaskPriority: (taskId: string, priority: string, companyId: string) => Promise<void>
  toggleTaskComplete: (taskId: string, completed: boolean, companyId: string) => Promise<void>
  bulkUpdateStatus: (taskIds: string[], status: string, companyId: string) => Promise<void>
  
  // Local state updates
  updateTaskLocally: (taskId: string, updates: Partial<WorkExecutionTask>) => void
}

const defaultFilters: FilterState = {
  search: '',
  projectId: null,
  status: [],
  priority: [],
  quickFilter: 'all',
  excludeCompleted: false,
}

const defaultSort: SortState = {
  sortBy: 'created_at',
  sortOrder: 'desc',
}

export const useWorkExecutionStore = create<WorkExecutionState>()(
  persist(
    (set, get) => ({
      // Initial state
      tasks: [],
      projects: [],
      viewMode: 'kanban',
      filters: defaultFilters,
      sort: defaultSort,
      selectedTaskIds: [],
      isLoading: false,
      isUpdating: false,
      error: null,
      pagination: null,
      
      // View mode
      setViewMode: (mode) => set({ viewMode: mode }),
      
      // Filters
      setFilters: (newFilters) => set((state) => ({
        filters: { ...state.filters, ...newFilters },
      })),
      
      setSort: (newSort) => set((state) => ({
        sort: { ...state.sort, ...newSort },
      })),
      
      setQuickFilter: (filter) => set((state) => ({
        filters: { ...state.filters, quickFilter: filter },
      })),
      
      resetFilters: () => set({ filters: defaultFilters }),
      
      // Selection
      toggleTaskSelection: (taskId) => set((state) => ({
        selectedTaskIds: state.selectedTaskIds.includes(taskId)
          ? state.selectedTaskIds.filter((id) => id !== taskId)
          : [...state.selectedTaskIds, taskId],
      })),
      
      selectAllTasks: () => set((state) => ({
        selectedTaskIds: state.tasks.map((t) => t.id),
      })),
      
      clearSelection: () => set({ selectedTaskIds: [] }),
      
      // Fetch tasks
      fetchTasks: async (companyId) => {
        const { filters, sort } = get()
        set({ isLoading: true, error: null })
        
        try {
          const params = new URLSearchParams()
          params.append('per_page', '200')
          
          // Search
          if (filters.search) {
            params.append('search', filters.search)
          }
          
          // Project filter
          if (filters.projectId) {
            params.append('project_id', filters.projectId)
          }
          
          // Status filter
          if (filters.status && filters.status.length > 0) {
            filters.status.forEach((s) => params.append('status[]', s))
          }
          
          // Priority filter
          if (filters.priority && filters.priority.length > 0) {
            filters.priority.forEach((p) => params.append('priority[]', p))
          }
          
          // Quick filters
          switch (filters.quickFilter) {
            case 'today':
              params.append('due_filter', 'today')
              break
            case 'this_week':
              params.append('due_filter', 'this_week')
              break
            case 'overdue':
              params.append('due_filter', 'overdue')
              break
            case 'high_priority':
              params.append('priority[]', 'high')
              params.append('priority[]', 'urgent')
              break
            case 'assigned_to_me':
              params.append('assigned_only', 'true')
              break
          }
          
          // Exclude completed
          if (filters.excludeCompleted) {
            params.append('exclude_completed', 'true')
          }
          
          // Sorting
          params.append('sort_by', sort.sortBy)
          params.append('sort_order', sort.sortOrder)
          
          const response = await api.get(`/api/v1/tasks/dashboard?${params.toString()}`, {
            headers: { 'X-Company-ID': companyId },
          })
          
          if (response.data.success) {
            set({
              tasks: response.data.data,
              pagination: {
                currentPage: response.data.pagination.current_page,
                lastPage: response.data.pagination.last_page,
                perPage: response.data.pagination.per_page,
                total: response.data.pagination.total,
                hasMore: response.data.pagination.has_more,
              },
              isLoading: false,
            })
          } else {
            set({
              error: response.data.message || 'Failed to fetch tasks',
              isLoading: false,
            })
          }
        } catch (error: any) {
          console.error('[WorkExecution] Failed to fetch tasks:', error)
          set({
            error: error.response?.data?.message || error.message || 'Failed to fetch tasks',
            isLoading: false,
          })
        }
      },
      
      // Fetch projects for filter dropdown
      fetchProjects: async (companyId) => {
        try {
          const response = await api.get('/api/v1/projects?per_page=100', {
            headers: { 'X-Company-ID': companyId },
          })
          
          if (response.data.success) {
            set({
              projects: response.data.data.map((p: any) => ({
                id: p.id,
                name: p.name,
                code: p.code,
              })),
            })
          }
        } catch (error) {
          console.error('[WorkExecution] Failed to fetch projects:', error)
        }
      },
      
      // Update task status
      updateTaskStatus: async (taskId, status, companyId) => {
        const previousTasks = get().tasks
        
        // Optimistic update
        set((state) => ({
          tasks: state.tasks.map((t) =>
            t.id === taskId
              ? { ...t, status, completed: status === 'done' }
              : t
          ),
          isUpdating: true,
        }))
        
        try {
          await api.put(`/api/v1/tasks/${taskId}`, {
            status,
            completed: status === 'done',
          }, {
            headers: { 'X-Company-ID': companyId },
          })
          
          set({ isUpdating: false })
        } catch (error: any) {
          console.error('[WorkExecution] Failed to update task status:', error)
          // Revert on error
          set({ tasks: previousTasks, isUpdating: false })
        }
      },
      
      // Update task priority
      updateTaskPriority: async (taskId, priority, companyId) => {
        const previousTasks = get().tasks
        
        // Optimistic update
        set((state) => ({
          tasks: state.tasks.map((t) =>
            t.id === taskId ? { ...t, priority } : t
          ),
          isUpdating: true,
        }))
        
        try {
          await api.put(`/api/v1/tasks/${taskId}`, { priority }, {
            headers: { 'X-Company-ID': companyId },
          })
          
          set({ isUpdating: false })
        } catch (error: any) {
          console.error('[WorkExecution] Failed to update task priority:', error)
          set({ tasks: previousTasks, isUpdating: false })
        }
      },
      
      // Toggle task completion
      toggleTaskComplete: async (taskId, completed, companyId) => {
        const previousTasks = get().tasks
        
        // Optimistic update
        set((state) => ({
          tasks: state.tasks.map((t) =>
            t.id === taskId
              ? { ...t, completed, status: completed ? 'done' : 'todo' }
              : t
          ),
          isUpdating: true,
        }))
        
        try {
          await api.put(`/api/v1/tasks/${taskId}`, { completed }, {
            headers: { 'X-Company-ID': companyId },
          })
          
          set({ isUpdating: false })
        } catch (error: any) {
          console.error('[WorkExecution] Failed to toggle task completion:', error)
          set({ tasks: previousTasks, isUpdating: false })
        }
      },
      
      // Bulk update status
      bulkUpdateStatus: async (taskIds, status, companyId) => {
        const previousTasks = get().tasks
        
        // Optimistic update
        set((state) => ({
          tasks: state.tasks.map((t) =>
            taskIds.includes(t.id)
              ? { ...t, status, completed: status === 'done' }
              : t
          ),
          isUpdating: true,
          selectedTaskIds: [],
        }))
        
        try {
          // Update each task (could be optimized with a bulk endpoint later)
          await Promise.all(
            taskIds.map((taskId) =>
              api.put(`/api/v1/tasks/${taskId}`, {
                status,
                completed: status === 'done',
              }, {
                headers: { 'X-Company-ID': companyId },
              })
            )
          )
          
          set({ isUpdating: false })
        } catch (error: any) {
          console.error('[WorkExecution] Failed to bulk update tasks:', error)
          set({ tasks: previousTasks, isUpdating: false })
        }
      },
      
      // Local state update
      updateTaskLocally: (taskId, updates) => set((state) => ({
        tasks: state.tasks.map((t) =>
          t.id === taskId ? { ...t, ...updates } : t
        ),
      })),
    }),
    {
      name: 'work-execution-storage',
      partialize: (state) => ({
        viewMode: state.viewMode,
        filters: {
          excludeCompleted: state.filters.excludeCompleted,
          quickFilter: state.filters.quickFilter,
        },
        sort: state.sort,
      }),
      // Properly merge persisted state with defaults to avoid undefined properties
      merge: (persistedState, currentState) => {
        const persisted = persistedState as Partial<WorkExecutionState>
        return {
          ...currentState,
          viewMode: persisted.viewMode ?? currentState.viewMode,
          sort: { ...currentState.sort, ...persisted.sort },
          filters: {
            ...currentState.filters,
            ...persisted.filters,
          },
        }
      },
    }
  )
)

// Utility function to group tasks by status for Kanban view
export function groupTasksByStatus(tasks: WorkExecutionTask[]): Record<string, WorkExecutionTask[]> {
  const grouped: Record<string, WorkExecutionTask[]> = {}
  
  // Initialize all columns
  KANBAN_COLUMNS.forEach((col) => {
    grouped[col.id] = []
  })
  
  // Group tasks
  tasks.forEach((task) => {
    const status = task.status
    if (grouped[status]) {
      grouped[status].push(task)
    } else {
      // Default to 'todo' if status doesn't match
      grouped['todo'].push(task)
    }
  })
  
  return grouped
}

// Get column info by status
export function getColumnByStatus(status: string) {
  return KANBAN_COLUMNS.find((col) => col.id === status) || KANBAN_COLUMNS[1] // Default to 'To Do'
}

