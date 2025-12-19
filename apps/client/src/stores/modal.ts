import { create } from 'zustand'

// Modal names as constants for type safety
export const MODAL_NAMES = {
  CREATE_COMPANY: 'createCompany',
  CREATE_PROJECT: 'createProject',
  CREATE_TOPIC: 'createTopic',
  CREATE_TASK: 'createTask',
  CREATE_CONTACT: 'createContact',
  EDIT_CONTACT: 'editContact',
  ADD_ACTIVITY: 'addActivity',
  ALERT: 'alert',
  LINK_PROJECT: 'linkProject',
  CREATE_DEAL: 'createDeal',
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

export interface CreateContactModalProps {
  companyId?: string
  onSuccess?: (contact: any) => void
}

export interface EditContactModalProps {
  contact: any
  onSuccess?: (contact: any) => void
}

export interface AlertModalProps {
  title: string
  message: string
  type?: 'info' | 'success' | 'warning' | 'error'
  confirmText?: string
  cancelText?: string
  showCancel?: boolean
  onConfirm?: () => void
}

export interface LinkProjectModalProps {
  contactId: string
  contactName: string
  onSuccess?: (assignedProjects: string[]) => void
}

export interface AddActivityModalProps {
  companyId?: string
  preSelectedContactId?: string
  onSuccess?: (activity: any) => void
}

export interface CreateDealModalProps {
  contactId?: string
  contactName?: string
  onSuccess?: (deal: any) => void
}

// Generic modal props - uses Record for flexibility since we access properties dynamically
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type ModalProps = Record<string, any>

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

