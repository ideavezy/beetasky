import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Make Pusher available globally for Laravel Echo
declare global {
  interface Window {
    Pusher: typeof Pusher
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    Echo: Echo<any> | null
  }
}

window.Pusher = Pusher

// eslint-disable-next-line @typescript-eslint/no-explicit-any
let echoInstance: Echo<any> | null = null

export interface EchoConfig {
  key: string
  cluster: string
  host: string
  port: number
  scheme: 'http' | 'https'
}

/**
 * Initialize Laravel Echo with Soketi/Pusher configuration
 * @param config - Configuration from backend API
 * @param authToken - Supabase access token for authentication
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function initializeEcho(config: EchoConfig, authToken: string): Echo<any> {
  // Clean up existing instance if any
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }

  const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000'
  
  echoInstance = new Echo({
    broadcaster: 'pusher',
    key: config.key,
    cluster: config.cluster,
    wsHost: config.host,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS: config.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    encrypted: true,
    authEndpoint: `${apiUrl}/api/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${authToken}`,
        Accept: 'application/json',
      },
    },
  })

  window.Echo = echoInstance
  
  // Debug logging in development
  if (import.meta.env.DEV) {
    console.log('[Echo] Initialized with config:', {
      key: config.key,
      host: config.host,
      port: config.port,
      scheme: config.scheme,
    })
    
    // Log connection events
    const pusher = echoInstance.connector.pusher as Pusher
    
    pusher.connection.bind('connected', () => {
      console.log('[Echo] Connected to Soketi')
    })
    
    pusher.connection.bind('disconnected', () => {
      console.log('[Echo] Disconnected from Soketi')
    })
    
    pusher.connection.bind('error', (error: unknown) => {
      console.error('[Echo] Connection error:', error)
    })
  }

  return echoInstance
}

/**
 * Get the current Echo instance
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function getEcho(): Echo<any> | null {
  return echoInstance
}

/**
 * Disconnect and cleanup Echo instance
 */
export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
    window.Echo = null
  }
}

/**
 * Update the auth token for the Echo instance
 */
export function updateEchoAuth(authToken: string): void {
  if (echoInstance && echoInstance.connector.options) {
    const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000'
    
    echoInstance.connector.options.auth = {
      headers: {
        Authorization: `Bearer ${authToken}`,
        Accept: 'application/json',
      },
    }
    echoInstance.connector.options.authEndpoint = `${apiUrl}/api/broadcasting/auth`
  }
}

export default echoInstance

