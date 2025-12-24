import { create } from 'zustand'

type RefreshScope = 
  | 'projects'
  | 'tasks'
  | 'contacts'
  | 'deals'
  | 'reminders'
  | 'topics'
  | 'comments'
  | 'all'

interface RefreshState {
  // Increment counters to trigger re-renders
  projectsVersion: number
  tasksVersion: number
  contactsVersion: number
  dealsVersion: number
  remindersVersion: number
  topicsVersion: number
  commentsVersion: number
  
  // Last refresh timestamp
  lastRefreshAt: number | null
  
  // Actions
  triggerRefresh: (scopes: RefreshScope | RefreshScope[]) => void
  refreshAll: () => void
}

// Map skill names to refresh scopes
const skillToScopeMap: Record<string, RefreshScope[]> = {
  // Project skills
  'create_project': ['projects'],
  'update_project': ['projects'],
  'delete_project': ['projects'],
  'list_projects': [], // Read-only, no refresh needed
  
  // Task skills
  'create_task': ['tasks'],
  'update_task': ['tasks'],
  'delete_task': ['tasks'],
  'search_tasks': [], // Read-only
  'list_tasks': [], // Read-only
  'assign_task': ['tasks'],
  
  // Topic skills
  'create_topic': ['topics', 'projects'],
  'update_topic': ['topics'],
  'delete_topic': ['topics', 'projects'],
  'list_topics': [], // Read-only
  
  // Comment skills
  'create_comment': ['comments', 'tasks'],
  'update_comment': ['comments'],
  'delete_comment': ['comments'],
  'list_comments': [], // Read-only
  
  // Contact/CRM skills
  'create_contact': ['contacts'],
  'update_contact': ['contacts'],
  'delete_contact': ['contacts'],
  'list_contacts': [], // Read-only
  'get_contact': [], // Read-only
  'convert_lead': ['contacts'],
  'add_contact_note': ['contacts'],
  'score_lead': ['contacts'],
  
  // Deal skills
  'create_deal': ['deals'],
  'update_deal': ['deals'],
  'delete_deal': ['deals'],
  'list_deals': [], // Read-only
  
  // Reminder skills
  'create_reminder': ['reminders'],
  'update_reminder': ['reminders'],
  'delete_reminder': ['reminders'],
  'list_reminders': [], // Read-only
}

export const useRefreshStore = create<RefreshState>((set, get) => ({
  projectsVersion: 0,
  tasksVersion: 0,
  contactsVersion: 0,
  dealsVersion: 0,
  remindersVersion: 0,
  topicsVersion: 0,
  commentsVersion: 0,
  lastRefreshAt: null,
  
  triggerRefresh: (scopes: RefreshScope | RefreshScope[]) => {
    const scopeArray = Array.isArray(scopes) ? scopes : [scopes]
    
    set((state) => {
      const updates: Partial<RefreshState> = {
        lastRefreshAt: Date.now(),
      }
      
      for (const scope of scopeArray) {
        if (scope === 'all') {
          updates.projectsVersion = state.projectsVersion + 1
          updates.tasksVersion = state.tasksVersion + 1
          updates.contactsVersion = state.contactsVersion + 1
          updates.dealsVersion = state.dealsVersion + 1
          updates.remindersVersion = state.remindersVersion + 1
          updates.topicsVersion = state.topicsVersion + 1
          updates.commentsVersion = state.commentsVersion + 1
        } else if (scope === 'projects') {
          updates.projectsVersion = (updates.projectsVersion ?? state.projectsVersion) + 1
        } else if (scope === 'tasks') {
          updates.tasksVersion = (updates.tasksVersion ?? state.tasksVersion) + 1
        } else if (scope === 'contacts') {
          updates.contactsVersion = (updates.contactsVersion ?? state.contactsVersion) + 1
        } else if (scope === 'deals') {
          updates.dealsVersion = (updates.dealsVersion ?? state.dealsVersion) + 1
        } else if (scope === 'reminders') {
          updates.remindersVersion = (updates.remindersVersion ?? state.remindersVersion) + 1
        } else if (scope === 'topics') {
          updates.topicsVersion = (updates.topicsVersion ?? state.topicsVersion) + 1
        } else if (scope === 'comments') {
          updates.commentsVersion = (updates.commentsVersion ?? state.commentsVersion) + 1
        }
      }
      
      return updates as RefreshState
    })
  },
  
  refreshAll: () => {
    get().triggerRefresh('all')
  },
}))

/**
 * Get refresh scopes for a skill execution
 */
export function getRefreshScopesForSkill(skillName: string): RefreshScope[] {
  return skillToScopeMap[skillName] || []
}

/**
 * Trigger refresh based on skill execution results
 */
export function triggerRefreshFromSkillResults(toolResults: Array<{ skill: string; result: any }>) {
  const { triggerRefresh } = useRefreshStore.getState()
  
  const scopesToRefresh = new Set<RefreshScope>()
  
  for (const { skill, result } of toolResults) {
    // Only refresh if the skill was successful
    if (result?.success !== false) {
      const scopes = getRefreshScopesForSkill(skill)
      scopes.forEach(scope => scopesToRefresh.add(scope))
    }
  }
  
  if (scopesToRefresh.size > 0) {
    triggerRefresh(Array.from(scopesToRefresh))
  }
}

