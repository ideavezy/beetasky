import { create } from 'zustand'

// Modal names as constants for type safety
export const MODAL_NAMES = {
  CREATE_COMPANY: 'createCompany',
  CREATE_PROJECT: 'createProject',
  CREATE_TOPIC: 'createTopic',
  CREATE_TASK: 'createTask',
} as const

export type ModalName = typeof MODAL_NAMES[keyof typeof MODAL_NAMES]

// Props for each modal type
export interface CreateCompanyModalProps {
  onSuccess?: (company: any) => void
}

export interface CreateProjectModalProps {
  companyId?: string
  onSuccess?: (project: any) => void
}

export interface CreateTopicModalProps {
  projectId: string
  onSuccess?: (topic: any) => void
}

export interface CreateTaskModalProps {
  topicId: string
  projectId: string
  topicName?: string
  onSuccess?: (task: any) => void
}

// Union of all modal props
export type ModalProps = 
  | CreateCompanyModalProps 
  | CreateProjectModalProps 
  | CreateTopicModalProps 
  | CreateTaskModalProps
  | Record<string, any>

interface ModalState {
  // Current active modal (null if none open)
  activeModal: ModalName | null
  // Props to pass to the active modal
  modalProps: ModalProps
  
  // Actions
  openModal: (name: ModalName, props?: ModalProps) => void
  closeModal: () => void
}

export const useModalStore = create<ModalState>()((set) => ({
  activeModal: null,
  modalProps: {},

  openModal: (name, props = {}) => {
    set({
      activeModal: name,
      modalProps: props,
    })
  },

  closeModal: () => {
    set({
      activeModal: null,
      modalProps: {},
    })
  },
}))

