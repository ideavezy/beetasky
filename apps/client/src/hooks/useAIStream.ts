import { useRef, useCallback } from 'react'
import { useLocation } from 'react-router-dom'
import { useChatStore } from '../stores/chat'
import { useAuthStore } from '../stores/auth'
import { useContractStore } from '../stores/contracts'

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

interface StreamCallbacks {
  onStart?: (conversationId: string, messageId: string | null) => void
  onChunk?: (content: string) => void
  onDone?: (messageId: string, conversationId: string) => void
  onError?: (error: string) => void
}

/**
 * Hook for managing AI chat streaming via SSE.
 * Provides lower-level control than the store's sendMessage.
 */
export function useAIStream() {
  const abortControllerRef = useRef<AbortController | null>(null)
  const { session } = useAuthStore()
  const { company } = useAuthStore()

  /**
   * Stream a message to the AI and receive chunks via callbacks.
   */
  const streamMessage = useCallback(
    async (
      message: string,
      conversationId: string | null,
      callbacks: StreamCallbacks = {}
    ): Promise<void> => {
      if (!session?.access_token) {
        callbacks.onError?.('Not authenticated')
        return
      }

      if (!company?.id) {
        callbacks.onError?.('No company selected')
        return
      }

      // Create new abort controller
      abortControllerRef.current = new AbortController()

      try {
        const response = await fetch(`${API_URL}/api/v1/ai/chat/stream`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
            Authorization: `Bearer ${session.access_token}`,
            'X-Company-ID': company.id,
          },
          body: JSON.stringify({
            message,
            conversation_id: conversationId,
          }),
          signal: abortControllerRef.current.signal,
        })

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }

        if (!response.body) {
          throw new Error('No response body')
        }

        const reader = response.body.getReader()
        const decoder = new TextDecoder()
        let buffer = ''

        while (true) {
          const { done, value } = await reader.read()

          if (done) break

          buffer += decoder.decode(value, { stream: true })

          // Process complete SSE messages
          const lines = buffer.split('\n')
          buffer = lines.pop() || ''

          for (const line of lines) {
            const trimmedLine = line.trim()

            if (!trimmedLine || !trimmedLine.startsWith('data: ')) {
              continue
            }

            const jsonStr = trimmedLine.slice(6)

            try {
              const data = JSON.parse(jsonStr)

              if (data.type === 'start') {
                callbacks.onStart?.(data.conversation_id, data.message_id)
              } else if (data.type === 'chunk') {
                callbacks.onChunk?.(data.content)
              } else if (data.type === 'done') {
                callbacks.onDone?.(data.message_id, data.conversation_id)
              } else if (data.type === 'error') {
                callbacks.onError?.(data.message)
              }
            } catch {
              console.warn('Invalid SSE JSON:', jsonStr)
            }
          }
        }
      } catch (error: any) {
        if (error.name === 'AbortError') {
          // User cancelled - don't report as error
          return
        }
        callbacks.onError?.(error.message || 'Failed to stream response')
      } finally {
        abortControllerRef.current = null
      }
    },
    [session, company]
  )

  /**
   * Abort the current stream.
   */
  const abort = useCallback(() => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort()
      abortControllerRef.current = null
    }
  }, [])

  /**
   * Check if currently streaming.
   */
  const isStreaming = useCallback(() => {
    return abortControllerRef.current !== null
  }, [])

  return {
    streamMessage,
    abort,
    isStreaming,
  }
}

/**
 * Simplified hook that uses the chat store directly.
 * Most components should use this instead of useAIStream.
 */
export function useChatActions() {
  const { sendMessage, stopStreaming, clearConversation, loadConversations, selectConversation } =
    useChatStore()
  const { generateContractFromChat, isLoading: isGeneratingContract } = useContractStore()
  const { company, user } = useAuthStore()
  const location = useLocation()

  // Check if we're on the contract template builder page
  const isOnTemplateBuilder = location.pathname.includes('/documents/contract-templates/')

  // Pattern to detect contract generation requests
  const isContractGenerationRequest = useCallback((content: string): boolean => {
    const patterns = [
      /generate\s+(a\s+)?contract/i,
      /create\s+(a\s+)?contract/i,
      /make\s+(a\s+)?contract/i,
      /draft\s+(a\s+)?contract/i,
      /write\s+(a\s+)?contract/i,
      /build\s+(a\s+)?contract/i,
      /contract\s+for\s+/i,
      /contract\s+template\s+for/i,
    ]
    return patterns.some(pattern => pattern.test(content))
  }, [])

  const send = useCallback(
    async (content: string) => {
      if (!company?.id || !user?.id) {
        console.error('Cannot send message: missing company or user')
        return
      }

      // If on template builder and message looks like contract generation, handle specially
      if (isOnTemplateBuilder && isContractGenerationRequest(content)) {
        try {
          await generateContractFromChat(content)
          // The template builder will pick up pendingAISections automatically
          return
        } catch (error) {
          console.error('Contract generation failed:', error)
          // Fall through to regular chat
        }
      }

      await sendMessage(content, company.id, user.id)
    },
    [sendMessage, company?.id, user?.id, isOnTemplateBuilder, isContractGenerationRequest, generateContractFromChat]
  )

  const stop = useCallback(() => {
    stopStreaming()
  }, [stopStreaming])

  const clear = useCallback(() => {
    clearConversation()
  }, [clearConversation])

  const loadHistory = useCallback(() => {
    if (company?.id) {
      loadConversations(company.id)
    }
  }, [loadConversations, company?.id])

  return {
    send,
    stop,
    clear,
    loadHistory,
    selectConversation,
    isOnTemplateBuilder,
    isGeneratingContract,
  }
}

