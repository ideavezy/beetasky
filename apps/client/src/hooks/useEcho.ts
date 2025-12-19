import { useEffect, useRef, useCallback } from 'react'
import type { Channel, PresenceChannel } from 'laravel-echo'
import { useEchoStore } from '../stores/echo'
import { useAuthStore } from '../stores/auth'

/**
 * Hook to manage Echo connection lifecycle
 * Should be called once at app root level
 */
export function useEchoConnection() {
  const { session, isAuthenticated } = useAuthStore()
  const { isConnected, connect, disconnect, updateAuth } = useEchoStore()
  
  // Connect when authenticated
  useEffect(() => {
    if (isAuthenticated && session?.access_token && !isConnected) {
      connect()
    }
    
    // Disconnect when signed out
    if (!isAuthenticated && isConnected) {
      disconnect()
    }
  }, [isAuthenticated, session?.access_token, isConnected, connect, disconnect])
  
  // Update auth token when session refreshes
  useEffect(() => {
    if (session?.access_token && isConnected) {
      updateAuth(session.access_token)
    }
  }, [session?.access_token, isConnected, updateAuth])
  
  return { isConnected }
}

/**
 * Hook to subscribe to a private channel
 * @param channelName - Name of the private channel (without 'private-' prefix)
 * @param events - Object mapping event names to handlers
 */
export function usePrivateChannel(
  channelName: string | null,
  events: Record<string, (data: any) => void>
) {
  const { isConnected, subscribeToPrivate, leaveChannel } = useEchoStore()
  const channelRef = useRef<Channel | null>(null)
  const eventsRef = useRef(events)
  
  // Keep events ref up to date
  useEffect(() => {
    eventsRef.current = events
  }, [events])
  
  useEffect(() => {
    if (!isConnected || !channelName) {
      return
    }
    
    // Subscribe to channel
    const channel = subscribeToPrivate(channelName)
    if (!channel) {
      return
    }
    
    channelRef.current = channel
    
    // Bind all event handlers
    Object.entries(eventsRef.current).forEach(([eventName, handler]) => {
      channel.listen(eventName, handler)
    })
    
    // Cleanup
    return () => {
      if (channelRef.current) {
        // Unbind handlers before leaving
        Object.entries(eventsRef.current).forEach(([eventName]) => {
          channelRef.current?.stopListening(eventName)
        })
        leaveChannel(channelName)
        channelRef.current = null
      }
    }
  }, [isConnected, channelName, subscribeToPrivate, leaveChannel])
  
  return channelRef.current
}

/**
 * Hook to subscribe to a presence channel (shows who's online)
 * @param channelName - Name of the presence channel (without 'presence-' prefix)
 * @param callbacks - Presence callbacks for here, joining, leaving
 */
export function usePresenceChannel(
  channelName: string | null,
  callbacks: {
    onHere?: (members: any[]) => void
    onJoining?: (member: any) => void
    onLeaving?: (member: any) => void
    events?: Record<string, (data: any) => void>
  }
) {
  const { isConnected, subscribeToPresence, leaveChannel } = useEchoStore()
  const channelRef = useRef<PresenceChannel | null>(null)
  const callbacksRef = useRef(callbacks)
  
  // Keep callbacks ref up to date
  useEffect(() => {
    callbacksRef.current = callbacks
  }, [callbacks])
  
  useEffect(() => {
    if (!isConnected || !channelName) {
      return
    }
    
    // Subscribe to presence channel
    const channel = subscribeToPresence(channelName)
    if (!channel) {
      return
    }
    
    channelRef.current = channel
    
    // Bind presence callbacks
    if (callbacksRef.current.onHere) {
      channel.here(callbacksRef.current.onHere)
    }
    if (callbacksRef.current.onJoining) {
      channel.joining(callbacksRef.current.onJoining)
    }
    if (callbacksRef.current.onLeaving) {
      channel.leaving(callbacksRef.current.onLeaving)
    }
    
    // Bind event handlers
    if (callbacksRef.current.events) {
      Object.entries(callbacksRef.current.events).forEach(([eventName, handler]) => {
        channel.listen(eventName, handler)
      })
    }
    
    // Cleanup
    return () => {
      if (channelRef.current) {
        if (callbacksRef.current.events) {
          Object.entries(callbacksRef.current.events).forEach(([eventName]) => {
            channelRef.current?.stopListening(eventName)
          })
        }
        leaveChannel(channelName)
        channelRef.current = null
      }
    }
  }, [isConnected, channelName, subscribeToPresence, leaveChannel])
  
  return channelRef.current
}

/**
 * Hook to subscribe to project channel for real-time task/topic updates
 * @param projectId - Project ID to subscribe to
 */
export function useProjectChannel(
  projectId: string | null,
  handlers: {
    onTaskCreated?: (data: any) => void
    onTaskUpdated?: (data: any) => void
    onTaskDeleted?: (data: any) => void
    onTopicUpdated?: (data: any) => void
    onCommentAdded?: (data: any) => void
  }
) {
  const events = {
    '.task.created': handlers.onTaskCreated || (() => {}),
    '.task.updated': handlers.onTaskUpdated || (() => {}),
    '.task.deleted': handlers.onTaskDeleted || (() => {}),
    '.topic.updated': handlers.onTopicUpdated || (() => {}),
    '.comment.added': handlers.onCommentAdded || (() => {}),
  }
  
  return usePrivateChannel(
    projectId ? `project.${projectId}` : null,
    events
  )
}

/**
 * Hook to subscribe to user's personal channel for notifications
 * @param userId - User ID to subscribe to
 */
export function useUserChannel(
  userId: string | null,
  handlers: {
    onNotification?: (data: any) => void
    onFlowUpdate?: (data: any) => void
    onFlowCompleted?: (data: any) => void
    onFlowInputRequired?: (data: any) => void
  }
) {
  const events = {
    '.notification': handlers.onNotification || (() => {}),
    '.flow.step.completed': handlers.onFlowUpdate || (() => {}),
    '.flow.completed': handlers.onFlowCompleted || (() => {}),
    '.flow.input.required': handlers.onFlowInputRequired || (() => {}),
  }
  
  return usePrivateChannel(
    userId ? `user.${userId}` : null,
    events
  )
}

/**
 * Hook to subscribe to conversation channel for real-time messages
 * @param conversationId - Conversation ID to subscribe to
 */
export function useConversationChannel(
  conversationId: string | null,
  handlers: {
    onMessage?: (data: any) => void
    onTyping?: (data: any) => void
    onRead?: (data: any) => void
  }
) {
  const events = {
    '.message.sent': handlers.onMessage || (() => {}),
    '.typing': handlers.onTyping || (() => {}),
    '.message.read': handlers.onRead || (() => {}),
  }
  
  return usePrivateChannel(
    conversationId ? `conversation.${conversationId}` : null,
    events
  )
}

/**
 * Hook to join a project presence channel (shows who's viewing)
 * @param projectId - Project ID
 */
export function useProjectPresence(
  projectId: string | null,
  callbacks?: {
    onMembersChange?: (members: any[]) => void
  }
) {
  const membersRef = useRef<any[]>([])
  
  const updateMembers = useCallback(() => {
    callbacks?.onMembersChange?.(membersRef.current)
  }, [callbacks])
  
  return usePresenceChannel(
    projectId ? `presence.project.${projectId}` : null,
    {
      onHere: (members) => {
        membersRef.current = members
        updateMembers()
      },
      onJoining: (member) => {
        membersRef.current = [...membersRef.current, member]
        updateMembers()
      },
      onLeaving: (member) => {
        membersRef.current = membersRef.current.filter(m => m.id !== member.id)
        updateMembers()
      },
    }
  )
}

