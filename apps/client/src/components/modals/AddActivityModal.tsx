import { useState, useEffect } from 'react'
import { X, MessageSquare, Phone, Mail, Calendar, CheckSquare, Loader2, Search } from 'lucide-react'
import { api } from '../../lib/api'

interface Contact {
  id: string
  full_name: string
  email: string | null
}

interface AddActivityModalProps {
  companyId?: string
  preSelectedContactId?: string
  onClose: () => void
  onSuccess: (activity: any) => void
}

const ACTIVITY_TYPES = [
  { value: 'note', label: 'Note', icon: MessageSquare },
  { value: 'call', label: 'Call', icon: Phone },
  { value: 'email', label: 'Email', icon: Mail },
  { value: 'meeting', label: 'Meeting', icon: Calendar },
  { value: 'task', label: 'Task', icon: CheckSquare },
] as const

type ActivityType = (typeof ACTIVITY_TYPES)[number]['value']

export default function AddActivityModal({
  preSelectedContactId,
  onClose,
  onSuccess,
}: AddActivityModalProps) {
  const [activityType, setActivityType] = useState<ActivityType>('note')
  const [content, setContent] = useState('')
  const [contactId, setContactId] = useState(preSelectedContactId || '')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Contact search
  const [contacts, setContacts] = useState<Contact[]>([])
  const [isLoadingContacts, setIsLoadingContacts] = useState(false)
  const [contactSearch, setContactSearch] = useState('')
  const [showContactDropdown, setShowContactDropdown] = useState(false)
  const [selectedContact, setSelectedContact] = useState<Contact | null>(null)

  // Fetch contacts for dropdown
  useEffect(() => {
    const fetchContacts = async () => {
      setIsLoadingContacts(true)
      try {
        const params: Record<string, string> = { per_page: '50' }
        if (contactSearch.trim()) {
          params.search = contactSearch.trim()
        }
        const response = await api.get('/api/v1/contacts', { params })
        if (response.data.success) {
          setContacts(response.data.data)
        }
      } catch (err) {
        console.error('Failed to fetch contacts:', err)
      } finally {
        setIsLoadingContacts(false)
      }
    }

    const timer = setTimeout(fetchContacts, 300)
    return () => clearTimeout(timer)
  }, [contactSearch])

  // Set pre-selected contact
  useEffect(() => {
    if (preSelectedContactId && contacts.length > 0) {
      const contact = contacts.find((c) => c.id === preSelectedContactId)
      if (contact) {
        setSelectedContact(contact)
        setContactId(contact.id)
      }
    }
  }, [preSelectedContactId, contacts])

  const handleContactSelect = (contact: Contact) => {
    setSelectedContact(contact)
    setContactId(contact.id)
    setShowContactDropdown(false)
    setContactSearch('')
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)

    if (!contactId) {
      setError('Please select a contact')
      return
    }

    if (!content.trim()) {
      setError('Please enter activity details')
      return
    }

    setIsSubmitting(true)

    try {
      const response = await api.post('/api/v1/activities', {
        type: activityType,
        content: content.trim(),
        contact_id: contactId,
      })

      if (response.data.success) {
        onSuccess(response.data.data)
      } else {
        setError(response.data.message || 'Failed to add activity')
      }
    } catch (err: any) {
      console.error('Failed to add activity:', err)
      setError(err.response?.data?.message || 'Failed to add activity')
    } finally {
      setIsSubmitting(false)
    }
  }

  const getPlaceholder = () => {
    switch (activityType) {
      case 'call':
        return 'Describe the call... (e.g., Discussed project timeline, follow-up needed)'
      case 'email':
        return 'Summarize the email... (e.g., Sent proposal, awaiting response)'
      case 'meeting':
        return 'Meeting notes... (e.g., Reviewed requirements, agreed on next steps)'
      case 'task':
        return 'Task description... (e.g., Send follow-up email by Friday)'
      default:
        return 'Add a note... (e.g., Customer mentioned interest in upgrade)'
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

      {/* Modal */}
      <div className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-base-300">
          <h2 className="text-xl font-semibold">Add Activity</h2>
          <button onClick={onClose} className="btn btn-ghost btn-sm btn-circle">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <form onSubmit={handleSubmit} className="p-6 space-y-5">
          {/* Activity Type */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-base-content/70">Activity Type</label>
            <div className="flex flex-wrap gap-2">
              {ACTIVITY_TYPES.map((type) => {
                const Icon = type.icon
                return (
                  <button
                    key={type.value}
                    type="button"
                    onClick={() => setActivityType(type.value)}
                    className={`btn btn-sm gap-2 ${
                      activityType === type.value ? 'btn-primary' : 'btn-ghost border border-base-300'
                    }`}
                  >
                    <Icon className="w-4 h-4" />
                    {type.label}
                  </button>
                )
              })}
            </div>
          </div>

          {/* Contact Selection */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-base-content/70">
              Contact <span className="text-error">*</span>
            </label>
            <div className="relative">
              {selectedContact ? (
                <div className="flex items-center justify-between p-3 bg-base-100 rounded-lg border border-base-300">
                  <div>
                    <div className="font-medium">{selectedContact.full_name}</div>
                    {selectedContact.email && (
                      <div className="text-sm text-base-content/60">{selectedContact.email}</div>
                    )}
                  </div>
                  <button
                    type="button"
                    onClick={() => {
                      setSelectedContact(null)
                      setContactId('')
                    }}
                    className="btn btn-ghost btn-xs btn-circle"
                  >
                    <X className="w-4 h-4" />
                  </button>
                </div>
              ) : (
                <>
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50" />
                    <input
                      type="text"
                      placeholder="Search contacts..."
                      className="input input-bordered w-full pl-9"
                      value={contactSearch}
                      onChange={(e) => setContactSearch(e.target.value)}
                      onFocus={() => setShowContactDropdown(true)}
                    />
                  </div>

                  {showContactDropdown && (
                    <div className="absolute z-10 mt-1 w-full bg-base-100 rounded-lg border border-base-300 shadow-lg max-h-48 overflow-y-auto">
                      {isLoadingContacts ? (
                        <div className="p-4 text-center">
                          <span className="loading loading-spinner loading-sm"></span>
                        </div>
                      ) : contacts.length === 0 ? (
                        <div className="p-4 text-center text-base-content/60">
                          {contactSearch ? 'No contacts found' : 'Type to search contacts'}
                        </div>
                      ) : (
                        contacts.map((contact) => (
                          <button
                            key={contact.id}
                            type="button"
                            onClick={() => handleContactSelect(contact)}
                            className="w-full p-3 text-left hover:bg-base-200 transition-colors"
                          >
                            <div className="font-medium">{contact.full_name}</div>
                            {contact.email && (
                              <div className="text-sm text-base-content/60">{contact.email}</div>
                            )}
                          </button>
                        ))
                      )}
                    </div>
                  )}
                </>
              )}
            </div>
          </div>

          {/* Content */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-base-content/70">
              Details <span className="text-error">*</span>
            </label>
            <textarea
              value={content}
              onChange={(e) => setContent(e.target.value)}
              placeholder={getPlaceholder()}
              className="textarea textarea-bordered w-full min-h-32 resize-none"
              rows={4}
            />
          </div>

          {/* Error */}
          {error && (
            <div className="alert alert-error text-sm py-2">
              <span>{error}</span>
            </div>
          )}

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} className="btn btn-ghost" disabled={isSubmitting}>
              Cancel
            </button>
            <button type="submit" className="btn btn-primary gap-2" disabled={isSubmitting}>
              {isSubmitting ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Adding...
                </>
              ) : (
                'Add Activity'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}



