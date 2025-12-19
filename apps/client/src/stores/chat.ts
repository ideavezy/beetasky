import { create } from 'zustand'
import { api, getAuthToken } from '../lib/api'
import { useFlowStore } from './flow'

// Chat message type
export interface ChatMessage {
  id: string
  conversationId: string
  senderType: 'user' | 'ai_agent' | 'system'
  senderId: string | null
  sender: {
    id: string | null
    name: string
    avatar: string | null
    type: string
  }
  role: 'user' | 'assistant' | 'system'
  content: string
  isStreaming?: boolean
  error?: boolean
  createdAt: string
}

// Conversation type
export interface Conversation {
  id: string
  name: string
  lastMessageAt: string | null
  lastMessagePreview: string | null
  status: string
}

interface ChatState {
  // State
  conversationId: string | null
  messages: ChatMessage[]
  conversations: Conversation[]
  isStreaming: boolean
  isLoadingMessages: boolean
  isLoadingConversations: boolean
  error: string | null
  abortController: AbortController | null

  // Actions
  sendMessage: (content: string, companyId: string | null, userId: string) => Promise<void>
  stopStreaming: () => void
  clearConversation: () => void
  loadConversations: (companyId: string | null) => Promise<void>
  loadMessages: (conversationId: string) => Promise<void>
  selectConversation: (conversationId: string | null) => void
  appendStreamChunk: (chunk: string) => void
  setStreamingComplete: (messageId: string, conversationId: string) => void
  setStreamingError: (errorMessage: string) => void
}

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

export const useChatStore = create<ChatState>((set, get) => ({
  // Initial state
  conversationId: null,
  messages: [],
  conversations: [],
  isStreaming: false,
  isLoadingMessages: false,
  isLoadingConversations: false,
  error: null,
  abortController: null,

  sendMessage: async (content: string, companyId: string | null, userId: string) => {
    if (!companyId) {
      set({ error: 'No company selected' })
      return
    }

    // Create optimistic user message
    const tempUserMessageId = `temp-user-${Date.now()}`
    const userMessage: ChatMessage = {
      id: tempUserMessageId,
      conversationId: get().conversationId || 'pending',
      senderType: 'user',
      senderId: userId,
      sender: {
        id: userId,
        name: 'You',
        avatar: null,
        type: 'user',
      },
      role: 'user',
      content,
      createdAt: new Date().toISOString(),
    }

    // Create placeholder AI message for streaming
    // Using fixed UUID for AI assistant (matches backend constant)
    const AI_ASSISTANT_ID = '00000000-0000-0000-0000-000000000001'
    const tempAiMessageId = `temp-ai-${Date.now()}`
    const aiMessage: ChatMessage = {
      id: tempAiMessageId,
      conversationId: get().conversationId || 'pending',
      senderType: 'ai_agent',
      senderId: AI_ASSISTANT_ID,
      sender: {
        id: AI_ASSISTANT_ID,
        name: 'AI Assistant',
        avatar: null,
        type: 'ai_agent',
      },
      role: 'assistant',
      content: '',
      isStreaming: true,
      createdAt: new Date().toISOString(),
    }

    // Add messages to state
    set((state) => ({
      messages: [...state.messages, userMessage, aiMessage],
      isStreaming: true,
      error: null,
    }))

    // Create abort controller
    const abortController = new AbortController()
    set({ abortController })

    try {
      // Get the auth token from the API module
      const token = getAuthToken()

      if (!token) {
        throw new Error('Not authenticated')
      }

      // Start SSE stream
      const response = await fetch(`${API_URL}/api/v1/ai/chat/stream`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
          'Authorization': `Bearer ${token}`,
          'X-Company-ID': companyId,
        },
        body: JSON.stringify({
          message: content,
          conversation_id: get().conversationId,
        }),
        signal: abortController.signal,
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
        buffer = lines.pop() || '' // Keep incomplete line in buffer

        for (const line of lines) {
          const trimmedLine = line.trim()
          
          if (!trimmedLine || !trimmedLine.startsWith('data: ')) {
            continue
          }

          const jsonStr = trimmedLine.slice(6) // Remove 'data: ' prefix
          
          try {
            const data = JSON.parse(jsonStr)

            if (data.type === 'start') {
              // Update conversation ID if new
              if (data.conversation_id) {
                set((state) => ({
                  conversationId: data.conversation_id,
                  messages: state.messages.map((m) => ({
                    ...m,
                    conversationId: data.conversation_id,
                  })),
                }))
              }
            } else if (data.type === 'chunk') {
              // Append content chunk
              get().appendStreamChunk(data.content)
            } else if (data.type === 'done') {
              // Streaming complete
              get().setStreamingComplete(data.message_id, data.conversation_id)
            } else if (data.type === 'error') {
              // Handle error from server
              get().setStreamingError(data.message)
            } else if (data.type === 'flow_created') {
              // Flow was created - the flow store will be updated via WebSocket events
              // For now, we can show the flow info in the chat
              console.log('Flow created:', data.flow_id, data.title)
            }
          } catch {
            // Skip invalid JSON
            console.warn('Invalid SSE JSON:', jsonStr)
          }
        }
      }
    } catch (error: any) {
      if (error.name === 'AbortError') {
        // User cancelled - mark message as cancelled
        set((state) => ({
          messages: state.messages.map((m) =>
            m.id === tempAiMessageId
              ? { ...m, isStreaming: false, content: m.content || '(Cancelled)' }
              : m
          ),
          isStreaming: false,
          abortController: null,
        }))
      } else {
        console.error('Chat stream error:', error)
        get().setStreamingError(error.message || 'Failed to get response')
      }
    }
  },

  stopStreaming: () => {
    const { abortController } = get()
    if (abortController) {
      abortController.abort()
      set({ abortController: null, isStreaming: false })
    }
  },

  clearConversation: () => {
    // Stop any ongoing stream
    get().stopStreaming()
    
    set({
      conversationId: null,
      messages: [],
      error: null,
    })
  },

  loadConversations: async (companyId: string | null) => {
    if (!companyId) return

    set({ isLoadingConversations: true })

    try {
      const response = await api.get('/api/v1/ai/chat/conversations')

      if (response.data.success) {
        set({
          conversations: response.data.data.map((c: any) => ({
            id: c.id,
            name: c.name,
            lastMessageAt: c.last_message_at,
            lastMessagePreview: c.last_message_preview,
            status: c.status,
          })),
          isLoadingConversations: false,
        })
      }
    } catch (error) {
      console.error('Failed to load conversations:', error)
      set({ isLoadingConversations: false })
    }
  },

  loadMessages: async (conversationId: string) => {
    set({ isLoadingMessages: true, conversationId })

    try {
      const response = await api.get(`/api/v1/ai/chat/conversations/${conversationId}/messages`)

      if (response.data.success) {
        set({
          messages: response.data.data.map((m: any) => ({
            id: m.id,
            conversationId: m.conversation_id,
            senderType: m.sender_type,
            senderId: m.sender_id,
            sender: m.sender,
            role: m.role,
            content: m.content,
            createdAt: m.created_at,
          })),
          isLoadingMessages: false,
        })
      }
    } catch (error) {
      console.error('Failed to load messages:', error)
      set({ isLoadingMessages: false, error: 'Failed to load messages' })
    }
  },

  selectConversation: (conversationId: string | null) => {
    if (conversationId) {
      get().loadMessages(conversationId)
    } else {
      set({ conversationId: null, messages: [] })
    }
  },

  appendStreamChunk: (chunk: string) => {
    set((state) => ({
      messages: state.messages.map((m) =>
        m.isStreaming ? { ...m, content: m.content + chunk } : m
      ),
    }))
  },

  setStreamingComplete: (messageId: string, conversationId: string) => {
    set((state) => ({
      conversationId,
      messages: state.messages.map((m) =>
        m.isStreaming
          ? { ...m, id: messageId, isStreaming: false, conversationId }
          : m
      ),
      isStreaming: false,
      abortController: null,
    }))
  },

  setStreamingError: (errorMessage: string) => {
    set((state) => ({
      messages: state.messages.map((m) =>
        m.isStreaming
          ? { ...m, isStreaming: false, error: true, content: 'Sorry, I encountered an error. Please try again.' }
          : m
      ),
      isStreaming: false,
      error: errorMessage,
      abortController: null,
    }))
  },
}))

