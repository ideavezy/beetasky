import { create } from 'zustand'
import type Echo from 'laravel-echo'
import type { Channel, PresenceChannel } from 'laravel-echo'
import { 
  initializeEcho, 
  disconnectEcho, 
  getEcho,
  updateEchoAuth,
  type EchoConfig 
} from '../lib/echo'
import { api, getAuthToken } from '../lib/api'

interface EchoState {
  // State
  isConnected: boolean
  isConnecting: boolean
  config: EchoConfig | null
  error: string | null
  
  // Actions
  connect: () => Promise<void>
  disconnect: () => void
  reconnect: () => Promise<void>
  updateAuth: (token: string) => void
  
  // Channel helpers
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  getEcho: () => Echo<any> | null
  subscribeToPrivate: (channelName: string) => Channel | null
  subscribeToPresence: (channelName: string) => PresenceChannel | null
  leaveChannel: (channelName: string) => void
}

export const useEchoStore = create<EchoState>()((set, get) => ({
  isConnected: false,
  isConnecting: false,
  config: null,
  error: null,

  connect: async () => {
    const state = get()
    
    // Don't connect if already connected or connecting
    if (state.isConnected || state.isConnecting) {
      return
    }
    
    // Get auth token
    const authToken = getAuthToken()
    if (!authToken) {
      set({ error: 'No auth token available' })
      return
    }
    
    set({ isConnecting: true, error: null })
    
    try {
      // Fetch Pusher/Soketi config from backend
      const response = await api.get('/api/config/pusher')
      const config: EchoConfig = response.data
      
      // Initialize Echo
      initializeEcho(config, authToken)
      
      set({ 
        isConnected: true, 
        isConnecting: false, 
        config,
        error: null 
      })
      
      console.log('[EchoStore] Connected to Soketi')
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to connect to websocket'
      console.error('[EchoStore] Connection failed:', message)
      set({ 
        isConnected: false, 
        isConnecting: false, 
        error: message 
      })
    }
  },

  disconnect: () => {
    disconnectEcho()
    set({ isConnected: false, config: null, error: null })
    console.log('[EchoStore] Disconnected')
  },

  reconnect: async () => {
    const { disconnect, connect } = get()
    disconnect()
    await connect()
  },

  updateAuth: (token: string) => {
    updateEchoAuth(token)
  },

  getEcho: () => {
    return getEcho()
  },

  subscribeToPrivate: (channelName: string) => {
    const echo = getEcho()
    if (!echo) {
      console.warn('[EchoStore] Cannot subscribe - Echo not initialized')
      return null
    }
    return echo.private(channelName)
  },

  subscribeToPresence: (channelName: string) => {
    const echo = getEcho()
    if (!echo) {
      console.warn('[EchoStore] Cannot subscribe - Echo not initialized')
      return null
    }
    return echo.join(channelName) as PresenceChannel
  },

  leaveChannel: (channelName: string) => {
    const echo = getEcho()
    if (echo) {
      echo.leave(channelName)
    }
  },
}))

