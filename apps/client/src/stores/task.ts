import { create } from 'zustand'
import { api } from '../lib/api'

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
  comments_count?: number
  created_at: string
  updated_at: string
}

interface ProjectMember {
  id: string
  name: string
  avatar: string | null
  role?: string
}

interface TaskStore {
  // Selected task for the drawer
  selectedTask: Task | null
  isDrawerOpen: boolean
  
  // Project context
  currentProjectId: string | null
  projectMembers: ProjectMember[]
  
  // Actions
  openTaskDrawer: (task: Task) => void
  closeTaskDrawer: () => void
  updateSelectedTask: (data: Partial<Task>) => void
  setProjectContext: (projectId: string, members: ProjectMember[]) => void
  clearProjectContext: () => void
  
  // API actions
  fetchTaskDetails: (taskId: string) => Promise<Task | null>
  updateTask: (taskId: string, data: Partial<Task>) => Promise<Task | null>
}

export const useTaskStore = create<TaskStore>((set, get) => ({
  selectedTask: null,
  isDrawerOpen: false,
  currentProjectId: null,
  projectMembers: [],
  
  openTaskDrawer: (task) => {
    set({ selectedTask: task, isDrawerOpen: true })
  },
  
  closeTaskDrawer: () => {
    set({ isDrawerOpen: false })
    // Delay clearing the task to allow for close animation
    setTimeout(() => {
      set({ selectedTask: null })
    }, 300)
  },
  
  updateSelectedTask: (data) => {
    const { selectedTask } = get()
    if (selectedTask) {
      set({ selectedTask: { ...selectedTask, ...data } })
    }
  },
  
  setProjectContext: (projectId, members) => {
    set({ currentProjectId: projectId, projectMembers: members })
  },
  
  clearProjectContext: () => {
    set({ currentProjectId: null, projectMembers: [] })
  },
  
  fetchTaskDetails: async (taskId) => {
    try {
      const response = await api.get(`/api/v1/tasks/${taskId}`)
      if (response.data.success) {
        const task = response.data.data
        set({ selectedTask: task })
        return task
      }
      return null
    } catch (error) {
      console.error('Failed to fetch task details:', error)
      return null
    }
  },
  
  updateTask: async (taskId, data) => {
    try {
      const response = await api.put(`/api/v1/tasks/${taskId}`, data)
      if (response.data.success) {
        const updatedTask = response.data.data
        const { selectedTask } = get()
        if (selectedTask && selectedTask.id === taskId) {
          set({ selectedTask: updatedTask })
        }
        return updatedTask
      }
      return null
    } catch (error) {
      console.error('Failed to update task:', error)
      return null
    }
  },
}))

