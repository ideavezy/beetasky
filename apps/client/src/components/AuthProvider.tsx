import { useEffect, ReactNode } from 'react'
import { useAuthStore } from '../stores/auth'

interface AuthProviderProps {
  children: ReactNode
}

// Track if we've already started initialization (outside of React to survive re-renders)
let initializationStarted = false

/**
 * AuthProvider initializes the authentication state on app load.
 * Wrap your app with this component to enable auth features.
 * Note: Loading states are handled by AuthGuard/GuestGuard, not here.
 */
export function AuthProvider({ children }: AuthProviderProps) {
  const initialize = useAuthStore((state) => state.initialize)

  useEffect(() => {
    // Only initialize once across the entire app lifecycle
    if (initializationStarted) {
      return
    }
    
    initializationStarted = true
    initialize()
  }, [initialize])

  // Render children immediately - guards will handle loading states
  return <>{children}</>
}

