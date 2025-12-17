import axios from 'axios'

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

// Token storage - set by auth store, used by axios
let currentToken: string | null = null

// Company ID storage - set by auth store, used by axios
let currentCompanyId: string | null = null

/**
 * Set the current auth token (called by auth store)
 */
export function setAuthToken(token: string | null) {
  currentToken = token
}

/**
 * Set the current company ID (called by auth store)
 */
export function setCompanyId(companyId: string | null) {
  currentCompanyId = companyId
}

/**
 * Axios instance configured with Supabase JWT authentication.
 * Token is set by the auth store - no async getSession calls here!
 */
export const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  withCredentials: true,
})

// Request interceptor to add JWT token and company ID (synchronous - no getSession calls!)
api.interceptors.request.use(
  (config) => {
    if (currentToken) {
      config.headers.Authorization = `Bearer ${currentToken}`
    }
    if (currentCompanyId) {
      config.headers['X-Company-ID'] = currentCompanyId
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor to handle errors (no retry logic - let auth store handle it)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Just pass through errors - auth store will handle 401s if needed
    return Promise.reject(error)
  }
)

// Singleton for sync - ensures only ONE request ever happens at a time
let syncState: {
  promise: Promise<any> | null
  result: any | null
  timestamp: number
  isLoading: boolean
} = {
  promise: null,
  result: null,
  timestamp: 0,
  isLoading: false,
}

const SYNC_CACHE_DURATION = 60 * 1000 // Cache for 60 seconds

/**
 * Sync user data to Laravel backend after Supabase auth.
 * This ensures our local users table stays in sync with Supabase auth.
 * Uses aggressive caching and deduplication to prevent multiple requests.
 */
export async function syncUserToBackend(forceRefresh = false): Promise<any> {
  const now = Date.now()
  
  // Return cached result if still valid (and not forcing refresh)
  if (!forceRefresh && syncState.result && now - syncState.timestamp < SYNC_CACHE_DURATION) {
    console.log('[Sync] Returning cached result')
    return syncState.result
  }
  
  // If already loading, wait for the existing promise
  if (syncState.isLoading && syncState.promise) {
    console.log('[Sync] Already loading, waiting for existing promise')
    return syncState.promise
  }
  
  // Start new request
  syncState.isLoading = true
  
  syncState.promise = api.get('/api/user')
    .then((response) => {
      syncState.result = response.data
      syncState.timestamp = Date.now()
      return response.data
    })
    .catch((error) => {
      console.error('[Sync] Failed to sync user to backend:', error.message)
      throw error
    })
    .finally(() => {
      syncState.isLoading = false
      // Keep the promise around for a bit so concurrent calls can use it
      setTimeout(() => {
        if (!syncState.isLoading) {
          syncState.promise = null
        }
      }, 1000)
    })
  
  return syncState.promise
}

// Function to invalidate sync cache (call after profile updates)
export function invalidateSyncCache() {
  syncState.result = null
  syncState.timestamp = 0
}

/**
 * Get current user with companies from backend.
 */
export async function getCurrentUser() {
  const response = await api.get('/api/user')
  return response.data
}

// Deduplication for profile updates
let profileUpdatePromise: Promise<any> | null = null

/**
 * Update user profile in backend.
 * Uses deduplication to prevent double submissions.
 */
export async function updateUserProfile(data: {
  first_name?: string
  last_name?: string
  email?: string
  phone?: string
  avatar_url?: string
}) {
  // If an update is already in progress, return the existing promise
  if (profileUpdatePromise) {
    return profileUpdatePromise
  }
  
  profileUpdatePromise = (async () => {
    try {
      const response = await api.put('/api/user/profile', data)
      return response.data
    } finally {
      // Clear after completion
      profileUpdatePromise = null
    }
  })()
  
  return profileUpdatePromise
}

