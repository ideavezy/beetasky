import { useFlowStore } from '../stores/flow'
import FlowPromptModal from './modals/FlowPromptModal'
import FlowProgressCard from './FlowProgressCard'

/**
 * FlowManager component renders flow-related modals and progress cards.
 * This component should be rendered at the app root level.
 */
export default function FlowManager() {
  const { showPromptModal, pendingPrompt, closePromptModal, currentFlow } = useFlowStore()

  // Check if there's an active flow that should show progress
  const showProgress = currentFlow && 
    (currentFlow.status === 'running' || currentFlow.status === 'pending' || currentFlow.status === 'awaiting_user')

  return (
    <>
      {/* Flow Progress Card - shown as floating card in bottom-left */}
      {showProgress && (
        <div className="fixed bottom-4 left-4 z-40 w-96 max-w-[calc(100vw-2rem)] shadow-2xl animate-in slide-in-from-bottom-4 duration-300">
          <FlowProgressCard flow={currentFlow} compact />
        </div>
      )}

      {/* Flow Prompt Modal - for user input */}
      {showPromptModal && pendingPrompt && (
        <FlowPromptModal onClose={closePromptModal} />
      )}
    </>
  )
}

