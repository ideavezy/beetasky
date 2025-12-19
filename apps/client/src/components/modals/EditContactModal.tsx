import { useState, useEffect, useRef } from 'react'
import { X, UserCog, Loader2, Mail, Phone, Building2, Briefcase, Globe, ChevronDown } from 'lucide-react'
import { api } from '../../lib/api'
import { useAuthStore } from '../../stores/auth'

interface Contact {
  id: string
  full_name: string
  email: string | null
  phone: string | null
  organization: string | null
  job_title: string | null
  relation_type: string
  status: string
  source: string | null
}

interface EditContactModalProps {
  contact: Contact
  onClose: () => void
  onSuccess?: (contact: any) => void
}

const RELATION_TYPES = [
  { value: 'lead', label: 'Lead', description: 'New potential customer' },
  { value: 'prospect', label: 'Prospect', description: 'Qualified potential customer' },
  { value: 'customer', label: 'Customer', description: 'Active customer' },
  { value: 'vendor', label: 'Vendor', description: 'Supplier or service provider' },
  { value: 'partner', label: 'Partner', description: 'Business partner' },
]

const SOURCE_OPTIONS = [
  { value: 'manual', label: 'Manual Entry' },
  { value: 'website', label: 'Website' },
  { value: 'referral', label: 'Referral' },
  { value: 'social_media', label: 'Social Media' },
  { value: 'advertisement', label: 'Advertisement' },
  { value: 'cold_call', label: 'Cold Call' },
  { value: 'email_campaign', label: 'Email Campaign' },
  { value: 'trade_show', label: 'Trade Show / Event' },
  { value: 'partner', label: 'Partner' },
  { value: 'other', label: 'Other' },
]

export default function EditContactModal({ contact, onClose, onSuccess }: EditContactModalProps) {
  const { company } = useAuthStore()
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  // Form state
  const [formData, setFormData] = useState({
    full_name: contact.full_name || '',
    email: contact.email || '',
    phone: contact.phone || '',
    organization: contact.organization || '',
    job_title: contact.job_title || '',
    relation_type: contact.relation_type || 'lead',
    status: contact.status || 'active',
    source: contact.source || 'manual',
  })

  // Focus input when modal opens
  useEffect(() => {
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

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
    if (error) setError(null)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    // Validation
    if (!formData.full_name.trim()) {
      setError('Full name is required')
      return
    }

    if (formData.full_name.trim().length < 2) {
      setError('Full name must be at least 2 characters')
      return
    }

    if (formData.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      setError('Please enter a valid email address')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const response = await api.put(`/api/v1/contacts/${contact.id}`, {
        full_name: formData.full_name.trim(),
        email: formData.email.trim() || null,
        phone: formData.phone.trim() || null,
        organization: formData.organization.trim() || null,
        job_title: formData.job_title.trim() || null,
        relation_type: formData.relation_type,
        status: formData.status,
        source: formData.source,
      })

      if (response.data.success) {
        if (onSuccess) {
          onSuccess(response.data.data)
        }
        onClose()
      } else {
        setError(response.data.message || 'Failed to update contact')
      }
    } catch (err: any) {
      console.error('Failed to update contact:', err)
      setError(
        err.response?.data?.error ||
          err.response?.data?.message ||
          err.response?.data?.errors?.full_name?.[0] ||
          err.response?.data?.errors?.email?.[0] ||
          'Failed to update contact. Please try again.'
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
      <div className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden animate-in fade-in zoom-in-95 duration-200 max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-base-300">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-secondary/20 flex items-center justify-center">
              <UserCog className="w-5 h-5 text-secondary" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-base-content">Edit Contact</h2>
              <p className="text-sm text-base-content/60">Update contact information</p>
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
        <form onSubmit={handleSubmit} className="p-5 overflow-y-auto flex-1">
          <div className="space-y-4">
            {/* Full Name */}
            <div>
              <label htmlFor="full_name" className="block text-sm font-medium text-base-content mb-2">
                Full Name <span className="text-error">*</span>
              </label>
              <input
                ref={inputRef}
                id="full_name"
                name="full_name"
                type="text"
                value={formData.full_name}
                onChange={handleChange}
                placeholder="John Doe"
                className="input input-bordered w-full"
                disabled={isLoading}
                maxLength={255}
              />
            </div>

            {/* Email & Phone Row */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-base-content mb-2">
                  Email
                </label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50" />
                  <input
                    id="email"
                    name="email"
                    type="email"
                    value={formData.email}
                    onChange={handleChange}
                    placeholder="john@example.com"
                    className="input input-bordered w-full pl-10"
                    disabled={isLoading}
                    maxLength={255}
                  />
                </div>
              </div>

              <div>
                <label htmlFor="phone" className="block text-sm font-medium text-base-content mb-2">
                  Phone
                </label>
                <div className="relative">
                  <Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50" />
                  <input
                    id="phone"
                    name="phone"
                    type="tel"
                    value={formData.phone}
                    onChange={handleChange}
                    placeholder="+1 (555) 123-4567"
                    className="input input-bordered w-full pl-10"
                    disabled={isLoading}
                    maxLength={50}
                  />
                </div>
              </div>
            </div>

            {/* Organization & Job Title Row */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label htmlFor="organization" className="block text-sm font-medium text-base-content mb-2">
                  Organization
                </label>
                <div className="relative">
                  <Building2 className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50" />
                  <input
                    id="organization"
                    name="organization"
                    type="text"
                    value={formData.organization}
                    onChange={handleChange}
                    placeholder="Company Inc."
                    className="input input-bordered w-full pl-10"
                    disabled={isLoading}
                    maxLength={255}
                  />
                </div>
              </div>

              <div>
                <label htmlFor="job_title" className="block text-sm font-medium text-base-content mb-2">
                  Job Title
                </label>
                <div className="relative">
                  <Briefcase className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50" />
                  <input
                    id="job_title"
                    name="job_title"
                    type="text"
                    value={formData.job_title}
                    onChange={handleChange}
                    placeholder="CEO, Manager, etc."
                    className="input input-bordered w-full pl-10"
                    disabled={isLoading}
                    maxLength={255}
                  />
                </div>
              </div>
            </div>

            {/* Relation Type */}
            <div>
              <label htmlFor="relation_type" className="block text-sm font-medium text-base-content mb-2">
                Contact Type
              </label>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                {RELATION_TYPES.map((type) => (
                  <label
                    key={type.value}
                    className={`flex flex-col p-3 rounded-xl border cursor-pointer transition-all ${
                      formData.relation_type === type.value
                        ? 'border-primary bg-primary/10'
                        : 'border-base-300 bg-base-100 hover:border-base-content/30'
                    }`}
                  >
                    <input
                      type="radio"
                      name="relation_type"
                      value={type.value}
                      checked={formData.relation_type === type.value}
                      onChange={handleChange}
                      className="hidden"
                      disabled={isLoading}
                    />
                    <span
                      className={`font-medium text-sm ${
                        formData.relation_type === type.value ? 'text-primary' : 'text-base-content'
                      }`}
                    >
                      {type.label}
                    </span>
                    <span className="text-xs text-base-content/50 mt-0.5">{type.description}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Status & Source Row */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label htmlFor="status" className="block text-sm font-medium text-base-content mb-2">
                  Status
                </label>
                <div className="relative">
                  <select
                    id="status"
                    name="status"
                    value={formData.status}
                    onChange={handleChange}
                    className="select select-bordered w-full"
                    disabled={isLoading}
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="converted">Converted</option>
                    <option value="lost">Lost</option>
                  </select>
                  <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50 pointer-events-none" />
                </div>
              </div>

              <div>
                <label htmlFor="source" className="block text-sm font-medium text-base-content mb-2">
                  Source
                </label>
                <div className="relative">
                  <Globe className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50" />
                  <select
                    id="source"
                    name="source"
                    value={formData.source}
                    onChange={handleChange}
                    className="select select-bordered w-full pl-10"
                    disabled={isLoading}
                  >
                    {SOURCE_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                  <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50 pointer-events-none" />
                </div>
              </div>
            </div>

            {/* Error message */}
            {error && (
              <div className="p-3 bg-error/10 border border-error/30 rounded-lg">
                <p className="text-sm text-error">{error}</p>
              </div>
            )}
          </div>

          {/* Actions */}
          <div className="flex gap-3 mt-6">
            <button type="button" onClick={onClose} disabled={isLoading} className="btn btn-ghost flex-1">
              Cancel
            </button>
            <button
              type="submit"
              disabled={isLoading || !formData.full_name.trim()}
              className="btn btn-primary flex-1 gap-2"
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Saving...
                </>
              ) : (
                <>
                  <UserCog className="w-4 h-4" />
                  Save Changes
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

