import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { Session, User as SupabaseUser } from '@supabase/supabase-js'
import { supabase } from '../lib/supabase'
import { api, syncUserToBackend, updateUserProfile, invalidateSyncCache, setAuthToken, setCompanyId } from '../lib/api'

// Backend user type (from our database)
export interface User {
  id: string
  email: string
  first_name: string
  last_name: string | null
  avatar_url: string | null
  phone: string | null
  global_role: 'admin' | 'user'
  full_name: string
  companies?: Company[]
}

export interface Company {
  id: string
  name: string
  slug: string
  logo_url: string | null
  billing_status: 'trial' | 'active' | 'suspended' | 'cancelled'
  pivot?: {
    role_in_company: 'owner' | 'manager' | 'staff' | 'agent'
    is_active: boolean
  }
}

// User presets (settings, default company, etc.)
export interface UserPresets {
  default_company_id: string | null
  default_company: {
    id: string
    name: string
    slug: string
    logo_url: string | null
  } | null
  settings: Record<string, any>
}

// 2FA enrollment data
export interface TwoFactorEnrollment {
  id: string
  totp: {
    qr_code: string
    secret: string
    uri: string
  }
}

interface AuthState {
  // State
  user: User | null
  supabaseUser: SupabaseUser | null
  session: Session | null
  company: Company | null
  presets: UserPresets | null
  isLoading: boolean      // True during sign-in/sign-up actions (for button spinners)
  isInitializing: boolean // True only during initial session check on app start
  isInitialized: boolean
  error: string | null
  successMessage: string | null
  
  // 2FA state
  twoFactorEnrollment: TwoFactorEnrollment | null
  isTwoFactorEnabled: boolean
  
  // Computed
  isAuthenticated: boolean
  
  // Actions
  initialize: () => Promise<void>
  signUp: (email: string, password: string, metadata: { first_name: string; last_name?: string }) => Promise<boolean>
  signIn: (email: string, password: string) => Promise<boolean>
  signInWithGoogle: () => Promise<void>
  signOut: () => Promise<void>
  resetPassword: (email: string) => Promise<boolean>
  updatePassword: (newPassword: string) => Promise<boolean>
  updateProfile: (data: { first_name?: string; last_name?: string; email?: string; phone?: string }) => Promise<boolean>
  updateEmail: (newEmail: string) => Promise<boolean>
  clearError: () => void
  clearSuccessMessage: () => void
  setActiveCompany: (company: Company, updateBackend?: boolean) => void
  setDefaultCompany: (companyId: string) => Promise<void>
  
  // 2FA Actions
  check2FAStatus: () => Promise<boolean>
  enroll2FA: () => Promise<TwoFactorEnrollment | null>
  verify2FA: (factorId: string, code: string) => Promise<boolean>
  unenroll2FA: (factorId: string) => Promise<boolean>
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      // Initial state
      user: null,
      supabaseUser: null,
      session: null,
      company: null,
      presets: null,
      isLoading: false,
      isInitializing: true,
      isInitialized: false,
      error: null,
      successMessage: null,
      twoFactorEnrollment: null,
      isTwoFactorEnabled: false,
      isAuthenticated: false,

      initialize: async () => {
        // Prevent re-initialization
        if (get().isInitialized) {
          return
        }
        
        set({ isInitializing: true, error: null })
        
        try {
          // Get current session from Supabase (this is the ONLY place we call getSession)
          const { data: { session }, error: sessionError } = await supabase.auth.getSession()
          
          if (sessionError) {
            console.error('[Auth] Session error:', sessionError)
          }
          
          if (session) {
            // Set the token for axios immediately
            setAuthToken(session.access_token)
            
            // Set company ID from persisted company (if available) for immediate API calls
            const persistedCompany = get().company
            if (persistedCompany?.id) {
              setCompanyId(persistedCompany.id)
            }
            
            // Set session and user data from Supabase immediately
            set({
              session,
              supabaseUser: session.user,
              isAuthenticated: true,
              // Use Supabase user metadata as initial user data
              user: {
                id: session.user.id,
                email: session.user.email || '',
                first_name: session.user.user_metadata?.first_name || '',
                last_name: session.user.user_metadata?.last_name || null,
                avatar_url: session.user.user_metadata?.avatar_url || null,
                phone: null,
                global_role: 'user',
                full_name: `${session.user.user_metadata?.first_name || ''} ${session.user.user_metadata?.last_name || ''}`.trim() || session.user.email || '',
              },
            })
            
            // Sync with backend ONCE (the cache will prevent any further calls)
            syncUserToBackend()
              .then((backendData) => {
                // Backend returns user.companies array, we need to pick one
                const userCompanies = backendData?.user?.companies || []
                const presets = backendData?.presets || null
                
                // Prefer: 1) default company from presets, 2) persisted company, 3) first active, 4) first company
                const defaultCompanyId = presets?.default_company_id
                const persistedCompanyId = get().company?.id
                const selectedCompany = 
                  (defaultCompanyId && userCompanies.find((c: Company) => c.id === defaultCompanyId)) ||
                  userCompanies.find((c: Company) => c.id === persistedCompanyId) ||
                  userCompanies.find((c: Company) => c.pivot?.is_active) ||
                  userCompanies[0] ||
                  null
                
                if (backendData?.user) {
                  set({
                    user: backendData.user,
                    company: selectedCompany,
                    presets: presets,
                  })
                  // Set company ID for API requests
                  setCompanyId(selectedCompany?.id || null)
                }
              })
              .catch((error) => {
                console.error('[Auth] Backend sync failed:', error)
              })
          }
          
          // Set up auth state change listener
          supabase.auth.onAuthStateChange((event, newSession) => {
            // Skip INITIAL_SESSION as we already handled it above
            if (event === 'INITIAL_SESSION') {
              return
            }
            
            // Update token for axios
            if (newSession) {
              setAuthToken(newSession.access_token)
            } else {
              setAuthToken(null)
            }
            
            if (event === 'SIGNED_IN' && newSession) {
              set({
                session: newSession,
                supabaseUser: newSession.user,
                isAuthenticated: true,
              })
              // Don't sync here - signIn/signUp already do it
            } else if (event === 'SIGNED_OUT') {
              // Clear API tokens and company ID
              setAuthToken(null)
              setCompanyId(null)
              
              set({
                session: null,
                supabaseUser: null,
                user: null,
                company: null,
                presets: null,
                isAuthenticated: false,
              })
            } else if (event === 'TOKEN_REFRESHED' && newSession) {
              set({ session: newSession })
            } else if (event === 'USER_UPDATED' && newSession) {
              set({
                session: newSession,
                supabaseUser: newSession.user,
              })
            }
          })
        } catch (error) {
          console.error('[Auth] Initialization error:', error)
          set({ error: (error as Error).message })
        } finally {
          set({ isInitializing: false, isInitialized: true })
        }
      },

      signUp: async (email, password, metadata) => {
        set({ isLoading: true, error: null })
        
        try {
          const { data, error } = await supabase.auth.signUp({
            email,
            password,
            options: {
              data: {
                first_name: metadata.first_name,
                last_name: metadata.last_name || '',
              },
            },
          })
          
          if (error) {
            set({ error: error.message, isLoading: false })
            return false
          }
          
          if (data.session) {
            // Set the token for axios
            setAuthToken(data.session.access_token)
            
            // Set basic auth state immediately
            set({
              session: data.session,
              supabaseUser: data.user,
              isAuthenticated: true,
              user: {
                id: data.user!.id,
                email: data.user!.email || '',
                first_name: metadata.first_name,
                last_name: metadata.last_name || null,
                avatar_url: null,
                phone: null,
                global_role: 'user',
                full_name: `${metadata.first_name} ${metadata.last_name || ''}`.trim(),
              },
              isLoading: false,
            })
            
            // Sync with backend in background
            syncUserToBackend()
              .then((backendData) => {
                if (backendData?.user) {
                  const userCompanies = backendData.user.companies || []
                  const selectedCompany = userCompanies[0] || null
                  set({
                    user: backendData.user,
                    company: selectedCompany,
                  })
                  // Set company ID for API requests
                  setCompanyId(selectedCompany?.id || null)
                }
              })
              .catch((error) => {
                console.error('Backend sync failed:', error)
              })
            
            return true
          } else if (data.user) {
            // User needs to confirm email
            set({ 
              error: 'Please check your email to confirm your account.',
              isLoading: false 
            })
            return false
          }
          
          set({ isLoading: false })
          return false
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          return false
        }
      },

      signIn: async (email, password) => {
        set({ isLoading: true, error: null })
        
        try {
          const { data, error } = await supabase.auth.signInWithPassword({
            email,
            password,
          })
          
          if (error) {
            set({ error: error.message, isLoading: false })
            return false
          }
          
          // Set the token for axios
          if (data.session) {
            setAuthToken(data.session.access_token)
          }
          
          // Set basic auth state immediately
          set({
            session: data.session,
            supabaseUser: data.user,
            isAuthenticated: true,
            // Use Supabase metadata for immediate display
            user: {
              id: data.user.id,
              email: data.user.email || '',
              first_name: data.user.user_metadata?.first_name || '',
              last_name: data.user.user_metadata?.last_name || null,
              avatar_url: data.user.user_metadata?.avatar_url || null,
              phone: null,
              global_role: 'user',
              full_name: `${data.user.user_metadata?.first_name || ''} ${data.user.user_metadata?.last_name || ''}`.trim() || data.user.email || '',
            },
            isLoading: false,
          })
          
          // Sync with backend in background (cache will prevent duplicates)
          syncUserToBackend()
            .then((backendData) => {
              if (backendData?.user) {
                const userCompanies = backendData.user.companies || []
                const persistedCompanyId = get().company?.id
                const selectedCompany = 
                  userCompanies.find((c: Company) => c.id === persistedCompanyId) ||
                  userCompanies.find((c: Company) => c.pivot?.is_active) ||
                  userCompanies[0] ||
                  null
                set({
                  user: backendData.user,
                  company: selectedCompany,
                })
                // Set company ID for API requests
                setCompanyId(selectedCompany?.id || null)
              }
            })
            .catch((error) => {
              console.error('Backend sync failed:', error)
            })
          
          return true
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          return false
        }
      },

      signInWithGoogle: async () => {
        try {
          set({ isLoading: true, error: null })
          
          const { error } = await supabase.auth.signInWithOAuth({
            provider: 'google',
            options: {
              redirectTo: `${window.location.origin}/dashboard`,
            },
          })
          
          if (error) throw error
          
          // User will be redirected to Google, then back to our app
          // The onAuthStateChange listener will handle the rest
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          throw error
        }
      },

      signOut: async () => {
        try {
          set({ isLoading: true, error: null })
          
          const { error } = await supabase.auth.signOut()
          
          if (error) throw error
          
          // Clear API tokens and company ID
          setAuthToken(null)
          setCompanyId(null)
          
          set({
            session: null,
            supabaseUser: null,
            user: null,
            company: null,
            presets: null,
            isAuthenticated: false,
          })
        } catch (error) {
          const message = (error as Error).message
          set({ error: message })
          throw error
        } finally {
          set({ isLoading: false })
        }
      },

      resetPassword: async (email: string) => {
        set({ isLoading: true, error: null })
        
        try {
          const { error } = await supabase.auth.resetPasswordForEmail(email, {
            redirectTo: `${window.location.origin}/reset-password`,
          })
          
          if (error) {
            set({ error: error.message, isLoading: false })
            return false
          }
          
          set({ isLoading: false })
          return true
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          return false
        }
      },

      updatePassword: async (newPassword: string) => {
        set({ isLoading: true, error: null })
        
        try {
          const { error } = await supabase.auth.updateUser({
            password: newPassword,
          })
          
          if (error) {
            set({ error: error.message, isLoading: false })
            return false
          }
          
          set({ isLoading: false })
          return true
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          return false
        }
      },

      clearError: () => set({ error: null }),
      
      clearSuccessMessage: () => set({ successMessage: null }),

      setActiveCompany: (company, updateBackend = true) => {
        set({ company })
        
        // Update the API company ID header
        setCompanyId(company?.id || null)
        
        // Also update as default company in backend
        if (updateBackend && company?.id) {
          api.put('/api/v1/user-presets', { default_company_id: company.id })
            .then((response) => {
              if (response.data.success) {
                set({ presets: response.data.data })
              }
            })
            .catch((error) => {
              console.error('Failed to update default company:', error)
            })
        }
      },

      setDefaultCompany: async (companyId: string) => {
        try {
          const response = await api.put('/api/v1/user-presets', { 
            default_company_id: companyId 
          })
          
          if (response.data.success) {
            set({ presets: response.data.data })
            
            // Also update active company if we have the full company data
            const user = get().user
            const company = user?.companies?.find(c => c.id === companyId)
            if (company) {
              set({ company })
              // Update the API company ID header
              setCompanyId(company.id)
            }
          }
        } catch (error) {
          console.error('Failed to set default company:', error)
        }
      },

      updateProfile: async (data) => {
        set({ isLoading: true, error: null, successMessage: null })
        
        try {
          // Update user metadata in Supabase first (this always works)
          const { error: supabaseError } = await supabase.auth.updateUser({
            data: {
              first_name: data.first_name,
              last_name: data.last_name,
            },
          })
          
          if (supabaseError) {
            throw supabaseError
          }
          
          // If email is being changed, update in Supabase
          if (data.email && data.email !== get().user?.email) {
            const { error: emailError } = await supabase.auth.updateUser({
              email: data.email,
            })
            
            if (emailError) {
              // Email update requires confirmation or has other issues
              console.warn('Email update issue:', emailError.message)
              set({ 
                successMessage: 'Profile updated. Note: Email change may require confirmation.',
                isLoading: false 
              })
              return true
            }
          }
          
          // Try to update profile in backend (may not exist yet)
          try {
            await updateUserProfile(data)
          } catch (backendError) {
            // Backend endpoint might not exist yet, that's okay
            console.warn('Backend profile update failed (endpoint may not exist):', backendError)
          }
          
          // Try to refresh user data from backend
          try {
            invalidateSyncCache() // Clear cache to get fresh data
            const backendData = await syncUserToBackend(true)
            set({
              user: backendData.user,
              successMessage: 'Profile updated successfully!',
              isLoading: false,
            })
          } catch (syncError) {
            // Update local user state with the new data
            const currentUser = get().user
            if (currentUser) {
              set({
                user: {
                  ...currentUser,
                  first_name: data.first_name || currentUser.first_name,
                  last_name: data.last_name || currentUser.last_name,
                  full_name: `${data.first_name || currentUser.first_name} ${data.last_name || currentUser.last_name || ''}`.trim(),
                },
                successMessage: 'Profile updated successfully!',
                isLoading: false,
              })
            } else {
              set({ successMessage: 'Profile updated successfully!', isLoading: false })
            }
          }
          
          return true
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          return false
        }
      },

      updateEmail: async (newEmail: string) => {
        set({ isLoading: true, error: null, successMessage: null })
        
        try {
          const { error } = await supabase.auth.updateUser({
            email: newEmail,
          })
          
          if (error) {
            set({ error: error.message, isLoading: false })
            return false
          }
          
          set({ 
            successMessage: 'Please check your email to confirm the change.',
            isLoading: false 
          })
          return true
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          return false
        }
      },

      check2FAStatus: async () => {
        try {
          // Only check if we have an active session
          const session = get().session
          if (!session) {
            return false
          }
          
          const { data, error } = await supabase.auth.mfa.listFactors()
          
          if (error) {
            // MFA might not be enabled for this project, that's okay
            console.warn('2FA check skipped:', error.message)
            return false
          }
          
          const hasVerifiedFactor = data?.totp?.some(factor => factor.status === 'verified') || false
          set({ isTwoFactorEnabled: hasVerifiedFactor })
          return hasVerifiedFactor
        } catch (error) {
          // Silently fail - MFA might not be configured
          console.warn('2FA check failed:', error)
          return false
        }
      },

      enroll2FA: async () => {
        set({ isLoading: true, error: null })
        
        try {
          const { data, error } = await supabase.auth.mfa.enroll({
            factorType: 'totp',
            friendlyName: 'Authenticator App',
          })
          
          if (error) {
            set({ error: error.message, isLoading: false })
            return null
          }
          
          const enrollment: TwoFactorEnrollment = {
            id: data.id,
            totp: {
              qr_code: data.totp.qr_code,
              secret: data.totp.secret,
              uri: data.totp.uri,
            },
          }
          
          set({ twoFactorEnrollment: enrollment, isLoading: false })
          return enrollment
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          return null
        }
      },

      verify2FA: async (factorId: string, code: string) => {
        set({ isLoading: true, error: null })
        
        try {
          const { error } = await supabase.auth.mfa.challengeAndVerify({
            factorId,
            code,
          })
          
          if (error) {
            set({ error: error.message, isLoading: false })
            return false
          }
          
          set({ 
            isTwoFactorEnabled: true, 
            twoFactorEnrollment: null,
            successMessage: 'Two-factor authentication enabled successfully!',
            isLoading: false 
          })
          return true
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          return false
        }
      },

      unenroll2FA: async (factorId: string) => {
        set({ isLoading: true, error: null })
        
        try {
          const { error } = await supabase.auth.mfa.unenroll({
            factorId,
          })
          
          if (error) {
            set({ error: error.message, isLoading: false })
            return false
          }
          
          set({ 
            isTwoFactorEnabled: false,
            successMessage: 'Two-factor authentication disabled.',
            isLoading: false 
          })
          return true
        } catch (error) {
          const message = (error as Error).message
          set({ error: message, isLoading: false })
          return false
        }
      },
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        // Only persist the company selection, not the full auth state
        // Supabase handles session persistence
        company: state.company,
      }),
    }
  )
)

