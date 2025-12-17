import { lazy, Suspense } from 'react'
import { useModalStore, MODAL_NAMES } from '../stores/modal'

// Lazy load modals for code splitting
const CreateCompanyModal = lazy(() => import('./modals/CreateCompanyModal'))
const CreateProjectModal = lazy(() => import('./modals/CreateProjectModal'))
const CreateTopicModal = lazy(() => import('./modals/CreateTopicModal'))
const CreateTaskModal = lazy(() => import('./modals/CreateTaskModal'))

// Loading fallback for modals
function ModalLoadingFallback() {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" />
      <div className="relative bg-base-200 rounded-2xl p-8">
        <span className="loading loading-spinner loading-lg text-primary"></span>
      </div>
    </div>
  )
}

export default function ModalManager() {
  const { activeModal, modalProps, closeModal } = useModalStore()

  if (!activeModal) return null

  const renderModal = () => {
    switch (activeModal) {
      case MODAL_NAMES.CREATE_COMPANY:
        return (
          <CreateCompanyModal
            onClose={closeModal}
            onSuccess={(company) => {
              if (modalProps.onSuccess) {
                (modalProps.onSuccess as (company: any) => void)(company)
              }
              closeModal()
            }}
          />
        )
      case MODAL_NAMES.CREATE_PROJECT:
        return (
          <CreateProjectModal
            companyId={modalProps.companyId as string | undefined}
            onClose={closeModal}
            onSuccess={(project) => {
              if (modalProps.onSuccess) {
                (modalProps.onSuccess as (project: any) => void)(project)
              }
              closeModal()
            }}
          />
        )
      case MODAL_NAMES.CREATE_TOPIC:
        return (
          <CreateTopicModal
            projectId={modalProps.projectId as string}
            onClose={closeModal}
            onSuccess={(topic) => {
              if (modalProps.onSuccess) {
                (modalProps.onSuccess as (topic: any) => void)(topic)
              }
              closeModal()
            }}
          />
        )
      case MODAL_NAMES.CREATE_TASK:
        return (
          <CreateTaskModal
            topicId={modalProps.topicId as string}
            projectId={modalProps.projectId as string}
            topicName={modalProps.topicName as string | undefined}
            onClose={closeModal}
            onSuccess={(task) => {
              if (modalProps.onSuccess) {
                (modalProps.onSuccess as (task: any) => void)(task)
              }
              closeModal()
            }}
          />
        )
      default:
        return null
    }
  }

  return (
    <Suspense fallback={<ModalLoadingFallback />}>
      {renderModal()}
    </Suspense>
  )
}

