import { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuthStore } from '../stores/auth'

interface AuthGuardProps {
  children: ReactNode
}

/**
 * AuthGuard protects routes that require authentication.
 * Redirects to /login if the user is not authenticated.
 */
export function AuthGuard({ children }: AuthGuardProps) {
  const { isAuthenticated, isInitializing } = useAuthStore()
  const location = useLocation()

  // Show loading only during initial auth check (not during sign-in actions)
  if (isInitializing) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100">
        <span className="loading loading-spinner loading-lg text-primary"></span>
      </div>
    )
  }

  // Redirect to login if not authenticated
  if (!isAuthenticated) {
    // Save the attempted URL for redirect after login
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  return <>{children}</>
}

interface OwnerGuardProps {
  children: ReactNode
}

/**
 * OwnerGuard protects routes that require owner role.
 * Redirects to /dashboard if the user is not an owner of the current company.
 */
export function OwnerGuard({ children }: OwnerGuardProps) {
  const { isAuthenticated, isInitializing, company } = useAuthStore()
  const location = useLocation()

  // Show loading only during initial auth check
  if (isInitializing) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100">
        <span className="loading loading-spinner loading-lg text-primary"></span>
      </div>
    )
  }

  // Redirect to login if not authenticated
  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  // Check if user is owner of the current company
  const isOwner = company?.pivot?.role_in_company === 'owner'

  // Redirect to dashboard if not an owner
  if (!isOwner) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}

interface GuestGuardProps {
  children: ReactNode
}

/**
 * GuestGuard protects routes that should only be accessible to guests.
 * Redirects to /dashboard if the user is already authenticated.
 */
export function GuestGuard({ children }: GuestGuardProps) {
  const { isAuthenticated, isInitializing } = useAuthStore()

  // Show loading only during initial auth check (not during sign-in actions)
  if (isInitializing) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-base-100">
        <span className="loading loading-spinner loading-lg text-primary"></span>
      </div>
    )
  }

  // Redirect to dashboard if already authenticated
  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}

