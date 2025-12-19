import { useState, useEffect, useRef } from 'react'
import { X, Building2, Loader2 } from 'lucide-react'
import { api } from '../../lib/api'
import { useAuthStore } from '../../stores/auth'
import { invalidateSyncCache, syncUserToBackend } from '../../lib/api'

interface CreateCompanyModalProps {
  onClose: () => void
  onSuccess?: (company: any) => void
}

export default function CreateCompanyModal({ onClose, onSuccess }: CreateCompanyModalProps) {
  const [companyName, setCompanyName] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const { setActiveCompany } = useAuthStore()

  // Focus input when modal opens
  useEffect(() => {
    setCompanyName('')
    setError(null)
    setTimeout(() => inputRef.current?.focus(), 100)
  }, [])

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !isLoading) {
        onClose()
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isLoading, onClose])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!companyName.trim()) {
      setError('Company name is required')
      return
    }

    if (companyName.trim().length < 2) {
      setError('Company name must be at least 2 characters')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const response = await api.post('/api/v1/companies', {
        name: companyName.trim(),
      })

      if (response.data.success) {
        // Set the new company as active
        setActiveCompany(response.data.data)
        
        // Invalidate sync cache so next page load gets fresh data
        invalidateSyncCache()
        
        // Refresh user data to get updated companies list
        try {
          const syncData = await syncUserToBackend(true)
          if (syncData?.user?.companies?.length > 0) {
            // Find the newly created company
            const newCompany = syncData.user.companies.find(
              (c: any) => c.id === response.data.data.id
            )
            if (newCompany) {
              setActiveCompany(newCompany)
            }
          }
        } catch (syncError) {
          console.error('Failed to sync after company creation:', syncError)
        }
        
        if (onSuccess) {
          onSuccess(response.data.data)
        } else {
          // Reload the page to refresh all data
          window.location.reload()
        }
      } else {
        setError(response.data.message || 'Failed to create company')
      }
    } catch (err: any) {
      console.error('Failed to create company:', err)
      setError(
        err.response?.data?.message || 
        err.response?.data?.errors?.name?.[0] ||
        'Failed to create company. Please try again.'
      )
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div 
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={() => !isLoading && onClose()}
      />
      
      {/* Modal */}
      <div className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-base-300">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-primary/20 flex items-center justify-center">
              <Building2 className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-base-content">Create Company</h2>
              <p className="text-sm text-base-content/60">Set up your workspace</p>
            </div>
          </div>
          <button
            onClick={onClose}
            disabled={isLoading}
            className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/60 hover:text-base-content disabled:opacity-50"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-5">
          <div className="space-y-4">
            <div>
              <label htmlFor="companyName" className="block text-sm font-medium text-base-content mb-2">
                Company Name
              </label>
              <input
                ref={inputRef}
                id="companyName"
                type="text"
                value={companyName}
                onChange={(e) => setCompanyName(e.target.value)}
                placeholder="Enter your company name"
                className="input input-bordered w-full"
                disabled={isLoading}
                maxLength={255}
              />
              {error && (
                <p className="mt-2 text-sm text-error">{error}</p>
              )}
            </div>

            <p className="text-xs text-base-content/50">
              This will be your main workspace for managing projects, tasks, and team members.
            </p>
          </div>

          {/* Actions */}
          <div className="flex gap-3 mt-6">
            <button
              type="button"
              onClick={onClose}
              disabled={isLoading}
              className="btn btn-ghost flex-1"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isLoading || !companyName.trim()}
              className="btn btn-primary flex-1 gap-2"
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Creating...
                </>
              ) : (
                <>
                  <Building2 className="w-4 h-4" />
                  Create Company
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}


