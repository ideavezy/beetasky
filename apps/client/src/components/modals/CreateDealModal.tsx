import { useState, useEffect } from 'react'
import { X, DollarSign, Calendar, User, Loader2 } from 'lucide-react'
import { useModalStore, MODAL_NAMES, CreateDealModalProps } from '../../stores/modal'
import { useAuthStore } from '../../stores/auth'
import { api } from '../../lib/api'

interface Contact {
  id: string
  full_name: string
  email: string | null
  organization: string | null
}

const STAGES = [
  { value: 'qualification', label: 'Qualification', probability: 10 },
  { value: 'proposal', label: 'Proposal', probability: 30 },
  { value: 'negotiation', label: 'Negotiation', probability: 60 },
]

export default function CreateDealModal() {
  const { activeModal, modalProps, closeModal } = useModalStore()
  const { companyId } = useAuthStore()
  const props = modalProps as CreateDealModalProps

  const [formData, setFormData] = useState({
    title: '',
    value: '',
    currency: 'USD',
    stage: 'qualification',
    contact_id: props?.contactId || '',
    expected_close_date: '',
    description: '',
  })
  
  const [contacts, setContacts] = useState<Contact[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [isLoadingContacts, setIsLoadingContacts] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const isOpen = activeModal === MODAL_NAMES.CREATE_DEAL

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      setFormData({
        title: '',
        value: '',
        currency: 'USD',
        stage: 'qualification',
        contact_id: props?.contactId || '',
        expected_close_date: '',
        description: '',
      })
      setError(null)
      fetchContacts()
    }
  }, [isOpen, props?.contactId])

  const fetchContacts = async () => {
    if (!companyId) return
    setIsLoadingContacts(true)
    
    try {
      const response = await api.get('/api/v1/contacts', {
        params: { limit: 50 }
      })
      setContacts(response.data.data || [])
    } catch (err) {
      console.error('Failed to fetch contacts:', err)
    } finally {
      setIsLoadingContacts(false)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!formData.title.trim()) {
      setError('Deal title is required')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const payload = {
        title: formData.title.trim(),
        value: formData.value ? parseFloat(formData.value) : null,
        currency: formData.currency,
        stage: formData.stage,
        contact_id: formData.contact_id || null,
        expected_close_date: formData.expected_close_date || null,
        description: formData.description.trim() || null,
      }

      const response = await api.post('/api/v1/deals', payload)

      props?.onSuccess?.(response.data)
      closeModal()
    } catch (err: any) {
      console.error('Failed to create deal:', err)
      setError(err.response?.data?.error || err.message || 'Failed to create deal')
    } finally {
      setIsLoading(false)
    }
  }

  if (!isOpen) return null

  return (
    <div className="modal modal-open">
      <div className="modal-box w-11/12 max-w-lg bg-base-200">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-lg font-semibold text-base-content">New Deal</h3>
          <button 
            onClick={closeModal}
            className="btn btn-ghost btn-sm btn-square"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Error */}
        {error && (
          <div className="alert alert-error mb-4">
            <span className="text-sm">{error}</span>
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Title */}
          <div className="form-control">
            <label className="label">
              <span className="label-text">Deal Title *</span>
            </label>
            <input
              type="text"
              placeholder="e.g., Enterprise License Agreement"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              className="input input-bordered w-full"
              autoFocus
            />
          </div>

          {/* Value & Currency */}
          <div className="grid grid-cols-3 gap-3">
            <div className="form-control col-span-2">
              <label className="label">
                <span className="label-text">Value</span>
              </label>
              <div className="relative">
                <DollarSign className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" />
                <input
                  type="number"
                  placeholder="0.00"
                  value={formData.value}
                  onChange={(e) => setFormData({ ...formData, value: e.target.value })}
                  className="input input-bordered w-full pl-9"
                  min="0"
                  step="0.01"
                />
              </div>
            </div>
            <div className="form-control">
              <label className="label">
                <span className="label-text">Currency</span>
              </label>
              <select
                value={formData.currency}
                onChange={(e) => setFormData({ ...formData, currency: e.target.value })}
                className="select select-bordered w-full"
              >
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
                <option value="CAD">CAD</option>
                <option value="AUD">AUD</option>
              </select>
            </div>
          </div>

          {/* Stage */}
          <div className="form-control">
            <label className="label">
              <span className="label-text">Stage</span>
            </label>
            <select
              value={formData.stage}
              onChange={(e) => setFormData({ ...formData, stage: e.target.value })}
              className="select select-bordered w-full"
            >
              {STAGES.map((stage) => (
                <option key={stage.value} value={stage.value}>
                  {stage.label} ({stage.probability}% probability)
                </option>
              ))}
            </select>
          </div>

          {/* Contact */}
          <div className="form-control">
            <label className="label">
              <span className="label-text">Associated Contact</span>
            </label>
            <select
              value={formData.contact_id}
              onChange={(e) => setFormData({ ...formData, contact_id: e.target.value })}
              className="select select-bordered w-full"
              disabled={isLoadingContacts}
            >
              <option value="">No contact selected</option>
              {contacts.map((contact) => (
                <option key={contact.id} value={contact.id}>
                  {contact.full_name}
                  {contact.organization ? ` (${contact.organization})` : ''}
                </option>
              ))}
            </select>
          </div>

          {/* Expected Close Date */}
          <div className="form-control">
            <label className="label">
              <span className="label-text">Expected Close Date</span>
            </label>
            <div className="relative">
              <Calendar className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" />
              <input
                type="date"
                value={formData.expected_close_date}
                onChange={(e) => setFormData({ ...formData, expected_close_date: e.target.value })}
                className="input input-bordered w-full pl-9"
              />
            </div>
          </div>

          {/* Description */}
          <div className="form-control">
            <label className="label">
              <span className="label-text">Description</span>
            </label>
            <textarea
              placeholder="Additional notes about this deal..."
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              className="textarea textarea-bordered w-full h-20"
            />
          </div>

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4">
            <button 
              type="button" 
              onClick={closeModal}
              className="btn btn-ghost"
              disabled={isLoading}
            >
              Cancel
            </button>
            <button 
              type="submit"
              className="btn btn-primary gap-2"
              disabled={isLoading}
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Creating...
                </>
              ) : (
                'Create Deal'
              )}
            </button>
          </div>
        </form>
      </div>
      <div className="modal-backdrop" onClick={closeModal}></div>
    </div>
  )
}

