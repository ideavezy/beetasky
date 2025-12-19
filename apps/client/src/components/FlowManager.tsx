import { useFlowStore } from '../stores/flow'
import FlowPromptModal from './modals/FlowPromptModal'

/**
 * FlowManager component renders flow-related modals and handles real-time updates.
 * This component should be rendered at the app root level.
 */
export default function FlowManager() {
  const { showPromptModal, pendingPrompt, closePromptModal } = useFlowStore()

  // Only render modal when there's a pending prompt and showPromptModal is true
  if (!showPromptModal || !pendingPrompt) {
    return null
  }

  return <FlowPromptModal onClose={closePromptModal} />
}

